<?php
header('Content-Type: application/manifest+json');
header('Cache-Control: no-cache, must-revalidate');
while (ob_get_level()) ob_end_clean();
?>
{
  "name": "MES UOL Society",
  "short_name": "MES UOL",
  "description": "Official portal of Mechanical Engineering Society, University of Lahore",
  "start_url": "https://mesuol.xo.je/mes-society/public/",
  "display": "standalone",
  "background_color": "#ffffff",
  "theme_color": "#f57c00",
  "icons": [
    {
      "src": "https://mesuol.xo.je/mes-society/assets/images/android-chrome-192x192.png",
      "sizes": "192x192",
      "type": "image/png",
      "purpose": "any maskable"
    },
    {
      "src": "https://mesuol.xo.je/mes-society/assets/images/android-chrome-512x512.png",
      "sizes": "512x512",
      "type": "image/png",
      "purpose": "any maskable"
    }
  ],
  "scope": "/mes-society/"
}