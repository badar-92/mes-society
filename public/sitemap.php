<?php
if (ob_get_level()) ob_end_clean();
header('Content-Type: application/xml; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(0);

$base_url = 'https://mesuol.xo.je';

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

function outputUrl($loc, $priority = 0.5, $changefreq = 'weekly') {
    echo "  <url>\n";
    echo "    <loc>$loc</loc>\n";
    echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
    echo "    <changefreq>$changefreq</changefreq>\n";
    echo "    <priority>$priority</priority>\n";
    echo "  </url>\n";
}

/* ONLY PUBLIC, SEO-WORTHY PAGES */
$pages = [
    '/' => ['priority' => 1.0, 'freq' => 'daily'],
    '/about.php' => ['priority' => 0.8, 'freq' => 'monthly'],
    '/events.php' => ['priority' => 0.9, 'freq' => 'weekly'],
    '/competitions.php' => ['priority' => 0.9, 'freq' => 'weekly'],
    '/gallery.php' => ['priority' => 0.7, 'freq' => 'weekly'],
    '/team.php' => ['priority' => 0.7, 'freq' => 'monthly'],
    '/contact.php' => ['priority' => 0.6, 'freq' => 'monthly'],
    '/privacy-policy.php' => ['priority' => 0.2, 'freq' => 'yearly'],
    '/terms-of-service.php' => ['priority' => 0.2, 'freq' => 'yearly']
];

foreach ($pages as $page => $data) {
    outputUrl($base_url . $page, $data['priority'], $data['freq']);
}

echo '</urlset>';
?>
