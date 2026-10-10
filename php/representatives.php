<?php
/*
 * Database helpers, using PDO so the same code works with SQLite or MySQL.
 *
 * By default this opens representatives.db (SQLite) next to this file.
 * To use MySQL instead (e.g. on the class server), create config.php with:
 *
 *   <?php
 *   define('DB_DSN',  'mysql:host=localhost;dbname=YOUR_DB;charset=utf8mb4');
 *   define('DB_USER', 'YOUR_USER');
 *   define('DB_PASS', 'YOUR_PASSWORD');
 *
 * config.php is in .gitignore, so passwords never reach GitHub.
 */

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}
defined('DB_DSN')  || define('DB_DSN', 'sqlite:' . dirname(__DIR__) . '/data/representatives.db');
defined('DB_USER') || define('DB_USER', null);
defined('DB_PASS') || define('DB_PASS', null);

/*
 * Links. The same pages run two ways:
 *   1. Live through PHP (php -S): pages are in php/, so the CSS is at ../css/
 *   2. As a static website built by build_site.php into docs/ (STATIC_BUILD).
 *      There, index.html is at the top and profiles are in reps/.
 * $SITE_ROOT is the path from the current page back to the top of the site.
 */
defined('STATIC_BUILD') || define('STATIC_BUILD', false);
$SITE_ROOT = $SITE_ROOT ?? '../';

/** Path to a file at the top of the site, e.g. url('css/style.css'). */
function url(string $path): string {
    return $GLOBALS['SITE_ROOT'] . $path;
}

function home_url(): string {
    return STATIC_BUILD ? url('index.html') : 'index.php';
}

function profile_url(int $member_id): string {
    return STATIC_BUILD ? url("reps/$member_id.html") : "profile.php?id=$member_id";
}

function get_db(): PDO {
    static $conn = null;
    if ($conn === null) {
        $conn = new PDO(DB_DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $conn;
}

/** Return all current representatives, optionally ordered by a column. */
function get_all_representatives(string $order_by = 'district'): array {
    $db = get_db();
    $allowed = ['id','full_name','district','party','city'];
    $col = in_array($order_by, $allowed) ? $order_by : 'district';
    return $db->query("SELECT * FROM `representatives` WHERE `is_current` = 1 ORDER BY `$col`")->fetchAll();
}

/** Filter by party ('Republican' or 'Democrat'). */
function get_by_party(string $party): array {
    $stmt = get_db()->prepare("SELECT * FROM `representatives` WHERE `party` = ? AND `is_current` = 1 ORDER BY `district`");
    $stmt->execute([$party]);
    return $stmt->fetchAll();
}

/** Find the current representative for a district number. */
function get_by_district(int $district): ?array {
    $stmt = get_db()->prepare("SELECT * FROM `representatives` WHERE `district` = ? AND `is_current` = 1 LIMIT 1");
    $stmt->execute([$district]);
    return $stmt->fetch() ?: null;
}

/** Search by partial name (case-insensitive). */
function search_by_name(string $query): array {
    $stmt = get_db()->prepare("SELECT * FROM `representatives` WHERE `full_name` LIKE ? ORDER BY `full_name`");
    $stmt->execute(['%' . $query . '%']);
    return $stmt->fetchAll();
}

/** Find one representative (current or former) by their website MemberId. */
function get_by_member_id(int $member_id): ?array {
    $stmt = get_db()->prepare("SELECT * FROM `representatives` WHERE `member_id` = ? LIMIT 1");
    $stmt->execute([$member_id]);
    return $stmt->fetch() ?: null;
}

/** Filter by partial city name (case-insensitive). */
function get_by_city(string $city): array {
    $stmt = get_db()->prepare("SELECT * FROM `representatives` WHERE `city` LIKE ? ORDER BY `full_name`");
    $stmt->execute(['%' . $city . '%']);
    return $stmt->fetchAll();
}

// ── Quick demo when run from CLI ──────────────────────────────────────────
if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $all = get_all_representatives();
    echo "Total : " . count($all) . "\n";
    echo "R     : " . count(get_by_party('Republican')) . "\n";
    echo "D     : " . count(get_by_party('Democrat')) . "\n\n";

    $rep = get_by_district(5);
    echo "District 5 : {$rep['full_name']} ({$rep['party']}), {$rep['city']}\n";
    echo "Photo      : {$rep['photo_url']}\n";
}
