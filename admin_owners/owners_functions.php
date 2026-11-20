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

  $search = get_str('search');
  $ico = regex_match('/^\s*(\d{8})\s*$/', $search, 1);
  if ($ico !== false)
  {
    $section->addSQLWhereString("o.ico = '" . $ico . "'");
    $search = '';
  }
  $section->setSearch(array('o.last_name', 'o.first_name'), $search);

  $section->setSortBy('o.last_name, o.first_name, o.id', 'asc');

  return $section;
}

function owners_list_prepare($rows, $section)
{
  global $db;

  $owner_ids = array();
  $municipality_ids = array();
  foreach ($rows as $r)
  {
    $owner_ids[] = intval($r['id']);
    $municipality_ids[intval($r['municipality_id'])] = intval($r['municipality_id']);
  }
  $owner_in = implode(', ', $owner_ids);

  $names = array();
  $res = $db->query("select id, name from municipalities where id in (" . implode(', ', $municipality_ids) . ")");
  if ($res) while ($m = $res->fetch_assoc()) $names[$m['id']] = $m['name'];

  $counts = array();
  $res = $db->query("select owner_id, count(*) as cnt from ownerships where owner_id in (" . $owner_in . ") group by owner_id");
  if ($res) while ($c = $res->fetch_assoc()) $counts[$c['owner_id']] = $c['cnt'];

  $parcels = array();
  if ($section->hasFilter('municipality'))
  {
    $res = $db->query("select os.owner_id, os.share_num, os.share_den, p.area_m2, m.name as municipality_name
      from ownerships os
      left join parcels p on p.id = os.parcel_id
      left join municipalities m on m.id = p.municipality_id
      where os.owner_id in (" . $owner_in . ")
      order by os.owner_id, m.name, p.cadastral_area, p.parcel_number");
    if ($res) while ($p = $res->fetch_assoc()) $parcels[$p['owner_id']][] = $p;
  }

  foreach ($rows as $i => $r)
  {
    $rows[$i]['_municipality_name'] = isset($names[$r['municipality_id']]) ? $names[$r['municipality_id']] : null;
    $rows[$i]['_parcel_count'] = isset($counts[$r['id']]) ? $counts[$r['id']] : '0';
    if ($section->hasFilter('municipality'))
    {
      $rows[$i]['_in_municipality'] = parcels_in_municipality_stats(isset($parcels[$r['id']]) ? $parcels[$r['id']] : array(), $section->getFilterValue('municipality'));
    }
  }

  return $rows;
}

function owners_list_row($r, $section)
{
  $html = '<a href="index.php?url=owners/detail&amp;id=' . $r['id'] . '">' . h(owner_name($r)) . '</a>';
  if ($r['do_not_contact'] == 1) $html .= ' <span class="badge badge_red">neoslovovat</span>';
  if (strlen((string)$r['note'])) $html .= ' <span class="badge" title="' . h($r['note']) . '">pozn.</span>';
  $r['owner_html'] = $html;

  $r['type_label'] = owner_type_label($r['type']);
  $r['birth_ico'] = $r['type'] == 'P' ? 'IČO ' . $r['ico'] : format_date($r['birth_date']);

  $r['municipality_html'] = h(array_key_exists('_municipality_name', $r) ? $r['_municipality_name'] : get_municipality_name($r['municipality_id']));
  $r['parcel_count'] = array_key_exists('_parcel_count', $r) ? $r['_parcel_count'] : owner_parcel_count($r['id']);

  if ($section->hasFilter('municipality'))
  {
    $in = array_key_exists('_in_municipality', $r) ? $r['_in_municipality'] : owner_parcels_in_municipality($r['id'], $section->getFilterValue('municipality'));
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
