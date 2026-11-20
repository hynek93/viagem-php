<?php

function owners_build_section(?Section $section = null, $for_export = false)
{
  if (!$section) $section = new Section('owners');

  $section->setItemName('vlastníků');
  $section->setTable('owners o');
  $section->setTableId('id');
  $section->setDetailUrl('index.php?url=owners/detail&amp;id=');
  $section->setExportUrl('index.php?url=owners/export');

  $section->addSelect('o.*');

  $section->addFilter('municipality', 'Obec', 'autocomplete', "m.name like '%{value}%'");
  $section->addFilter('type', 'Typ', 'select', "o.type = '{value}'", array('F' => 'Fyzická osoba', 'P' => 'Právnická osoba'));
  $section->addFilter('dnc', 'Neoslovovat', 'select', "o.do_not_contact = '{value}'", array('1' => 'ano', '0' => 'ne'));

  if ($section->hasFilter('municipality') || $for_export)
  {
    $section->addJoin('left join municipalities m on m.id = o.municipality_id');
    if ($for_export) $section->addSelect('m.name as municipality_name');
  }

  $search = (string)get_param('search', '');
  $ico = regex_match('/^\s*(\d{8})\s*$/', $search, 1);
  if ($ico !== false)
  {
    $section->addSQLWhereString("o.ico = '" . $ico . "'");
    $search = '';
  }
  $section->setSearch(array('o.last_name', 'o.first_name'), $search);

  $section->setSortBy("concat(o.last_name, ' ', o.first_name)", 'asc');

  return $section;
}

function owners_list_row($r, $section)
{
  $html = '<a href="index.php?url=owners/detail&amp;id=' . $r['id'] . '">' . h(owner_name($r)) . '</a>';
  if ($r['do_not_contact'] == 1) $html .= ' <span class="badge badge_red">neoslovovat</span>';
  if (strlen((string)$r['note'])) $html .= ' <span class="badge" title="' . h($r['note']) . '">pozn.</span>';
  $r['owner_html'] = $html;

  $r['type_label'] = owner_type_label($r['type']);
  $r['birth_ico'] = $r['type'] == 'P' ? 'IČO ' . $r['ico'] : format_date($r['birth_date']);

  $r['municipality_html'] = h(get_municipality_name($r['municipality_id']));
  $r['parcel_count'] = owner_parcel_count($r['id']);

  if ($section->hasFilter('municipality'))
  {
    $in = owner_parcels_in_municipality($r['id'], $section->getFilterValue('municipality'));
    if ($in['cnt'] > 0) $r['in_municipality'] = $in['cnt'] . ' <small>(' . format_area($in['area']) . ')</small>';
    else $r['in_municipality'] = '<span class="muted">–</span>';
  }

  if (is_empty_date($r['duedate']))
  {
    $r['duedate_html'] = '<span class="muted">nenastaven</span>';
  }
  else
  {
    $ts = strtotime($r['duedate']);
    $r['duedate_html'] = '<span class="' . ($ts < time() ? 'overdue' : 'planned') . '">' . date('j. n. Y', $ts) . '</span>';
  }

  if (!$r['parcel_count']) $r['_class'] = 'no_parcels';

  return $r;
}
