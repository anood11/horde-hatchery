<script type="text/javascript">
function toggleTags(domid)
{
    $('tag-show_' + domid).toggle();
    $('tag-hide_' + domid).toggle();
    $('tagnode_' + domid).toggle();
}
</script>
<?php
Horde::addScriptFile('QuickFinder.js', 'horde');
Horde::addScriptFile('redbox.js', 'horde');
Horde::addScriptFile('calendar-panel.js', 'kronolith');

$info_img = Horde::img('info_icon.png', _("Calendar Information"), array('class' => 'calendar-info'), $registry->getImageDir('horde'));

$current_user = Horde_Auth::getAuth();
$my_calendars = array();
$shared_calendars = array();
foreach (Kronolith::listCalendars() as $id => $cal) {
    if ($cal->get('owner') && $cal->get('owner') == $current_user) {
        $my_calendars[$id] = $cal;
    } else {
        $shared_calendars[$id] = $cal;
    }
}

$tagger = Kronolith::getTagger();
?>
<div id="pageControls">
 <a id="sbarShow" href="#" onclick="sbarToggle(); return false;"><?php echo _("Calendars") ?></a>
 <a id="sbarHide" href="#" onclick="sbarToggle(); return false;"><?php echo _("Calendars") ?></a>
 <?php if (!empty($_SESSION['kronolith']['display_cal'])): ?>
   <a id="sbarClose" href="<?php echo Horde::selfUrl(true)->add('display_cal', '') ?>"><?php echo Horde_Auth::getAuth() ? _("Return to my calendars") : _("Return to calendars") ?></a>
 <?php endif; ?>
<div id="pageControlsInner">
<?php if (!empty($_SESSION['kronolith']['display_cal'])): ?>
  <h4><?php echo ngettext("Showing calendar:", "Showing calendars:", count($calendar_names)) ?></h4>
  <?php foreach ($calendar_names as $name): ?>
   <h4><?php echo $name ?></h4>
  <?php endforeach; ?>
<?php else: ?>
<form action="<?php echo Horde::selfUrl() ?>" method="post">
<?php echo Horde_Util::formInput() ?>
 <h4><?php echo _("Search for Calendars:") ?></h4>
 <p>
  <input id="calendarSearch" type="text" for="mycalendars,sharedcalendars,remotecalendars,externalcalendars" />
 </p>

<?php if (Horde_Auth::getAuth()): ?>
 <p>
  <a href="<?php echo Horde::applicationUrl('calendars/') ?>"><?php echo _("[Manage Calendars]") ?></a><br />
  <a href="<?php echo Horde::applicationUrl('resources/')?>"><?php echo (Horde_Auth::isAdmin() ? _("[Manage Resource Calendars]") : _("[Resource Calendars]"))?></a>
 </p>
<?php endif; ?>

<?php if (count($my_calendars)): ?>
 <h4><?php echo _("My Calendars:") ?></h4>
 <ul id="mycalendars">
<?php foreach ($my_calendars as $id => $cal): ?>
  <li<?php echo Kronolith::getCSSColors($cal) ?>><label><input type="checkbox" class="checkbox" name="display_cal[]" value="<?php echo htmlspecialchars($id) ?>"<?php echo (in_array($id, $display_calendars) ? ' checked="checked"' : '') . ' /> ' . htmlspecialchars($cal->get('name')) ?></label> <?php echo $info_img ?>
   <div class="panel-tags">
   <?php $tags = $tagger->getTags($id, 'calendar'); ?>
    <span style="cursor:pointer;" id="tag-show_<?php echo $id?>" onclick="toggleTags('<?php echo $id?>');" title="<?php echo _("Add new tags")?>"><?php echo Horde::img('plus.png')?></span><span id="tag-hide_<?php echo $id?>" style="display:none;cursor:pointer;" onclick="toggleTags('<?php echo $id?>');"><?php echo Horde::img('minus.png')?></span>
    <h5 style="display: inline;"><?php echo _("Tags")?></h5>
    <div id="tagnode_<?php echo $id?>" style="display:none;">
      <ul id="tags_<?php echo $id?>">
        <?php foreach ($tags as $tag_id => $tag): ?>
         <li class="panel-tags">
<?php
echo htmlspecialchars($tag);
if ($cal->hasPermission(Horde_Auth::getAuth(), Horde_Perms::EDIT)) {
    echo '<a href="#" id="remove' . md5($id . $tag_id) . '">'
        . Horde::img('delete-small.png', _("Remove Tag"), '', $registry->getImageDir('horde'))
        . '</a>';
}
$ta = Horde_Ajax_Imple::factory(array('kronolith', 'TagActions'),
                                array('triggerId' => 'remove' . md5($id . $tag_id),
                                      'action' => 'delete',
                                      'resource' => $id,
                                      'type' => 'calendar',
                                      'tagId' => $tag_id));
$ta->attach();
?>
        </li>
        <?php endforeach;?>
      </ul>
      <div class="clear">&nbsp;</div>
      <input type="text" name="newtags-input_<?php echo $id?>" id="newtags-input_<?php echo $id?>" size="10" /><input type="button" class="button" value="add" name="newtags-button_<?php echo $id?>" id="newtags-button_<?php echo $id?>" />
      <span id="newtags-input_<?php echo $id?>_loading_img" style="display:none;"><?php echo Horde::img('loading.gif', _("Loading...")) ?></span>
     <?php
      $tac = Horde_Ajax_Imple::factory(array('kronolith', 'TagAutoCompleter'), array('triggerId' => 'newtags-input_' . $id, 'id' => $id));
      $tac->attach();
      $ta = Horde_Ajax_Imple::factory(array('kronolith', 'TagActions'),
                                          array('triggerId' => 'newtags-button',
                                                'resource' => $id,
                                                'type' => 'calendar',
                                                'action' => 'add'));
      $ta->attach();
      ?>
  </div>
  </div>
  </li>
<?php endforeach; ?>
 </ul>
<?php endif; ?>

<?php if (count($shared_calendars)): ?>
 <h4><?php echo _("Shared Calendars:") ?></h4>
 <ul id="sharedcalendars">
<?php foreach ($shared_calendars as $id => $cal): ?>
   <li<?php echo Kronolith::getCSSColors($cal) ?>><label><input type="checkbox" class="checkbox" name="display_cal[]" value="<?php echo htmlspecialchars($id) ?>"<?php echo (in_array($id, $display_calendars) ? ' checked="checked"' : '') . ' /> ' . ($cal->get('owner') ? '[' . htmlspecialchars(Horde_Auth::convertUsername($cal->get('owner'), false)) . '] ' : '') . htmlspecialchars($cal->get('name')) ?></label> <?php echo $info_img ?></li>
<?php endforeach; ?>
 </ul>
<?php endif; ?>

<?php if (count($GLOBALS['all_external_calendars'])): ?>
 <h4><?php echo _("External Events:") ?></h4>
 <ul id="externalcalendars">
<?php foreach ($GLOBALS['all_external_calendars'] as $api => $categories): ?>
 <?php if (count($categories)): ?>
  <li class="category"><?php echo htmlspecialchars($GLOBALS['registry']->get('name', $GLOBALS['registry']->hasInterface($api))) ?></li>
  <?php foreach ($categories as $id => $name): ?>
   <li><label><input type="checkbox" class="checkbox" name="display_cal[]" value="external_<?php echo htmlspecialchars($api . '/' . $id) ?>"<?php echo (in_array($api . '/' . $id, $GLOBALS['display_external_calendars']) ? ' checked="checked"' : '') . ' /> ' . htmlspecialchars($name) ?></label></li>
  <?php endforeach; ?>
 <?php endif; ?>
<?php endforeach; ?>
 </ul>
<?php endif; ?>

<?php if (count($GLOBALS['all_remote_calendars'])): ?>
 <h4><?php echo _("Remote Calendars:") ?></h4>
 <ul id="remotecalendars">
<?php foreach ($GLOBALS['all_remote_calendars'] as $id => $cal): ?>
  <li<?php echo Kronolith::getCSSColors($cal) ?>><label><input type="checkbox" class="checkbox" name="display_cal[]" value="remote_<?php echo htmlspecialchars($cal['url']) ?>"<?php echo (in_array($cal['url'], $display_remote_calendars) ? ' checked="checked"' : '') . ' /> ' . htmlspecialchars($cal['name']) ?></label> <?php echo $info_img ?></li>
<?php endforeach; ?>
 </ul>
<?php endif; ?>

<?php if (!empty($GLOBALS['conf']['holidays']['enable'])): ?>
 <h4><?php echo _("Holidays:") ?></h4>
 <ul id="holidays">
<?php foreach ($GLOBALS['all_holidays'] as $holiday): ?>
  <li<?php echo Kronolith::getCSSColors($holiday) ?>><label><input type="checkbox" class="checkbox" name="display_cal[]" value="holiday_<?php echo htmlspecialchars($holiday['id']) ?>"<?php echo (in_array($holiday['id'], $display_holidays) ? ' checked="checked"' : '') . ' /> ' . htmlspecialchars($holiday['title']) ?></label></li>
<?php endforeach; ?>
 </ul>
<?php endif; ?>

<noscript>
 <p>
  <input type="submit" value="<?php echo _("Save") ?>" class="button" />
 </p>
</noscript>
</form>

</div>
</div>
<?php endif; ?>
