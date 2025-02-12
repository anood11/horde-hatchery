<?php
/**
 * Agora_Messages_split_sql:: provides the functions to access both threads
 * sotred in a scope dedicated tables
 *
 * $Horde: agora/lib/Messages/split_sql.php,v 1.19 2009/01/06 17:48:46 jan Exp $
 *
 * Copyright 2003-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Duck <duck@obala.net>
 * @package Agora
 */
class Agora_Messages_split_sql extends Agora_Messages {

    /**
     * Constructor
     */
    public function __construct($scope)
    {
        parent::__construct($scope);

        /* trick table name */
        if ($scope != 'agora') {
            $this->_threads_table = 'agora_messages_' . $scope;
            $this->_forums_table = 'agora_forums_' . $scope;
        }
    }

    /**
     * Returns an ID for a given forum name.
     *
     * @param string $forum_name  The full forum name.
     *
     * @return integer  The ID of the forum.
     */
    public function getForumId($forum_name)
    {
        static $ids = array();

        if (!isset($ids[$forum_name])) {
            $sql = 'SELECT forum_id FROM ' . $this->_forums_table . ' WHERE forum_name = ?';
            $ids[$forum_name] = $this->_db->getOne($sql, array('integer'), array($forum_name));
        }

        return $ids[$forum_name];
    }

    /**
     * Get forums ids and titles
     *
     * @return array  An array of forums and form names.
     */
    public function getBareForums()
    {
        if ($this->_scope == 'agora') {
            $sql = 'SELECT forum_id, forum_name FROM ' . $this->_forums_table;
        } else {
            $sql = 'SELECT forum_id, forum_description FROM ' . $this->_forums_table;
        }

        return $this->_db->getAssoc($sql);
    }

    /**
     * Fetches a list of forums.
     *
     * @param integer $root_forum  The first level forum.
     * @param boolean $formatted   Whether to return the list formatted or raw.
     * @param string  $sort_by     The column to sort by.
     * @param integer $sort_dir    Sort direction, 0 = ascending,
     *                             1 = descending.
     * @param boolean $add_scope   Add parent forum if forum for another
     *                             scopelication.
     * @param string  $from        The forum to start listing at.
     * @param string  $count       The number of forums to return.
     *
     * @return mixed  An array of forums or PEAR_Error on failure.
     */
    protected function _getForums($root_forum = 0, $formatted = true,
                        $sort_by = 'forum_name', $sort_dir = 0,
                        $add_scope = false,  $from = 0, $count = 0)
    {
        $key = $this->_scope . ':' . $root_forum . ':' . $formatted . ':'
            . $sort_by . ':' . $sort_dir . ':' . $add_scope . ':' . $from
            . ':' . $count;
        $forums = $this->_getCache($key);
        if ($forums) {
            return unserialize($forums);
        }

        $sql = 'SELECT forum_id, forum_name';

        if ($formatted) {
            $sql .= ', scope, active, forum_description, forum_parent_id, '
                . 'forum_moderated, forum_attachments, message_count, thread_count, '
                . 'last_message_id, last_message_author, last_message_timestamp';
        }

        $sql .= ' FROM ' . $this->_forums_table . ' WHERE active = ? ';
        $params = array(1);

        if ($root_forum != 0) {
            $sql .= ' AND forum_parent_id = ? ';
            $params[] = $root_forum;
        }

        /* Sort by result colomn if possible */
        $sql .= ' ORDER BY ';
        if ($sort_by == 'forum_name' || $sort_by == 'message_count') {
            $sql .= $sort_by;
        } else {
            $sql .= 'forum_id';
        }
        $sql .= ' ' . ($sort_dir ? 'DESC' : 'ASC');

        /* Slice direcly in DB. */
        if ($count) {
            $this->_db->setLimit($count, $from);
        }

        $forums = $this->_db->getAssoc($sql, null, $params, null, MDB2_FETCHMODE_ASSOC, $formatted);
        if ($forums instanceof PEAR_Error || empty($forums)) {
            return $forums;
        }

        $forums = $this->_formatForums($forums, $formatted);

        $this->_setCache($key, serialize($forums));

        return $forums;
    }

    /**
     * Returns a list of threads.
     *
     * @param integer $thread_root   Message at which to start the thread.
     *                               If null get all forum threads
     * @param boolean $all_levels    Show all child levels or just one level.
     * @param string  $sort_by       The column by which to sort.
     * @param integer $sort_dir      The direction by which to sort:
     *                                   0 - ascending
     *                                   1 - descending
     * @param boolean $message_view
     * @param string  $from          The thread to start listing at.
     * @param string  $count         The number of threads to return.
     */
    protected function _getThreads($thread_root = 0,
                         $all_levels = false,
                         $sort_by = 'message_modifystamp',
                         $sort_dir = 0,
                         $message_view = false,
                         $from = 0,
                         $count = 0)
    {
        /* Cache */
        $key = $this->_scope . ':' . $this->_forum_id . ':' . $thread_root . ':' . intval($all_levels) . ':'
             . $sort_by . ':' . $sort_dir . ':' . intval($message_view) . ':' . intval($from) . ':' . intval($count);
        $messages = $this->_getCache($key, $thread_root);
        if ($messages) {
            return unserialize($messages);
        }

        $bind = $this->_buildThreadsQuery(null, $thread_root, $all_levels, $sort_by,
                                            $sort_dir, $message_view, $from, $count);

        /* Slice direcly in DB. */
        if ($sort_by != 'message_thread' && $count) {
            $this->_db->setLimit($count, $from);
        }

        $messages = $this->_db->getAssoc($bind[0], null, $bind[1], null, MDB2_FETCHMODE_ASSOC, true);
        if ($messages instanceof PEAR_Error) {
            Horde::logMessage($messages, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $messages;
        }

        $messages = $this->_formatThreads($messages, $sort_by, $message_view, $thread_root);

        $this->_setCache($key, serialize($messages), $thread_root);

        return $messages;
    }

    /**
     * Returns a list of threads.
     *
     * @param string  $forum_owner   Forum owner
     * @param integer $thread_root   Message at which to start the thread.
     *                               If null get all forum threads
     * @param boolean $all_levels    Show all child levels or just one level.
     * @param string  $sort_by       The column by which to sort.
     * @param integer $sort_dir      The direction by which to sort:
     *                                   0 - ascending
     *                                   1 - descending
     * @param boolean $message_view
     * @param string  $from          The thread to start listing at.
     * @param string  $count         The number of threads to return.
     */
    public function getThreadsByForumOwner($forum_owner,
                         $thread_root = 0,
                         $all_levels = false,
                         $sort_by = 'message_modifystamp',
                         $sort_dir = 0,
                         $message_view = false,
                         $from = 0,
                         $count = 0)
    {
        $bind = $this->_buildThreadsQuery($forum_owner, $thread_root, $all_levels,
                                            $sort_by, $sort_dir, $message_view, $from, $count);

        if ($sort_by != 'message_thread' && $count) {
            $this->_db->setLimit($count, $from);
        }

        $messages = $this->_db->getAssoc($bind[0], null, $bind[1], null, MDB2_FETCHMODE_ASSOC, true);
        if ($messages instanceof PEAR_Error) {
            Horde::logMessage($messages, __FILE__, __LINE__, PEAR_LOG_ERR);
            return $messages;
        }

        return $this->_formatThreads($messages, $sort_by, $message_view, $thread_root);
    }

    /**
     * Build threads query.
     *
     * @param string  $forum_owner   Forum owner
     * @param integer $thread_root   Message at which to start the thread.
     *                               If null get all forum threads
     * @param boolean $all_levels    Show all child levels or just one level.
     * @param string  $sort_by       The column by which to sort.
     * @param integer $sort_dir      The direction by which to sort:
     *                                   0 - ascending
     *                                   1 - descending
     * @param boolean $message_view
     * @param string  $from          The thread to start listing at.
     * @param string  $count         The number of threads to return.
     */
    private function _buildThreadsQuery($forum_owner = null,
                         $thread_root = 0,
                         $all_levels = false,
                         $sort_by = 'message_modifystamp',
                         $sort_dir = 0,
                         $message_view = false,
                         $from = 0,
                         $count = 0)
    {
        $params = array();
        $where = '';
        $sql = 'SELECT m.message_id, m.forum_id, m.message_thread, m.parents, m.message_author, '
             . 'm.message_subject, m.message_timestamp, m.locked, m.view_count, '
             . 'm.message_seq, m.attachments';

        if ($message_view) {
            $sql .= ', m.body';
        }

        if ($thread_root == 0) {
            $sql .= ', m.last_message_id, m.last_message_author, m.message_modifystamp AS last_message_timestamp';
        }

        /* Get messages form a specific owner */
        if ($forum_owner !== null) {
            $sql .= ', f.forum_name FROM ' . $this->_threads_table . ' m, ' . $this->_forums_table . ' f';
            $where .= ' AND f.author = ? AND f.forum_id = m.forum_id ';
            $params[] = $forum_owner;
        } else {
            $sql .= ' FROM ' . $this->_threads_table . ' m';
            /* Get messages form a specific forum */
            if ($this->_forum_id) {
                $where .= ' AND m.forum_id = ?';
                $params[] = $this->_forum_id;
            }
        }

        /* Get all levels? */
        if (!$all_levels) {
            $where .= ' AND m.parents = ?';
            $params[] = '';
        }

        /* Get only approved messages. */
        if ($this->_forum['forum_moderated']) {
            $where .= ' AND m.approved = ?';
            $params[] = 1;
        }

        if ($thread_root) {
            $where .= ' AND (message_id = ? OR message_thread = ?)';
            $params[] = (int)$thread_root;
            $params[] = (int)$thread_root;
        }

        /* Sort by result column. */
        $sql .= ' WHERE ' . substr($where, 5) . ' ORDER BY ' . $sort_by . ' ' . ($sort_dir ? 'DESC' : 'ASC');

        return array($sql, $params);
    }
}
