## app-fl-house-reps-directory

## Class assignment for Spring 2026 JOU4364 Advanced Web Apps for Communicators at the University of Florida.

## Folders

| Folder    | What's inside |
|-----------|---------------|
| `php/`    | The website pages, database helpers, and `build_site.php` |
| `python/` | `rep.py` (the scraper) and `build_db.py` (makes the database) |
| `css/`    | `style.css` |
| `images/` | `placeholder.svg`, shown when a photo can't load |
| `data/`   | `representatives.json` and the SQLite database `representatives.db` |
| `docs/`   | The finished website for GitHub Pages. **Made by `build_site.php`, so don't edit it by hand.** |

## How to run it

The database is a single SQLite file (`data/representatives.db`), so there is no database server to start. Run these commands from the main project folder.

1. **Get fresh data** (optional, takes about 2 minutes):
   ```bash
   python3 python/rep.py        # scrapes flhouse.gov -> data/representatives.json
   python3 python/build_db.py   # data/representatives.json -> data/representatives.db
   ```
2. **Preview with PHP:**
   ```bash
   php -S localhost:8000
   ```
   Then open http://localhost:8000/php/
3. **Update the public website** (after changing data or pages):
   ```bash
   php php/build_site.php       # rebuilds docs/
   ```
   Then commit and push. GitHub Pages publishes the `docs/` folder.

To use MySQL instead (e.g. on the class server), see the comment at the top of `php/representatives.php`.
