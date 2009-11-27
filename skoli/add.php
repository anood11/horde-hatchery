<?php
/**
 * Copyright 2001-2008 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Martin Blumenthal <tinu@humbapa.ch>
 */

@define('SKOLI_BASE', dirname(__FILE__));
require_once SKOLI_BASE . '/lib/base.php';
require_once SKOLI_BASE . '/lib/Forms/Entry.php';

/* Redirect to create a new class if we don't have access to any class */
if (count(Skoli::listClasses(false, Horde_Perms::EDIT)) == 0 && Horde_Auth::getAuth()) {
    $notification->push(_("Please create a new Class first."), 'horde.message');
    header('Location: ' . Horde::applicationUrl('classes/create.php', true));
    exit;
}

$vars = Horde_Variables::getDefaultVariables();
$form = new Skoli_EntryForm($vars);

// Execute if the form is valid.
if ($form->validate($vars)) {
    $result = $form->execute();
    if (is_a($result, 'PEAR_Error')) {
        $notification->push($result, 'horde.error');
    } else {
        $notification->push(sprintf(_("The new entry for \"%s\" has been added."), $result), 'horde.success');
    }

    header('Location: ' . Horde::applicationUrl(Horde_Util::addParameter('add.php', 'class', $vars->get('class_id')), true));
    exit;
}

$title = $form->getTitle();
require SKOLI_TEMPLATES . '/common-header.inc';
require SKOLI_TEMPLATES . '/menu.inc';
echo $form->renderActive($form->getRenderer(), $vars, 'add.php', 'post');
require $registry->get('templates', 'horde') . '/common-footer.inc';
