<?php
/**
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */
require_once dirname(__FILE__) . '/../../lib/base.php';

$title = _("Resource Groups");

require KRONOLITH_TEMPLATES . '/common-header.inc';
require KRONOLITH_TEMPLATES . '/menu.inc';

// Exit if this isn't an authenticated user.
if (!Horde_Auth::getAuth()) {
    header('Location: ' . Horde::applicationUrl($prefs->getValue('defaultview') . '.php'));
    exit;
}
$edit_url_base = Horde::applicationUrl('resources/groups/edit.php');
$edit_img = Horde::img('edit.png', _("Edit"), null, $registry->getImageDir('horde'));

$resources = Kronolith::getDriver('Resource')->listResources(Horde_Perms::EDIT, array('type' => Kronolith_Resource::TYPE_GROUP));
//$display_url_base = Horde::applicationUrl('month.php', true, -1);
$delete_url_base = Horde::applicationUrl('resources/groups/delete.php');
$delete_img = Horde::img('delete.png', _("Delete"), null, $registry->getImageDir('horde'));
?>
<script type="text/javascript">
function performAction(action, rid)
{
    document.resourceform.actionId.value = action;
    document.resourceform.actionValue.value = rid;
    document.resourceform.submit();
    return false;
}
</script>
<!-- Break out into template -->
<div id="page">

<h1 class="header">
 <?php echo _("Resources") ?>
</h1>
<?php if ($isAdmin = Horde_Auth::isAdmin()): ?>
 <form method="get" action="create.php">
  <?php echo Horde_Util::formInput() ?>
  <input type="submit" class="button" value="<?php echo _("Create a new Resource Group") ?>" />
  <a class="button" href="<?php echo Horde::applicationUrl('resources')?>"><?php echo _("Return to Single Resources")?></a>
 </form>
<?php endif ?>
<table summary="<?php echo _("Resource Group List") ?>" cellspacing="0" id="calendar-list" class="striped sortable">
 <thead>
  <tr>
   <th>&nbsp;</th>
   <th class="sortdown"><?php echo _("Name") ?></th>
   <th><?php echo _("Description") ?></th>
  </tr>
 </thead>
 <tbody>
<?php foreach ($resources as $resource): ?>
 <tr>
  <?php if ($isAdmin):?>
  <td>
    <?php echo $delete_url_base->add('c', $resource->getId())->link(array('title' => _("Delete"))) . $delete_img . '</a>' ?>
    <?php echo $edit_url_base->add('c', $resource->getId())->link(array('title' => _("Edit"))) . $edit_img . '</a>' ?>
  <?php else:?>
  <td>&nbsp;</td>
  <?php endif;?>
  <td><?php echo htmlspecialchars($resource->get('name')) ?></td>
  <td><?php echo htmlspecialchars($resource->get('description')) ?></td>
 </tr>
<?php endforeach; ?>
</tbody>
</table>

<?php
/**
 * Show just the beginning and end of long URLs.
 */
function shorten_url($url, $separator = '...', $first_chunk_length = 35, $last_chunk_length = 15)
{
    $url_length = strlen($url);
    $max_length = $first_chunk_length + strlen($separator) + $last_chunk_length;

    if ($url_length > $max_length) {
        return substr_replace($url, $separator, $first_chunk_length, -$last_chunk_length);
    }

    return $url;
}
?>
</div>