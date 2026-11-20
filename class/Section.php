<?php

class Section
{
  public $name;
  public $table;
  public $table_id = 'id';
  public $cols = array();
  public $filters = array();
  public $search_items = array();
  public $sql_where = array();
  public $sql_join = array();

  // deklarace kvuli PHP 8.2 (dynamicke vlastnosti jsou deprecated)
  public $db;
  public $item_name;
  public $limit;
  public $select_items;
  public $row_function;
  public $detail_url;
  public $export_url;
  public $search_value;
  public $sort;
  public $sort_dir;
  public $default_sort;
  public $default_sort_dir;
  public $page;
  public $total = 0;
  public $pages = 0;

  function __construct($name = null)
  {
    global $db;

    $this->db = $db;
    $this->name = $name;
    $this->item_name = 'záznamů';
    $this->limit = LIST_LIMIT;
    $this->select_items = array();
    $this->row_function = null;
    $this->detail_url = '';
    $this->export_url = '';
    $this->search_value = '';
    $this->sort = '';
    $this->sort_dir = 'asc';
    $this->default_sort = '';
    $this->default_sort_dir = 'asc';

    $this->page = intval(get_param('page', 1));
    if ($this->page < 1) $this->page = 1;
  }

  function setItemName($name) { $this->item_name = $name; }
  function setTable($table) { $this->table = $table; }
  function setTableId($id) { $this->table_id = $id; }
  function setLimit($limit) { $this->limit = $limit; }
  function setDetailUrl($url) { $this->detail_url = $url; }
  function setExportUrl($url) { $this->export_url = $url; }
  function addSelect($item) { $this->select_items[] = $item; }
  function addJoin($sql) { $this->sql_join[] = $sql; }
  function addSQLWhereString($sql) { $this->sql_where[] = $sql; }
  function setRowShowFunction($fn) { $this->row_function = $fn; }

  /*
  *   SLOUPCE
  */
  function addCol($title, $width = 0, $sort = '', $type = 'string', $sortable = true)
  {
    $col = array();
    $col['title'] = $title;
    $col['width'] = $width;
    $col['sort'] = $sort;
    $col['type'] = $type;
    $col['sortable'] = (bool)$sortable;

    $this->cols[$sort] = $col;
  }

  function removeCol($sort)
  {
    unset($this->cols[$sort]);
  }

  /*
  *   FILTRY
  */
  function addFilter($id, $title, $type = 'text', $sql = '', $options = array())
  {
    $filter = array();
    $filter['id'] = $id;
    $filter['title'] = $title;
    $filter['type'] = $type;
    $filter['sql'] = $sql;
    $filter['options'] = $options;
    $filter['value'] = trim((string)get_param('f_' . $id, ''));

    $this->filters[$id] = $filter;
  }

  function getFilterValue($id)
  {
    if (!isset($this->filters[$id])) return '';
    return $this->filters[$id]['value'];
  }

  function hasFilter($id)
  {
    return strlen($this->getFilterValue($id)) > 0;
  }

  function setSearch($items, $value)
  {
    $this->search_items = $items;
    $this->search_value = trim((string)$value);
  }

  function setSortBy($sort, $dir = 'asc')
  {
    $this->default_sort = $sort;
    $this->default_sort_dir = $dir;
  }

  /*
  *   SQL
  */
  function getWhereArray()
  {
    $where = $this->sql_where;

    foreach ($this->filters as $f)
    {
      if ($f['value'] === '' || !$f['sql']) continue;
      $where[] = str_replace('{value}', $f['value'], $f['sql']);
    }

    if ($this->search_value != '' && count($this->search_items))
    {
      $parts = array();
      foreach ($this->search_items as $item)
      {
        $parts[] = $item . " like '%" . $this->search_value . "%'";
      }
      $where[] = '(' . implode(' or ', $parts) . ')';
    }

    return $where;
  }

  function getWhere()
  {
    return $this->db->getWhere($this->getWhereArray());
  }

  function getJoin()
  {
    return implode("\n", $this->sql_join);
  }

  function getSelect()
  {
    if (!count($this->select_items)) return '*';
    return implode(', ', $this->select_items);
  }

  function getOrder()
  {
    $sort = get_param('sort');
    $dir = strtolower((string)get_param('dir', '')) == 'desc' ? 'desc' : 'asc';

    if ($sort && isset($this->cols[$sort]) && $this->cols[$sort]['sortable'])
    {
      $this->sort = $sort;
      $this->sort_dir = $dir;
      return ' order by ' . $sort . ' ' . $dir;
    }

    if ($this->default_sort) return ' order by ' . $this->default_sort . ' ' . $this->default_sort_dir;

    return '';
  }

  function getCount()
  {
    $sql = "select count(*) as pocet from " . $this->table . " " . $this->getJoin() . " " . $this->getWhere();

    $r = $this->db->queryOneResult($sql);
    if (!$r) return 0;

    return $r['pocet'];
  }

  function getRows($use_paging = true)
  {
    $sql = "select " . $this->getSelect() . "
      from " . $this->table . "
      " . $this->getJoin() . "
      " . $this->getWhere() . "
      " . $this->getOrder();

    if ($use_paging) $sql .= " limit " . (($this->page - 1) * $this->limit) . ", " . $this->limit;
    elseif ($this->limit) $sql .= " limit " . $this->limit;

    $res = $this->db->query($sql);
    if (!$res) return false;

    $rows = array();
    while ($r = $res->fetch_assoc())
    {
      if ($this->row_function)
      {
        $fn = $this->row_function;
        $r = $fn($r, $this);
      }
      $rows[] = $r;
    }

    return $rows;
  }

  /*
  *   VYPIS
  */
  function show()
  {
    $this->total = $this->getCount();
    $this->pages = ceil($this->total / $this->limit);

    $rows = $this->getRows();

    echo $this->showFilters();
    echo $this->showInfo();

    if (!$rows)
    {
      echo '<p class="empty">Nebyly nalezeny žádné ' . $this->item_name . '.</p>';
      return;
    }

    echo $this->showTable($rows);
    echo $this->showPagination();
  }

  function showFilters()
  {
    $html = '<form method="get" action="index.php" class="filters">';
    $html .= '<input type="hidden" name="url" value="' . h(get_param('url')) . '">';

    foreach ($this->filters as $f)
    {
      $html .= '<label>' . $f['title'] . ' ';
      if ($f['type'] == 'select')
      {
        $html .= '<select name="f_' . $f['id'] . '"><option value="">– vše –</option>' . select_options($f['options'], $f['value']) . '</select>';
      }
      else
      {
        $attr = $f['type'] == 'autocomplete' ? ' data-autocomplete="' . $f['id'] . '" autocomplete="off"' : '';
        $html .= '<input type="text" name="f_' . $f['id'] . '" value="' . $f['value'] . '"' . $attr . '>';
      }
      $html .= '</label> ';
    }

    if (count($this->search_items))
    {
      $html .= '<label>Hledat <input type="text" name="search" value="' . $this->search_value . '" placeholder="jméno, příjmení nebo IČO"></label> ';
    }

    $html .= '<button type="submit">Filtrovat</button> ';
    $html .= '<a href="' . url(array('url' => get_param('url'))) . '" class="reset">zrušit filtr</a>';

    if ($this->export_url)
    {
      $html .= ' <a href="' . $this->getExportLink() . '" class="button export">Export do CSV</a>';
    }

    $html .= '</form>';

    return $html;
  }

  function getExportLink()
  {
    $params = $_GET;
    unset($params['url'], $params['page'], $params['sort'], $params['dir']);
    if (!count($params)) return $this->export_url;
    return $this->export_url . '&amp;' . h(http_build_query($params));
  }

  function showInfo()
  {
    $html = '<div class="section_info">Nalezeno <b>' . number_format($this->total, 0, ',', ' ') . '</b> ' . $this->item_name;
    if ($this->search_value != '') $html .= ' pro hledaný výraz „<b>' . $this->search_value . '</b>“';
    if ($this->pages > 1) $html .= ', strana ' . $this->page . ' z ' . number_format($this->pages, 0, ',', ' ');
    $html .= '</div>';

    return $html;
  }

  function showTable($rows)
  {
    $html = '<table class="list"><thead><tr>';

    foreach ($this->cols as $key => $col)
    {
      $style = $col['width'] ? ' style="width: ' . $col['width'] . '%"' : '';
      if ($col['sortable'])
      {
        $dir = ($this->sort == $key && $this->sort_dir == 'asc') ? 'desc' : 'asc';
        $arrow = '';
        if ($this->sort == $key) $arrow = $this->sort_dir == 'asc' ? ' ▲' : ' ▼';
        $html .= '<th' . $style . '><a href="' . url(array('sort' => $key, 'dir' => $dir, 'page' => null), true) . '">' . $col['title'] . $arrow . '</a></th>';
      }
      else
      {
        $html .= '<th' . $style . '>' . $col['title'] . '</th>';
      }
    }

    $html .= '</tr></thead><tbody>';

    foreach ($rows as $r)
    {
      $html .= '<tr' . (isset($r['_class']) ? ' class="' . $r['_class'] . '"' : '') . '>';
      foreach ($this->cols as $key => $col)
      {
        $html .= '<td class="col_' . $col['type'] . '">' . $this->formatCell($key, $col, $r) . '</td>';
      }
      $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    return $html;
  }

  function formatCell($key, $col, $r)
  {
    $field = $key;
    if (strpos($field, '.') !== false)
    {
      $parts = explode('.', $field);
      $field = end($parts);
    }

    $val = isset($r[$field]) ? $r[$field] : null;

    switch ($col['type'])
    {
      case 'html':
        return $val;
      case 'date':
        return format_date($val);
      case 'datetime':
        return format_datetime($val);
      case 'int':
        return number_format((float)$val, 0, ',', ' ');
      case 'area':
        return format_area($val);
      case 'link':
        return '<a href="' . $this->detail_url . $r[$this->table_id] . '">' . h($val) . '</a>';
      default:
        return h($val);
    }
  }

  function showPagination()
  {
    if ($this->pages <= 1) return '';

    $html = '<div class="pagination">';

    if ($this->page > 1) $html .= '<a href="' . url(array('page' => $this->page - 1), true) . '">‹ předchozí</a> ';

    $from = max(1, $this->page - 4);
    $to = min($this->pages, $this->page + 4);

    if ($from > 1) $html .= '<a href="' . url(array('page' => 1), true) . '">1</a> … ';

    for ($i = $from; $i <= $to; $i++)
    {
      if ($i == $this->page) $html .= '<b>' . $i . '</b> ';
      else $html .= '<a href="' . url(array('page' => $i), true) . '">' . $i . '</a> ';
    }

    if ($to < $this->pages) $html .= '… <a href="' . url(array('page' => $this->pages), true) . '">' . $this->pages . '</a> ';

    if ($this->page < $this->pages) $html .= '<a href="' . url(array('page' => $this->page + 1), true) . '">další ›</a>';

    $html .= '</div>';

    return $html;
  }
}
