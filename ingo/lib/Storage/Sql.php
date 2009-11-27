<?php
/**
 * Ingo_Storage_Sql implements the Ingo_Storage API to save Ingo data via
 * PHP's PEAR database abstraction layer.
 *
 * Required values for $params:<pre>
 *   'phptype'  - The database type (e.g. 'pgsql', 'mysql', etc.).
 *   'charset'  - The database's internal charset.</pre>
 *
 * Required by some database implementations:<pre>
 *   'database' - The name of the database.
 *   'hostspec' - The hostname of the database server.
 *   'protocol' - The communication protocol ('tcp', 'unix', etc.).
 *   'username' - The username with which to connect to the database.
 *   'password' - The password associated with 'username'.
 *   'options'  - Additional options to pass to the database.
 *   'tty'      - The TTY on which to connect to the database.
 *   'port'     - The port on which to connect to the database.</pre>
 *
 * The table structure can be created by the scripts/drivers/sql/ingo.sql
 * script.
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Jan Schneider <jan@horde.org>
 * @package Ingo
 */
class Ingo_Storage_Sql extends Ingo_Storage
{
    /**
     * Handle for the current database connection.
     *
     * @var DB
     */
    protected $_db;

    /**
     * Handle for the current database connection, used for writing.
     *
     * Defaults to the same handle as $_db if a separate write database is not
     * required.
     *
     * @var DB
     */
    protected $_write_db;

    /**
     * Boolean indicating whether or not we're connected to the SQL server.
     *
     * @var boolean
     */
    protected $_connected = false;

    /**
     * Constructor.
     *
     * @param array $params  Additional parameters for the subclass.
     *
     * @throws Horde_Exception
     */
    public function __construct($params = array())
    {
        $this->_params = $params;

        Horde::assertDriverConfig($this->_params, 'storage',
                                  array('phptype', 'charset'));

        if (!isset($this->_params['database'])) {
            $this->_params['database'] = '';
        }
        if (!isset($this->_params['username'])) {
            $this->_params['username'] = '';
        }
        if (!isset($this->_params['hostspec'])) {
            $this->_params['hostspec'] = '';
        }
        $this->_params['table_rules'] = 'ingo_rules';
        $this->_params['table_lists'] = 'ingo_lists';
        $this->_params['table_vacations'] = 'ingo_vacations';
        $this->_params['table_forwards'] = 'ingo_forwards';
        $this->_params['table_spam'] = 'ingo_spam';

        /* Connect to the SQL server using the supplied parameters. */
        $this->_write_db = &DB::connect($this->_params,
                                        array('persistent' => !empty($this->_params['persistent']),
                                              'ssl' => !empty($this->_params['ssl'])));
        if (is_a($this->_write_db, 'PEAR_Error')) {
            throw new Horde_Exception($this->_write_db);
        }
        /* Set DB portability options. */
        switch ($this->_write_db->phptype) {
        case 'mssql':
            $this->_write_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS | DB_PORTABILITY_RTRIM);
            break;
        default:
            $this->_write_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS);
        }


        /* Check if we need to set up the read DB connection seperately. */
        if (!empty($this->_params['splitread'])) {
            $params = array_merge($this->_params, $this->_params['read']);
            $this->_db = &DB::connect($params,
                                      array('persistent' => !empty($params['persistent']),
                                            'ssl' => !empty($params['ssl'])));
            if (is_a($this->_db, 'PEAR_Error')) {
                throw new Horde_Exception($this->_db);
            }

            switch ($this->_db->phptype) {
            case 'mssql':
                $this->_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS | DB_PORTABILITY_RTRIM);
                break;
            default:
                $this->_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS);
            }
        } else {
            /* Default to the same DB handle for the writer too. */
            $this->_db =& $this->_write_db;
        }

        parent::__construct();
    }

    /**
     * Retrieves the specified data from the storage backend.
     *
     * @param integer $field     The field name of the desired data.
     *                           See lib/Storage.php for the available fields.
     * @param boolean $readonly  Whether to disable any write operations.
     *
     * @return Ingo_Storage_Rule|Ingo_Storage_Filters  The specified data.
     */
    protected function _retrieve($field, $readonly = false)
    {
        switch ($field) {
        case self::ACTION_BLACKLIST:
        case self::ACTION_WHITELIST:
            if ($field == self::ACTION_BLACKLIST) {
                $ob = new Ingo_Storage_Blacklist();
                $filters = &$this->retrieve(self::ACTION_FILTERS);
                if (is_a($filters, 'PEAR_Error')) {
                    return $filters;
                }
                $rule = $filters->findRule($field);
                if (isset($rule['action-value'])) {
                    $ob->setBlacklistFolder($rule['action-value']);
                }
            } else {
                $ob = new Ingo_Storage_Whitelist();
            }
            $query = sprintf('SELECT list_address FROM %s WHERE list_owner = ? AND list_blacklist = ?',
                             $this->_params['table_lists']);
            $values = array(Ingo::getUser(),
                            (int)($field == self::ACTION_BLACKLIST));
            Horde::logMessage('Ingo_Storage_Sql::_retrieve(): ' . $query,
                              __FILE__, __LINE__, PEAR_LOG_DEBUG);
            $addresses = $this->_db->getCol($query, 0, $values);
            if (is_a($addresses, 'PEAR_Error')) {
                Horde::logMessage($addresses, __FILE__, __LINE__, PEAR_LOG_ERR);
                return $addresses;
            }
            if ($field == self::ACTION_BLACKLIST) {
                $ob->setBlacklist($addresses, false);
            } else {
                $ob->setWhitelist($addresses, false);
            }
            break;

        case self::ACTION_FILTERS:
            $ob = new Ingo_Storage_Filters_Sql($this->_db, $this->_write_db, $this->_params);
            if (is_a($result = $ob->init($readonly), 'PEAR_Error')) {
                return $result;
            }
            break;

        case self::ACTION_FORWARD:
            $query = sprintf('SELECT * FROM %s WHERE forward_owner = ?',
                             $this->_params['table_forwards']);
            Horde::logMessage('Ingo_Storage_Sql::_retrieve(): ' . $query,
                              __FILE__, __LINE__, PEAR_LOG_DEBUG);
            $result = $this->_db->query($query, Ingo::getUser());
            $data = $result->fetchRow(DB_FETCHMODE_ASSOC);

            $ob = new Ingo_Storage_Forward();
            if ($data && !is_a($data, 'PEAR_Error')) {
                $ob->setForwardAddresses(explode("\n", $data['forward_addresses']), false);
                $ob->setForwardKeep((bool)$data['forward_keep']);
                $ob->setSaved(true);
            } elseif ($data = @unserialize($GLOBALS['prefs']->getDefault('vacation'))) {
                $ob->setForwardAddresses($data['a'], false);
                $ob->setForwardKeep($data['k']);
            }
            break;

        case self::ACTION_VACATION:
            $query = sprintf('SELECT * FROM %s WHERE vacation_owner = ?',
                             $this->_params['table_vacations']);
            Horde::logMessage('Ingo_Storage_Sql::_retrieve(): ' . $query,
                              __FILE__, __LINE__, PEAR_LOG_DEBUG);
            $result = $this->_db->query($query, Ingo::getUser());
            $data = $result->fetchRow(DB_FETCHMODE_ASSOC);

            $ob = new Ingo_Storage_Vacation();
            if ($data && !is_a($data, 'PEAR_Error')) {
                $ob->setVacationAddresses(explode("\n", $data['vacation_addresses']), false);
                $ob->setVacationDays((int)$data['vacation_days']);
                $ob->setVacationStart((int)$data['vacation_start']);
                $ob->setVacationEnd((int)$data['vacation_end']);
                $ob->setVacationExcludes(explode("\n", $data['vacation_excludes']), false);
                $ob->setVacationIgnorelist((bool)$data['vacation_ignorelists']);
                $ob->setVacationReason(Horde_String::convertCharset($data['vacation_reason'], $this->_params['charset']));
                $ob->setVacationSubject(Horde_String::convertCharset($data['vacation_subject'], $this->_params['charset']));
                $ob->setSaved(true);
            } elseif ($data = @unserialize($GLOBALS['prefs']->getDefault('vacation'))) {
                $ob->setVacationAddresses($data['addresses'], false);
                $ob->setVacationDays($data['days']);
                $ob->setVacationExcludes($data['excludes'], false);
                $ob->setVacationIgnorelist($data['ignorelist']);
                $ob->setVacationReason($data['reason']);
                $ob->setVacationSubject($data['subject']);
                if (isset($data['start'])) {
                    $ob->setVacationStart($data['start']);
                }
                if (isset($data['end'])) {
                    $ob->setVacationEnd($data['end']);
                }
            }
            break;

        case self::ACTION_SPAM:
            $query = sprintf('SELECT * FROM %s WHERE spam_owner = ?',
                             $this->_params['table_spam']);
            Horde::logMessage('Ingo_Storage_Sql::_retrieve(): ' . $query,
                              __FILE__, __LINE__, PEAR_LOG_DEBUG);
            $result = $this->_db->query($query, Ingo::getUser());
            $data = $result->fetchRow(DB_FETCHMODE_ASSOC);

            $ob = new Ingo_Storage_Spam();
            if ($data && !is_a($data, 'PEAR_Error')) {
                $ob->setSpamFolder($data['spam_folder']);
                $ob->setSpamLevel((int)$data['spam_level']);
                $ob->setSaved(true);
            } elseif ($data = @unserialize($GLOBALS['prefs']->getDefault('spam'))) {
                $ob->setSpamFolder($data['folder']);
                $ob->setSpamLevel($data['level']);
            }
            break;

        default:
            $ob = false;
        }

        return $ob;
    }

    /**
     * Stores the specified data in the storage backend.
     *
     * @access private
     *
     * @param Ingo_Storage_Rule|Ingo_Storage_Filters $ob  The object to store.
     *
     * @return boolean  True on success.
     */
    protected function _store(&$ob)
    {
        switch ($ob->obType()) {
        case self::ACTION_BLACKLIST:
        case self::ACTION_WHITELIST:
            $is_blacklist = (int)($ob->obType() == self::ACTION_BLACKLIST);
            if ($is_blacklist) {
                $filters = &$this->retrieve(self::ACTION_FILTERS);
                if (is_a($filters, 'PEAR_Error')) {
                    return $filters;
                }
                $id = $filters->findRuleId(self::ACTION_BLACKLIST);
                if ($id !== null) {
                    $rule = $filters->getRule($id);
                    if (!isset($rule['action-value']) ||
                        $rule['action-value'] != $ob->getBlacklistFolder()) {
                        $rule['action-value'] = $ob->getBlacklistFolder();
                        $filters->updateRule($rule, $id);
                    }
                }
            }
            $query = sprintf('DELETE FROM %s WHERE list_owner = ? AND list_blacklist = ?',
                             $this->_params['table_lists']);
            $values = array(Ingo::getUser(), $is_blacklist);
            Horde::logMessage('Ingo_Storage_Sql::_store(): ' . $query,
                              __FILE__, __LINE__, PEAR_LOG_DEBUG);
            $result = $this->_write_db->query($query, $values);
            if (is_a($result, 'PEAR_Error')) {
                Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
                return $result;
            }
            $query = sprintf('INSERT INTO %s (list_owner, list_blacklist, list_address) VALUES (?, ?, ?)',
                             $this->_params['table_lists']);
            Horde::logMessage('Ingo_Storage_Sql::_store(): ' . $query,
                              __FILE__, __LINE__, PEAR_LOG_DEBUG);
            $addresses = $is_blacklist ? $ob->getBlacklist() : $ob->getWhitelist();
            foreach ($addresses as $address) {
                $result = $this->_write_db->query($query,
                                                  array(Ingo::getUser(),
                                                        $is_blacklist,
                                                        $address));
                if (is_a($result, 'PEAR_Error')) {
                    Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
                    return $result;
                }
            }
            $ob->setSaved(true);
            $ret = true;
            break;

        case self::ACTION_FILTERS:
            $ret = true;
            break;

        case self::ACTION_FORWARD:
            if ($ob->isSaved()) {
                $query = 'UPDATE %s SET forward_addresses = ?, forward_keep = ? WHERE forward_owner = ?';
            } else {
                $query = 'INSERT INTO %s (forward_addresses, forward_keep, forward_owner) VALUES (?, ?, ?)';
            }
            $query = sprintf($query, $this->_params['table_forwards']);
            $values = array(
                implode("\n", $ob->getForwardAddresses()),
                (int)(bool)$ob->getForwardKeep(),
                Ingo::getUser());
            Horde::logMessage('Ingo_Storage_Sql::_store(): ' . $query,
                              __FILE__, __LINE__, PEAR_LOG_DEBUG);
            $ret = $this->_write_db->query($query, $values);
            if (!is_a($ret, 'PEAR_Error')) {
                $ob->setSaved(true);
            }
            break;

        case self::ACTION_VACATION:
            if ($ob->isSaved()) {
                $query = 'UPDATE %s SET vacation_addresses = ?, vacation_subject = ?, vacation_reason = ?, vacation_days = ?, vacation_start = ?, vacation_end = ?, vacation_excludes = ?, vacation_ignorelists = ? WHERE vacation_owner = ?';
            } else {
                $query = 'INSERT INTO %s (vacation_addresses, vacation_subject, vacation_reason, vacation_days, vacation_start, vacation_end, vacation_excludes, vacation_ignorelists, vacation_owner) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
            }
            $query = sprintf($query, $this->_params['table_vacations']);
            $values = array(
                implode("\n", $ob->getVacationAddresses()),
                Horde_String::convertCharset($ob->getVacationSubject(),
                                       Horde_Nls::getCharset(),
                                       $this->_params['charset']),
                Horde_String::convertCharset($ob->getVacationReason(),
                                       Horde_Nls::getCharset(),
                                       $this->_params['charset']),
                (int)$ob->getVacationDays(),
                (int)$ob->getVacationStart(),
                (int)$ob->getVacationEnd(),
                implode("\n", $ob->getVacationExcludes()),
                (int)(bool)$ob->getVacationIgnorelist(),
                Ingo::getUser());
            Horde::logMessage('Ingo_Storage_Sql::_store(): ' . $query,
                              __FILE__, __LINE__, PEAR_LOG_DEBUG);
            $ret = $this->_write_db->query($query, $values);
            if (!is_a($ret, 'PEAR_Error')) {
                $ob->setSaved(true);
            }
            break;

        case self::ACTION_SPAM:
            if ($ob->isSaved()) {
                $query = 'UPDATE %s SET spam_level = ?, spam_folder = ? WHERE spam_owner = ?';
            } else {
                $query = 'INSERT INTO %s (spam_level, spam_folder, spam_owner) VALUES (?, ?, ?)';
            }
            $query = sprintf($query, $this->_params['table_spam']);
            $values = array(
                (int)$ob->getSpamLevel(),
                $ob->getSpamFolder(),
                Ingo::getUser());
            Horde::logMessage('Ingo_Storage_Sql::_store(): ' . $query,
                              __FILE__, __LINE__, PEAR_LOG_DEBUG);
            $ret = $this->_write_db->query($query, $values);
            if (!is_a($ret, 'PEAR_Error')) {
                $ob->setSaved(true);
            }
            break;

        default:
            $ret = false;
            break;
        }

        if (is_a($ret, 'PEAR_Error')) {
            Horde::logMessage($ret, __FILE__, __LINE__);
        }

        return $ret;
    }

    /**
     * Removes the data of the specified user from the storage backend.
     *
     * @param string $user  The user name to delete filters for.
     *
     * @return mixed  True | PEAR_Error
     */
    function removeUserData($user)
    {
        if (!Horde_Auth::isAdmin() && $user != Horde_Auth::getAuth()) {
            return PEAR::raiseError(_("Permission Denied"));
        }

        $queries = array(sprintf('DELETE FROM %s WHERE rule_owner = ?',
                                 $this->_params['table_rules']),
                         sprintf('DELETE FROM %s WHERE list_owner = ?',
                                 $this->_params['table_lists']),
                         sprintf('DELETE FROM %s WHERE vacation_owner = ?',
                                 $this->_params['table_vacations']),
                         sprintf('DELETE FROM %s WHERE forward_owner = ?',
                                 $this->_params['table_forwards']),
                         sprintf('DELETE FROM %s WHERE spam_owner = ?',
                                 $this->_params['table_spam']));

        $values = array($user);
        foreach ($queries as $query) {
            Horde::logMessage('Ingo_Storage_sql::removeUserData(): ' . $query, __FILE__, __LINE__, PEAR_LOG_DEBUG);
            $result = $this->_write_db->query($query, $values);
            if (is_a($result, 'PEAR_Error')) {
                return $result;
            }
        }

        return true;
    }

}
