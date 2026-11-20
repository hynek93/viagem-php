<?php

require_once ROOT . '/admin_owners/owners_functions.php';

$page_title = 'Vlastníci';

$section = owners_build_section();

$section->addCol('Vlastník', 26, 'owner_html', 'html', false);
$section->addCol('Typ', 10, 'type_label', 'string', false);
$section->addCol('Narození / IČO', 10, 'birth_ico', 'string', false);
$section->addCol('Obec', 16, 'municipality_html', 'html', false);
$section->addCol('Parcel', 6, 'parcel_count', 'int', false);
if ($section->hasFilter('municipality'))
{
  $section->addCol('V obci', 10, 'in_municipality', 'html', false);
}
$section->addCol('Termín oslovení', 10, 'duedate_html', 'html', false);
$section->addCol('Založeno', 12, 'o.created_at', 'datetime');

$section->setRowsPrepareFunction('owners_list_prepare');
$section->setRowShowFunction('owners_list_row');

?>
<h1>Vlastníci pozemků</h1>

<?php echo flash_show() ?>

<?php $section->show() ?>

<p class="hint">
  Do pole <b>Hledat</b> lze zadat i osmimístné IČO. Obec stačí zadat částí názvu, např. „Lhota“.
  Šedě jsou vlastníci bez evidovaných parcel.
</p>
