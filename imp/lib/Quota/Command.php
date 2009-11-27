<?php
/**
 * Implementation of IMP_Quota API for IMAP servers with a *nix quota command.
 * This requires a modified "quota" command that allows the httpd server
 * account to get quotas for other users. It also requires that your
 * web server and imap server be the same server or at least have shared
 * authentication and file servers (e.g. via NIS/NFS).  And last, it (as
 * written) requires the POSIX PHP extensions.
 *
 * You must configure this driver in horde/imp/config/servers.php.  The
 * driver supports the following parameters:
 *   'quota_path' => Path to the quota binary - REQUIRED
 *   'grep_path'  => Path to the grep binary - REQUIRED
 *   'partition'  => If all user mailboxes are on a single partition, the
 *                   partition label.  By default, quota will determine
 *                   quota information using the user's home directory value.
 *
 * Copyright 2002-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Eric Rostetter <eric.rostetter@physics.utexas.edu>
 * @package IMP_Quota
 */
class IMP_Quota_Command extends IMP_Quota
{
    /**
     * Constructor.
     *
     * @param array $params  Hash containing connection parameters.
     */
    protected function __construct($params = array())
    {
        $params = array_merge(array(
            'quota_path' => 'quota',
            'grep_path'  => 'grep',
            'partition'  => null
        ), $params);

        parent::__construct($params);
    }

    /**
     * Get the disk block size, if possible.
     *
     * We try to find out the disk block size from stat(). If not
     * available, stat() should return -1 for this value, in which
     * case we default to 1024 (for historical reasons). There are a
     * large number of reasons this may fail, such as OS support,
     * SELinux interference, the file being > 2 GB in size, the file
     * we're referring to not being readable, etc.
     *
     * @return integer  The disk block size.
     */
    protected function _blockSize()
    {
        $results = stat(__FILE__);
        return ($results['blksize'] > 1)
            ? $results['blksize']
            : 1024;
    }

    /**
     * Get quota information (used/allocated), in bytes.
     *
     * @return array  An array with the following keys:
     *                'limit' = Maximum quota allowed
     *                'usage' = Currently used portion of quota (in bytes)
     * @throws Horde_Exception
     */
    public function getQuota()
    {
        if (empty($this->_params['partition'])) {
            $passwd_array = posix_getpwnam($_SESSION['imp']['user']);
            list($junk, $search_string, $junk) = explode('/', $passwd_array['dir']);
        } else {
            $search_string = $this->_params['partition'];
        }
        $cmdline = $this->_params['quota_path'] . ' -u ' . $_SESSION['imp']['user'] . ' | ' . $this->_params['grep_path'] . ' ' . $search_string;
        exec($cmdline, $quota_data, $return_code);
        if (($return_code == 0) && (count($quota_data) == 1)) {
            $quota = split("[[:blank:]]+", trim($quota_data[0]));
            $blocksize = $this->_blockSize();
            return array('usage' => $quota[1] * $blocksize,
                        'limit' => $quota[2] * $blocksize);
        }

        throw new Horde_Exception(_("Unable to retrieve quota"), 'horde.error');
    }

}
