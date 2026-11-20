<?php

/** @var Database $db */

require_once dirname(__FILE__) . '/includes/config.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim(get_str('q'));

if (strlen($q) < 2)
{
  echo json_encode(array());
  exit;
}

$res = $db->query("select id, code, name, district
  from municipalities
  where name like '" . $q . "%'
  order by name
  limit 15");

$out = array();

if ($res)
{
  while ($r = $res->fetch_assoc())
  {
    $item = array();
    $item['id'] = $r['id'];
    $item['code'] = $r['code'];
    $item['value'] = $r['name'];
    $item['label'] = $r['name'] . ' (okres ' . $r['district'] . ')';
    $out[] = $item;
  }
}

echo json_encode($out);
