<?php
/** Turba_EditContactForm */
require_once TURBA_BASE . '/lib/Forms/EditContact.php';

/**
 * The Turba_View_EditContact:: class provides an API for viewing events.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @package Turba
 */
class Turba_View_EditContact {

    var $contact;

    /**
     * @param Turba_Object $contact
     */
    public function __construct($contact)
    {
        $this->contact = $contact;
    }

    function getTitle()
    {
        if (!$this->contact || is_a($this->contact, 'PEAR_Error')) {
            return _("Not Found");
        }
        return sprintf(_("Edit %s"), $this->contact->getValue('name'));
    }

    function html($active = true)
    {
        global $conf, $prefs, $vars;

        if (!$this->contact || is_a($this->contact, 'PEAR_Error')) {
            echo '<h3>' . _("The requested contact was not found.") . '</h3>';
            return;
        }

        if (!$this->contact->hasPermission(Horde_Perms::EDIT)) {
            if (!$this->contact->hasPermission(Horde_Perms::READ)) {
                echo '<h3>' . _("You do not have permission to view this contact.") . '</h3>';
                return;
            } else {
                echo '<h3>' . _("You only have permission to view this contact.") . '</h3>';
                return;
            }
        }

        echo '<div id="EditContact"' . ($active ? '' : ' style="display:none"') . '>';
        $form = &new Turba_EditContactForm($vars, $this->contact);
        $form->renderActive(new Horde_Form_Renderer, $vars, 'edit.php', 'post');
        echo '</div>';

        if ($active && $GLOBALS['browser']->hasFeature('dom')) {
            if ($this->contact->hasPermission(Horde_Perms::READ)) {
                $view = new Turba_View_Contact($this->contact);
                $view->html(false);
            }
            if ($this->contact->hasPermission(Horde_Perms::DELETE)) {
                $delete = new Turba_View_DeleteContact($this->contact);
                $delete->html(false);
            }
        }
    }

}
