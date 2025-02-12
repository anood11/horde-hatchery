<?php if (count($branches) > 1): ?>
<div class="options">
 <span>
  <form method="get" action="browsedir.php">
   <?php echo _("View Branch:") ?>
   <?php echo Chora::formInputs() ?>
   <input type="hidden" name="f" value="<?php echo htmlspecialchars(Horde_Util::getFormData('f')) ?>" />
   <select name="onb" onchange="this.form.submit()">
<?php foreach ($branches as $branch): ?>
    <option value="<?php echo htmlspecialchars($branch) ?>"<?php echo ($branch == $onb) ? ' selected="selected"' : '' ?>><?php echo htmlspecialchars($branch) ?></option>
<?php endforeach; ?>
   </select>
   <noscript><input class="button" type="submit" value="<?php echo _("View") ?>" /></noscript>
  </form>
 </span>
</div>
<?php endif; ?>

<table id="browse" cellspacing="0" class="browse sortable striped nowrap">
<thead>
 <tr>
  <th id="s<?php echo Horde_Vcs::SORT_NAME ?>"<?php if ($acts['sbt'] == Horde_Vcs::SORT_NAME) echo ' class="' . $sortdirclass . '"' ?>>
   <?php echo Horde::widget($url['name'], _("File"), 'sortlink', '', '', _("_File")) ?>
  </th>
  <th id="s<?php echo Horde_Vcs::SORT_REV ?>"<?php if ($acts['sbt'] == Horde_Vcs::SORT_REV) echo ' class="' . $sortdirclass . '"' ?>>
   <?php echo Horde::widget($url['rev'], _("Rev"), 'sortlink', '', '', _("_Rev")) ?>
  </th>
  <th id="s<?php echo Horde_Vcs::SORT_AUTHOR ?>"<?php if ($acts['sbt'] == Horde_Vcs::SORT_AUTHOR) echo ' class="' . $sortdirclass . '"' ?>>
   <?php echo Horde::widget($url['author'], _("Author"), 'sortlink', '', '', _("_Author")) ?>
  </th>
  <th id="s<?php echo Horde_Vcs::SORT_AGE ?>"<?php if ($acts['sbt'] == Horde_Vcs::SORT_AGE) echo ' class="' . $sortdirclass . '"' ?>>
   <?php echo Horde::widget($url['age'], _("Date"), 'sortlink', '', '', _("Da_te")) ?>
  </th>
  <th class="nosort"><?php echo _("Last Log") ?></th>
 </tr>
</thead>
