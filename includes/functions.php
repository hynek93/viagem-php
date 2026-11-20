<?php

function db_escape($val)
{
  if (is_array($val))
  {
    foreach ($val as $key => $v) $val[$key] = db_escape($v);
    return $val;
  }

  // get_magic_quotes_gpc() bylo odstraneno v PHP 8.0 (od 5.4 vracelo vzdy false)
  return addslashes((string)$val);
}

function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function get_param($name, $default = null)
{
  if (isset($_GET[$name])) return $_GET[$name];
  return $default;
}

function post_param($name, $default = null)
{
  if (isset($_POST[$name])) return $_POST[$name];
  return $default;
}

// Textovy parametr z URL / formulare. Pole (napr. ?url[]=x) se povazuje za chybejici hodnotu:
// v PHP 7.4 vedlo jen k warningu, od PHP 8.0 predani pole do trim() apod. konci TypeError (HTTP 500).
function get_str($name, $default = '')
{
  $val = get_param($name, $default);
  if (is_array($val)) return $default;
  return $val === null ? null : (string)$val;
}

function post_str($name, $default = '')
{
  $val = post_param($name, $default);
  if (is_array($val)) return $default;
  return $val === null ? null : (string)$val;
}

function redirect($url)
{
  header('Location: ' . $url);
  exit;
}

function url($params = array(), $keep_current = false)
{
  $query = $keep_current ? $_GET : array();
  foreach ($params as $key => $val)
  {
    if ($val === null) unset($query[$key]);
    else $query[$key] = $val;
  }
  if (!count($query)) return 'index.php';
  return 'index.php?' . h(http_build_query($query));
}

// puvodne match(), od PHP 8.0 je "match" rezervovane slovo
function regex_match($pattern, $subject, $group = 0)
{
  if (!preg_match($pattern, (string)$subject, $m)) return false;
  return isset($m[$group]) ? $m[$group] : false;
}

// PHP 8.4 ma mb_ucfirst()/mb_lcfirst() nativne (jmena funkci nerozlisuji velikost pismen),
// vlastni implementace proto slouzi jen jako polyfill pro starsi PHP.
if (!function_exists('mb_ucfirst'))
{
  function mb_ucFirst($s)
  {
    return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8');
  }
}

if (!function_exists('mb_lcfirst'))
{
  function mb_lcFirst($s)
  {
    return mb_strtolower(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8');
  }
}

function is_ico($s)
{
  return regex_match('/^\d{8}$/', trim((string)$s)) !== false;
}

// Prazdne datum z DB ('0000-00-00'). Nahrazuje puvodni test `$date == 0`,
// ktery od PHP 8.0 kvuli zmene porovnani retezec/cislo prestal fungovat.
function is_empty_date($date)
{
  return !$date || $date === '0000-00-00' || $date === '0000-00-00 00:00:00';
}

function format_date($date, $empty = '–')
{
  if (is_empty_date($date)) return $empty;
  return date('j. n. Y', strtotime($date));
}

function format_datetime($date, $empty = '–')
{
  if (is_empty_date($date)) return $empty;
  return date('j. n. Y H:i', strtotime($date));
}

function format_area($m2)
{
  $m2 = (float)$m2;
  if ($m2 >= 10000) return number_format($m2 / 10000, 2, ',', ' ') . ' ha';
  return number_format($m2, 0, ',', ' ') . ' m²';
}

// Vymera odpovidajici podilu. Od PHP 8.0 je deleni nulou DivisionByZeroError (7.4 vratilo INF + warning).
function share_area($area_m2, $num, $den)
{
  if (!$den) return 0;
  return $area_m2 * $num / $den;
}

function format_share($num, $den)
{
  if ($num == $den) return 'celý';
  return $num . '/' . $den;
}

function trim_text($s, $length = 80)
{
  $s = (string)$s;
  if (strlen($s) <= $length) return $s;
  return mb_substr($s, 0, $length, 'UTF-8') . '…';
}

function owner_type_label($type, $lower = false)
{
  switch ($type)
  {
    case 'F':
      $label = 'Fyzická osoba';
      break;
    case 'P':
      $label = 'Právnická osoba';
      break;
    default:
      $label = 'Neurčeno';
  }

  if ($lower) $label = mb_lcFirst($label);

  return $label;
}

function owner_name($r)
{
  if ($r['type'] == 'P') return $r['last_name'];
  return trim($r['title'] . ' ' . $r['first_name'] . ' ' . $r['last_name']);
}

function get_municipality_name($id)
{
  global $db;

  $r = $db->queryOneResult("select name from municipalities where id = " . intval($id));
  return $r['name'];
}

function owner_parcel_count($owner_id)
{
  global $db;

  $r = $db->queryOneResult("select count(*) as cnt from ownerships where owner_id = " . intval($owner_id));
  return $r['cnt'];
}

function owner_parcels_in_municipality($owner_id, $municipality)
{
  $result = array('cnt' => 0, 'area' => 0);

  $parcels = owner_parcels($owner_id);
  if (!count($parcels)) return $result;

  foreach ($parcels as $p)
  {
    if (mb_stripos((string)$p['municipality_name'], $municipality, 0, 'UTF-8') === false) continue;
    $result['cnt']++;
    $result['area'] += share_area($p['area_m2'], $p['share_num'], $p['share_den']);
  }

  return $result;
}

function owner_parcels($owner_id)
{
  global $db;

  // puvodne null -> count(null) je od PHP 8.0 TypeError
  $list = array();

  $res = $db->query("select os.id as ownership_id, os.share_num, os.share_den, os.lv_number,
      p.id, p.cadastral_area, p.cadastral_code, p.parcel_number, p.area_m2, p.land_type,
      m.name as municipality_name
    from ownerships os
    left join parcels p on p.id = os.parcel_id
    left join municipalities m on m.id = p.municipality_id
    where os.owner_id = " . intval($owner_id) . "
    order by m.name, p.cadastral_area, p.parcel_number");

  if (!$res) return $list;

  while ($r = $res->fetch_assoc())
  {
    $list[] = $r;
  }

  return $list;
}

function app_log($file, $msg)
{
  $line = date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL;
  @file_put_contents(LOG_DIR . '/' . $file, $line, FILE_APPEND);
}

function log_access($page)
{
  global $db;

  $user = isset($_SESSION['user']) ? $_SESSION['user'] : '';
  $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';

  $db->query("insert into access_log (page, ip, user_name, created_at)
    values ('" . db_escape(substr($page, 0, 255)) . "', '" . db_escape($ip) . "', '" . db_escape($user) . "', now())");
}

function flash_set($msg, $type = 'ok')
{
  $_SESSION['flash'][] = array('msg' => $msg, 'type' => $type);
}

function flash_show()
{
  if (empty($_SESSION['flash'])) return '';

  $html = '';
  foreach ($_SESSION['flash'] as $f)
  {
    $html .= '<div class="flash flash_' . $f['type'] . '">' . h($f['msg']) . '</div>';
  }
  unset($_SESSION['flash']);

  return $html;
}

function select_options($options, $selected = null)
{
  $html = '';
  foreach ($options as $key => $title)
  {
    $html .= '<option value="' . h($key) . '"' . ((string)$key === (string)$selected ? ' selected' : '') . '>' . h($title) . '</option>';
  }
  return $html;
}
