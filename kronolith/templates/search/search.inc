<form method="post" name="eventform" action="search.php">
<?php Horde_Util::pformInput() ?>
<input type="hidden" name="actionID" value="search_calendar" />
<input type="hidden" name="search_mode" value="basic" />

<br /><br />
<h1 class="header">
 <span class="smallheader rightFloat">
  <?php echo Horde::widget(Horde::applicationUrl('search.php')->add('search_mode', 'advanced'), _("Advanced Search"), 'smallheader', '', '', _("Ad_vanced Search")) ?>
 </span>
 <?php echo _("Basic Search") ?>
</h1>

<table cellspacing="0" class="striped">
<!-- search pattern -->
<tr>
  <td class="rightAlign">
    <strong><?php echo Horde::label('pattern_title', _("Title")) ?></strong>
  </td>
  <td>
    <input name="pattern_title" id="pattern_title" type="text" value="<?php echo htmlspecialchars(Horde_Util::getFormData('pattern_title')) ?>" size="50" maxlength="100" />
  </td>
</tr>
<tr>
  <td class="rightAlign">
    <strong><?php echo Horde::label('pattern_desc', _("Descri_ption")) ?></strong>
  </td>
  <td>
    <input name="pattern_desc" id="pattern_desc" type="text" value="<?php echo htmlspecialchars(Horde_Util::getFormData('pattern_desc')) ?>" size="50" maxlength="100" />
  </td>
</tr>
<!-- range -->
<tr>
  <td class="rightAlign">
    <strong><?php echo Horde::label('range', _("Time Sp_an")) ?></strong>
  </td>
  <td>
    <select id="range" name="range">
<?php
function range_option_string($thisrange, $descr)
{
    echo '<option ';
    if (Horde_Util::getFormData('range') == $thisrange) {
        echo "value=\"$thisrange\" selected=\"selected\"";
    } else {
        echo "value=\"$thisrange\"";
    }
    echo '>' . $descr . '</option>';
}

range_option_string('-', _("All Past"));
range_option_string('-604800', _("Last week"));
range_option_string('-86400', _("Last 24 hours"));
range_option_string('0', _("Now"));
range_option_string('86400', _("Next 24 hours"));
range_option_string('604800', _("Next week"));
range_option_string('+', _("All Future"));
?>
    </select>
  </td>
</tr>
</table>

<br />
<input type="submit" class="button" value="<?php echo _("Search") ?>" />
</form>
