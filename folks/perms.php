<?php
/**
 * Copyright 2002-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Chuck Hagenbuch <chuck@horde.org>
 * @author Jan Schneider <jan@horde.org>
 */

require_once dirname(__FILE__) . '/lib/base.php';
require_once 'Horde/Group.php';

$shares = &Horde_Share::singleton('folks');
$groups = &Group::singleton();
$auth = Horde_Auth::singleton($conf['auth']['driver']);

$reload = false;
$actionID = Horde_Util::getFormData('actionID', 'edit');
switch ($actionID) {
case 'edit':
    $share = &$shares->getShareById(Horde_Util::getFormData('cid'));
    if (!is_a($share, 'PEAR_Error')) {
        $perm = &$share->getPermission();
    } elseif (($category = Horde_Util::getFormData('share')) !== null) {
        $share = &$shares->getShare($category);
        if (!is_a($share, 'PEAR_Error')) {
            $perm = &$share->getPermission();
        }
    }
    if (is_a($share, 'PEAR_Error')) {
        $notification->push($share, 'horde.error');
    } elseif (!Horde_Auth::getAuth() ||
              (isset($share) && Horde_Auth::getAuth() != $share->get('owner'))) {
        exit('permission denied');
    }
    break;

case 'editform':
    $share = &$shares->getShareById(Horde_Util::getFormData('cid'));
    if (is_a($share, 'PEAR_Error')) {
        $notification->push(_("Attempt to edit a non-existent share."), 'horde.error');
    } else {
        if (!Horde_Auth::getAuth() ||
            Horde_Auth::getAuth() != $share->get('owner')) {
            exit('permission denied');
        }
        $perm = &$share->getPermission();

        // Process owner and owner permissions.
        $old_owner = $share->get('owner');
        $new_owner = Horde_Auth::convertUsername(Horde_Util::getFormData('owner', $old_owner), true);
        if ($old_owner !== $new_owner && !empty($new_owner)) {
            if ($old_owner != Horde_Auth::getAuth() && !Horde_Auth::isAdmin()) {
                $notification->push(_("Only the owner or system administrator may change ownership or owner permissions for a share"), 'horde.error');
            } else {
                $share->set('owner', $new_owner);
                $share->save();
            }
        }

        // Process default permissions.
        if (Horde_Util::getFormData('default_show')) {
            $perm->addDefaultPermission(Horde_Perms::SHOW, false);
        } else {
            $perm->removeDefaultPermission(Horde_Perms::SHOW, false);
        }
        if (Horde_Util::getFormData('default_read')) {
            $perm->addDefaultPermission(Horde_Perms::READ, false);
        } else {
            $perm->removeDefaultPermission(Horde_Perms::READ, false);
        }
        if (Horde_Util::getFormData('default_edit')) {
            $perm->addDefaultPermission(Horde_Perms::EDIT, false);
        } else {
            $perm->removeDefaultPermission(Horde_Perms::EDIT, false);
        }
        if (Horde_Util::getFormData('default_delete')) {
            $perm->addDefaultPermission(Horde_Perms::DELETE, false);
        } else {
            $perm->removeDefaultPermission(Horde_Perms::DELETE, false);
        }

        // Process guest permissions.
        if (Horde_Util::getFormData('guest_show')) {
            $perm->addGuestPermission(Horde_Perms::SHOW, false);
        } else {
            $perm->removeGuestPermission(Horde_Perms::SHOW, false);
        }
        if (Horde_Util::getFormData('guest_read')) {
            $perm->addGuestPermission(Horde_Perms::READ, false);
        } else {
            $perm->removeGuestPermission(Horde_Perms::READ, false);
        }
        if (Horde_Util::getFormData('guest_edit')) {
            $perm->addGuestPermission(Horde_Perms::EDIT, false);
        } else {
            $perm->removeGuestPermission(Horde_Perms::EDIT, false);
        }
        if (Horde_Util::getFormData('guest_delete')) {
            $perm->addGuestPermission(Horde_Perms::DELETE, false);
        } else {
            $perm->removeGuestPermission(Horde_Perms::DELETE, false);
        }

        // Process creator permissions.
        if (Horde_Util::getFormData('creator_show')) {
            $perm->addCreatorPermission(Horde_Perms::SHOW, false);
        } else {
            $perm->removeCreatorPermission(Horde_Perms::SHOW, false);
        }
        if (Horde_Util::getFormData('creator_read')) {
            $perm->addCreatorPermission(Horde_Perms::READ, false);
        } else {
            $perm->removeCreatorPermission(Horde_Perms::READ, false);
        }
        if (Horde_Util::getFormData('creator_edit')) {
            $perm->addCreatorPermission(Horde_Perms::EDIT, false);
        } else {
            $perm->removeCreatorPermission(Horde_Perms::EDIT, false);
        }
        if (Horde_Util::getFormData('creator_delete')) {
            $perm->addCreatorPermission(Horde_Perms::DELETE, false);
        } else {
            $perm->removeCreatorPermission(Horde_Perms::DELETE, false);
        }

        // Process user permissions.
        $u_names = Horde_Util::getFormData('u_names');
        $u_show = Horde_Util::getFormData('u_show');
        $u_read = Horde_Util::getFormData('u_read');
        $u_edit = Horde_Util::getFormData('u_edit');
        $u_delete = Horde_Util::getFormData('u_delete');

        foreach ($u_names as $key => $user) {
            // Apply backend hooks
            $user = Horde_Auth::convertUsername($user, true);
            // If the user is empty, or we've already set permissions
            // via the owner_ options, don't do anything here.
            if (empty($user) || $user == $new_owner) {
                continue;
            }

            if (!empty($u_show[$key])) {
                $perm->addUserPermission($user, Horde_Perms::SHOW, false);
            } else {
                $perm->removeUserPermission($user, Horde_Perms::SHOW, false);
            }
            if (!empty($u_read[$key])) {
                $perm->addUserPermission($user, Horde_Perms::READ, false);
            } else {
                $perm->removeUserPermission($user, Horde_Perms::READ, false);
            }
            if (!empty($u_edit[$key])) {
                $perm->addUserPermission($user, Horde_Perms::EDIT, false);
            } else {
                $perm->removeUserPermission($user, Horde_Perms::EDIT, false);
            }
            if (!empty($u_delete[$key])) {
                $perm->addUserPermission($user, Horde_Perms::DELETE, false);
            } else {
                $perm->removeUserPermission($user, Horde_Perms::DELETE, false);
            }
        }

        // Process group permissions.
        $g_names = Horde_Util::getFormData('g_names');
        $g_show = Horde_Util::getFormData('g_show');
        $g_read = Horde_Util::getFormData('g_read');
        $g_edit = Horde_Util::getFormData('g_edit');
        $g_delete = Horde_Util::getFormData('g_delete');

        foreach ($g_names as $key => $group) {
            if (empty($group)) {
                continue;
            }

            if (!empty($g_show[$key])) {
                $perm->addGroupPermission($group, Horde_Perms::SHOW, false);
            } else {
                $perm->removeGroupPermission($group, Horde_Perms::SHOW, false);
            }
            if (!empty($g_read[$key])) {
                $perm->addGroupPermission($group, Horde_Perms::READ, false);
            } else {
                $perm->removeGroupPermission($group, Horde_Perms::READ, false);
            }
            if (!empty($g_edit[$key])) {
                $perm->addGroupPermission($group, Horde_Perms::EDIT, false);
            } else {
                $perm->removeGroupPermission($group, Horde_Perms::EDIT, false);
            }
            if (!empty($g_delete[$key])) {
                $perm->addGroupPermission($group, Horde_Perms::DELETE, false);
            } else {
                $perm->removeGroupPermission($group, Horde_Perms::DELETE, false);
            }
        }

        $result = $share->setPermission($perm, false);
        if (is_a($result, 'PEAR_Error')) {
            $notification->push($result, 'horde.error');
        } else {
            $result = $share->save();
            if (is_a($result, 'PEAR_Error')) {
                $notification->push($result, 'horde.error');
            } else {
                if (Horde_Util::getFormData('save_and_finish')) {
                    Horde_Util::closeWindowJS();
                    exit;
                }
                $notification->push(sprintf(_("Updated \"%s\"."), $share->get('name')), 'horde.success');
            }
        }
    }
    break;
}

if (is_a($share, 'PEAR_Error')) {
    $title = _("Edit Permissions");
} else {
    $title = sprintf(_("Edit Permissions for %s"), $share->get('name'));
}

if ($auth->hasCapability('list')) {
    $userList = $auth->listUsers();
    if (is_a($userList, 'PEAR_Error')) {
        Horde::logMessage($userList, __FILE__, __LINE__, PEAR_LOG_ERR);
        $userList = array();
    }
    sort($userList);
} else {
    $userList = array();
}

if (!empty($conf['share']['any_group'])) {
    $groupList = $groups->listGroups();
} else {
    $groupList = $groups->getGroupMemberships(Horde_Auth::getAuth(), true);
}
if (is_a($groupList, 'PEAR_Error')) {
    Horde::logMessage($groupList, __FILE__, __LINE__, PEAR_LOG_NOTICE);
    $groupList = array();
}
asort($groupList);

require FOLKS_TEMPLATES . '/common-header.inc';
$notification->notify(array('listeners' => 'status'));
require $registry->get('templates', 'horde') . '/shares/edit.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
