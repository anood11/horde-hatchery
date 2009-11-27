<?php
/**
 * Skoli storage implementation for PHP's PEAR database abstraction layer.
 *
 * Required parameters:<pre>
 *   'phptype'      The database type (e.g. 'pgsql', 'mysql', etc.).
 *   'charset'      The database's internal charset.</pre>
 *
 * Required by some database implementations:<pre>
 *   'hostspec'     The hostname of the database server.
 *   'protocol'     The communication protocol ('tcp', 'unix', etc.).
 *   'database'     The name of the database.
 *   'username'     The username with which to connect to the database.
 *   'password'     The password associated with 'username'.
 *   'options'      Additional options to pass to the database.
 *   'tty'          The TTY on which to connect to the database.
 *   'port'         The port on which to connect to the database.</pre>
 *
 * Optional values when using separate reading and writing servers, for example
 * in replication settings:<pre>
 *   'splitread'   Boolean, whether to implement the separation or not.
 *   'read'        Array containing the parameters which are different for
 *                 the read database connection, currently supported
 *                 only 'hostspec' and 'port' parameters.</pre>
 *
 * Optional parameters:<pre>
 *   'objects_table'            The name of the objects table in 'database'.
 *                              Default is 'skoli_objects'.
 *   'object_attributes_table'  The name of the attributes table in 'database'.
 *                              Default ist 'skoli_object_attributes'.
 *   'students_table'           The name of the students table in 'database'.
 *                              Default is 'skoli_classes_students'.</pre>
 *
 * The table structure can be created by the scripts/sql/skoli.sql script.
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Martin Blumenthal <tinu@humbapa.ch>
 * @package Skoli
 */
class Skoli_Driver_sql extends Skoli_Driver {

    /**
     * Handle for the current database connection.
     *
     * @var DB
     */
    var $_db;

    /**
     * Handle for the current database connection, used for writing. Defaults
     * to the same handle as $_db if a separate write database is not required.
     *
     * @var DB
     */
    var $_write_db;

    /**
     * Constructs a new SQL storage object.
     *
     * @param string $classlist  The classlist to load.
     * @param array $params     A hash containing connection parameters.
     */
    function Skoli_Driver_sql($class, $params = array())
    {
        $this->_class = $class;
        $this->_params = $params;
    }

    /**
     * Attempts to open a connection to the SQL server.
     *
     * @return boolean  True on success, PEAR_Error on failure.
     */
    function initialize()
    {
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
        if (!isset($this->_params['objects_table'])) {
            $this->_params['objects_table'] = 'skoli_objects';
        }
        if (!isset($this->_params['object_attributes_table'])) {
            $this->_params['object_attributes_table'] = 'skoli_object_attributes';
        }
        if (!isset($this->_params['students_table'])) {
            $this->_params['students_table'] = 'skoli_classes_students';
        }

        /* Connect to the SQL server using the supplied parameters. */
        $this->_write_db = &DB::connect($this->_params,
                                        array('persistent' => !empty($this->_params['persistent'])));
        if (is_a($this->_write_db, 'PEAR_Error')) {
            return $this->_write_db;
        }

        /* Set DB portability options. */
        switch ($this->_write_db->phptype) {
        case 'mssql':
            $this->_write_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS | DB_PORTABILITY_RTRIM);
            break;
        default:
            $this->_write_db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS);
        }

        /* Check if we need to set up the read DB connection
         * seperately. */
        if (!empty($this->_params['splitread'])) {
            $params = array_merge($this->_params, $this->_params['read']);
            $this->_db = &DB::connect($params,
                                      array('persistent' => !empty($params['persistent'])));
            if (is_a($this->_db, 'PEAR_Error')) {
                return $this->_db;
            }

            /* Set DB portability options. */
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

        return true;
    }

    /**
     * Get all students from the backend storage.
     *
     * @return array  List with all student IDs.
     */
    function getStudents()
    {
        $query = 'SELECT student_id FROM ' . $this->_params['students_table'] .
                 ' WHERE class_id = ?';
        $values = array($this->_class);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Skoli_Driver_sql::getStudents(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the select query. */
        $students = $this->_db->getAll($query, $values, DB_FETCHMODE_ASSOC);

        /* Return an error immediately if the query failed. */
        if (is_a($students, 'PEAR_Error')) {
            Horde::logMessage($students, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $students;
        }

        return $students;
    }

    /**
     * Add students to the backend storage.
     *
     * @param array $students  List with students.
     *
     * @return boolean  True on success, PEAR_Error on failure.
     */
    function addStudents($students)
    {
        /* Delete any existing Students */
        $query = 'DELETE FROM ' . $this->_params['students_table'] .
                 ' WHERE class_id=?';
        $result = $this->_write_db->query($query, array($this->_class));

        foreach ($students as $addressid) {
            $query = 'INSERT INTO ' . $this->_params['students_table'] .
                     ' (class_id, student_id)' .
                     ' VALUES (?, ?)';
            $values = array($this->_class, $addressid);

            /* Log the query at a DEBUG log level. */
            Horde::logMessage(sprintf('Skoli_Driver_sql::addStudents(): %s', $query),
                              __FILE__, __LINE__, PEAR_LOG_DEBUG);

            /* Attempt the insertion query. */
            $result = $this->_write_db->query($query, $values);

            /* Return an error immediately if the query failed. */
            if (is_a($result, 'PEAR_Error')) {
                Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
                return $result;
            }
        }

        return true;
    }

    /**
     * Get an entry from the backend storage.
     *
     * @param string  $entryid  The entry ID.
     *
     * @return array  List with all entry fields.
     */
    function getEntry($entryid)
    {
        $query = 'SELECT * FROM ' . $this->_params['objects_table'] .
                 ' WHERE object_id = ?';
        $values = array($entryid);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Skoli_Driver_sql::getEntry(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the select query. */
        $entry = $this->_db->getRow($query, $values, DB_FETCHMODE_ASSOC);

        /* Return an error immediately if the query failed. */
        if (is_a($entry, 'PEAR_Error')) {
            Horde::logMessage($entry, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $entry;
        } else if (!is_array($entry)) {
            return array();
        }

        $query = 'SELECT * FROM ' . $this->_params['object_attributes_table'] .
                         ' WHERE object_id = ?';
        $values = array($entryid);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Skoli_Driver_sql::getEntry(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the select query. */
        $attributes = $this->_db->getAll($query, $values, DB_FETCHMODE_ASSOC);

        /* Return an error immediately if the query failed. */
        if (is_a($attributes, 'PEAR_Error')) {
            Horde::logMessage($attributes, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $attributes;
        }

        $entry['_attributes'] = array();
        foreach ($attributes as $attribute) {
            $entry['_attributes'][$attribute['attr_name']] = $attribute['attr_value'];
        }

        if (empty($this->_class)) {
            $this->_class = $entry['class_id'];
        }

        return $entry;
    }

    /**
     * Get all entries for the current class or student from the backend storage.
     *
     * @param string  $studentid    The student ID.
     *
     * @param string  $type         The entry type to search in.
     *
     * @param array  $searchparams  Some additional search parameters.
     *
     * @return array  List with all entries.
     */
    function getEntries($studentid = null, $type = null, $searchparams = array())
    {
        if (is_null($studentid)) {
            $students = $this->getStudents();
        } else {
            $students = array(array('student_id' => $studentid));
        }

        foreach ($students as $studentkey=>$student) {
            /* Build the search parameter */
            if (count($searchparams)) {
                $where = '';
                $search_values = array();
                if (count($searchparams) === 1 && !is_array($searchparams[0])) {
                    /* search all attributes for the specified text */
                    $where = ' AND a.attr_value LIKE ?';
                    $search_values[] = '%' . $searchparams[0] . '%';
                } else {
                    /* search only in the specified fields */
                    $where = ' AND ';
                    for ($i = 0; $i < count($searchparams); $i++) {
                        $strict = !empty($searchparams[$i]['strict']);
                        $where .= '(a.attr_name = ? AND a.attr_value ' . ($strict ? '=' : 'LIKE') . ' ?) OR ';
                        $search_values[] = $searchparams[$i]['name'];
                        if ($strict) {
                            $search_values[] = $searchparams[$i]['value'];
                        } else {
                            $search_values[] = '%' . $searchparams[$i]['value'] . '%';
                        }
                    }
                    $where = substr($where, 0, -4);
                }
                $query = 'SELECT o.* FROM ' . $this->_params['object_attributes_table'] . ' AS a, ' .
                         $this->_params['objects_table'] . ' AS o' .
                         ' WHERE o.object_id = a.object_id AND o.class_id = ? AND o.student_id = ?' . (!is_null($type) ? ' AND o.object_type = ?' : '') . $where .
                         ' GROUP BY o.object_id';
                $values = array($this->_class, $student['student_id']);
                if (!is_null($type)) {
                    $values[] = $type;
                }
                $values = array_merge($values, $search_values);

                /* Log the query at a DEBUG log level. */
                Horde::logMessage(sprintf('Skoli_Driver_sql::getEntries(): %s', $query),
                                  __FILE__, __LINE__, PEAR_LOG_DEBUG);

                /* Attempt the select query. */
                $entries = $this->_db->getAll($query, $values, DB_FETCHMODE_ASSOC);

                /* Return an error immediately if the query failed. */
                if (is_a($entries, 'PEAR_Error')) {
                    Horde::logMessage($entries, __FILE__, __LINE__, PEAR_LOG_ERR);
                    return $entries;
                }
            } else {
                $query = 'SELECT * FROM ' . $this->_params['objects_table'] .
                         ' WHERE class_id = ? AND student_id = ?' .
                         (!is_null($type) ? ' AND object_type = ?' : '');
                $values = array($this->_class, $student['student_id']);
                if (!is_null($type)) {
                    $values[] = $type;
                }

                /* Log the query at a DEBUG log level. */
                Horde::logMessage(sprintf('Skoli_Driver_sql::getEntries(): %s', $query),
                                  __FILE__, __LINE__, PEAR_LOG_DEBUG);

                /* Attempt the select query. */
                $entries = $this->_db->getAll($query, $values, DB_FETCHMODE_ASSOC);

                /* Return an error immediately if the query failed. */
                if (is_a($entries, 'PEAR_Error')) {
                    Horde::logMessage($entries, __FILE__, __LINE__, PEAR_LOG_ERR);
                    return $entries;
                }
            }

            $students[$studentkey]['_entries'] = $entries;

            foreach ($entries as $entrykey=>$entry) {
                $query = 'SELECT * FROM ' . $this->_params['object_attributes_table'] .
                         ' WHERE object_id = ?';
                $values = array($entry['object_id']);

                /* Log the query at a DEBUG log level. */
                Horde::logMessage(sprintf('Skoli_Driver_sql::getEntries(): %s', $query),
                                  __FILE__, __LINE__, PEAR_LOG_DEBUG);

                /* Attempt the select query. */
                $attributes = $this->_db->getAll($query, $values, DB_FETCHMODE_ASSOC);

                /* Return an error immediately if the query failed. */
                if (is_a($attributes, 'PEAR_Error')) {
                    Horde::logMessage($attributes, __FILE__, __LINE__, PEAR_LOG_ERR);
                    return $attributes;
                }

                $students[$studentkey]['_entries'][$entrykey]['_attributes'] = array();
                foreach ($attributes as $attribute) {
                    $students[$studentkey]['_entries'][$entrykey]['_attributes'][$attribute['attr_name']] = $attribute['attr_value'];
                }
            }
        }

        return $students;
    }

    /**
     * Get the timestamp of the last entry for the given student.
     *
     * @param string  $studentid    The student ID.
     *
     * @return int  The last entry.
     */
    function lastEntry($studentid)
    {
        $query = 'SELECT object_time FROM ' . $this->_params['objects_table'] .
                 ' WHERE class_id = ? AND student_id = ? ORDER BY object_time DESC LIMIT 1';
        $values = array($this->_class, $studentid);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Skoli_Driver_sql::lastEntry(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the select query. */
        $lastentry = $this->_db->getRow($query, $values, DB_FETCHMODE_ORDERED);

        /* Return an error immediately if the query failed. */
        if (is_a($lastentry, 'PEAR_Error')) {
            Horde::logMessage($lastentry, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $lastentry;
        }

        if (count($lastentry)) {
            return $lastentry[0];
        }

        return null;
    }

    /**
     * Add or update a new entry to the backend storage.
     *
     * @param string     $entryid  The entry ID.
     *
     * @param Variables  $vars  List with form variables.
     *
     * @return boolean  True on success, PEAR_Error on failure.
     */
    function updateEntry($entryid, $vars)
    {
        $attributes = array();
        foreach ($vars->_vars as $key=>$value) {
            if (strpos($key, 'attribute_') === 0 && $value != '') {
                $attribute = substr($key, 10);
                $attributes[$attribute] = $value;
            }
        }

        $query = 'UPDATE ' . $this->_params['objects_table'] . ' SET' .
                 ' class_id = ?, student_id = ?, object_time = ?, object_type = ?' .
                 ' WHERE object_id = ?';
        require_once 'Horde/Date.php';
        $date = new Horde_Date($vars->get('object_time'));
        $values = array($this->_class, $vars->get('student_id'), $date->datestamp(), $vars->get('object_type'), $entryid);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Skoli_Driver_sql::updateEntry(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the insertion query. */
        $result = $this->_write_db->query($query, $values);

        /* Return an error immediately if the query failed. */
        if (is_a($result, 'PEAR_Error')) {
            Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $result;
        }

        $query = 'DELETE FROM ' . $this->_params['object_attributes_table'] .
                 ' WHERE object_id = ?';
        $values = array($entryid);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Skoli_Driver_sql::updateEntry(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the delete query. */
        $result = $this->_write_db->query($query, $values);

        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        foreach ($attributes as $attribute=>$value) {
            $query = 'INSERT INTO ' . $this->_params['object_attributes_table'] .
                     ' (object_id, attr_name, attr_value)' .
                     ' VALUES (?, ?, ?)';
            $values = array($entryid, $attribute, $value);

            /* Log the query at a DEBUG log level. */
            Horde::logMessage(sprintf('Skoli_Driver_sql::addEntry(): %s', $query),
                              __FILE__, __LINE__, PEAR_LOG_DEBUG);

            /* Attempt the insertion query. */
            $result = $this->_write_db->query($query, $values);

            /* Return an error immediately if the query failed. */
            if (is_a($result, 'PEAR_Error')) {
                Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
                return $result;
            }
        }

        return true;
    }

    /**
     * Add a new entry to the backend storage.
     *
     * @param Variables  $vars  List with form variables.
     *
     * @return Mixed  Studentnames on success, PEAR_Error on failure.
     */
    function addEntry($vars)
    {
        $names = '';
        $class = current(Skoli::listStudents($this->_class));

        $attributes = array();
        foreach ($vars->_vars as $key=>$value) {
            if (strpos($key, 'attribute_') === 0 && $value != '') {
                $attribute = substr($key, 10);
                $attributes[$attribute] = $value;
            }
        }

        require_once 'Horde/Date.php';
        foreach ($vars->get('student_id') as $studentid) {
            $query = 'INSERT INTO ' . $this->_params['objects_table'] .
                     ' (object_id, object_owner, object_uid, class_id, student_id, object_time, object_type)' .
                     ' VALUES (?, ?, ?, ?, ?, ?, ?)';
            $entryId = md5(uniqid(mt_rand(), true));
            $date = new Horde_Date($vars->get('object_time'));
            $values = array($entryId, Horde_Auth::getAuth(), $this->generateUID(), $this->_class, $studentid, $date->datestamp(), $vars->get('object_type'));

            /* Log the query at a DEBUG log level. */
            Horde::logMessage(sprintf('Skoli_Driver_sql::addEntry(): %s', $query),
                              __FILE__, __LINE__, PEAR_LOG_DEBUG);

            /* Attempt the insertion query. */
            $result = $this->_write_db->query($query, $values);

            /* Return an error immediately if the query failed. */
            if (is_a($result, 'PEAR_Error')) {
                Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
                return $result;
            }

            foreach ($attributes as $attribute=>$value) {
                $query = 'INSERT INTO ' . $this->_params['object_attributes_table'] .
                         ' (object_id, attr_name, attr_value)' .
                         ' VALUES (?, ?, ?)';
                $values = array($entryId, $attribute, $value);

                /* Log the query at a DEBUG log level. */
                Horde::logMessage(sprintf('Skoli_Driver_sql::addEntry(): %s', $query),
                                  __FILE__, __LINE__, PEAR_LOG_DEBUG);

                /* Attempt the insertion query. */
                $result = $this->_write_db->query($query, $values);

                /* Return an error immediately if the query failed. */
                if (is_a($result, 'PEAR_Error')) {
                    Horde::logMessage($result, __FILE__, __LINE__, PEAR_LOG_ERR);
                    return $result;
                }
            }

            $studentdetails = Skoli::getStudent($class['address_book'], $studentid);
            $names .= $studentdetails[$GLOBALS['conf']['addresses']['name_field']] . ', ';
        }

        return substr($names, 0, -2);
    }

    /**
     * Get all currently used subjects from the current class.
     *
     * @param string  $type  Get subjects only from this type.
     *
     * @return array  List with all subjects.
     */
    function getSubjects($type = null)
    {
        $where = !is_null($type) ? ' AND o.object_type = ?' : '';
        $query = 'SELECT DISTINCT a.attr_value FROM ' . $this->_params['object_attributes_table'] . ' AS a, ' .
                 $this->_params['objects_table'] . ' AS o' .
                 ' WHERE a.object_id = o.object_id AND o.class_id = ? AND a.attr_name = ?' . $where;
        $values = array($this->_class, 'subject');
        if (!is_null($type)) {
            $values[] = $type;
        }

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Skoli_Driver_sql::getSubjects(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the select query. */
        $subjects = $this->_db->getAll($query, $values, DB_FETCHMODE_ORDERED);

        /* Return an error immediately if the query failed. */
        if (is_a($subjects, 'PEAR_Error')) {
            Horde::logMessage($subjects, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $subjects;
        }

        $subjectlist = array();
        foreach ($subjects as $subject) {
            $subjectlist[] = $subject[0];
        }

        return $subjectlist;
    }

    /**
     * Deletes all data from the current class.
     *
     * @return boolean  True on success, PEAR_Error on failure.
     */
    function deleteAll()
    {
        $query = 'DELETE FROM ' . $this->_params['students_table'] .
                 ' WHERE class_id = ?';
        $values = array($this->_class);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Skoli_Driver_sql::deleteAll(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the delete query. */
        $result = $this->_write_db->query($query, $values);

        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        $query = 'SELECT object_id FROM ' . $this->_params['objects_table'] .
                 ' WHERE class_id = ?';
        $values = array($this->_class);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Skoli_Driver_sql::deleteAll(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the select query. */
        $entries = $this->_db->getAll($query, $values, DB_FETCHMODE_ASSOC);

        /* Return an error immediately if the query failed. */
        if (is_a($entries, 'PEAR_Error')) {
            Horde::logMessage($entries, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $entries;
        }

        foreach ($entries as $entry) {
            $result = $this->deleteEntry($entry['object_id']);

            /* Return an error immediately if the query failed. */
            if (is_a($result, 'PEAR_Error')) {
                return $result;
            }
        }

        return true;
    }

    /**
     * Deletes an entry from the current class.
     *
     * @param string  $object_id  The entry ID to delete.
     *
     * @return boolean  True on success, PEAR_Error on failure.
     */
    function deleteEntry($object_id)
    {
        $query = 'DELETE FROM ' . $this->_params['objects_table'] .
                 ' WHERE object_id = ? AND class_id = ?';
        $values = array($object_id, $this->_class);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Skoli_Driver_sql::deleteEntry(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the delete query. */
        $result = $this->_write_db->query($query, $values);

        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        $query = 'DELETE FROM ' . $this->_params['object_attributes_table'] .
                 ' WHERE object_id = ?';
        $values = array($object_id);

        /* Log the query at a DEBUG log level. */
        Horde::logMessage(sprintf('Skoli_Driver_sql::deleteEntry(): %s', $query),
                          __FILE__, __LINE__, PEAR_LOG_DEBUG);

        /* Attempt the delete query. */
        $result = $this->_write_db->query($query, $values);

        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        return true;
    }
}
