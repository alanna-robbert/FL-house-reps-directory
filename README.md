## app-fl-house-reps-directory

## Class assignment for Spring 2026 JOU4364 Advanced Web Apps for Communicators at the University of Florida.

## How to run it

The database is a single SQLite file (`representatives.db`), so there is no database server to start.

1. **Get fresh data** (optional, takes about 2 minutes):
   ```bash
   python3 rep.py        # scrapes flhouse.gov -> representatives.json
   python3 build_db.py   # representatives.json -> representatives.db
   ```
2. **Start the website:**
   ```bash
   php -S localhost:8000
   ```
3. Open http://localhost:8000/index.php

To use MySQL instead (e.g. on the class server), see the comment at the top of `representatives.php`.
