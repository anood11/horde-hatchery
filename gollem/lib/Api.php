<?php
/**
 * Gollem external API interface.
 *
 * This file defines Gollem's external API interface. Other
 * applications can interact with Gollem through this API.
 *
 * @author  Amith Varghese (amith@xalan.com)
 * @author  Michael Slusarz (slusarz@curecanti.org)
 * @author  Ben Klang (bklang@alkaloid.net)
 * @package Gollem
 */
class Gollem_Api extends Horde_Registry_Api
{
    /**
     * Browses through the VFS tree.
     *
     * Each VFS backend is listed as a directory at the top level.  No modify
     * operations are allowed outside any VFS area.
     *
     * @param string $path       The level of the tree to browse.
     * @param array $properties  The item properties to return. Defaults to 'name',
     *                           'icon', and 'browseable'.
     *
     * @return array  The contents of $path.
     */
    public function browse($path = '', $properties = array())
    {
        $path = Gollem::stripAPIPath($path);

        // Default properties.
        if (!$properties) {
            $properties = array('name', 'icon', 'browseable');
        }

        $results = array();
        if ($path == '') {
            // We are at the root of gollem.  Return a set of folders, one for
            // each backend available.
            foreach ($backends as $backend => $curBackend) {
                if (Gollem::checkPermissions('backend', Horde_Perms::SHOW, $backend)) {
                    $results['gollem/' . $backend]['name'] = $curBackend['name'];
                    $results['gollem/' . $backend]['browseable'] = true;
                }
            }
        } else {
            // A file or directory has been requested.

            // Locate the backend_key in the path.
            if (strchr($path, '/')) {
                $backend_key = substr($path, 0, strpos($path, '/'));
            } else {
                $backend_key = $path;
            }

            // Validate and perform permissions checks on the requested backend
            if (!isset($backends[$backend_key])) {
                return PEAR::raiseError(sprintf(_("Invalid backend requested: %s"), $backend_key));
            }
            //if (!Gollem::canAutoLogin($backend_key)) {
            //    // FIXME: Is it possible to request secondary authentication
            //    // credentials here for backends that require it?
            //    return PEAR::raiseError(_("Additional authentication required."));
            //}
            if (!Gollem_Session::createSession($backend_key)) {
                return PEAR::raiseError(_("Unable to create Gollem session"));
            }
            if (!Gollem::checkPermissions('backend', Horde_Perms::READ)) {
                return PEAR::raiseError(_("Permission denied to this backend."));
            }

            // Trim off the backend_key (and '/') to get the VFS relative path
            $fullpath = substr($path, strlen($backend_key) + 1);

            // Get the VFS-standard $name,$path pair
            list($name, $path) = Gollem::getVFSPath($fullpath);

            // Check to see if the request is a file or folder
            if ($GLOBALS['gollem_vfs']->isFolder($path, $name)) {
                // This is a folder request.  Return a directory listing.
                $list = Gollem::listFolder($path . '/' . $name);
                if (is_a($list, 'PEAR_Error')) {
                    return $list;
                }

                // Iterate over the directory contents
                if (is_array($list) && count($list)) {
                    $index = 'gollem/' . $backend_key . '/' . $fullpath;
                    foreach ($list as $key => $val) {
                        $entry = Gollem::pathEncode($index . '/' . $val['name']);
                        $results[$entry]['name'] = $val['name'];
                        $results[$entry]['modified'] = $val['date'];
                        if ($val['type'] == '**dir') {
                            $results[$entry]['browseable'] = true;
                        } else {
                            $results[$entry]['browseable'] = false;
                            $results[$entry]['contentlength'] = $val['size'];
                        }
                    }
                }
            } else {
                // A file has been requested.  Return the contents of the file.

                // Get the file meta-data
                $list = Gollem::listFolder($path);
                $i = false;
                foreach ($list as $key => $file) {
                    if ($file['name'] == $name) {
                        $i = $key;
                        break;
                    }
                }
                if ($i === false) {
                    // File not found
                    return $i;
                }

                // Read the file contents
                $data = $GLOBALS['gollem_vfs']->read($path, $name);
                if (is_a($data, 'PEAR_Error')) {
                    return false;
                }

                // Send the file
                $results['name'] = $name;
                $results['data'] = $data;
                $results['contentlength'] = $list[$i]['size'];
                $results['mtime'] = $list[$i]['date'];
            }
        }

        return $results;
    }

    /**
     * Accepts a file for storage into the VFS
     *
     * @param string $path           Path to store file
     * @param string $content        Contents of file
     * @param string $content_type   MIME type of file
     *
     * @return mixed                 True on success; PEAR_Error on failure
     */
    public function put($path, $content, $content_type)
    {
        // Clean off the irrelevant portions of the path
        $path = Gollem::stripAPIPath($path);

        if ($path == '') {
            // We are at the root of gollem.  Any writes at this level are
            // disallowed.
            return PEAR::raiseError(_("Files must be written inside a VFS backend."));
        } else {
            // We must be inside one of the VFS areas.  Determine which one.
            // Locate the backend_key in the path
            if (strchr($path, '/')) {
                $backend_key = substr($path, 0, strpos($path, '/'));
            } else {
                $backend_key = $path;
            }

            // Validate and perform permissions checks on the requested backend
            if (!isset($backends[$backend_key])) {
                return PEAR::raiseError(sprintf(_("Invalid backend requested: %s"), $backend_key));
            }
            //if (!Gollem::canAutoLogin($backend_key)) {
            //    // FIXME: Is it possible to request secondary authentication
            //    // credentials here for backends that require it?
            //    return PEAR::raiseError(_("Additional authentication required."));
            //}
            if (!Gollem_Session::createSession($backend_key)) {
                return PEAR::raiseError(_("Unable to create Gollem session"));
            }
            if (!Gollem::checkPermissions('backend', Horde_Perms::EDIT)) {
                return PEAR::raiseError(_("Permission denied to this backend."));
            }

            // Trim off the backend_key (and '/') to get the VFS relative path
            $fullpath = substr($path, strlen($backend_key) + 1);

            // Get the VFS-standard $name,$path pair
            list($name, $path) = Gollem::getVFSPath($fullpath);

            return $GLOBALS['gollem_vfs']->writeData($path, $name, $content);
        }
    }

    /**
     * Creates a directory ("collection" in WebDAV-speak) within the VFS
     *
     * @param string $path  Path of directory to create
     *
     * @return mixed  True on success; PEAR_Error on failure
     */
    public function mkcol($path)
    {
        // Clean off the irrelevant portions of the path
        $path = Gollem::stripAPIPath($path);

        if ($path == '') {
            // We are at the root of gollem.  Any writes at this level are
            // disallowed.
            return PEAR::raiseError(_('Folders must be created inside a VFS backend.'));
        } else {
            // We must be inside one of the VFS areas.  Determine which one.
            // Locate the backend_key in the path
            if (!strchr($path, '/')) {
                // Disallow attempts to create a share-level directory.
                return PEAR::raiseError(_('Folders must be created inside a VFS backend.'));
            } else {
                $backend_key = substr($path, 0, strpos($path, '/'));
            }

            // Validate and perform permissions checks on the requested backend
            if (!isset($backends[$backend_key])) {
                return PEAR::raiseError(sprintf(_("Invalid backend requested: %s"), $backend_key));
            }
            //if (!Gollem::canAutoLogin($backend_key)) {
            //    // FIXME: Is it possible to request secondary authentication
            //    // credentials here for backends that require it?
            //    return PEAR::raiseError(_("Additional authentication required."));
            //}
            if (!Gollem_Session::createSession($backend_key)) {
                return PEAR::raiseError(_("Unable to create Gollem session"));
            }
            if (!Gollem::checkPermissions('backend', Horde_Perms::EDIT)) {
                return PEAR::raiseError(_("Permission denied to this backend."));
            }

            // Trim off the backend_key (and '/') to get the VFS relative path
            $fullpath = substr($path, strlen($backend_key) + 1);

            // Get the VFS-standard $name,$path pair
            list($name, $path) = Gollem::getVFSPath($fullpath);

            return $GLOBALS['gollem_vfs']->createFolder($path, $name);
        }
    }

    /**
     * Renames a file or directory
     *
     * @param string $path  Path to source object to be renamed
     * @param string $dest  Path to new name
     *
     * @return mixed  True on success; PEAR_Error on failure
     */
    public function move($path, $dest)
    {
        // Clean off the irrelevant portions of the path
        $path = Gollem::stripAPIPath($path);
        $dest = Gollem::stripAPIPath($dest);

        if ($path == '') {
            // We are at the root of gollem.  Any writes at this level are
            // disallowed.
            return PEAR::raiseError(_('Folders must be created inside a VFS backend.'));
        } else {
            // We must be inside one of the VFS areas.  Determine which one.
            // Locate the backend_key in the path
            if (!strchr($path, '/')) {
                // Disallow attempts to rename a share-level directory.
                return PEAR::raiseError(_('Renaming of backends is not allowed.'));
            } else {
                $backend_key = substr($path, 0, strpos($path, '/'));
            }

            // Ensure that the destination is within the same backend
            if (!strchr($dest, '/')) {
                // Disallow attempts to rename a share-level directory.
                return PEAR::raiseError(_('Renaming of backends is not allowed.'));
            } else {
                $dest_backend_key = substr($path, 0, strpos($path, '/'));
                if ($dest_backend_key != $backend_key) {
                    return PEAR::raiseError(_('Renaming across backends is not supported.'));
                }
            }

            // Validate and perform permissions checks on the requested backend
            if (!isset($backends[$backend_key])) {
                return PEAR::raiseError(sprintf(_("Invalid backend requested: %s"), $backend_key));
            }
            //if (!Gollem::canAutoLogin($backend_key)) {
            //    // FIXME: Is it possible to request secondary authentication
            //    // credentials here for backends that require it?
            //    return PEAR::raiseError(_("Additional authentication required."));
            //}
            if (!Gollem_Session::createSession($backend_key)) {
                return PEAR::raiseError(_("Unable to create Gollem session"));
            }
            if (!Gollem::checkPermissions('backend', Horde_Perms::EDIT)) {
                return PEAR::raiseError(_("Permission denied to this backend."));
            }

            // Trim off the backend_key (and '/') to get the VFS relative path
            $srcfullpath = substr($path, strlen($backend_key) + 1);
            $dstfullpath = substr($dest, strlen($backend_key) + 1);

            // Get the VFS-standard $name,$path pair
            list($srcname, $srcpath) = Gollem::getVFSPath($srcfullpath);
            list($dstname, $dstpath) = Gollem::getVFSPath($dstfullpath);

            return $GLOBALS['gollem_vfs']->rename($srcpath, $srcname, $dstpath, $dstname);
        }
    }

    /**
     * Removes a file or folder from the VFS
     *
     * @param string $path  Path of file or folder to delete
     *
     * @return mixed  True on success; PEAR_Error on failure
     */
    public function path_delete($path)
    {
        // Clean off the irrelevant portions of the path
        $path = Gollem::stripAPIPath($path);

        if ($path == '') {
            // We are at the root of gollem.  Any writes at this level are
            // disallowed.
            return PEAR::raiseError(_("The application folder can not be deleted."));
        } else {
            // We must be inside one of the VFS areas.  Determine which one.
            // Locate the backend_key in the path
            if (strchr($path, '/')) {
                $backend_key = substr($path, 0, strpos($path, '/'));
            } else {
                $backend_key = $path;
            }

            // Validate and perform permissions checks on the requested backend
            if (!isset($backends[$backend_key])) {
                return PEAR::raiseError(sprintf(_("Invalid backend requested: %s"), $backend_key));
            }
            //if (!Gollem::canAutoLogin($backend_key)) {
            //    // FIXME: Is it possible to request secondary authentication
            //    // credentials here for backends that require it?
            //    return PEAR::raiseError(_("Additional authentication required."));
            //}
            if (!Gollem_Session::createSession($backend_key)) {
                return PEAR::raiseError(_("Unable to create Gollem session"));
            }
            if (!Gollem::checkPermissions('backend', Horde_Perms::EDIT)) {
                return PEAR::raiseError(_("Permission denied to this backend."));
            }

            // Trim off the backend_key (and '/') to get the VFS relative path
            $fullpath = substr($path, strlen($backend_key) + 1);

            // Get the VFS-standard $name,$path pair
            list($name, $path) = Gollem::getVFSPath($fullpath);

            // Apparently Gollem::verifyDir() (called by deleteF* next) needs to
            // see a path with a leading '/'
            $path = $backends[$backend_key]['root'] . $path;
            if ($GLOBALS['gollem_vfs']->isFolder($path, $name)) {
                return Gollem::deleteFolder($path, $name);
            } else {
                return Gollem::deleteFile($path, $name);
            }
        }
    }

    /**
     * Returns a link to the gollem file preview interface
     *
     * @param string $dir       File absolute path
     * @param string $file      File basename
     * @param string $backend   Backend key. Defaults to Gollem::getPreferredBackend()
     *
     * @return string  The URL string.
     */
    public function getViewLink($dir, $file, $backend = '')
    {
        if (empty($backend)) {
            $backend = Gollem::getPreferredBackend();
        }

        $url = Horde_Util::addParameter(
            Horde::applicationUrl('view.php'),
            array('actionID' => 'view_file',
            'type' => substr($file, strrpos($file, '.') + 1),
            'file' => $file,
            'dir' => $dir,
            'driver' => $_SESSION['gollem']['backends'][$backend]['driver']));

        return $url;
    }

    /**
     * Creates a link to the gollem file selection window.
     *
     * The file section window will return a cache ID value which should be used
     * (along with the selectListResults and returnFromSelectList functions below)
     * to obtain the data from a list of selected files.
     *
     * There MUST be a form field named 'selectlist_selectid' in the calling
     * form. This field will be populated with the selection ID when the user
     * completes file selection.
     *
     * There MUST be a form parameter named 'actionID' in the calling form.
     * This form will be populated with the value 'selectlist_process' when
     * the user completes file selection.  The calling form will be submitted
     * after the window closes (i.e. the calling form must process the
     * 'selectlist_process' actionID).
     *
     * @param string $link_text   The text to use in the link.
     * @param string $link_style  The style to use for the link.
     * @param string $formid      The formid of the calling script.
     * @param boolean $icon       Create the link with an icon instead of text?
     * @param string $selectid    Selection ID.
     *
     * @return string  The URL string.
     */
    public function selectlistLink($link_text, $link_style, $formid,
                                   $icon = false, $selectid = '')
    {
        $link = Horde::link('#', $link_text, $link_style, '_blank', Horde::popupJs(Horde::applicationUrl('selectlist.php'), array('params' => array('formid' => $formid, 'cacheid' => $selectid), 'height' => 500, 'width' => 300, 'urlencode' => true)) . 'return false;');
        if ($icon) {
            $link_text = Horde::img('gollem.png', $link_text);
        }
        return '<script type="text/javascript">document.write(\''
            . addslashes($link . $link_text) . '<\' + \'/a>\');</script>';
    }

    /**
     * Returns the list of files selected by the user for a given selection ID.
     *
     * @param string $selectid  The selection ID.
     *
     * @param array  An array with each file entry stored in its own array, with
     *               the key as the directory name and the value as the filename.
     */
    public function selectlistResults($selectid)
    {
        if (!isset($_SESSION['gollem']['selectlist'][$selectid]['files'])) {
            return null;
        }

        $list = array();
        foreach ($_SESSION['gollem']['selectlist'][$selectid]['files'] as $val) {
            list($dir, $filename) = explode('|', $val);
            $list[] = array($dir => $filename);
        }

        return $list;
    }

    /**
     * Returns the data for a given selection ID and index.
     *
     * @param string $selectid  The selection ID.
     * @param integer $index    The index of the file data to return.
     *
     * @return string  The file data.
     */
    public function returnFromSelectlist($selectid, $index)
    {
        if (!isset($_SESSION['gollem']['selectlist'][$selectid]['files'][$index])) {
            return null;
        }

        list($dir, $filename) = explode('|', $_SESSION['gollem']['selectlist'][$selectid]['files'][$index]);
        return $GLOBALS['gollem_vfs']->read($dir, $filename);
    }

    /**
     * Sets the files selected for a given selection ID.
     *
     * @param string $selectid  The selection ID to use.
     * @param array $files      An array with each file entry stored in its own
     *                          array, with the key as the directory name and the
     *                          value as the filename.
     *
     * @return string  The selection ID.
     */
    public function setSelectlist($selectid = '', $files = array())
    {
        if (empty($selectid)) {
            $selectid = uniqid(mt_rand(), true);
        }

        if (count($files) > 0) {
            $list = array();
            foreach ($files as $file) {
                $list[] = key($file) . '|' . current($file);
            }
            $_SESSION['gollem']['selectlist'][$selectid]['files'] = $list;
        }

        return $selectid;
    }

}
