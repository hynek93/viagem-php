<?php

/** @var Database $db */

$id = intval(get_param('id'));

$owner = $db->getById($id, 'owners');
if (!$owner)
{
  $page_title = 'Vlastník nenalezen';
  echo '<h1>Vlastník nenalezen</h1><p><a href="index.php?url=owners">Zpět na výpis vlastníků</a></p>';
  return;
}

$errors = array();

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
  $in = array();
  $in['id'] = $id;
  $in['title'] = trim(post_str('title'));
  $in['first_name'] = trim(post_str('first_name'));
  $in['last_name'] = trim(post_str('last_name'));
  $in['street'] = trim(post_str('street'));
  $in['zip'] = str_replace(' ', '', post_str('zip'));
  $in['city'] = trim(post_str('city'));
  $in['phone'] = trim(post_str('phone'));
  $in['email'] = trim(post_str('email'));
  $in['duedate'] = post_str('duedate', null);
  $in['do_not_contact'] = post_param('do_not_contact') ? '1' : '0';
  $in['note'] = post_str('note', null);

  if ($in['last_name'] == '') $errors[] = $owner['type'] == 'P' ? 'Vyplňte název firmy.' : 'Vyplňte příjmení.';
  if ($in['email'] != '' && !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail nemá platný formát.';
  if ($in['zip'] != '' && !preg_match('/^\d{5}$/', $in['zip'])) $errors[] = 'PSČ musí mít 5 číslic.';
  if ($in['duedate'] != '' && !strtotime($in['duedate'])) $errors[] = 'Termín oslovení není platné datum.';

  if (!count($errors))
  {
    if ($db->update($in, 'owners', 'id'))
    {
      flash_set('Údaje vlastníka byly uloženy.');
      app_log('owners.log', $_SESSION['user'] . ' upravil vlastníka ' . $id);
    }
    else
    {
      flash_set('Uložení se nezdařilo.', 'error');
    }
    redirect('index.php?url=owners/detail&id=' . $id);
  }

  $owner = array_merge($owner, $in);
}

$page_title = 'Úprava – ' . owner_name($owner);

?>
<p class="back"><a href="index.php?url=owners/detail&amp;id=<?php echo $id ?>">‹ zpět na detail</a></p>

<h1>Úprava vlastníka</h1>

<?php foreach ($errors as $e) { ?>
  <div class="flash flash_error"><?php echo h($e) ?></div>
<?php } ?>

<form method="post" action="index.php?url=owners/edit&amp;id=<?php echo $id ?>" class="edit_form">
  <table class="detail">
<?php if ($owner['type'] == 'P') { ?>
    <tr><th>Název firmy</th><td><input type="text" name="last_name" value="<?php echo h($owner['last_name']) ?>" size="50"></td></tr>
    <tr><th>IČO</th><td><?php echo h($owner['ico']) ?></td></tr>
<?php } else { ?>
    <tr><th>Titul</th><td><input type="text" name="title" value="<?php echo h($owner['title']) ?>" size="10"></td></tr>
    <tr><th>Jméno</th><td><input type="text" name="first_name" value="<?php echo h($owner['first_name']) ?>"></td></tr>
    <tr><th>Příjmení</th><td><input type="text" name="last_name" value="<?php echo h($owner['last_name']) ?>"></td></tr>
<?php } ?>
    <tr><th>Ulice a č. p.</th><td><input type="text" name="street" value="<?php echo h($owner['street']) ?>" size="40"></td></tr>
    <tr><th>PSČ / město</th><td>
      <input type="text" name="zip" value="<?php echo h($owner['zip']) ?>" size="6">
      <input type="text" name="city" value="<?php echo h($owner['city']) ?>" size="30">
    </td></tr>
    <tr><th>Telefon</th><td><input type="text" name="phone" value="<?php echo h($owner['phone']) ?>"></td></tr>
    <tr><th>E-mail</th><td><input type="text" name="email" value="<?php echo h($owner['email']) ?>" size="40"></td></tr>
    <tr><th>Termín oslovení</th><td><input type="date" name="duedate" value="<?php echo is_empty_date($owner['duedate']) ? '' : h($owner['duedate']) ?>"></td></tr>
    <tr><th>Neoslovovat</th><td>
      <label><input type="checkbox" name="do_not_contact" value="1"<?php if ($owner['do_not_contact'] == 1) echo ' checked' ?>> vlastník si nepřeje být kontaktován</label>
    </td></tr>
    <tr><th>Poznámka</th><td><textarea name="note" rows="3" cols="60"><?php echo h($owner['note']) ?></textarea></td></tr>
  </table>
  <p><button type="submit">Uložit</button></p>
</form>
