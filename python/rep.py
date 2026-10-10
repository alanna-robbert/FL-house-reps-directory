#!/usr/bin/env python3
"""
Florida House of Representatives scraper.
Fetches the member directory and each legislator's bio page,
then saves all data to representatives.json.

Structure discovered from live site:
  Directory : div.team-box > a[href*=details.aspx] > h5 (name), p (party/district)
  Bio page  :
    Name          — <h1> (whitespace-separated spans)
    District/Party— <h2 class="sub-title"> e.g. "District: 5  -- Republican"
    Photo         — <img data-src="/FileStores/Web/Imaging/Member/{id}.jpg">
    Bio fields    — <div class="mi-content"> pairs of
                      <span class="textBold">Label:</span>
                      <span class="text">Value</span>
"""

import json
import re
import time
import sys
from urllib.parse import urljoin

import requests
from bs4 import BeautifulSoup

BASE_URL = "https://www.flhouse.gov"
DIRECTORY_URL = f"{BASE_URL}/representatives"
OUTPUT_FILE = "representatives.json"
REQUEST_DELAY = 0.75  # seconds between requests — be polite

HEADERS = {
    "User-Agent": (
        "Mozilla/5.0 (compatible; FL-House-Scraper/1.0; educational use)"
    )
}


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def get_soup(url: str, session: requests.Session) -> BeautifulSoup:
    resp = session.get(url, headers=HEADERS, timeout=30)
    resp.raise_for_status()
    return BeautifulSoup(resp.text, "html.parser")


def clean(text: str | None) -> str:
    """Strip and normalise whitespace."""
    if not text:
        return ""
    return re.sub(r"\s+", " ", text).strip()


# ---------------------------------------------------------------------------
# Step 1 — parse the directory page
# ---------------------------------------------------------------------------

def parse_directory(soup: BeautifulSoup) -> list[dict]:
    """Return a list of basic member dicts from the directory listing."""
    members = []

    for box in soup.select("div.team-box"):
        detail_link = box.find("a", href=re.compile(r"details\.aspx", re.I))
        if not detail_link:
            continue

        bio_url = urljoin(BASE_URL, detail_link["href"])

        # MemberId lives in the link: details.aspx?MemberId=4864&...
        id_m = re.search(r"MemberId=(\d+)", bio_url, re.I)
        member_id = int(id_m.group(1)) if id_m else None

        # Name is in the <h5>. Former members who died are marked "* Name".
        name_tag = detail_link.find("h5")
        full_name = clean(name_tag.get_text()).lstrip("* ") if name_tag else ""

        # First <p>: "Republican — District: 5"
        paras = detail_link.find_all("p")
        party, district = "", ""
        if paras:
            pd_text = clean(paras[0].get_text())
            party_m = re.search(
                r"(Republican|Democrat|No Party Affiliation|NPA)", pd_text, re.I
            )
            dist_m = re.search(r"District[:\s]+(\d+)", pd_text, re.I)
            party = party_m.group(1) if party_m else ""
            district = dist_m.group(1) if dist_m else ""

        # Only current members have a <p class="rep-counties">. Former members
        # (who resigned or died mid-term) are also listed, without counties.
        counties_tag = detail_link.find("p", class_="rep-counties")
        counties = clean(counties_tag.get_text()) if counties_tag else ""
        is_current = counties_tag is not None

        # Last <p> holds the term dates, e.g. "11/06/24 - 07/18/25 (Deceased)"
        term = clean(paras[-1].get_text()) if len(paras) > 1 else ""

        members.append(
            {
                "member_id": member_id,
                "is_current": is_current,
                "term": term,
                "full_name": full_name,
                "district": district,
                "party": party,
                "counties": counties,
                "bio_url": bio_url,
            }
        )

    return members


# ---------------------------------------------------------------------------
# Step 2 — parse an individual bio page
# ---------------------------------------------------------------------------

def parse_bio(soup: BeautifulSoup) -> dict:
    """Extract detailed info from a legislator's bio page."""
    data: dict = {}

    # --- Name ---
    # Some templates: <div class="rep-title">Jon Albert</div>
    # Others:         <h1>Shane G. Abbott</h1>  (not sr-only / modal-title)
    rep_title_div = soup.find("div", class_="rep-title")
    if rep_title_div:
        data["full_name"] = clean(rep_title_div.get_text())
    else:
        # Skip screen-reader-only and modal h1s
        for h1 in soup.find_all("h1"):
            classes = h1.get("class", [])
            if "sr-only" in classes or "modal-title" in classes:
                continue
            data["full_name"] = clean(h1.get_text())
            break
        else:
            data["full_name"] = ""

    # --- District & Party ---
    # Template A: <h2 class="sub-title">District: 5  -- Republican</h2>
    sub_title = soup.find("h2", class_="sub-title")
    if sub_title and re.search(r"District", sub_title.get_text(), re.I):
        text = clean(sub_title.get_text())
        dist_m = re.search(r"District[:\s]+(\d+)", text, re.I)
        party_m = re.search(
            r"(Republican|Democrat|No Party Affiliation|NPA)", text, re.I
        )
        data["district"] = dist_m.group(1) if dist_m else ""
        data["party"] = party_m.group(1) if party_m else ""
    else:
        # Leave blank; will be filled from directory data
        data["district"] = ""
        data["party"] = ""

    # --- Photo ---
    # Template A: lazy-loaded  <img data-src="/FileStores/Web/Imaging/Member/4864.jpg">
    # Template B: direct src   <img class="member-photo" src="/FileStores/Web/Imaging/Member/cutout/4917.png">
    photo_img = (
        soup.find("img", attrs={"data-src": re.compile(r"/Member/", re.I)})
        or soup.find("img", class_="member-photo")
    )
    if photo_img:
        src = photo_img.get("data-src") or photo_img.get("src") or ""
        data["photo_url"] = urljoin(BASE_URL, src) if src.startswith("/") else src
    else:
        data["photo_url"] = ""

    # --- Biographical fields from div.mi-content ---
    # Structure: <span class="textBold">Label:</span> <span class="text">Value</span>
    bio_data: dict[str, str] = {}
    mi_content = soup.find("div", class_="mi-content")
    if mi_content:
        labels = mi_content.find_all("span", class_="textBold")
        for label_span in labels:
            label = clean(label_span.get_text()).rstrip(":")
            value_span = label_span.find_next_sibling("span", class_="text")
            value = clean(value_span.get_text()) if value_span else ""
            # Normalise label to a clean key
            key = re.sub(r"[^a-z0-9]+", "_", label.lower()).strip("_")
            bio_data[key] = value

    # Map known labels to standard field names
    field_map = {
        "city_of_residence": "city",
        "occupation": "occupation",
        "spouse": "spouse",
        "child_ren": "children",
        "grandchild_ren": "grandchildren",
        "education": "education",
        "born": "birthplace",
        "age": "age",
        "year_moved_to_florida": "year_moved_to_florida",
        "florida_since": "year_moved_to_florida",
        "moved_to_florida": "year_moved_to_florida",
        "religious_affiliation": "religion",
        "recreational_interest": "recreational_interests",
        "military": "military",
    }

    for raw_key, std_key in field_map.items():
        if raw_key in bio_data:
            data[std_key] = bio_data.pop(raw_key)
        elif std_key not in data:
            data[std_key] = ""

    # Preserve any extra bio fields the site may include
    data["extra_bio"] = bio_data

    return data


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def scrape_all() -> list[dict]:
    session = requests.Session()

    print("Fetching directory…")
    directory_soup = get_soup(DIRECTORY_URL, session)
    members = parse_directory(directory_soup)
    print(f"  Found {len(members)} representatives.")

    results = []
    for i, member in enumerate(members, 1):
        bio_url = member["bio_url"]
        print(f"  [{i:3d}/{len(members)}] {member['full_name']}")

        try:
            time.sleep(REQUEST_DELAY)
            bio_soup = get_soup(bio_url, session)
            bio = parse_bio(bio_soup)
        except Exception as exc:
            print(f"    WARNING: failed — {exc}", file=sys.stderr)
            bio = {
                "full_name": member["full_name"],
                "district": member["district"],
                "party": member["party"],
                "photo_url": "",
                "city": "", "occupation": "", "birthplace": "",
                "age": "", "year_moved_to_florida": "", "spouse": "",
                "children": "", "grandchildren": "", "education": "",
                "religion": "", "recreational_interests": "", "military": "",
                "extra_bio": {},
            }

        # Merge: prefer bio page values; fall back to directory values
        record = {
            "member_id": member["member_id"],
            "is_current": member["is_current"],
            "term": member["term"],
            "full_name": (bio.get("full_name") or member["full_name"]).lstrip("* "),
            "district": bio.get("district") or member["district"],
            "party": bio.get("party") or member["party"],
            "counties": member["counties"],
            "bio_url": bio_url,
            "photo_url": bio.get("photo_url", ""),
            "city": bio.get("city", ""),
            "occupation": bio.get("occupation", ""),
            "birthplace": bio.get("birthplace", ""),
            "age": bio.get("age", ""),
            "year_moved_to_florida": bio.get("year_moved_to_florida", ""),
            "spouse": bio.get("spouse", ""),
            "children": bio.get("children", ""),
            "grandchildren": bio.get("grandchildren", ""),
            "education": bio.get("education", ""),
            "religion": bio.get("religion", ""),
            "recreational_interests": bio.get("recreational_interests", ""),
            "military": bio.get("military", ""),
            "extra_bio": bio.get("extra_bio", {}),
        }
        results.append(record)

    return results


if __name__ == "__main__":
    data = scrape_all()

    with open(OUTPUT_FILE, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)

    print(f"\nDone. {len(data)} records saved to {OUTPUT_FILE}")
