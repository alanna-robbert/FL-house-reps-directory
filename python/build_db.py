#!/usr/bin/env python3
"""
Build representatives.db (SQLite) from representatives.json.

Run rep.py first to scrape the data, then:
    python3 build_db.py

SQLite keeps the whole database in a single file, so there is no server
to start. PHP opens the file directly (see representatives.php).
"""

import json
import sqlite3
from pathlib import Path

HERE = Path(__file__).parent
JSON_FILE = HERE / "representatives.json"
DB_FILE = HERE / "representatives.db"

SCHEMA = """
CREATE TABLE representatives (
    id                     INTEGER PRIMARY KEY AUTOINCREMENT,
    member_id              INTEGER NOT NULL UNIQUE,  -- MemberId from flhouse.gov
    is_current             INTEGER NOT NULL DEFAULT 1,  -- 1 = serving now, 0 = former
    term                   TEXT NOT NULL DEFAULT '',
    full_name              TEXT NOT NULL DEFAULT '',
    district               INTEGER,
    party                  TEXT NOT NULL DEFAULT '',
    counties               TEXT NOT NULL DEFAULT '',
    bio_url                TEXT NOT NULL DEFAULT '',
    photo_url              TEXT NOT NULL DEFAULT '',
    city                   TEXT NOT NULL DEFAULT '',
    occupation             TEXT NOT NULL DEFAULT '',
    birthplace             TEXT NOT NULL DEFAULT '',
    age                    TEXT NOT NULL DEFAULT '',
    year_moved_to_florida  TEXT NOT NULL DEFAULT '',
    spouse                 TEXT NOT NULL DEFAULT '',
    children               TEXT NOT NULL DEFAULT '',
    grandchildren          TEXT NOT NULL DEFAULT '',
    education              TEXT NOT NULL DEFAULT '',
    religion               TEXT NOT NULL DEFAULT '',
    recreational_interests TEXT NOT NULL DEFAULT '',
    military               TEXT NOT NULL DEFAULT '',
    extra_bio              TEXT NOT NULL DEFAULT '{}'  -- JSON object
);
"""

COLUMNS = [
    "member_id", "is_current", "term", "full_name", "district", "party",
    "counties", "bio_url", "photo_url", "city", "occupation", "birthplace",
    "age", "year_moved_to_florida", "spouse", "children", "grandchildren",
    "education", "religion", "recreational_interests", "military", "extra_bio",
]


def to_row(rep: dict) -> tuple:
    row = dict(rep)
    row["is_current"] = 1 if rep.get("is_current", True) else 0
    row["district"] = int(rep["district"]) if rep.get("district") else None
    row["extra_bio"] = json.dumps(rep.get("extra_bio") or {}, ensure_ascii=False)
    return tuple(row.get(col, "") for col in COLUMNS)


def main() -> None:
    reps = json.loads(JSON_FILE.read_text(encoding="utf-8"))

    # Start fresh each time so the database always matches the JSON
    DB_FILE.unlink(missing_ok=True)

    with sqlite3.connect(DB_FILE) as conn:
        conn.executescript(SCHEMA)
        placeholders = ", ".join("?" for _ in COLUMNS)
        conn.executemany(
            f"INSERT INTO representatives ({', '.join(COLUMNS)}) VALUES ({placeholders})",
            [to_row(r) for r in reps],
        )
        current = conn.execute(
            "SELECT COUNT(*) FROM representatives WHERE is_current = 1"
        ).fetchone()[0]

    print(f"Saved {len(reps)} records to {DB_FILE.name} ({current} current members)")


if __name__ == "__main__":
    main()
