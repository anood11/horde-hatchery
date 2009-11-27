<?php
/**
 * Copyright 2002-2008 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

@define('FIMA_BASE', dirname(dirname(__FILE__)));
require_once FIMA_BASE . '/lib/base.php';
require_once FIMA_BASE . '/lib/Forms/DeleteLedger.php';

// Exit if this isn't an authenticated user.
if (!Horde_Auth::getAuth()) {
    header('Location: ' . Horde::applicationUrl('postings.php', true));
    exit;
}

$vars = Horde_Variables::getDefaultVariables();
$ledger_id = $vars->get('l');
if ($ledger_id == Horde_Auth::getAuth()) {
    $notification->push(_("This ledger cannot be deleted."), 'horde.warning');
    header('Location: ' . Horde::applicationUrl('ledgers/', true));
    exit;
}

$ledger = $fima_shares->getShare($ledger_id);
if (is_a($ledger, 'PEAR_Error')) {
    $notification->push($ledger, 'horde.error');
    header('Location: ' . Horde::applicationUrl('ledgers/', true));
    exit;
} elseif ($ledger->get('owner') != Horde_Auth::getAuth()) {
    $notification->push(_("You are not allowed to delete this ledger."), 'horde.error');
    header('Location: ' . Horde::applicationUrl('ledgers/', true));
    exit;
}

$form = new Fima_DeleteLedgerForm($vars, $ledger);

// Execute if the form is valid (must pass with POST variables only).
if ($form->validate(new Horde_Variables($_POST))) {
    $result = $form->execute();
    if (is_a($result, 'PEAR_Error')) {
        $notification->push($result, 'horde.error');
    } elseif ($result) {
        $notification->push(sprintf(_("The ledger \"%s\" has been deleted."), $ledger->get('name')), 'horde.success');
    }

    header('Location: ' . Horde::applicationUrl('ledgers/', true));
    exit;
}

$title = $form->getTitle();
require FIMA_TEMPLATES . '/common-header.inc';
require FIMA_TEMPLATES . '/menu.inc';
echo $form->renderActive($form->getRenderer(), $vars, 'delete.php', 'post');
require $registry->get('templates', 'horde') . '/common-footer.inc';
