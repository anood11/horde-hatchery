<div class="header">
 <span class="rightFloat">
  <?php ($galleries_perpage < $num_galleries) ? printf(_("%d to %d of %d Galleries"), $start, $end, $num_galleries) : printf(ngettext("%d Gallery", "%d Galleries", $num_galleries), $num_galleries) ?>
 </span>
 <?php echo @htmlspecialchars($list_title, ENT_COMPAT, Horde_Nls::getCharset()) . ' ' . Horde::link(Horde::applicationUrl($refresh_link), _("Refresh List")) . Horde::img('reload.png', _("Refresh List"), null, $registry->getImageDir('horde')) . '</a>' ?>
</div>
<table width="100%" style="background:<?php echo $styleDef['background'] ?>;"><tr>
