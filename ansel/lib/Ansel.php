<?php
/**
 * Ansel Base Class.

  * Copyright 2001-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Michael J. Rubinsky <mrubinsk@horde.org>
 * @package Ansel
 */

/** Horde_Share */
require_once 'Horde/Share.php';

/** Need to bring this in explicitly since we extend the object class */
require_once 'Horde/Share/sql_hierarchical.php';

class Ansel
{
    /**
     * Build initial Ansel javascript object.
     *
     * @return string
     */
    static public function initJSVars()
    {
        $code = array('Ansel = {ajax: {}, widgets: {}}');
        return $code;
    }

    /**
     * Create and initialize the database object.
     *
     * @return mixed MDB2 object || PEAR_Error
     */
    static public function &getDb()
    {
        $config = $GLOBALS['conf']['sql'];
        unset($config['charset']);
        $mdb = MDB2::singleton($config);
        if (is_a($mdb, 'PEAR_Error')) {
            return $mdb;
        }
        $mdb->setOption('seqcol_name', 'id');

        /* Set DB portability options. */
        switch ($mdb->phptype) {
        case 'mssql':
            $mdb->setOption('field_case', CASE_LOWER);
            $mdb->setOption('portability', MDB2_PORTABILITY_FIX_CASE | MDB2_PORTABILITY_ERRORS | MDB2_PORTABILITY_RTRIM | MDB2_PORTABILITY_FIX_ASSOC_FIELD_NAMES);
            break;
        default:
            $mdb->setOption('field_case', CASE_LOWER);
            $mdb->setOption('portability', MDB2_PORTABILITY_FIX_CASE | MDB2_PORTABILITY_ERRORS | MDB2_PORTABILITY_FIX_ASSOC_FIELD_NAMES);
        }

        return $mdb;
    }

    /**
     * Create and initialize the VFS object
     *
     * @return VFS object or fatals on error.
     */
    static public function &getVFS()
    {
        $v_params = Horde::getVFSConfig('images');
        if (is_a($v_params, 'PEAR_Error')) {
            Horde::fatal(_("You must configure a VFS backend to use Ansel."),
                         __FILE__, __LINE__);
        }
        if ($v_params['type'] != 'none') {
            $vfs = VFS::singleton($v_params['type'], $v_params['params']);
        }
        if (empty($vfs) || is_a($vfs, 'PEAR_ERROR')) {
            Horde::fatal(_("You must configure a VFS backend to use Ansel."),
                         __FILE__, __LINE__);
        }

        return $vfs;
    }

    /**
     * Return a string containing an <option> listing of the given
     * gallery array.
     *
     * @param array $selected     The gallery_id of the  gallery that is
     *                            selected by default in the returned option
     *                            list.
     * @param integer $perm       The permissions filter to use.
     * @param mixed $attributes   Restrict the galleries returned to those
     *                            matching $attributes. An array of
     *                            attribute/values pairs or a gallery owner
     *                            username.
     * @param string $parent      The parent share to start listing at.
     * @param integer $from       The gallery to start listing at.
     * @param integer $count      The number of galleries to return.
     * @param integer $ignore     An Ansel_Gallery id to ignore when building
     *                            the tree.
     *
     * @return string  The <option> list.
     */
    static public function selectGalleries($selected = null, $perm = Horde_Perms::SHOW,
                             $attributes = null, $parent = null,
                             $allLevels = true, $from = 0, $count = 0,
                             $ignore = null)
    {
        global $ansel_storage;
        $galleries = $ansel_storage->listGalleries($perm, $attributes, $parent,
                                                   $allLevels, $from, $count);
        $tree = Horde_Tree::factory('gallery_tree', 'select');

        if (!empty($ignore)) {
           unset($galleries[$ignore]);
           if ($selected == $ignore) {
               $selected = null;
           }
        }
        foreach ($galleries as $gallery_id => $gallery) {
            // We don't use $gallery->getParents() on purpose since we
            // only need the count of parents. This potentially saves a number
            // of DB queries.
            $parents = $gallery->get('parents');
            if (empty($parents)) {
                $indents = 0;
            } else {
                $indents = substr_count($parents, ':') + 1;
            }

            $gallery_name = $gallery->get('name');
            $len = Horde_String::length($gallery_name);
            if ($len > 30) {
                $label = Horde_String::substr($gallery_name, 0, 30) . '...';
            } else {
                $label = $gallery_name;
            }

            $params['selected'] = ($gallery_id == $selected);
            $parent = $gallery->getParent();
            $parent = (is_null($parent)) ? $parent : $parent->id;
            if ((!empty($parent) && !empty($galleries[$parent])) ||
                (empty($parent))) {
                $tree->addNode($gallery->id, $parent, $label, $indents, true,
                               $params);
            }
        }

        return $tree->getTree();
    }

    /**
     * Return a link to a photo placeholder, suitable for use in an <img/>
     * tag (or a Horde::img() call, with the path parameter set to * '').
     * This photo should be used as a placeholder if the correct photo can't
     * be retrieved
     *
     * @param string $view  The view ('screen', 'thumb', or 'full') to show.
     *                      Defaults to 'screen'.
     *
     * @return string  The image path.
     */
    static public function getErrorImage($view = 'screen')
    {
        return $GLOBALS['registry']->getImageDir() . '/' . $view . '-error.png';
    }

    /**
     * Return a properly formatted link depending on the global pretty url
     * configuration
     *
     * @param string $controller       The controller to generate a URL for.
     * @param array $data              The data needed to generate the URL.
     * @param boolean $full            Generate a full URL.
     * @param integer $append_session  0 = only if needed, 1 = always,
     *                                 -1 = never.
     *
     * @param string  The generated URL
     */
    static public function getUrlFor($controller, $data, $full = false, $append_session = 0)
    {
        global $prefs;

        $rewrite = isset($GLOBALS['conf']['urls']['pretty']) &&
            $GLOBALS['conf']['urls']['pretty'] == 'rewrite';

        switch ($controller ) {
        case 'view':
            if ($rewrite && (empty($data['special']))) {
                $url = '';

                /* Viewing a List */
                if ($data['view'] == 'List') {
                    if (!empty($data['groupby']) &&
                        $data['groupby'] == 'category' &&
                        empty($data['category']) &&
                        empty($data['special'])) {

                        $data['groupby'] = 'owner';
                    }

                    $groupby = isset($data['groupby'])
                        ? $data['groupby']
                        : $prefs->getValue('groupby');
                    if ($groupby == 'owner' && !empty($data['owner'])) {
                        $url = 'user/' . urlencode($data['owner']) . '/';
                    } elseif ($groupby == 'owner') {
                        $url = 'user/';
                    } elseif ($groupby == 'category' &&
                              !empty($data['category'])) {
                            $url = 'category/' . urlencode($data['category']) . '/';

                    } elseif ($groupby == 'category') {
                        $url = 'category/';
                    } elseif ($groupby == 'none') {
                       $url = 'all/';
                    }

                    // Keep the URL as clean as possible - don't append the page
                    // number if it's zero, which would be the default.
                    if (!empty($data['page'])) {
                        $url = Horde_Util::addParameter($url, 'page', $data['page']);
                    }
                    return Horde::applicationUrl($url, $full, $append_session);
                }

                /* Viewing a Gallery or Image */
                if ($data['view'] == 'Gallery' || $data['view'] == 'Image') {

                    /**
                     * This is needed to correctly generate URLs for images in
                     * places that are not specifically requested by the user,
                     * for instance, in a gallery block. Otherwise, the proper
                     * date variables would not be attached to the url, since we
                     * don't know them ahead of time.  This is a slight hack and
                     * needs to be corrected, probably by delegating at least
                     * some of the URL generation to the gallery/image/view
                     * object...most likely when we move to PHP5.
                     */
                    if (empty($data['year']) && $data['view'] == 'Image') {
                        // Getting these objects is not ideal, but at this point
                        // they should already be locally cached so the cost
                        // is minimized.
                        $i = &$GLOBALS['ansel_storage']->getImage($data['image']);
                        $g = &$GLOBALS['ansel_storage']->getGallery($data['gallery']);
                        if (!is_a($g, 'PEAR_Error') &&
                            !is_a($i, 'PEAR_Error') &&
                            $g->get('view_mode') == 'Date') {

                            $imgDate = new Horde_Date($i->originalDate);
                            $data['year'] = $imgDate->year;
                            $data['month'] = $imgDate->month;
                            $data['day'] = $imgDate->mday;
                        }
                    }

                    $url = 'gallery/'
                        . (!empty($data['slug'])
                           ? $data['slug']
                           : 'id/' . (int)$data['gallery'])
                        . '/';

                    // See comments below about lightbox
                    if ($data['view'] == 'Image' &&
                        (empty($data['gallery_view']) ||
                         (!empty($data['gallery_view']) &&
                         $data['gallery_view'] != 'GalleryLightbox'))) {

                        $url .= (int)$data['image'] . '/';
                    }

                    $extras = array();
                    // We may have a value of zero here, but it's the default,
                    // so ignore it if it's empty.
                    if (!empty($data['havesearch'])) {
                        $extras['havesearch'] = $data['havesearch'];
                    }

                    // Block any auto navigation (for date views)
                    if (!empty($data['force_grouping'])) {
                        $extras['force_grouping'] = $data['force_grouping'];
                    }

                    if (count($extras)) {
                        $url = Horde_Util::addParameter($url, $extras);
                    }

                }

                if ($data['view'] == 'Results')  {
                    $url = 'tag/' . (!empty($data['tag'])
                                     ? urlencode($data['tag']) . '/'
                                     : '');

                    if (!empty($data['actionID'])) {
                        $url = Horde_Util::addParameter($url, 'actionID',
                                                  $data['actionID']);
                    }

                    if (!empty($data['owner'])) {
                        $url = Horde_Util::addParameter($url, 'owner',
                                                  $data['owner']);
                    }
                }

                // Keep the URL as clean as possible - don't append the page
                // number if it's zero, which would be the default.
                if (!empty($data['page'])) {
                    $url = Horde_Util::addParameter($url, 'page', $data['page']);
                }

                if (!empty($data['year'])) {
                    $url = Horde_Util::addParameter($url, array('year' => $data['year'],
                                                          'month' => (empty($data['month']) ? 0 : $data['month']),
                                                          'day' => (empty($data['day']) ? 0 : $data['day'])));
                }

                // If we are using GalleryLightbox, AND we are linking to an
                // image view, append the imageId here to be sure it's at the
                // end of the URL. This is a complete hack, but saves us from
                // having to delegate the URL generation to the view object for
                // now.
                if ($data['view'] == 'Image' &&
                    !empty($data['gallery_view']) &&
                    $data['gallery_view'] == 'GalleryLightbox') {

                    $url .= '#' . $data['image'];
                }

                return Horde::applicationUrl($url, $full, $append_session);
            } else {
                $url = Horde::applicationUrl(
                         Horde_Util::addParameter('view.php', $data),
                         $full,
                         $append_session);

                if ($data['view'] == 'Image' &&
                    !empty($data['gallery_view']) &&
                    $data['gallery_view'] == 'GalleryLightbox') {

                    $url .= '#' . $data['image'];
                }

                return $url;

            }
            break;

        case 'group':
            if ($rewrite) {
                if (empty($data['groupby'])) {
                    $data['groupby'] = $prefs->getValue('groupby');
                }

                if ($data['groupby'] == 'owner') {
                    $url = 'user/';
                }
                if ($data['groupby'] == 'category') {
                    $url = 'category/';
                }
                if ($data['groupby'] == 'none') {
                    $url = 'all/';
                }
                unset($data['groupby']);
                if (count($data)) {
                    $url = Horde_Util::addParameter($url,$data);
                }
                return Horde::applicationUrl($url, $full, $append_session);
            } else {
                return Horde::applicationUrl(
                    Horde_Util::addParameter('group.php', $data),
                    $full,
                    $append_session);
            }
            break;

        case 'rss_user':
            if ($rewrite) {
                $url = 'user/' . urlencode($data['owner']) . '/rss';
                return Horde::applicationUrl($url, $full, $append_session);
            } else {
                return Horde::applicationUrl(
                    Horde_Util::addParameter('rss.php',
                                       array('stream_type' => 'user',
                                             'id' => $data['owner'])),
                    $full, $append_session);
            }
            break;

        case 'rss_gallery':
            if ($rewrite) {
                $id = (!empty($data['slug'])) ? $data['slug'] : 'id/' . (int)$data['gallery'];
                $url = 'gallery/' . $id . '/rss';
                return Horde::applicationUrl($url, $full, $append_session);
            } else {
                return Horde::applicationUrl(
                    Horde_Util::addParameter('rss.php',
                                       array('stream_type' => 'gallery',
                                             'id' => (int)$data['gallery'])),
                    $full, $append_session);
            }
            break;

        case 'default_view':
            switch ($prefs->getValue('defaultview')) {
            case 'browse':
                $url = 'browse.php';
                return Horde::applicationUrl($url, $full, $append_session);
                break;

            case 'galleries':
                $url = Ansel::getUrlFor('view', array('view' => 'List'), true);
                break;

            case 'mygalleries':
            default:
               $url = Ansel::getUrlFor('view',
                                       array('view' => 'List',
                                             'owner' => Horde_Auth::getAuth(),
                                             'groupby' => 'owner'),
                                       true);
               break;
            }
            return $url;
        }
    }

    /**
     * Return a link to an image, suitable for use in an <img/> tag
     * Takes into account $conf['vfs']['direct'] and other
     * factors.
     *
     * @param string $imageId  The id of the image.
     * @param string $view     The view ('screen', 'thumb', 'prettythumb' or
     *                         'full') to show.
     * @param boolean $full    Return a path that includes the server name?
     * @param string $style    Use this gallery style
     *
     * @return string  The image path.
     */
    static public function getImageUrl($imageId, $view = 'screen', $full = false,
                         $style = null)
    {
        global $conf, $ansel_storage;

        // To avoid having to add a new img/* file everytime we add a new
        // thumbstyle, we check for the 'non-prettythumb' views, then route the
        // rest through prettythumb, passing it the style.
        switch ($view) {
        case 'screen':
        case 'full':
        case 'thumb':
        case 'mini':
            // Do nothing.
            break;
        default:
            $view = 'prettythumb';
        }

        if (empty($imageId)) {
            return Ansel::getErrorImage($view);
        }

        // Default to ansel_default since we really only need to know the style
        // if we are requesting a 'prettythumb'
        if (is_null($style)) {
            $style = 'ansel_default';
        }

        // Don't load the image if the view exists
        if ($conf['vfs']['src'] != 'php' &&
            ($viewHash = Ansel_Image::viewExists($imageId, $view, $style)) === false) {
            // We have to make sure the image exists first, since we won't
            // be going through img/*.php to auto-create it.
            try {
                $image = $ansel_storage->getImage($imageId);
            } catch (Horde_Exception $e) {
                Horde::logMessage($e->getMessage(), __FILE__, __LINE__, PEAR_LOG_ERR);
                return Ansel::getErrorImage($view);
            }
            try {
                $image->createView($view, $style, false);
            } catch (Horde_Exception $e) {
                return Ansel::getErrorImage($view);
            }
            $viewHash = $image->getViewHash($view, $style) . '/'
                . $image->getVFSName($view);
        }

        // First check for vfs-direct. If we are not using it, pass this off to
        // the img/*.php files, and check for sendfile support there.
        if ($conf['vfs']['src'] != 'direct') {
            $params = array('image' => $imageId);
            if (!is_null($style)) {
                $params['style'] = $style;
            }
            $url = Horde_Util::addParameter('img/' . $view . '.php', $params);
            return Horde::applicationUrl($url, $full);
        }

        // Using vfs-direct
        $path = substr(str_pad($imageId, 2, 0, STR_PAD_LEFT), -2) . '/'
            . $viewHash;
        if ($full && substr($conf['vfs']['path'], 0, 7) != 'http://') {
            return Horde::url($conf['vfs']['path'] . $path, true, -1);
        } else {
            return $conf['vfs']['path'] . htmlspecialchars($path);
        }
    }

    /**
     * Obtain a Horde_Image object
     *
     * @param array $params  Any additional parameters
     *
     * @return Horde_Image object | PEAR_Error
     */
    static public function getImageObject($params = array())
    {
        global $conf;
        $context = array('tmpdir' => Horde::getTempDir());
        if (!empty($conf['image']['convert'])) {
            $context['convert'] = $conf['image']['convert'];
        }
        $params = array_merge(array('type' => $conf['image']['type'],
                                    'context' => $context),
                              $params);
        //@TODO: get around to updating horde/config/conf.xml to include the imagick driver
        $driver = empty($conf['image']['convert']) ? 'Gd' : 'Im';
        return Horde_Image::factory($driver, $params);
    }

    /**
     * Read an image from the filesystem.
     *
     * @param string $file     The filename of the image.
     * @param array $override  Overwrite the file array with these values.
     *
     * @return array  The image data of the file as an array or PEAR_Error
     */
    static public function getImageFromFile($file, $override = array())
    {
        if (!file_exists($file)) {
            return PEAR::raiseError(sprintf(_("The file \"%s\" doesn't exist."),
                                    $file));
        }

        global $conf;

        // Get the mime type of the file (and make sure it's an image).
        $mime_type = Horde_Mime_Magic::analyzeFile($file, isset($conf['mime']['magic_db']) ? $conf['mime']['magic_db'] : null);
        if (strpos($mime_type, 'image') === false) {
            return PEAR::raiseError(sprintf(_("Can't get unknown file type \"%s\"."), $file));
        }

        $image = array('image_filename' => basename($file),
                       'image_caption' => '',
                       'image_type' => $mime_type,
                       'data' => file_get_contents($file),
                       );

        // Override the array, for example if we're setting the filename to
        // something else.
        if (count($override)) {
            $image = array_merge($image, $override);
        }

        return $image;
    }

    /**
     * Check to see if a particular image manipulation function is
     * available.
     *
     * @param string $feature  The name of the function.
     *
     * @return boolean  True if the function is available.
     */
    static public function isAvailable($feature)
    {
        static $capabilities;

        // If the administrator locked auto watermark on, disable user
        // intervention
        if ($feature == 'text_watermark' &&
            $GLOBALS['prefs']->getValue('watermark_auto') &&
            $GLOBALS['prefs']->isLocked('watermark_auto')) {

            return false;
        }

        if (!isset($capabilities)) {
            $im = Ansel::getImageObject();
            $capabilities = array_merge($im->getCapabilities(),
                                        $im->getLoadedEffects());
        }

        return in_array($feature, $capabilities);
    }

    /**
     * Build Ansel's list of menu items.
     */
    static public function getMenu()
    {
        global $conf, $registry;

        $menu = new Horde_Menu();

        /* Browse/Search */
        $menu->add(Horde::applicationUrl('browse.php'), _("_Browse"),
                   'browse.png', null, null, null,
                   (($GLOBALS['prefs']->getValue('defaultview') == 'browse' &&
                     basename($_SERVER['PHP_SELF']) == 'index.php') ||
                    (basename($_SERVER['PHP_SELF']) == 'browse.php'))
                   ? 'current'
                   : '__noselection');

        $menu->add(Ansel::getUrlFor('view', array('view' => 'List')), _("_Galleries"),
                   'galleries.png', null, null, null,
                   (($GLOBALS['prefs']->getValue('defaultview') == 'galleries' &&
                     basename($_SERVER['PHP_SELF']) == 'index.php') ||
                    ((basename($_SERVER['PHP_SELF']) == 'group.php') &&
                     Horde_Util::getFormData('owner') !== Horde_Auth::getAuth())
                    ? 'current'
                    : '__noselection'));
        if (Horde_Auth::getAuth()) {
            $url = Ansel::getUrlFor('view', array('owner' => Horde_Auth::getAuth(),
                                                  'groupby' => 'owner',
                                                  'view' => 'List'));
            $menu->add($url, _("_My Galleries"), 'mygalleries.png', null, null,
                       null,
                       (Horde_Util::getFormData('owner', false) == Horde_Auth::getAuth())
                       ? 'current' :
                       '__noselection');
        }

        /* Let authenticated users create new galleries. */
        if (Horde_Auth::isAdmin() ||
            (!$GLOBALS['perms']->exists('ansel') && Horde_Auth::getAuth()) ||
            $GLOBALS['perms']->hasPermission('ansel', Horde_Auth::getAuth(), Horde_Perms::EDIT)) {
            $menu->add(Horde::applicationUrl(Horde_Util::addParameter('gallery.php', 'actionID', 'add')),
                       _("_New Gallery"), 'add.png', null, null, null,
                       (basename($_SERVER['PHP_SELF']) == 'gallery.php' &&
                        Horde_Util::getFormData('actionID') == 'add')
                       ? 'current'
                       : '__noselection');
        }

        if ($conf['faces']['driver'] && Horde_Auth::isAuthenticated()) {
            $menu->add(Horde::applicationUrl('faces/search/all.php'), _("_Faces"), 'user.png', $registry->getImageDir('horde'));
        }

        /* Print. */
        if ($conf['menu']['print'] && ($pl = Horde_Util::nonInputVar('print_link'))) {
            $menu->add($pl, _("_Print"), 'print.png',
                       $registry->getImageDir('horde'), '_blank',
                       Horde::popupJs($pl, array('urlencode' => true)) . 'return false;');
        }

        return $menu;
    }

    /**
     * Generate a list of breadcrumbs showing where we are in the gallery
     * tree.
     */
    static public function getBreadCrumbs($separator = ' &raquo; ', $gallery = null)
    {
        global $prefs, $ansel_storage;

        $groupby = Horde_Util::getFormData('groupby', $prefs->getValue('groupby'));
        $owner = Horde_Util::getFormData('owner');
        $image_id = (int)Horde_Util::getFormData('image');
        $actionID = Horde_Util::getFormData('actionID');
        $page = Horde_Util::getFormData('page', 0);
        $haveSearch = Horde_Util::getFormData('havesearch', 0);

        if (is_null($gallery)) {
            $gallery_id = (int)Horde_Util::getFormData('gallery');
            $gallery_slug = Horde_Util::getFormData('slug');
            if (!empty($gallery_slug)) {
                $gallery = $ansel_storage->getGalleryBySlug($gallery_slug);
            } elseif (!empty($gallery_id)) {
                $gallery = $ansel_storage->getGallery($gallery_id);
            }
        }

        if (is_a($gallery, 'PEAR_Error')) {
            $gallery = null;
        }

        if ($gallery) {
            $owner = $gallery->get('owner');
        }

        if (!empty($image_id)) {
            $image = &$ansel_storage->getImage($image_id);
            if (empty($gallery) && !is_a($image, 'PEAR_Error')) {
                $gallery = $ansel_storage->getGallery($image->gallery);
            }
        }
        if (isset($gallery) && !is_a($gallery, 'PEAR_Error')) {
            $owner = $gallery->get('owner');
        }
        if (!empty($owner)) {
            if ($owner == Horde_Auth::getAuth()) {
                $owner_title = _("My Galleries");
            } elseif (!empty($GLOBALS['conf']['gallery']['customlabel'])) {
                $uprefs = Horde_Prefs::singleton($GLOBALS['conf']['prefs']['driver'],
                                           'ansel',
                                           $owner, '', null, false);
                $fullname = $uprefs->getValue('grouptitle');
                if (!$fullname) {
                    $identity = Horde_Prefs_Identity::singleton('none', $owner);
                    $fullname = $identity->getValue('fullname');
                    if (!$fullname) {
                        $fullname = $owner;
                    }
                    $owner_title = sprintf(_("%s's Galleries"), $fullname);
                } else {
                    $owner_title = $fullname;
                }
            } else {
                $owner_title = sprintf(_("%s's Galleries"), $owner);
            }
        }

        // Construct the breadcrumbs backward, from where we are now up through
        // the path back to the top.  By constructing it backward we can treat
        // the last element (the current page) specially.
        $levels = 0;
        $nav = '</span>';
        $urlFlags = array('havesearch' => $haveSearch,
                          'force_grouping' => true);

        // Check for an active image
        if (!empty($image_id) && !is_a($image, 'PEAR_Error')) {
            $text = '<span class="thiscrumb" id="PhotoName">' . htmlspecialchars($image->filename, ENT_COMPAT, Horde_Nls::getCharset()) . '</span>';
            $nav = $separator . $text . $nav;
            $levels++;
        }

        if ($gallery) {
            $trails = $gallery->getGalleryCrumbData();
            foreach ($trails as $trail) {
                $title = $trail['title'];
                $navdata = $trail['navdata'];
                if ($levels++ > 0) {
                    if ((empty($image_id) && $levels == 1) ||
                        (!empty($image_id) && $levels == 2)) {
                        $urlParameters = array_merge($urlFlags, array('page' => $page));
                    } else {
                        $urlParameters = $urlFlags;
                    }
                    $nav = $separator . Horde::link(Ansel::getUrlFor('view', array_merge($navdata, $urlParameters))) . $title . '</a>' . $nav;
                } else {
                    $nav = $separator . '<span class="thiscrumb">' . $title . '</span>' . $nav;
                }
            }
        }

        if (!empty($owner_title)) {
            $owner_title = htmlspecialchars($owner_title, ENT_COMPAT, Horde_Nls::getCharset());
            $levels++;
            if ($gallery) {
                $nav = $separator . Horde::link(Ansel::getUrlFor('view', array('view' => 'List', 'groupby' => 'owner', 'owner' => $owner, 'havesearch' => $haveSearch))) . $owner_title . '</a>' . $nav;
            } else {
                $nav = $separator . $owner_title . $nav;
            }
        }

        if ($haveSearch == 0) {
            $text = _("Galleries");
            $link = Horde::link(Ansel::getUrlFor('view', array('view' => 'List')));
        } else {
            $text = _("Browse Tags");
            $link = Horde::link(Ansel::getUrlFor('view', array('view' => 'Results'), true));
        }
        if ($levels > 0) {
            $nav = $link . $text . '</a>' . $nav;
        } else {
            $nav = $text . $nav;
        }

        $nav = '<span class="breadcrumbs">' . $nav;

        return $nav;
    }

    /**
     * Build a HTML <select> element containing all the available
     * gallery styles.
     *
     * @param string $element_name  The element's id/name attribute.
     * @param string $selected      Mark this element as currently selected.
     *
     * @return string  The HTML for the <select> element.
     */
    static public function getStyleSelect($element_name, $selected = '')
    {
        $styles = Horde::loadConfiguration('styles.php', 'styles', 'ansel');

        /* No prettythumbs allowed at all by admin choice */
        if (empty($GLOBALS['conf']['image']['prettythumbs'])) {
            $test = $styles;
            foreach ($test as $key => $style) {
                if ($style['thumbstyle'] != 'thumb') {
                    unset($styles[$key]);
                }
            }
        }

        /* Build the available styles, but don't show hidden styles */
        foreach ($styles as $key => $style) {
            if (empty($style['hide'])) {
                $options[$key] = $style['title'];
            }
        }

        /* Nothing explicitly selected, use the global pref */
        if ($selected == '') {
            $selected = $GLOBALS['prefs']->getValue('default_gallerystyle');
        }

        $html = '<select id="' . $element_name . '" name="' . $element_name . '">';
        foreach ($options as $key => $option) {
            $html .= '  <option value="' . $key . '"' . (($selected == $key) ? 'selected="selected"' : '') . '>' . $option . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    /**
     * Get an array of all currently viewable styles.
     */
    static public function getAvailableStyles()
    {
        /* Brings in the $styles array in this scope only */
        $styles = Horde::loadConfiguration('styles.php', 'styles', 'ansel');

        /* No prettythumbs allowed at all by admin choice */
        if (empty($GLOBALS['conf']['image']['prettythumbs'])) {
            $test = $styles;
            foreach ($test as $key => $style) {
                if ($style['thumbstyle'] != 'thumb') {
                    unset($styles[$key]);
                }
            }
        }

        /* Check if the browser / server has png support */
        if ($GLOBALS['browser']->hasQuirk('png_transparency') ||
            $GLOBALS['conf']['image']['type'] != 'png') {

            $test = $styles;
            foreach ($test as $key => $style) {
                if (!empty($style['requires_png'])) {
                    if (!empty($style['fallback'])) {
                        $styles[$key] = $styles[$style['fallback']];
                    } else {
                        unset($styles[$key]);
                    }
                }
            }
        }
        return $styles;
    }

    /**
     * Get a style definition for the requested named style
     *
     * @param string $style  The name of the style to fetch
     *
     * @return array  The definition of the requested style if it's available
     *                otherwise, the ansel_default style is returned.
     */
    static public function getStyleDefinition($style)
    {
        if (isset($GLOBALS['ansel_styles'][$style])) {
            $style_def = $GLOBALS['ansel_styles'][$style];
        } else {
            $style_def = $GLOBALS['ansel_styles']['ansel_default'];
        }

        /* Fill in defaults */
        if (empty($style_def['gallery_view'])) {
            $style_def['gallery_view'] = 'Gallery';
        }
        if (empty($style_def['default_galleryimage_type'])) {
            $style_def['default_galleryimage_type'] = 'plain';
        }
        if (empty($style_def['requires_png'])) {
            $style_def['requires_png'] = false;
        }

        return $style_def;
    }

    /**
     * Return a hash key for the given view and style.
     *
     * @param string $view   The view (thumb, prettythumb etc...)
     * @param string $style  The named style.
     *
     * @return string  A md5 hash suitable for use as a key.
     */
    static public function getViewHash($view, $style)
    {
        $style = Ansel::getStyleDefinition($style);

        if ($view != 'screen' && $view != 'thumb' && $view != 'mini' &&
            $view != 'full') {

            $view = md5($style['thumbstyle'] . '.' . $style['background']);
        }

        return $view;
    }

    /**
     * Add a custom stylesheet to the current page. Need our own implementation
     * since we want to be able to ouput specific CSS files at specific times
     * (like when rendering embedded content, or calling via the api etc...).
     *
     * @param string $stylesheet  The stylesheet to add. A path relative
     *                            to $themesfs
     * @param boolean $link       Immediately output the CSS link
     */
    static public function attachStylesheet($stylesheet, $link = false)
    {
       $GLOBALS['ansel_stylesheets'][] = $stylesheet;
       if ($link) {
           Ansel::stylesheetLinks(true);
       }
    }

    /**
     * Output the stylesheet links
     *
     * @param boolean $custom_only  Don't include ansel's base CSS file
     */
    static public function stylesheetLinks($custom_only = false)
    {
        /* Custom CSS */
        $themesuri = $GLOBALS['registry']->get('themesuri', 'ansel');
        $themesfs = $GLOBALS['registry']->get('themesfs', 'ansel');
        $css = array();
        if (!empty($GLOBALS['ansel_stylesheets'])) {
            foreach ($GLOBALS['ansel_stylesheets'] as $css_file) {
                $css[] = array('u' => Horde::applicationUrl($themesuri . '/' . $css_file, true),
                               'f' => $themesfs . '/' . $css_file);
            }
        }

        /* Use Horde's stylesheet code if we aren't ouputting css directly */
        if (!$custom_only) {
            Horde::includeStylesheetFiles(array('additional' => $css));
        } else {
            foreach ($css as $file) {
                echo '<link href="' . $file['u']
                     . '" rel="stylesheet" type="text/css"'
                     . (isset($file['m']) ? ' media="' . $file['m'] . '"' : '')
                     . ' />' . "\n";
            }
        }
    }

    /**
     * Get a date parts array containing only enough date parts for the depth
     * we are at. If an empty array is passed, attempt to get the parts from
     * url parametrs. Any missing date parts must be set to 0.
     *
     * @param array $date  A full date parts array or an empty array.
     *
     * @return A trimmed down (if necessary) date parts array.
     */
    static public function getDateParameter($date = array())
    {
        if (!count($date)) {
            $date = array(
                'year' => Horde_Util::getFormData('year', 0),
                'month' => Horde_Util::getFormData('month', 0),
                'day' => Horde_Util::getFormData('day', 0));
        }
        $return = array();
        $return['year'] = !empty($date['year']) ? $date['year'] : 0;
        $return['month'] = !empty($date['month']) ? $date['month'] : 0;
        $return['day'] = !empty($date['day']) ? $date['day'] : 0;

        return $return;
    }

    /**
     * Downloads all requested images as a zip file.  Assumes all permissions
     * have been checked on the requested resource.
     *
     * @param array $gallery
     * @param array $images
     */
    static public function downloadImagesAsZip($gallery = null, $images = array())
    {

        if (empty($GLOBALS['conf']['gallery']['downloadzip'])) {
            $GLOBALS['notification']->push(_("Downloading zip files is not enabled. Talk to your server administrator."));
            header('Location: ' . Horde::applicationUrl('view.php?view=List', true));
            exit;
        }

        /* Requested a gallery */
        if (!is_null($gallery)) {
            /* We can name the zip file with the slug if we have it */
            $slug = $gallery->get('slug');

            /* Set the date in case we are viewing in date mode */
            $gallery->setDate(Ansel::getDateParameter());

            /*
             * More efficeint to get the images and then see how many instead of calling
             * countImages() and then getting the images.
             */
            $images = $gallery->listImages();
        }

        /* At this point, we should always have a list of images */
        if (!count($images)) {
            $notification->push(sprintf(_("There are no photos in %s to download."),
                                $gallery->get('name')), 'horde.message');
            header('Location: ' . Horde::applicationUrl('view.php?view=List', true));
            exit;
        }

        // Try to close off the current session to avoid locking it while the
        // gallery is downloading.
        @session_write_close();

        if (!is_null($gallery)) {
            // Check full photo permissions
            if ($gallery->canDownload()) {
                $view = 'full';
            } else {
                $view = 'screen';
            }
        }

        $zipfiles = array();
        foreach ($images as $id) {
            $image = &$GLOBALS['ansel_storage']->getImage($id);
            if (!is_a($image, 'PEAR_Error')) {
                // If we didn't select an entire gallery, check the download
                // size for each image.
                if (!isset($view)) {
                    $g = $GLOBALS['ansel_storage']->getGallery($image->gallery);
                    $v = $g->canDownload() ? 'full' : 'screen';
                } else {
                    $v = $view;
                }

                $zipfiles[] = array('data' => $image->raw($v),
                                    'name' => $image->filename);
            }
        }

        $zip = Horde_Compress::factory('zip');
        $body = $zip->compress($zipfiles);
        if (!empty($gallery)) {
            $filename = (!empty($slug) ? $slug : $gallery->id) . '.zip';
        } else {
            $filename = 'Ansel.zip';
        }
        $GLOBALS['browser']->downloadHeaders($filename, 'application/zip', false,
                                  strlen($body));
        echo $body;
        exit;
    }

    /**
     * Generate the JS necessary to embed a gallery / images into another
     * external site.
     *
     * @param array $options  The options to build the view.
     *
     * @return string  The javascript
     */
    static public function embedCode($options)
    {
        if (empty($options['container'])) {
            $domid = md5(uniqid());
            $options['container'] = $domid;
        } else {
            $domid = $options['container'];
        }

        $imple = Horde_Ajax_Imple::factory(array('ansel', 'Embed'), $options);
        $src = $imple->getUrl();

       return '<script type="text/javascript" src="' . $src . '"></script><div id="' . $domid . '"></div>';
    }

}
