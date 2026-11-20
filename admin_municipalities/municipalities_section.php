<?php

/** @var Database $db */

function municipalities_row($r, $section)
{
  global $db;

  $r['parcels'] = $db->queryValue("select count(*) from parcels where municipality_id = " . $r['id']);
  $r['owners_link'] = '<a href="index.php?url=owners&amp;f_municipality=' . urlencode($r['name']) . '">vlastníci</a>';

  return $r;
}

$districts = array();
$res = $db->query("select distinct district from municipalities order by district");
while ($r = $res->fetch_assoc())
{
  $districts[$r['district']] = $r['district'];
}

$regions = array();
$res = $db->query("select distinct region from municipalities order by region");
while ($r = $res->fetch_assoc())
{
  $regions[$r['region']] = $r['region'];
}

$section = new Section('municipalities');
$section->setItemName('obcí');
$section->setTable('municipalities');
$section->setTableId('id');

$section->addCol('Kód', 8, 'code');
$section->addCol('Název', 30, 'name');
$section->addCol('Okres', 20, 'district');
$section->addCol('Kraj', 18, 'region');
$section->addCol('Parcel', 8, 'parcels', 'int', false);
$section->addCol('', 8, 'owners_link', 'html', false);

$section->addFilter('district', 'Okres', 'select', "district = '{value}'", $districts);
$section->addFilter('region', 'Kraj', 'select', "region = '{value}'", $regions);
$section->setSearch(array('name', 'code'), get_param('search'));

$section->setSortBy('name', 'asc');
$section->setRowShowFunction('municipalities_row');

?>
<h1>Obce</h1>

<?php $section->show() ?>
