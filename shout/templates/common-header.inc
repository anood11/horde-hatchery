<?php
     if (isset($language)) {
     header('Content-type: text/html; charset=' . Horde_NLS::getCharset());
     header('Vary: Accept-Language');
     }
     ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
          "DTD/xhtml1-transitional.dtd">
<!--          Shout: Copyright 2005-2006, Ben Klang.           -->
<!-- Horde Project: http://horde.org/ | Shout: http://projects.alkaloid.net/ -->
<!--          Horde Licenses: http://www.horde.org/licenses/           -->
<?php echo !empty($language) ? '<html lang="' . strtr($language, '_', '-') .
                                      '">' : '<html>' ?>
        <head>
            <?php

                 $page_title = $GLOBALS['registry']->get('name');
            if (!empty($title)) $page_title .= ' :: ' . $title;
            if (!empty($refresh_time) && !empty($refresh_url)) {
            echo "<meta http-equiv=\"refresh\"
                        content=\"$refresh_time;url=$refresh_url\">\n";
                }

                Horde::includeScriptFiles();

                ?>
                <title><?php echo $page_title ?></title>
                <?php echo Horde::includeStylesheetFiles() ?>
</head>

<body<?php if (Horde_Util::nonInputVar('bodyClass')) echo ' class="' . $bodyClass .
'"' ?>>
