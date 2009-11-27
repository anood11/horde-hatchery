<?php
/**
 * Turba external API interface.
 *
 * This file defines Turba's external API interface. Other applications can
 * interact with Turba through this API.
 *
 * @package Turba
 */
class Turba_Api extends Horde_Registry_Api
{
    /**
     * Links.
     *
     * @var array
     */
    public $links = array(
        'show' => '%application%/contact.php?source=|source|&key=|key|&uid=|uid|'
    );

    /**
     * The listing of API calls that do not require permissions checking.
     *
     * @var array
     */
    public $noPerms = array(
        'getClientSource', 'getClient', 'getClients', 'searchClients'
    );

    /**
     * Callback for comment API
     *
     * @param integer $id  Internal data identifier
     *
     * @return mixed  Name of object on success | false on failure
     */
    public function commentCallback($id)
    {
        if (!$GLOBALS['conf']['comments']['allow']) {
            return false;
        }

        require_once dirname(__FILE__) . '/base.php';
        global $cfgSources;

        @list($source, $key) = explode('.', $id, 2);
        if (isset($cfgSources[$source]) && $key) {
            $driver = Turba_Driver::singleton($source);
            if (!is_a($driver, 'PEAR_Error')) {
                $object = $driver->getObject($key);
                if (!is_a($object, 'PEAR_Error')) {
                    return $object->getValue('name');
                }
            }
        }

        return false;
    }

    /**
     * Returns if applications allows comments
     *
     * @return boolean
     */
    public function hasComments()
    {
        return $GLOBALS['conf']['comments']['allow'];
    }

    /**
     * Returns a list of available sources.
     *
     * @param boolean $writeable  Set to true to limit to writeable sources.
     *
     * @return array  An array of the available sources.
     */
    public function sources($writeable = false)
    {
        require_once dirname(__FILE__) . '/base.php';

        $addressbooks = Turba::getAddressBooks($writeable ? Horde_Perms::EDIT : Horde_Perms::READ);
        foreach ($addressbooks as $addressbook => $config) {
            $addressbooks[$addressbook] = $config['title'];
        }

        return $addressbooks;
    }

    /**
     * Returns a list of fields avaiable in a source.
     *
     * @param string $source  The name of the source
     *
     * @return mixed  An array describing the fields | PEAR_Error
     */
    public function fields($source = null)
    {
        require_once dirname(__FILE__) . '/base.php';
        global $cfgSources, $attributes;

        if (empty($source) || !isset($cfgSources[$source])) {
            return PEAR::raiseError(sprintf(_("Invalid address book: %s"), $source), 'horde.error', null, null, $source);
        }

        $fields = array();
        foreach ($cfgSources[$source]['map'] as $field_name => $null) {
            if (substr($field_name, 0, 2) != '__') {
                $fields[$field_name] = array('name' => $field_name,
                    'type' => $attributes[$field_name]['type'],
                    'label' => $attributes[$field_name]['label'],
                    'search' => in_array($field_name, $cfgSources[$source]['search']));
            }
        }

        return $fields;
    }

    /**
     * Retrieve the UID for the current user's default Turba share.
     */
    public function getDefaultShare()
    {
        global $prefs;

        // Bring in turba's base and a clean copy of sources.
        require_once dirname(__FILE__) . '/base.php';
        require TURBA_BASE . '/config/sources.php';

        if (!empty($_SESSION['turba']['has_share'])) {
            $shares = Turba::listShares(true);
            if (is_a($shares, 'PEAR_Error')) {
                return false;
            }
            foreach ($shares as $uid => $share) {
                $params = @unserialize($share->get('params'));
                if (empty($params['source'])) {
                    continue;
                }
                $driver = Turba_Driver::factory($params['source'], $cfgSources[$params['source']]);
                if (is_a($driver, 'PEAR_Error')) {
                    continue;
                }
                if ($driver->checkDefaultShare($share, $cfgSources[$params['source']])) {
                    return $uid;
                }
            }
        }

        // Return Turba's default_dir as default
        return $prefs->getValue('default_dir');
    }

    private function _modified($uid)
    {
        $modified = $this->getActionTimestamp($uid, 'modify');
        if (empty($modified)) {
            $modified = $this->getActionTimestamp($uid, 'add');
        }
        return $modified;
    }

    /**
     * Browses through Turba's object tree.
     *
     * @param string $path       The path of the tree to browse.
     * @param array $properties  The item properties to return. Defaults to 'name',
     *                           'icon', and 'browseable'.
     *
     * @return array  Content of the specified path.
     */
    public function browse($path = '', $properties = array())
    {
        require_once dirname(__FILE__) . '/base.php';
        global $registry, $cfgSources;

        // Default properties.
        if (!$properties) {
            $properties = array('name', 'icon', 'browseable');
        }

        // Strip off the application name if present
        if (substr($path, 0, 5) == 'turba') {
            $path = substr($path, 5);
        }
        $path = trim($path, '/');
        $parts = explode('/', $path);

        $now = time();
        $results = array();
        if (empty($path)) {
            // We always provide the "global" folder which contains address book
            // sources that are shared among all users.  Per-user shares are shown
            // in a folder for each respective user.
            $results = array();
            $shares = Turba::listShares();
            $owners = array('global' => true);
            foreach ($shares as $share) {
                $owners[$share->get('owner')] = true;
            }

            foreach (array_keys($owners) as $owner) {
                if (in_array('name', $properties)) {
                    $results['turba/' . $owner]['name'] = $owner;
                }
                if (in_array('icon', $properties)) {
                    $results['turba/' . $owner]['icon'] = $registry->getImageDir() . '/turba.png';
                }
                if (in_array('browseable', $properties)) {
                    $results['turba/' . $owner]['browseable'] = true;
                }
                if (in_array('contenttype', $properties)) {
                    $results['turba/' . $owner]['contenttype'] = 'httpd/unix-directory';
                }
                if (in_array('contentlength', $properties)) {
                    $results['turba/' . $owner]['contentlength'] = 0;
                }
                if (in_array('modified', $properties)) {
                    // @TODO: Get a real modification date
                    $results['turba/' . $owner]['modified'] = $now;
                }
                if (in_array('created', $properties)) {
                    // @TODO Get a real creation date
                    $results['turba/' . $owner]['created'] = 0;
                }
            }
            return $results;
        } elseif (count($parts) == 1) {
            //
            // We should either have the username that is a valid share owner or
            // 'global'
            //
            if (empty($parts[0])) {
                // We need either 'global' or a valid username with shares
                return array();
            }

            if ($parts[0] == 'global') {
                // The client is requesting a list of global address books.
                $addressbooks = Turba::getAddressBooks();
                foreach ($addressbooks as $addressbook => $info) {
                    if ($info['type'] == 'share') {
                        // Ignore address book shares in the 'global' folder
                        unset($addressbooks[$addressbook]);
                    }
                }
            } else {
                // Assume $parts[0] is a valid username and we need to list their
                // shared addressbooks.
                if (empty($_SESSION['turba']['has_share'])) {
                    // No backends are configured to provide shares
                    return array();
                }
                $addressbooks = $GLOBALS['turba_shares']->listShares($parts[0],
                    Horde_Perms::READ,
                    $parts[0]);
                // The last check returns all addressbooks for the requested user,
                // but that does not mean the requesting user has access to them.
                // Filter out those address books for which the requesting user has
                // no access.
                $addressbooks = Turba::permissionsFilter($addressbooks);
            }

            $curpath = 'turba/' . $parts[0] . '/';
            foreach ($addressbooks as $addressbook => $info) {
                if (in_array('name', $properties)) {
                    if (is_a($info, 'Horde_Share_Object')) {
                        $name = $info->get('title');
                    } else {
                        $name = $info['title'];
                    }
                    $results[$curpath . $addressbook]['name'] = $name;
                }
                if (in_array('icon', $properties)) {
                    $results[$curpath . $addressbook]['icon'] = $registry->getImageDir() . '/turba.png';
                }
                if (in_array('browseable', $properties)) {
                    $results[$curpath . $addressbook]['browseable'] = true;
                }
                if (in_array('contenttype', $properties)) {
                    $results[$curpath . $addressbook]['contenttype'] = 'httpd/unix-directory';
                }
                if (in_array('contentlength', $properties)) {
                    $results[$curpath . $addressbook]['contentlength'] = 0;
                }
                if (in_array('modified', $properties)) {
                    // @TODO: Get a real modification date
                    $results[$curpath . $addressbook]['modified'] = $now;
                }
                if (in_array('created', $properties)) {
                    // @TODO Get a real creation date
                    $results[$curpath . $addressbook]['created'] = 0;
                }
            }
            return $results;

        } elseif (count($parts) == 2) {
            //
            // The client is requesting all contacts from a given addressbook
            //
            if (empty($parts[0]) || empty($parts[1])) {
                // $parts[0] must be either 'global' or a valid user with shares
                // $parts[1] must be an address book ID
                return array();
            }

            $addressbooks = Turba::getAddressBooks();
            if (!isset($addressbooks[$parts[1]])) {
                // We must have a valid addressbook to continue.
                return array();
            }

            // Load the Turba driver.
            $driver = Turba_Driver::singleton($parts[1]);
            if (is_a($driver, 'PEAR_Error')) {
                return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $cfgSources[$parts[1]]);
            }

            $contacts = $driver->search(array());
            if (is_a($contacts, 'PEAR_Error')) {
                return $contacts;
            }

            $contacts->reset();
            $curpath = 'turba/' . $parts[0] . '/' . $parts[1] . '/';
            while ($contact = $contacts->next()) {
                $key = $curpath . $contact->getValue('__key');
                if (in_array('name', $properties)) {
                    $results[$key]['name'] = Turba::formatName($contact);
                }
                if (in_array('icon', $properties)) {
                    $results[$key]['icon'] = $registry->getImageDir('horde') . '/mime/vcard.png';
                }
                if (in_array('browseable', $properties)) {
                    $results[$key]['browseable'] = false;
                }
                if (in_array('contenttype', $properties)) {
                    $results[$key]['contenttype'] = 'text/x-vcard';
                }
                if (in_array('contentlength', $properties)) {
                    $data = $this->export($contact->getValue('__uid'), 'text/x-vcard', $contact->getSource());
                    if (is_a($data, 'PEAR_Error')) {
                        $data = '';
                    }
                    $results[$key]['contentlength'] = strlen($data);
                }
                if (in_array('modified', $properties)) {
                    $results[$key]['modified'] = $this->_modified($contact->getValue('__uid'));
                }
                if (in_array('created', $properties)) {
                    $results[$key]['created'] = $this->getActionTimestamp($contact->getValue('__uid'), 'add');
                }
            }

            return $results;

        } elseif (count($parts) == 3) {
            //
            // The client is requesting an individual contact
            //
            $addressbooks = Turba::getAddressBooks();
            if (!isset($addressbooks[$parts[1]])) {
                // We must have a valid addressbook to continue.
                return array();
            }

            // Load the Turba driver.
            $driver = Turba_Driver::singleton($parts[1]);
            if (is_a($driver, 'PEAR_Error')) {
                return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $cfgSources[$parts[1]]);
            }

            $contact = &$driver->getObject($parts[2]);
            if (is_a($contact, 'PEAR_Error')) {
                return $contact;
            }

            $result = array('data' => $this->export($contact->getValue('__uid'), 'text/x-vcard', $contact->getSource()),
                'mimetype' => 'text/x-vcard');
            $modified = $this->_modified($contact->getValue('__uid'));
            if (!empty($modified)) {
                $result['mtime'] = $modified;
            }
            return $result;
        } else {
            return PEAR::raiseError(_("Malformed request."));
        }
    }

    /**
     * Deletes a file from the Turba tree.
     *
     * @param string $path  The path to the file.
     *
     * @return mixed  The event's UID, or a PEAR_Error on failure.
     */
    public function path_delete($path)
    {
        require_once dirname(__FILE__) . '/base.php';
        global $registry, $cfgSources;

        // Strip off the application name if present
        if (substr($path, 0, 5) == 'turba') {
            $path = substr($path, 5);
        }
        $path = trim($path, '/');
        $parts = explode('/', $path);

        $now = time();
        $results = array();

        if (count($parts) < 3) {
            // Deletes must be on individual contacts
            return PEAR::raiseError(_("Delete denied."), 403);
        }
        if (!array_key_exists($parts[1], Turba::getAddressBooks())) {
            return PEAR::raiseError("Address book does not exist", 404);
        }

        // Load the Turba driver.
        $driver = Turba_Driver::singleton($parts[1]);
        if (is_a($driver, 'PEAR_Error')) {
            return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 500, null, null, $cfgSources[$parts]);
        }

        $ret = $driver->delete($parts[2]);
        if (is_a($ret, 'PEAR_Error')) {
            // A deeper error occurred.  Make sure the code is a valid HTTP response
            $ret->code = 500;
            return $ret;
        }
    }

    /**
     * Returns an array of UIDs for all contacts that the current user is
     * authorized to see.
     *
     * @param string|array $sources  The name(s) of the source(s) to return
     *                               contacts of. If left empty, the current user's
     *                               sync sources or default source are used.
     *
     * @return array  An array of UIDs for all contacts the user can access.
     */
    public function listContacts($sources = null)
    {
        require_once dirname(__FILE__) . '/base.php';

        global $cfgSources, $prefs;

        /* Get default address book from user preferences. */
        if (empty($sources)) {
            $sources = @unserialize($prefs->getValue('sync_books'));
        } elseif (!is_array($sources)) {
            $sources = array($sources);
        }
        if (empty($sources)) {
            $sources = array(Turba::getDefaultAddressbook());
        }
        if (empty($sources)) {
            return PEAR::raiseError(_("No address book specified"), 'horde.error');
        }

        $uids = array();
        foreach ($sources as $source) {
            if (empty($source) || !isset($cfgSources[$source])) {
                return PEAR::raiseError(sprintf(_("Invalid address book: %s"), $source), 'horde.error', null, null, $source);
            }

            $storage = Turba_Driver::singleton($source);
            if (is_a($storage, 'PEAR_Error')) {
                return PEAR::raiseError(sprintf(_("Connection failed: %s"), $storage->getMessage()), 'horde.error', null, null, $source);
            }

            $results = $storage->search(array());

            if (is_a($results, 'PEAR_Error')) {
                return PEAR::raiseError(sprintf(_("Error searching the address book: %s"), $results->getMessage()), 'horde.error', null, null, $source);
            }

            foreach ($results->objects as $o) {
                $uids[] = $o->getValue('__uid');
            }
        }

        return $uids;
    }

    /**
     * Returns an array of UIDs for contacts that have had $action happen since
     * $timestamp.
     *
     * @param string  $action        The action to check for - add, modify, or
     *                               delete.
     * @param integer $timestamp     The time to start the search.
     * @param string|array $sources  The source(s) for which to retrieve the
     *                               history.
     *
     * @return array  An array of UIDs matching the action and time criteria.
     */
    public function listBy($action, $timestamp, $sources = null)
    {
        global $prefs, $cfgSources;
        require_once dirname(__FILE__) . '/base.php';

        /* Get default address book from user preferences. */
        if (empty($sources)) {
            $sources = @unserialize($prefs->getValue('sync_books'));
        } elseif (!is_array($sources)) {
            $sources = array($sources);
        }
        if (empty($sources)) {
            $sources = array(Turba::getDefaultAddressbook());
        }
        if (empty($sources)) {
            return PEAR::raiseError(_("No address book specified"), 'horde.error');
        }

        $uids = array();
        foreach ($sources as $source) {
            if (empty($source) || !isset($cfgSources[$source])) {
                return PEAR::raiseError(sprintf(_("Invalid address book: %s"), $source), 'horde.error', null, null, $source);
            }

            $driver = Turba_Driver::singleton($source);
            if (is_a($driver, 'PEAR_Error')) {
                return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $source);
            }

            $history = Horde_History::singleton();
            $histories = $history->getByTimestamp('>', $timestamp,
                array(array('op' => '=',
                'field' => 'action',
                'value' => $action)),
                'turba:' . $driver->getName());
            if (is_a($histories, 'PEAR_Error')) {
                return $histories;
            }

            // Strip leading turba:addressbook:.
            $uids = array_merge($uids,
                str_replace('turba:' . $driver->getName() . ':',
                '',
                array_keys($histories)));
        }

        return $uids;
    }

    /**
     * Returns the timestamp of an operation for a given uid an action.
     *
     * @param string $uid            The uid to look for.
     * @param string $action         The action to check for - add, modify, or
     *                               delete.
     * @param string|array $sources  The source(s) for which to retrieve the
     *                               history.
     *
     * @return integer  The timestamp for this action.
     */
    public function getActionTimestamp($uid, $action, $sources = null)
    {
        global $prefs, $cfgSources;
        require_once dirname(__FILE__) . '/base.php';

        /* Get default address book from user preferences. */
        if (empty($sources)) {
            $sources = @unserialize($prefs->getValue('sync_books'));
        } elseif (!is_array($sources)) {
            $sources = array($sources);
        }
        if (empty($sources)) {
            $sources = array(Turba::getDefaultAddressbook());
        }
        if (empty($sources)) {
            return PEAR::raiseError(_("No address book specified"), 'horde.error');
        }

        $last = 0;
        foreach ($sources as $source) {
            if (empty($source) || !isset($cfgSources[$source])) {
                return PEAR::raiseError(sprintf(_("Invalid address book: %s"), $source), 'horde.error', null, null, $source);
            }

            $driver = Turba_Driver::singleton($source);
            if (is_a($driver, 'PEAR_Error')) {
                return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $source);
            }

            $history = Horde_History::singleton();
            $ts = $history->getActionTimestamp('turba:' . $driver->getName()
                . ':' . $uid,
                $action);
            if (!empty($ts) && $ts > $last) {
                $last = $ts;
            }
        }

        return $last;
    }

    /**
     * Import a contact represented in the specified contentType.
     *
     * @param string $content      The content of the contact.
     * @param string $contentType  What format is the data in? Currently supports
     *                             array, text/directory, text/vcard and
     *                             text/x-vcard.
     * @param string $source       The source into which the contact will be
     *                             imported.
     *
     * @return string  The new UID, or false on failure.
     */
    public function import($content, $contentType = 'array',
                           $import_source = null)
    {
        require_once dirname(__FILE__) . '/base.php';
        global $cfgSources, $prefs;

        /* Get default address book from user preferences. */
        if (empty($import_source)) {
            $import_source = $prefs->getValue('default_dir');
            /* On new installations default_dir is not set, use first source
             * instead. */
            if (empty($import_source)) {
                $import_source = key(Turba::getAddressBooks(Horde_Perms::EDIT));
            }
        }

        // Check existance of and permissions on the specified source.
        if (!isset($cfgSources[$import_source])) {
            return PEAR::raiseError(sprintf(_("Invalid address book: %s"),
                $import_source),
            'horde.warning');
        }

        $driver = Turba_Driver::singleton($import_source);
        if (is_a($driver, 'PEAR_Error')) {
            return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $import_source);
        }

        if (!$driver->hasPermission(Horde_Perms::EDIT)) {
            return PEAR::raiseError(_("Permission denied"), 'horde.error', null, null, $import_source);
        }

        /* Create a category manager. */
        $cManager = new Horde_Prefs_CategoryManager();
        $categories = $cManager->get();

        if (!is_a($content, 'Horde_iCalendar_vcard')) {
            switch ($contentType) {
            case 'array':
                break;

            case 'text/x-vcard':
            case 'text/vcard':
            case 'text/directory':
                $iCal = new Horde_iCalendar();
                if (!$iCal->parsevCalendar($content)) {
                    return PEAR::raiseError(_("There was an error importing the iCalendar data."));
                }
                switch ($iCal->getComponentCount()) {
                case 0:
                    return PEAR::raiseError(_("No vCard data was found."));

                case 1:
                    $content = $iCal->getComponent(0);
                    break;

                default:
                    $ids = array();
                    foreach ($iCal->getComponents() as $c) {
                        if (is_a($c, 'Horde_iCalendar_vcard')) {
                            $content = $driver->toHash($c);
                            $result = $driver->search($content);
                            if (is_a($result, 'PEAR_Error')) {
                                return $result;
                            } elseif ($result->count() > 0) {
                                continue;
                            }
                            $result = $driver->add($content);
                            if (is_a($result, 'PEAR_Error')) {
                                return $result;
                            }
                            if (!empty($content['category']) &&
                                !in_array($content['category'], $categories)) {
                                    $cManager->add($content['category']);
                                    $categories[] = $content['category'];
                                }
                            $ids[] = $result;
                        }
                    }
                    return $ids;
                }
                break;

            default:
                return PEAR::raiseError(sprintf(_("Unsupported Content-Type: %s"), $contentType));
            }
        }

        if (is_a($content, 'Horde_iCalendar_vcard')) {
            $content = $driver->toHash($content);
        }

        // Check if the entry already exists in the data source:
        $result = $driver->search($content);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        } elseif ($result->count() > 0) {
            $o = $result->objects[0];
            return PEAR::raiseError(_("Already Exists"), 'horde.message', null, null, $o->getValue('__uid'));
        }

        $result = $driver->add($content);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        if (!empty($content['category']) &&
            !in_array($content['category'], $categories)) {
                $cManager->add($content['category']);
            }

        $object = &$driver->getObject($result);
        return is_a($object, 'PEAR_Error') ? $object : $object->getValue('__uid');
    }

    /**
     * Export a contact, identified by UID, in the requested contentType.
     *
     * @param string $uid            Identify the contact to export.
     * @param mixed $contentType     What format should the data be in?
     *                               Either a string with one of:
     *                               - text/directory
     *                               - text/vcard
     *                               - text/x-vcard
     *                               The first two produce a vcard3.0 (rfc2426),
     *                               the second produces a vcard in old 2.1 format
     *                               defined by imc.org
     * @param string|array $sources  The source(s) from which the contact will be
     *                               exported.
     *
     * @return mixed  The requested data | PEAR_Error
     */
    public function export($uid, $contentType, $sources = null)
    {
        require_once dirname(__FILE__) . '/base.php';
        global $cfgSources, $prefs;

        /* Get default address book from user preferences. */
        if (empty($sources)) {
            $sources = @unserialize($prefs->getValue('sync_books'));
        } elseif (!is_array($sources)) {
            $sources = array($sources);
        }
        if (empty($sources)) {
            $sources = array(Turba::getDefaultAddressbook());
        }
        if (empty($sources)) {
            return PEAR::raiseError(_("No address book specified"), 'horde.error');
        }

        foreach ($sources as $source) {
            if (empty($source) || !isset($cfgSources[$source])) {
                return PEAR::raiseError(sprintf(_("Invalid address book: %s"), $source), 'horde.error', null, null, $source);
            }

            if (empty($uid)) {
                return PEAR::raiseError(_("Invalid ID"), 'horde.error', null, null, $source);
            }

            $driver = Turba_Driver::singleton($source);
            if (is_a($driver, 'PEAR_Error')) {
                return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $source);
            }

            if (!$driver->hasPermission(Horde_Perms::READ)) {
                continue;
            }

            $result = $driver->search(array('__uid' => $uid));
            if (is_a($result, 'PEAR_Error')) {
                return $result;
            } elseif ($result->count() == 0) {
                continue;
            } elseif ($result->count() > 1) {
                return PEAR::raiseError("Internal Horde Error: multiple turba objects with same objectId.", 'horde.error', null, null, $source);
            }

            $version = '3.0';
            list($contentType,) = explode(';', $contentType);
            switch ($contentType) {
            case 'text/x-vcard':
                $version = '2.1';

            case 'text/vcard':
            case 'text/directory':
                $export = '';
                foreach ($result->objects as $obj) {
                    $vcard = $driver->tovCard($obj, $version);
                    /* vCards are not enclosed in BEGIN:VCALENDAR..END:VCALENDAR.
                     * Export the individual cards instead. */
                    $export .= $vcard->exportvCalendar();
                }
                return $export;
            }

            return PEAR::raiseError(sprintf(_("Unsupported Content-Type: %s"), $contentType));
        }

        return PEAR::raiseError(_("Object not found"));
    }

    /**
     * Exports the user's own contact as a vCard string.
     *
     * @return string  The requested vCard data or PEAR_Error.
     */
    public function ownVCard()
    {
        $contact = $this->getOwnContactObject();
        if (is_a($contact, 'PEAR_Error')) {
            return $contact;
        }
        $driver = Turba_Driver::singleton($contact['source']);
        if (is_a($driver, 'PEAR_Error')) {
            return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()));
        }
        $vcard = $driver->tovCard($contact['contact'], '3.0');
        $vcard->setAttribute('VERSION', '3.0');

        return $vcard->exportvCalendar();
    }

    /**
     * Export the user's own contact as a hash
     *
     * @return Array  The contact hash or PEAR_Error
     */
    public function ownContact()
    {
        $contact = $this->getOwnContactObject();
        if (is_a($contact, 'PEAR_Error')) {
            return $contact;
        }

        return $contact['contact']->getAttributes();
    }

    /**
     * Helper function to  return the user's own contact object
     *
     * @return Array  A hash containing the Turba_Object representing the user's
     *                own contact and the source that it is from or PEAR_Error.
     */
    public function getOwnContactObject()
    {
        require_once dirname(__FILE__) . '/base.php';
        global $cfgSources;

        $own_contact = $GLOBALS['prefs']->getValue('own_contact');
        if (empty($own_contact)) {
            return PEAR::raiseError(_("You didn't mark a contact as your own yet."));
        }
        @list($source, $id) = explode(';', $own_contact);

        if (!isset($cfgSources[$source])) {
            return PEAR::raiseError(_("The address book with your own contact doesn't exist anymore."));
        }

        $driver = Turba_Driver::singleton($source);
        if (is_a($driver, 'PEAR_Error')) {
            return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()));
        }

        if (!$driver->hasPermission(Horde_Perms::READ)) {
            return PEAR::raiseError(_("You don't have sufficient permissions to read the address book that contains your own contact."));
        }

        $contact = $driver->getObject($id);
        if (is_a($contact, 'PEAR_Error')) {
            return PEAR::raiseError(_("Your own contact cannot be found in the address book."));
        }

        $return = array('contact' => $contact,
            'source'=> $source);

        return $return;
    }

    /**
     * Deletes a contact identified by UID.
     *
     * @param string|array $uid      Identify the contact to delete, either a
     *                               single UID or an array.
     * @param string|array $sources  The source(s) from which the contact will be
     *                               deleted.
     *
     * @return boolean  Success or failure.
     */
    public function delete($uid, $sources = null)
    {
        // Handle an array of UIDs for convenience of deleting multiple contacts
        // at once.
        if (is_array($uid)) {
            foreach ($uid as $g) {
                $result = $this->delete($uid, $source);
                if (is_a($result, 'PEAR_Error')) {
                    return $result;
                }
            }

            return true;
        }

        require_once dirname(__FILE__) . '/base.php';
        global $cfgSources, $prefs;

        /* Get default address book from user preferences. */
        if (empty($sources)) {
            $sources = @unserialize($prefs->getValue('sync_books'));
        } elseif (!is_array($sources)) {
            $sources = array($sources);
        }
        if (empty($sources)) {
            $sources = array(Turba::getDefaultAddressbook());
        }
        if (empty($sources)) {
            return PEAR::raiseError(_("No address book specified"), 'horde.error');
        }

        foreach ($sources as $source) {
            if (empty($source) || !isset($cfgSources[$source])) {
                return PEAR::raiseError(sprintf(_("Invalid address book: %s"), $source), 'horde.error', null, null, $source);
            }

            if (empty($uid)) {
                return PEAR::raiseError(_("Invalid ID"), 'horde.error', null, null, $source);
            }

            $driver = Turba_Driver::singleton($source);
            if (is_a($driver, 'PEAR_Error')) {
                return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $source);
            }

            if (!Horde_Auth::isAdmin() && !$driver->hasPermission(Horde_Perms::DELETE)) {
                continue;
            }

            // If the objectId isn't in $source in the first place, just return
            // true. Otherwise, try to delete it and return success or failure.
            $result = $driver->search(array('__uid' => $uid));
            if (is_a($result, 'PEAR_Error')) {
                return $result;
            } elseif ($result->count() == 0) {
                continue;
            } else {
                $r = $result->objects[0];
                return $driver->delete($r->getValue('__key'));
            }
        }

        return true;
    }

    /**
     * Replaces the contact identified by UID with the content represented in the
     * specified contentType.
     *
     * @param string $uid            Idenfity the contact to replace.
     * @param string $content        The content of the contact.
     * @param string $contentType    What format is the data in? Currently supports
     *                               array, text/directory, text/vcard and
     *                               text/x-vcard.
     * @param string|array $sources  The source(s) where the contact will be
     *                               replaced.
     *
     * @return boolean  Success or failure.
     */
    public function replace($uid, $content, $contentType, $sources = null)
    {
        require_once dirname(__FILE__) . '/base.php';
        global $cfgSources, $prefs;

        /* Get default address book from user preferences. */
        if (empty($sources)) {
            $sources = @unserialize($prefs->getValue('sync_books'));
        } elseif (!is_array($sources)) {
            $sources = array($sources);
        }
        if (empty($sources)) {
            $sources = array(Turba::getDefaultAddressbook());
        }
        if (empty($sources)) {
            return PEAR::raiseError(_("No address book specified"), 'horde.error');
        }

        foreach ($sources as $source) {
            if (empty($source) || !isset($cfgSources[$source])) {
                return PEAR::raiseError(sprintf(_("Invalid address book: %s"), $source), 'horde.error', null, null, $source);
            }

            if (empty($uid)) {
                return PEAR::raiseError(_("Invalid contact unique ID"), 'horde.error', null, null, $source);
            }

            // Check permissions.
            $driver = Turba_Driver::singleton($source);
            if (is_a($driver, 'PEAR_Error')) {
                return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $source);
            }
            if (!$driver->hasPermission(Horde_Perms::EDIT)) {
                continue;
            }
            $result = $driver->search(array('__uid' => $uid));
            if (is_a($result, 'PEAR_Error')) {
                return $result;
            } elseif (!$result->count()) {
                continue;
            } elseif ($result->count() > 1) {
                return PEAR::raiseError("Multiple contacts found with same unique ID.", 'horde.error', null, null, $source);
            }

            $object = $result->objects[0];

            switch ($contentType) {
            case 'array':
                break;

            case 'text/x-vcard':
            case 'text/vcard':
            case 'text/directory':
                $iCal = new Horde_iCalendar();
                if (!$iCal->parsevCalendar($content)) {
                    return PEAR::raiseError(_("There was an error importing the iCalendar data."));
                }

                switch ($iCal->getComponentCount()) {
                case 0:
                    return PEAR::raiseError(_("No vCard data was found."));

                case 1:
                    $content = $iCal->getComponent(0);
                    $content = $driver->toHash($content);
                    break;

                default:
                    return PEAR::raiseError(_("Only one vcard supported."));
                }
                break;

            default:
                return PEAR::raiseError(sprintf(_("Unsupported Content-Type: %s"), $contentType));
            }

            foreach ($content as $attribute => $value) {
                if ($attribute != '__key') {
                    $object->setValue($attribute, $value);
                }
            }

            return $object->store();
        }

        return PEAR::raiseError(_("Object not found"));
    }

    /**
     * Returns a contact search result.
     *
     * @param array $names          The search filter values.
     * @param array $sources        The sources to search in.
     * @param array $fields         The fields to search on.
     * @param boolean $matchBegin   Match word boundaries only?
     * @param boolean $forceSource  Whether to use the specified sources, even
     *                              if they have been disabled in the
     *                              preferences?
     *
     * @return array  Hash containing the search results.
     */
    public function search($names = array(), $sources = array(),
                           $fields = array(), $matchBegin = false,
                           $forceSource = false)
    {
        require_once dirname(__FILE__) . '/base.php';
        global $cfgSources, $attributes, $prefs;

        if (!isset($cfgSources) || !is_array($cfgSources) || !count($cfgSources)) {
            return array();
        }

        if (!is_array($names)) {
            $names = is_null($names) ? array() : array($names);
        }

        if (!$forceSource) {
            // Make sure the selected source is activated in Turba.
            $addressbooks = array_keys(Turba::getAddressBooks());
            foreach (array_keys($sources) as $id) {
                if (!in_array($sources[$id], $addressbooks)) {
                    unset($sources[$id]);
                }
            }
        }

        // ...and ensure the default source is used as a default.
        if (!count($sources)) {
            $sources = array(Turba::getDefaultAddressBook());
        }

        // Read the columns to display from the preferences.
        $sort_columns = Turba::getColumns();

        $results = array();
        $seen = array();
        foreach ($sources as $source) {
            // Skip invalid sources.
            if (!isset($cfgSources[$source])) {
                continue;
            }

            // Skip sources that aren't browseable if the search is empty.
            if (empty($cfgSources[$source]['browse']) &&
                (!count($names) || (count($names) == 1 && empty($names[0])))) {
                    continue;
                }

            $driver = Turba_Driver::singleton($source);
            if (is_a($driver, 'PEAR_Error')) {
                return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $source);
            }

            // Determine the name of the column to sort by.
            $columns = isset($sort_columns[$source])
                ? $sort_columns[$source] : array();

            foreach ($names as $name) {
                $criteria = array();
                if (isset($fields[$source])) {
                    foreach ($fields[$source] as $field) {
                        $criteria[$field] = trim($name);
                    }
                }
                if (count($criteria) == 0) {
                    $criteria['name'] = trim($name);
                }

                $search = $driver->search($criteria, Turba::getPreferredSortOrder(), 'OR', array(), array(), $matchBegin);
                if (!is_a($search, 'Turba_List')) {
                    continue;
                }

                while ($ob = $search->next()) {
                    if (!$ob->isGroup()) {
                        /* Not a group. */
                        $att = array('__key' => $ob->getValue('__key'));
                        foreach ($ob->driver->getCriteria() as $info_key => $info_val) {
                            $att[$info_key] = $ob->getValue($info_key);
                        }
                        $email = array();
                        foreach (array_keys($att) as $key) {
                            if (!$ob->getValue($key) ||
                                !isset($attributes[$key]) ||
                                $attributes[$key]['type'] != 'email') {
                                    continue;
                            }
                            $email_val = $ob->getValue($key);

                            // Multiple addresses support
                            if (isset($attributes[$key]['params'])
                                && is_array($attributes[$key]['params'])
                                && !empty($attributes[$key]['params']['allow_multi'])) {
                                $addrs = Horde_Mime_Address::explode($email_val);
                            } else {
                                $addrs = array($email_val);
                            }

                            foreach ($addrs as $addr) {
                                $email[] = trim($addr);
                            }
                        }

                        if ($ob->hasValue('name') ||
                            !isset($ob->driver->alternativeName)) {
                            $display_name = Turba::formatName($ob);
                        } else {
                            $display_name = $ob->getValue($ob->driver->alternativeName);
                        }
                        if (count($email)) {
                            for ($i = 0; $i < count($email); $i++) {
                                $seen_key = trim(Horde_String::lower($display_name)) . '/' . trim(Horde_String::lower($email[$i]));
                                if (!empty($seen[$seen_key])) {
                                    continue;
                                }
                                $seen[$seen_key] = true;
                                if (!isset($results[$name])) {
                                    $results[$name] = array();
                                }
                                $results[$name][] = array_merge($att,
                                    array('id' => $att['__key'],
                                    'name' => $display_name,
                                    'email' => $email[$i],
                                    '__type' => 'Object',
                                    'source' => $source));
                            }
                        } else {
                            if (!isset($results[$name])) {
                                $results[$name] = array();
                            }
                            $results[$name][] = array_merge($att,
                                array('id' => $att['__key'],
                                'name' => $display_name,
                                'email' => null,
                                '__type' => 'Object',
                                'source' => $source));
                        }
                    } else {
                        /* Is a distribution list. */
                        $listatt = $ob->getAttributes();
                        $seeninlist = array();
                        $members = $ob->listMembers();
                        $listName = $ob->getValue('name');
                        if (!is_a($members, 'Turba_List')) {
                            continue;
                        }
                        if ($members->count() > 0) {
                            if (!isset($results[$name])) {
                                $results[$name] = array();
                            }
                            $emails = array();
                            while ($ob = $members->next()) {
                                $att = $ob->getAttributes();
                                foreach (array_keys($att) as $key) {
                                    $value = $ob->getValue($key);
                                    if (empty($value)) {
                                        continue;
                                    }
                                    if (!is_array($value)) {
                                        $seen_key = trim(Horde_String::lower($ob->getValue('name')))
                                            . trim(Horde_String::lower($value));
                                    } else {
                                        $seen_key = trim(Horde_String::lower($ob->getValue('name')))
                                            . trim(Horde_String::lower($value['load']['file']));
                                    }
                                    if (isset($attributes[$key]) &&
                                        $attributes[$key]['type'] == 'email' &&
                                        empty($seeninlist[$seen_key])) {
                                            $emails[] = $value;
                                            $seeninlist[$seen_key] = true;
                                        }
                                }
                            }
                            $results[$name][] = array('name' => $listName,
                                'email' => implode(', ', $emails),
                                'id' => $listatt['__key'],
                                'source' => $source);
                        }
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Retrieves a contact.
     *
     * @param string $source    The source name where the contact is stored
     * @param string $objectId  The unique id of the contact to retrieve
     *
     * @return array  The retrieved contact.
     */
    public function getContact($source = null, $objectId = '')
    {
        require_once dirname(__FILE__) . '/base.php';
        global $cfgSources;

        if (!isset($cfgSources) || !is_array($cfgSources) || !count($cfgSources)) {
            return array();
        }

        if (isset($cfgSources[$source])) {
            $driver = Turba_Driver::singleton($source);
            if (is_a($driver, 'PEAR_Error')) {
                return $driver;
            }

            $object = $driver->getObject($objectId);
            if (is_a($object, 'PEAR_Error')) {
                return $object;
            }

            $attributes = array();
            foreach ($cfgSources[$source]['map'] as $field => $map) {
                $attributes[$field] = $object->getValue($field);
            }
            return $attributes;
        }

        return array();
    }

    /**
     * Retrieves a set of contacts from a single source.
     *
     * @param string $source    The source name where the contact is stored
     * @param array $objectIds  The unique ids of the contact to retrieve.
     *
     * @return mixed  The retrieved contact | PEAR_Error
     */
    public function getContacts($source = '', $objectIds = array())
    {
        require_once dirname(__FILE__) . '/base.php';
        global $cfgSources;
        $results = array();
        if (!is_array($objectIds)) {
            $objectIds = array($objectIds);
        }

        if (!isset($cfgSources) || !is_array($cfgSources) || !count($cfgSources)) {
            return array();
        }

        if (isset($cfgSources[$source])) {
            $driver = Turba_Driver::singleton($source);
            if (is_a($driver, 'PEAR_Error')) {
                return $driver;
            }

            $objects = $driver->getObjects($objectIds);
            if (is_a($objects, 'PEAR_Error')) {
                return $objects;
            }

            foreach ($objects as $object) {
                $attributes = array();
                foreach ($cfgSources[$source]['map'] as $field => $map) {
                    $attributes[$field] = $object->getValue($field);
                }
                $results[] = $attributes;
            }
        }

        return $results;
    }

    /**
     * Retrieves a list of all possible values of a field in specified source(s).
     *
     * @param string $field   Field name to check
     * @param array $sources  Array containing the sources to look in
     *
     * @return mixed  An array of fields and possible values | PEAR_Error
     */
    public function getAllAttributeValues($field = '', $sources = array())
    {
        require_once dirname(__FILE__) . '/base.php';
        global $cfgSources;

        if (!isset($cfgSources) || !is_array($cfgSources) || !count($cfgSources)) {
            return array();
        }

        if (!count($sources)) {
            $sources = array(Turba::getDefaultAddressBook());
        }

        $results = array();
        foreach ($sources as $source) {
            if (isset($cfgSources[$source])) {
                $driver = Turba_Driver::singleton($source);
                if (is_a($driver, 'PEAR_Error')) {
                    return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $source);
                }

                $res = $driver->search(array());
                if (!is_a($res, 'Turba_List')) {
                    return PEAR::raiseError(_("Search failed"), 'horde.error', null, null, $source);
                }

                while ($ob = $res->next()) {
                    if ($ob->hasValue($field)) {
                        $results[$source . ':' . $ob->getValue('__key')] = array(
                            'name' => $ob->getValue('name'),
                            'email' => $ob->getValue('email'),
                            $field => $ob->getValue($field));
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Retrieves a list of available time objects categories
     *
     * @return array  An array of all configured time object categories.
     */
    public function listTimeObjectCategories()
    {
        require_once dirname(__FILE__) . '/base.php';

        $categories = array();
        foreach ($GLOBALS['attributes'] as $key => $attribute) {
            if ($attribute['type'] == 'monthdayyear' &&
                !empty($attribute['time_object_label'])) {
                foreach ($GLOBALS['cfgSources'] as $srcKey => $source) {
                    if (!empty($source['map'][$key])) {
                        $categories[$key . '/'. $srcKey] = sprintf(_("%s in %s"), $attribute['time_object_label'], $source['title']);
                    }
                }
            }
        }

        return $categories;
    }

    /**
     * Lists birthdays and/or anniversaries as time objects.
     *
     * @param array $time_categories  The time categories (from
     *                                listTimeObjectCategories) to list.
     * @param mixed $start            The start date of the period.
     * @param mixed $end              The end date of the period.
     *
     * @return mixed  An array of timeObject results || PEAR_Error
     */
    public function listTimeObjects($time_categories, $start, $end)
    {
        require_once dirname(__FILE__) . '/base.php';
        global $cfgSources;

        $start = new Horde_Date($start);
        $end = new Horde_Date($end);

        if (!isset($cfgSources) || !is_array($cfgSources) || !count($cfgSources)) {
            return array();
        }

        $objects = array();
        foreach ($time_categories as $category) {
            list($category, $source) = explode('/', $category, 2);
            $driver = Turba_Driver::singleton($source);
            if (is_a($driver, 'PEAR_Error')) {
                return PEAR::raiseError(sprintf(_("Connection failed: %s"),
                    $driver->getMessage()), 'horde.error', null, null, $name);
            }
            $new_objects = $driver->listTimeObjects($start, $end, $category);
            if (is_a($new_objects, 'PEAR_Error')) {
                return $new_objects;
            }
            $objects = array_merge($objects, $new_objects);
        }

        return $objects;
    }

    /**
     * Returns the client source name
     *
     * @return string  The name of the source to use with the clients api.
     */
    public function getClientSource()
    {
        return !empty($GLOBALS['conf']['client']['addressbook']) ? $GLOBALS['conf']['client']['addressbook'] : false;
    }

    /**
     * Returns the availabble client fields
     *
     * @return mixed  An array describing the fields | PEAR_Error
     */
    public function clientFields()
    {
        return $this->fields($GLOBALS['conf']['client']['addressbook']);
    }

    /**
     * Returns a contact from the client source.
     *
     * @param string $objectId  Client unique ID
     *
     * @return mixed  Array of client data | PEAR_Error
     */
    public function getClient($objectId = '')
    {
        return $this->getContact($GLOBALS['conf']['client']['addressbook'], $objectId);
    }

    /**
     * Returns mulitple contacts from the client source
     *
     * @param array $objectIds  client unique ids
     *
     * @return mixed  An array of clients data | PEAR_Error
     */
    public function getClients($objectIds = array())
    {
        return $this->getContacts($GLOBALS['conf']['client']['addressbook'], $objectIds);
    }

    /**
     * Adds a client to the client source
     *
     * @param array $attributes  Array containing the client attributes
     */
    public function addClient($attributes = array())
    {
        return $this->import($attributes, 'array', $this->getClientSource());
    }

    /**
     * Updates client data
     *
     * @param string $objectId   The unique id of the client
     * @param array $attributes  An array of client attributes
     *
     * @return boolean
     */
    public function updateClient($objectId = '', $attributes = array())
    {
        return $this->replace($this->getClientSource() . ':' . $objectId, $attributes, 'array');
    }

    /**
     * Deletes a client
     *
     * @param string $objectId  The unique id of the client
     *
     * @return boolean
     */
    public function deleteClient($objectId = '')
    {
        return $this->delete($this->getClientSource() . ':' . $objectId);
    }

    /**
     * Search for clients
     *
     * @param array $names         The search filter values
     * @param array $fields        The fields to serach in
     * @param boolean $matchBegin  Match word boundaries only
     *
     * @return mixed  A hash containing the search results | PEAR_Error
     */
    public function searchClients($names = array(), $fields = array(),
                                  $matchBegin = false)
    {
        return $this->search(
            $names,
            array($GLOBALS['conf']['client']['addressbook']),
            array($GLOBALS['conf']['client']['addressbook'] => $fields),
            $matchBegin,
            true);
    }

    /**
     * Sets the value of the specified attribute of a contact
     *
     * @param string $address  Contact email address
     * @param string $name     Contact name
     * @param string $field    Field to update
     * @param string $value    Field value to set
     * @param string $source   Contact source
     *
     * @return mixed  The new __key value on success | PEAR_Error on failure
     */
    public function addField($address = '', $name = '', $field = '',
                             $value = '',
        $source = '')
    {
        require_once dirname(__FILE__) . '/base.php';
        global $cfgSources;

        if (empty($source) || !isset($cfgSources[$source])) {
            return PEAR::raiseError(sprintf(_("Invalid address book: %s"), $source), 'horde.error', null, null, $source);
        }

        if (empty($address)) {
            return PEAR::raiseError(_("Invalid email"), 'horde.error', null, null, $source);
        }

        if (empty($name)) {
            return PEAR::raiseError(_("Invalid name"), 'horde.error', null, null, $source);
        }

        if (empty($value)) {
            return PEAR::raiseError(_("Invalid entry"), 'horde.error', null, null, $source);
        }

        $driver = Turba_Driver::singleton($source);
        if (is_a($driver, 'PEAR_Error')) {
            return PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $source);
        }

        if (!$driver->hasPermission(Horde_Perms::EDIT)) {
            return PEAR::raiseError(_("Permission denied"), 'horde.error', null, null, $source);
        }

        $res = $driver->search(array('email' => trim($address)), null, 'AND');
        if (is_a($res, 'PEAR_Error')) {
            return PEAR::raiseError(sprintf(_("Search failed: %s"), $res->getMessage()), 'horde.message', null, null, $source);
        }

        if ($res->count() > 1) {
            $res2 = $driver->search(array('email' => trim($address), 'name' => trim($name)), null, 'AND');
            if (is_a($res2, 'PEAR_Error')) {
                return PEAR::raiseError(sprintf(_("Search failed: %s"), $res2->getMessage()), 'horde.message', null, null, $source);
            }

            if (!$res2->count()) {
                return PEAR::raiseError(sprintf(_("Multiple persons with address [%s], but none with name [%s] already exist"), trim($address), trim($name)), 'horde.message', null, null, $source);
            }

            $res3 = $driver->search(array('email' => $address, 'name' => $name, $field => $value));
            if (is_a($res3, 'PEAR_Error')) {
                return PEAR::raiseError(sprintf(_("Search failed: %s"), $res3->getMessage()), 'horde.message', null, null, $source);
            }

            if ($res3->count()) {
                return PEAR::raiseError(sprintf(_("This person already has a %s entry in the address book"), $field), 'horde.message', null, null, $source);
            }

            $ob = $res2->next();
            $ob->setValue($field, $value);
            $ob->store();
        } elseif ($res->count() == 1) {
            $res4 = $driver->search(array('email' => $address, $field => $value));
            if (is_a($res4, 'PEAR_Error')) {
                return PEAR::raiseError(sprintf(_("Search failed: %s"), $res4->getMessage()), 'horde.message', null, null, $source);
            }

            if ($res4->count()) {
                return PEAR::raiseError(sprintf(_("This person already has a %s entry in the address book"), $field), 'horde.message', null, null, $source);
            }

            $ob = $res->next();
            $ob->setValue($field, $value);
            $ob->store();
        } else {
            return $driver->add(array('email' => $address, 'name' => $name, $field => $value, '__owner' => Horde_Auth::getAuth()));
        }

        return;
    }

    /**
     * Returns a field value
     *
     * @param string $address    Contact email address
     * @param string $field      Field to get
     * @param array $sources     Sources to check
     * @param boolean $strict    Match the email address strictly
     * @param boolean $multiple  Return more than one entry if found and true,
     *                           return an error if this is false.
     *
     * @return mixed  An array of field value(s) | PEAR_Error on failure.
     */
    public function getField($address = '', $field = '', $sources = array(),
                             $strict = false, $multiple = false)
    {
        require_once dirname(__FILE__) . '/base.php';
        global $cfgSources;

        if (empty($address)) {
            return PEAR::raiseError(_("Invalid email"), 'horde.error');
        }

        if (!isset($cfgSources) || !is_array($cfgSources) || !count($cfgSources)) {
            return array();
        }

        if (!count($sources)) {
            $sources = array(Turba::getDefaultAddressbook());
        }

        $result = array();
        foreach ($sources as $source) {
            if (!isset($cfgSources[$source])) {
                continue;
            }

            $driver = Turba_Driver::singleton($source);
            if (is_a($driver, 'PEAR_Error')) {
                continue;
            }

            $list = $driver->search(array('email' => $address), null, 'AND', array(), $strict ? array('email') : array());
            if (!is_a($list, 'Turba_List')) {
                continue;
            }

            while ($ob = $list->next()) {
                if ($ob->hasValue($field)) {
                    $result[] = $ob->getValue($field);
                }
            }
        }

        if (count($result) > 1) {
            if ($multiple) {
                return $result;
            } else {
                return PEAR::raiseError(_("More than 1 entry found"), 'horde.warning', null, null, $source);
            }
        } elseif (empty($result)) {
            return PEAR::raiseError(sprintf(_("No %s entry found for %s"), $field, $address), 'horde.warning', null, null, $source);
        }
        return reset($result);
    }

    /**
     * Deletes a field value
     *
     * @param string $address Contact email address
     * @param string $field   Field to delete value for
     * @param array $sources  Sources to delete value from
     *
     * @return boolean
     */
    public function deleteField($address = '', $field = '', $sources = array())
    {
        require_once dirname(__FILE__) . '/base.php';
        global $cfgSources;

        if (empty($address)) {
            return PEAR::raiseError(_("Invalid email"), 'horde.error');
        }

        if (!isset($cfgSources) || !is_array($cfgSources) || !count($cfgSources)) {
            return array();
        }

        if (count($sources) == 0) {
            $sources = array(Turba::getDefaultAddressbook());
        }

        $success = false;

        foreach ($sources as $source) {
            if (isset($cfgSources[$source])) {
                $driver = Turba_Driver::singleton($source);
                if (is_a($driver, 'PEAR_Error')) {
                    continue;
                }
                if (!$driver->hasPermission(Horde_Perms::EDIT)) {
                    continue;
                }

                $res = $driver->search(array('email' => $address));
                if (is_a($res, 'Turba_List')) {
                    if ($res->count() > 1) {
                        continue;
                    }

                    $ob = $res->next();
                    if (is_object($ob) && $ob->hasValue($field)) {
                        $ob->setValue($field, '');
                        $ob->store();
                        $success = true;
                    }
                }
            }
        }

        if (!$success) {
            return PEAR::raiseError(sprintf(_("No %s entry found for %s"), $field, $address), 'horde.error');
        }

        return;
    }

}
