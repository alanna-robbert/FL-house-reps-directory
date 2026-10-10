# Florida House of Representatives Directory

## 👉 [Visit the live directory](https://alanna-robbert.github.io/FL-house-reps-directory/)

https://alanna-robbert.github.io/FL-house-reps-directory/

A searchable directory of the members of the Florida House of Representatives for the 2024–2026 term. You can search by name, look up a district, or filter members by party, religion, city, occupation, military service, recreational interests and university. Click any member to see their full profile.

No download or sign-up needed. The site works in any web browser, on a phone or a computer.

This is a class assignment for **JOU4364 Advanced Web Apps for Communicators** (Spring 2026) at the University of Florida.

## Features

- **Search** across names, cities, occupations, education, counties and more
- **District lookup:** enter a number from 1 to 120
- **Filters** that can be combined, with a count beside each option:
  - Party, Religion, City, Occupation
  - Military Service, Recreational Interests, University
- **Profile pages** with each member's full biography and a link to their official page
- **Former members:** people who resigned or died during the term are kept out of the main list, but their profile pages still exist and show the dates they served
- Works on phones as well as computers

## How it works

The project runs in three steps:

```
1. Scrape    python/rep.py         flhouse.gov  →  data/representatives.json
2. Store     python/build_db.py    JSON         →  data/representatives.db (SQLite)
3. Display   php/                  database     →  web pages
```

1. **Scrape.** `rep.py` reads the member directory on [flhouse.gov](https://www.flhouse.gov/representatives) and then each member's bio page. It waits between requests so it doesn't overload the site.
2. **Store.** `build_db.py` loads the scraped data into a SQLite database. SQLite keeps the whole database in a single file, so there's no database server to run.
3. **Display.** The PHP pages read the database and build the HTML. Search and filtering happen in the browser with JavaScript, so results update instantly.

### Two ways to serve the site

| | Live PHP | Static website |
|---|---|---|
| **Used for** | Development and the class server | The public site on GitHub Pages |
| **How** | PHP builds each page when it's requested | `php/build_site.php` saves every page as HTML in `docs/` |
| **Needs PHP on the server?** | Yes | No |

GitHub Pages can't run PHP, so `build_site.php` runs the PHP pages once and saves the results. Both versions come from the same PHP files.

## Project folders

| Folder    | What's inside |
|-----------|---------------|
| `php/`    | The pages (`index.php`, `profile.php`), database helpers (`representatives.php`) and `build_site.php` |
| `python/` | `rep.py` (the scraper) and `build_db.py` (builds the database) |
| `css/`    | `style.css` |
| `images/` | `placeholder.svg`, shown when a member's photo can't load |
| `data/`   | `representatives.json` (scraped data) and `representatives.db` (SQLite database) |
| `docs/`   | The published website. **`build_site.php` generates it, so don't edit it by hand.** |

## Running it yourself

**You'll need:** PHP 8 (with the `pdo_sqlite` extension) and Python 3.10 or newer.

Run all commands from the main project folder.

### 1. Preview the site

```bash
php -S localhost:8000
```

Then open http://localhost:8000/php/

### 2. Get fresh data (optional)

The repo already includes the data, so you only need this step to update it. It takes about 2 minutes.

```bash
pip install requests beautifulsoup4
python3 python/rep.py
python3 python/build_db.py
```

### 3. Update the public website

After you change the data or the pages, rebuild `docs/`:

```bash
php php/build_site.php
```

Then commit and push. GitHub Pages publishes the `docs/` folder from the `main` branch.

### Using MySQL instead of SQLite

The database code uses PHP's PDO, which works with both SQLite and MySQL. To connect to a MySQL server (such as the class server), create `php/config.php`:

```php
<?php
define('DB_DSN',  'mysql:host=localhost;dbname=YOUR_DB;charset=utf8mb4');
define('DB_USER', 'YOUR_USER');
define('DB_PASS', 'YOUR_PASSWORD');
```

`config.php` is listed in `.gitignore`, so your password stays off GitHub.

## Known limitations

- **Photos:** member photos load straight from flhouse.gov. That site sometimes blocks image requests from cloud servers. When a photo is blocked, a gray placeholder shows instead.
- **Data is a snapshot:** the data is only as current as the last time the scraper ran. When members join or leave, re-run the steps in [Get fresh data](#2-get-fresh-data-optional) and [Update the public website](#3-update-the-public-website).
- **University filter:** only schools with "University" in their name are listed, so colleges and law schools don't appear in that filter.

## Data source

All member information comes from the official [Florida House of Representatives website](https://www.flhouse.gov/representatives). This project isn't affiliated with or endorsed by the Florida House.

## License

[MIT](LICENSE) © 2026 Alanna Robbert
