<?php

/** @var Database $db */

$page_title = 'Přehled';

$stats = array();
$stats[] = array('title' => 'Vlastníci', 'value' => $db->queryValue("select count(*) from owners"), 'url' => 'index.php?url=owners');
$stats[] = array('title' => 'Parcely', 'value' => $db->queryValue("select count(*) from parcels"), 'url' => '');
$stats[] = array('title' => 'Vlastnické vztahy', 'value' => $db->queryValue("select count(*) from ownerships"), 'url' => '');
$stats[] = array('title' => 'Obce', 'value' => $db->queryValue("select count(*) from municipalities"), 'url' => 'index.php?url=municipalities');

$notes = $db->queryResult("select n.*, o.type, o.title, o.first_name, o.last_name
  from owner_notes n
  left join owners o on o.id = n.owner_id
  order by n.id desc
  limit 10");

$reports = glob(REPORT_DIR . '/nightly_*.txt');
if (!$reports) $reports = array();
rsort($reports);
$last_report = count($reports) ? $reports[0] : '';

$runs = $db->queryResult("select * from report_runs order by run_date desc limit 5");

?>
<h1>Přehled</h1>

<div class="cards">
<?php foreach ($stats as $s) { ?>
  <div class="card">
    <div class="card_value"><?php echo number_format($s['value'], 0, ',', ' ') ?></div>
    <div class="card_title"><?php if ($s['url']) { ?><a href="<?php echo $s['url'] ?>"><?php echo $s['title'] ?></a><?php } else echo $s['title'] ?></div>
  </div>
<?php } ?>
</div>

<h2>Poslední poznámky</h2>

<?php if (!count($notes)) { ?>
  <p class="empty">Žádné poznámky.</p>
<?php } else { ?>
<table class="list">
  <thead>
    <tr><th style="width: 15%">Datum</th><th style="width: 25%">Vlastník</th><th>Poznámka</th><th style="width: 12%">Zapsal</th></tr>
  </thead>
  <tbody>
<?php foreach ($notes as $n) { ?>
    <tr>
      <td><?php echo format_datetime($n['created_at']) ?></td>
      <td><a href="index.php?url=owners/detail&amp;id=<?php echo $n['owner_id'] ?>"><?php echo h(owner_name($n)) ?></a></td>
      <td><?php echo trim_text($n['note'], 120) ?></td>
      <td><?php echo h($n['created_by']) ?></td>
    </tr>
<?php } ?>
  </tbody>
</table>
<?php } ?>

<h2>Noční report</h2>

<?php if (count($runs)) { ?>
<p class="hint">
  Poslední běhy:
<?php foreach ($runs as $run) { ?>
  <?php echo format_date($run['run_date']) ?> (<?php echo $run['duration'] ?> s)<?php if ($run !== end($runs)) echo ',' ?>
<?php } ?>
</p>
<?php } ?>

<?php if ($last_report) { ?>
  <pre class="report"><?php echo h(file_get_contents($last_report)) ?></pre>
<?php } else { ?>
  <p class="empty">Report zatím nebyl vygenerován. Spouští se v noci skriptem <code>cron/nightly_report.php</code>.</p>
<?php } ?>
