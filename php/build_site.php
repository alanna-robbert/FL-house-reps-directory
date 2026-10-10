<?php
/*
 * Build a static copy of the website into docs/ for GitHub Pages.
 *
 * GitHub Pages can only serve ready-made files (HTML, CSS, images); it can't
 * run PHP. So this script runs our PHP pages once, saves the HTML they print,
 * and copies the CSS and images next to them. The search and filters still
 * work because they are JavaScript, which runs in the visitor's browser.
 *
 *   php php/build_site.php
 *
 * Output:
 *   docs/index.html          the directory
 *   docs/reps/<id>.html      one profile page per representative
 *   docs/css/, docs/images/  copied as-is
 */

define('STATIC_BUILD', true);
require_once __DIR__ . '/representatives.php';

$root = dirname(__DIR__);
$docs = "$root/docs";

/** Run a PHP page and return the HTML it prints, instead of sending it to a browser. */
function render(string $page, string $site_root, array $vars = []): string {
    $GLOBALS['SITE_ROOT'] = $site_root;
    extract($vars);
    ob_start();
    include __DIR__ . "/$page";
    return ob_get_clean();
}

function copy_dir(string $from, string $to): void {
    @mkdir($to, 0777, true);
    foreach (glob("$from/*") as $file) {
        copy($file, "$to/" . basename($file));
    }
}

// Start from an empty docs/ folder so removed representatives disappear too.
// Keep CNAME: GitHub Pages stores the custom domain name in it.
if (is_dir($docs)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($docs, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        if ($file->getPathname() === "$docs/CNAME") continue;
        $file->isDir() ? rmdir($file) : unlink($file);
    }
}
@mkdir("$docs/reps", 0777, true);

// index.html sits at the top of the site, so its links start from ''
file_put_contents("$docs/index.html", render('index.php', ''));

// Profile pages sit one folder down in reps/, so their links start from '../'
$ids = get_db()->query("SELECT member_id FROM representatives")->fetchAll(PDO::FETCH_COLUMN);
foreach ($ids as $id) {
    file_put_contents("$docs/reps/$id.html", render('profile.php', '../', ['member_id' => (int)$id]));
}

copy_dir("$root/css", "$docs/css");
copy_dir("$root/images", "$docs/images");

// Tells GitHub Pages to serve the files exactly as they are
touch("$docs/.nojekyll");

echo "Built docs/index.html and " . count($ids) . " profile pages.\n";
