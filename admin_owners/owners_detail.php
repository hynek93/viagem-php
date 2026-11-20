<?php

/** @var Database $db */

$id = (int)get_param('id');

if (isset($_GET['delete_note']))
{
  $db->query("delete from owner_notes where id = " . $_GET['delete_note']);
  flash_set('Poznámka byla smazána.');
  redirect('index.php?url=owners/detail&id=' . $id . '#notes');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && post_param('action') == 'add_note')
{
  $note = trim(post_param('note'));

  if ($note == '')
  {
    flash_set('Poznámka nesmí být prázdná.', 'error');
  }
  else
  {
    $in = array();
    $in['owner_id'] = $id;
    $in['note'] = $note;
    $in['created_by'] = $_SESSION['user'];
    $in['created_at'] = date('Y-m-d H:i:s');

    if ($db->insert($in, 'owner_notes')) flash_set('Poznámka byla uložena.');
    else flash_set('Poznámku se nepodařilo uložit.', 'error');
  }

  redirect('index.php?url=owners/detail&id=' . $id . '#notes');
}

$owner = $db->queryOneResult("select o.*, m.name as municipality_name, m.code as municipality_code, m.district, m.region
  from owners o
  left join municipalities m on m.id = o.municipality_id
  where o.id = " . $id);

if (!$owner)
{
  $page_title = 'Vlastník nenalezen';
  ?>
  <h1>Vlastník nenalezen</h1>
  <p>Vlastník s ID <?php echo $id ?> v evidenci není. <a href="index.php?url=owners">Zpět na výpis vlastníků</a></p>
  <?php
  return;
}

// PHP 8.0: nezazavorkovany vnoreny ternarni operator je fatal error
$owner_name = $owner['type'] == 'P' ? $owner['last_name'] : ($owner['first_name'] ? owner_name($owner) : $owner['last_name']);
$page_title = $owner_name;

$parcels = owner_parcels($id);
$notes = $db->queryResult("select * from owner_notes where owner_id = " . $id . " order by created_at desc, id desc");

$total_area = 0;
$share_area = 0;
if (count($parcels))
{
  foreach ($parcels as $p)
  {
    $total_area += $p['area_m2'];
    $share_area += $p['area_m2'] * $p['share_num'] / $p['share_den'];
  }
}

?>
<p class="back">
  <a href="javascript:history.back()">‹ zpět</a>
  <a href="index.php?url=owners/edit&amp;id=<?php echo $id ?>" class="button edit">Upravit údaje</a>
</p>

<h1>
  <?php echo h($owner_name) ?>
  <?php if ($owner['do_not_contact'] == 1) { ?><span class="badge badge_red">neoslovovat</span><?php } ?>
</h1>

<?php echo flash_show() ?>

<div class="detail_grid">
  <table class="detail">
    <tr><th>ID</th><td><?php echo $owner['id'] ?></td></tr>
    <tr><th>Typ</th><td>Vlastník je <?php echo owner_type_label($owner['type'], true) ?></td></tr>
<?php if ($owner['type'] == 'P') { ?>
    <tr><th>IČO</th><td><?php echo h($owner['ico']) ?></td></tr>
<?php } else { ?>
    <tr><th>Titul</th><td><?php echo h($owner['title']) ?></td></tr>
    <tr><th>Datum narození</th><td><?php echo format_date($owner['birth_date']) ?></td></tr>
<?php } ?>
    <tr><th>Adresa</th><td><?php echo h($owner['street']) ?>, <?php echo h($owner['zip']) ?> <?php echo h($owner['city']) ?></td></tr>
    <tr><th>Obec (KN)</th><td><?php echo h($owner['municipality_name']) ?> <small>(<?php echo h($owner['municipality_code']) ?>, okres <?php echo h($owner['district']) ?>)</small></td></tr>
    <tr><th>Telefon</th><td><?php echo h($owner['phone']) ?></td></tr>
    <tr><th>E-mail</th><td><?php echo h($owner['email']) ?></td></tr>
  </table>

  <table class="detail">
    <tr><th>Termín oslovení</th><td><?php echo format_date($owner['duedate'], 'nenastaven') ?></td></tr>
    <tr><th>Založeno</th><td><?php echo format_datetime($owner['created_at']) ?></td></tr>
    <tr><th>Počet parcel</th><td><?php echo count($parcels) ?></td></tr>
    <tr><th>Výměra celkem</th><td><?php echo format_area($total_area) ?></td></tr>
    <tr><th>Výměra dle podílů</th><td><?php echo format_area(round($share_area)) ?></td></tr>
    <tr><th>Poznámka</th><td><?php echo nl2br(h($owner['note'])) ?></td></tr>
  </table>
</div>

<h2>Parcely (<?php echo count($parcels) ?>)</h2>

<?php if (count($parcels) == 0) { ?>
  <p class="empty">Vlastník nemá v evidenci žádné parcely.</p>
<?php } else { ?>
  <table class="list">
    <thead>
      <tr>
        <th>Obec</th>
        <th>Katastrální území</th>
        <th>Parcela</th>
        <th>LV</th>
        <th>Druh pozemku</th>
        <th class="right">Výměra</th>
        <th class="right">Podíl</th>
        <th class="right">Výměra podílu</th>
      </tr>
    </thead>
    <tbody>
<?php foreach ($parcels as $p) { ?>
      <tr>
        <td><?php echo h($p['municipality_name']) ?></td>
        <td><?php echo h($p['cadastral_area']) ?> <small>(<?php echo $p['cadastral_code'] ?>)</small></td>
        <td><?php echo h($p['parcel_number']) ?></td>
        <td><?php echo $p['lv_number'] ?></td>
        <td><?php echo h(mb_ucFirst($p['land_type'])) ?></td>
        <td class="right"><?php echo format_area($p['area_m2']) ?></td>
        <td class="right"><?php echo format_share($p['share_num'], $p['share_den']) ?></td>
        <td class="right"><?php echo format_area(round($p['area_m2'] * $p['share_num'] / $p['share_den'])) ?></td>
      </tr>
<?php } ?>
    </tbody>
  </table>
<?php } ?>

<h2 id="notes">Poznámky (<?php echo count($notes) ?>)</h2>

<form method="post" action="index.php?url=owners/detail&amp;id=<?php echo $id ?>" class="note_form">
  <input type="hidden" name="action" value="add_note">
  <textarea name="note" rows="3" placeholder="Nová poznámka…"></textarea>
  <button type="submit">Přidat poznámku</button>
</form>

<?php if (!count($notes)) { ?>
  <p class="empty">Zatím žádné poznámky.</p>
<?php } ?>

<?php foreach ($notes as $n) { ?>
  <div class="note">
    <div class="note_head">
      <b><?php echo h($n['created_by']) ?></b>, <?php echo format_datetime($n['created_at']) ?>
      <a href="index.php?url=owners/detail&amp;id=<?php echo $id ?>&amp;delete_note=<?php echo $n['id'] ?>" class="delete" data-confirm="Opravdu smazat poznámku?">smazat</a>
    </div>
    <div class="note_text"><?php echo nl2br($n['note']) ?></div>
  </div>
<?php } ?>
