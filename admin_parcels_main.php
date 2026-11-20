<?php

$page_title = 'Parcely';

function parcels_row($r, $section)
{
  global $db;

  $owners = $db->queryResult("select o.id, o.type, o.title, o.first_name, o.last_name, os.share_num, os.share_den, os.lv_number
    from ownerships os
    left join owners o on o.id = os.owner_id
    where os.parcel_id = " . $r['id'] . "
    order by o.last_name");

  $links = array();
  $lv = array();
  foreach ($owners as $o)
  {
    $links[] = '<a href="index.php?url=owners/detail&amp;id=' . $o['id'] . '">' . h(owner_name($o)) . '</a> <small>(' . format_share($o['share_num'], $o['share_den']) . ')</small>';
    $lv[(string)$o['lv_number']] = $o['lv_number'];
  }

  $r['owners_html'] = count($links) ? implode('<br>', $links) : '<span class="muted">bez vlastníka</span>';
  $r['lv'] = implode(', ', $lv);
  $r['land_type'] = mb_ucFirst($r['land_type']);
  $r['parcel'] = $r['parcel_number'];

  return $r;
}

$land_types = array();
$res = $db->query("select distinct land_type from parcels order by land_type");
while ($r = $res->fetch_assoc())
{
  $land_types[$r['land_type']] = mb_ucFirst($r['land_type']);
}

$section = new Section('parcels');
$section->setItemName('parcel');
$section->setTable('parcels p');
$section->setTableId('id');

$section->addSelect('p.*');
$section->addSelect('m.name as municipality_name');
$section->addJoin('left join municipalities m on m.id = p.municipality_id');

$section->addCol('Obec', 18, 'municipality_name', 'string', false);
$section->addCol('Katastrální území', 20, 'p.cadastral_area');
$section->addCol('Parcela', 8, 'parcel', 'string', false);
$section->addCol('LV', 6, 'lv', 'string', false);
$section->addCol('Druh pozemku', 14, 'land_type', 'string', false);
$section->addCol('Výměra', 9, 'p.area_m2', 'area');
$section->addCol('Vlastníci', 25, 'owners_html', 'html', false);

$section->addFilter('municipality', 'Obec', 'autocomplete', "m.name like '%{value}%'");
$section->addFilter('land_type', 'Druh', 'select', "p.land_type = '{value}'", $land_types);
$section->setSearch(array('p.parcel_number', 'p.cadastral_code'), get_str('search'));

$section->setSortBy('p.id', 'asc');
$section->setRowShowFunction('parcels_row');

?>
<h1>Parcely</h1>

<?php $section->show() ?>

<p class="hint">Hledat lze podle čísla parcely (např. „st. 121“ nebo „1517/9“) nebo kódu katastrálního území.</p>
