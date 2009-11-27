<?php
/**
 * Horde_Form for editing resource calendars.
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @package Kronolith
 */

/** Horde_Form */
require_once 'Horde/Form.php';

/** Horde_Form_Renderer */
require_once 'Horde/Form/Renderer.php';

/**
 * The Kronolith_EditResourceForm class provides the form for
 * editing a calendar.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @package Kronolith
 */
class Kronolith_EditResourceForm extends Horde_Form {

    /**
     * Calendar being edited
     */
    var $_resource;

    function Kronolith_EditResourceForm(&$vars, &$resource)
    {
        $this->_resource = &$resource;
        parent::Horde_Form($vars, sprintf(_("Edit %s"), $resource->get('name')));
        $responses =  array(Kronolith_Resource::RESPONSETYPE_ALWAYS_ACCEPT => _("Always Accept"),
                            Kronolith_Resource::RESPONSETYPE_ALWAYS_DECLINE => _("Always Decline"),
                            Kronolith_Resource::RESPONSETYPE_AUTO => _("Automatically"),
                            Kronolith_Resource::RESPONSETYPE_MANUAL => _("Manual"),
                            Kronolith_Resource::RESPONSETYPE_NONE => _("None"));

        /* Get a list of available resource groups */
        $driver = Kronolith::getDriver('Resource');
        $groups = $driver->listResources(Horde_Perms::READ, array('type' => Kronolith_Resource::TYPE_GROUP));
        $enum = array();
        foreach ($groups as $id => $group) {
            $enum[$id] = $group->get('name');
        }

        $this->addHidden('', 'c', 'text', true);
        $this->addVariable(_("Name"), 'name', 'text', true);
        $this->addVariable(_("Description"), 'description', 'longtext', false, false, null, array(4, 60));
        $this->addVariable(_("Response type"), 'responsetype', 'enum', true, false, null, array('enum' => $responses));
        $this->addVariable(_("Groups"), 'category', 'multienum', false, false, null, array('enum' => $enum));
        $this->setButtons(array(_("Save")));
    }

    function execute()
    {
        $original_name = $this->_resource->get('name');
        $new_name = $this->_vars->get('name');
        $this->_resource->set('name', $new_name);
        $this->_resource->set('description', $this->_vars->get('description'));
        $this->_resource->set('response_type', $this->_vars->get('responsetype'));
        if ($original_name != $new_name) {
            $result = Kronolith::getDriver()->rename($original_name, $new_name);
            if (is_a($result, 'PEAR_Error')) {
                return PEAR::raiseError(sprintf(_("Unable to rename \"%s\": %s"), $original_name, $result->getMessage()));
            }
        }

        /* Update group memberships */
        $driver = Kronolith::getDriver('Resource');
        $existing_groups = $driver->getGroupMemberships($this->_resource->getId());
        $new_groups = $this->_vars->get('category');
        $new_groups = (is_null($new_groups) ? array() : $new_groups);
        foreach ($existing_groups as $gid) {
             $i = array_search($gid, $new_groups);
             if ($i === false) {
                 // No longer in this group
                 $group = $driver->getResource($gid);
                 $members = $group->get('members');
                 $idx = array_search($this->_resource->getId(), $members);
                 if ($idx !== false) {
                     unset($members[$idx]);
                     reset($members);
                     $group->set('members', $members);
                     $group->save();
                 }
             } else {
                 // We know it's already in the group, remove it so we don't
                 // have to check/add it again later.
                 unset($new_groups[$i]);
             }
        }

        reset($new_groups);
        foreach ($new_groups as $gid) {
            $group = $driver->getResource($gid);
            $members = $group->get('members');
            $members[] = $this->_resource->getId();
            $group->set('members', $members);
            $group->save();
        }


        $result = $this->_resource->save();
        if (is_a($result, 'PEAR_Error')) {
            return PEAR::raiseError(sprintf(_("Unable to save resource \"%s\": %s"), $new_name, $result->getMessage()));
        }

        return $this->_resource;

    }

}
