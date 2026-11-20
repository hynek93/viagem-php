<?php

/** @var Database $db */

// 15 2 * * * www-data php /var/www/html/cron/nightly_report.php > /dev/null 2>&1

if (PHP_SAPI != 'cli')
{
  header('HTTP/1.1 403 Forbidden');
  die('Skript lze spustit pouze z příkazové řádky.');
}

require_once dirname(dirname(__FILE__)) . '/includes/config.php';

function cron_log($msg)
{
  $line = date('Y-m-d H:i:s') . ' [nightly] ' . $msg;
  echo $line . PHP_EOL;
  app_log('cron.log', '[nightly] ' . $msg);
}

function remote_ping($url)
{
  $ch = curl_init();
  // PHP 8.0: curl_init() vraci objekt CurlHandle, ne resource -> is_resource() by vzdy selhalo
  if ($ch === false)
  {
    return array('ok' => false, 'msg' => 'curl není k dispozici');
  }

  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10);

  $body = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  // curl_close() je od 8.0 no-op a v 8.5 deprecated
  if (PHP_VERSION_ID < 80000) curl_close($ch);

  if ($body === false) return array('ok' => false, 'msg' => $error);
  if ($code != 200) return array('ok' => false, 'msg' => 'HTTP ' . $code);

  return array('ok' => true, 'msg' => trim($body));
}

function upload_report($url, $file)
{
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, array('report' => new CURLFile($file, 'text/plain', basename($file))));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($ch, CURLOPT_TIMEOUT, 20);

  $res = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if (PHP_VERSION_ID < 80000) curl_close($ch);

  return $res !== false && $code == 200;
}

$time_start = microtime(true);
$run_date = date('Y-m-d');
// backtick operator je v PHP 8.5 deprecated
$hostname = gethostname();

cron_log('Start, server ' . $hostname);

$stats = array();
$stats['owners_total'] = (int)$db->queryValue("select count(*) from owners");
$stats['owners_f'] = 0;
$stats['owners_p'] = 0;
$stats['owners_other'] = 0;

$res = $db->query("select type, count(*) as cnt from owners group by type");
while ($r = $res->fetch_assoc())
{
  switch ($r['type'])
  {
    case 'F':
      $stats['owners_f'] = (int)$r['cnt'];
      break;
    case 'P':
      $stats['owners_p'] = (int)$r['cnt'];
      break;
    default:
      $stats['owners_other'] += (int)$r['cnt'];
  }
}

$stats['parcels_total'] = (int)$db->queryValue("select count(*) from parcels");
$stats['parcels_area'] = (int)$db->queryValue("select sum(area_m2) from parcels");
$stats['ownerships_total'] = (int)$db->queryValue("select count(*) from ownerships");
$stats['notes_total'] = (int)$db->queryValue("select count(*) from owner_notes");
$stats['notes_24h'] = (int)$db->queryValue("select count(*) from owner_notes where created_at >= now() - interval 1 day");
$stats['do_not_contact'] = (int)$db->queryValue("select count(*) from owners where do_not_contact = 1");
$stats['duedate_empty'] = (int)$db->queryValue("select count(*) from owners where duedate = '0000-00-00'");
$stats['duedate_overdue'] = (int)$db->queryValue("select count(*) from owners where duedate <> '0000-00-00' and duedate < curdate()");

$top = $db->queryResult("select municipality_id, count(*) as cnt from owners group by municipality_id order by cnt desc, municipality_id limit 10");
$land_types = $db->queryResult("select land_type, count(*) as cnt, sum(area_m2) as area from parcels group by land_type order by area desc");

$report = array();
$report[] = 'NOČNÍ REPORT – ' . APP_NAME;
$report[] = 'Datum: ' . date('d.m.Y H:i') . ', server: ' . $hostname;
$report[] = str_repeat('=', 60);
$report[] = sprintf('%-40s %15s', 'Vlastníci celkem', number_format($stats['owners_total'], 0, ',', ' '));
$report[] = sprintf('%-40s %15s', '  z toho fyzické osoby', number_format($stats['owners_f'], 0, ',', ' '));
$report[] = sprintf('%-40s %15s', '  z toho právnické osoby', number_format($stats['owners_p'], 0, ',', ' '));
$report[] = sprintf('%-40s %15s', 'Parcely', number_format($stats['parcels_total'], 0, ',', ' '));
$report[] = sprintf('%-40s %15s', 'Výměra parcel celkem (ha)', number_format($stats['parcels_area'] / 10000, 2, ',', ' '));
$report[] = sprintf('%-40s %15s', 'Vlastnické vztahy', number_format($stats['ownerships_total'], 0, ',', ' '));
$report[] = sprintf('%-40s %15s', 'Poznámky celkem / za 24 h', $stats['notes_total'] . ' / ' . $stats['notes_24h']);
$report[] = sprintf('%-40s %15s', 'Zákaz oslovení', number_format($stats['do_not_contact'], 0, ',', ' '));
$report[] = sprintf('%-40s %15s', 'Bez termínu oslovení', number_format($stats['duedate_empty'], 0, ',', ' '));
$report[] = sprintf('%-40s %15s', 'Termín oslovení po splatnosti', number_format($stats['duedate_overdue'], 0, ',', ' '));
$report[] = '';
$report[] = 'Obce s nejvíce vlastníky:';
foreach ($top as $i => $t)
{
  $report[] = sprintf('%3d. %-45s %8d', $i + 1, get_municipality_name($t['municipality_id']), $t['cnt']);
}
$report[] = '';
$report[] = 'Druhy pozemků:';
foreach ($land_types as $lt)
{
  $report[] = sprintf('  %-35s %9d parcel %14s ha', mb_ucFirst($lt['land_type']), $lt['cnt'], number_format($lt['area'] / 10000, 2, ',', ' '));
}

$duration = round(microtime(true) - $time_start, 2);
$report[] = '';
$report[] = 'Doba výpočtu: ' . $duration . ' s';

if (!is_dir(REPORT_DIR)) mkdir(REPORT_DIR, 0775, true);

$report_file = REPORT_DIR . '/nightly_' . $run_date . '.txt';
if (!file_put_contents($report_file, implode(PHP_EOL, $report) . PHP_EOL))
{
  cron_log('CHYBA: report nelze zapsat do ' . $report_file);
  exit(1);
}
cron_log('Report uložen: ' . $report_file);

$in = array();
$in['run_date'] = $run_date;
$in['hostname'] = $hostname;
$in['duration'] = $duration;
$in['owners_total'] = $stats['owners_total'];
$in['report_file'] = basename($report_file);
$in['created_at'] = date('Y-m-d H:i:s');

if (!$db->insert($in, 'report_runs'))
{
  cron_log('Běh za ' . $run_date . ' už je v historii zapsaný, záznam přeskočen.');
}

$ping = remote_ping(REPORT_PING_URL . '?date=' . $run_date . '&host=' . urlencode($hostname));
if ($ping['ok']) cron_log('Reportovací služba: OK (' . $ping['msg'] . ')');
else cron_log('Reportovací služba nedostupná: ' . $ping['msg']);

$uploaded = (bool)upload_report(REPORT_UPLOAD_URL, $report_file);
if ($uploaded) cron_log('Report odeslán.');
else cron_log('Report se nepodařilo odeslat, zůstává jen lokálně.');

cron_log('Hotovo za ' . round(microtime(true) - $time_start, 2) . ' s, SQL dotazů: ' . $db->query_count);
