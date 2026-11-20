<?php

$time_start = microtime(true);

require_once dirname(__FILE__) . '/includes/config.php';

$url = trim(get_str('url'), '/');
$url_parts = $url != '' ? explode('/', $url) : array();

define('URL1', isset($url_parts[0]) ? $url_parts[0] : 'dashboard');
define('URL2', isset($url_parts[1]) ? $url_parts[1] : '');

$menu = array();
$menu['dashboard'] = 'Přehled';
$menu['owners'] = 'Vlastníci';
$menu['parcels'] = 'Parcely';
$menu['municipalities'] = 'Obce';

$main_file = ROOT . '/admin_' . URL1 . '_main.php';

if (!isset($menu[URL1]) || !is_file($main_file))
{
  header('HTTP/1.1 404 Not Found');
  $page_title = 'Stránka nenalezena';
  $content = '<h1>Stránka nenalezena</h1><p><a href="index.php">Zpět na přehled</a></p>';
}
else
{
  $page_title = $menu[URL1];

  ob_start();
  require $main_file;
  $content = ob_get_clean();
}

require ROOT . '/layout/header.php';
echo $content;
require ROOT . '/layout/footer.php';
