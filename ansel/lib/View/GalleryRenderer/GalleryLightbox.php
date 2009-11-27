<?php
/**
 * Ansel_View_GalleryRenderer_GalleryLightbox:: Class wraps display of the lightbox
 * style gallery views.
 *
 * Copyright 2008-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael J. Rubinsky <mrubinsk@horde.org>
 * @package Ansel
 */
class Ansel_View_GalleryRenderer_GalleryLightbox extends Ansel_View_GalleryRenderer_Base
{
    /**
     * Perform any tasks that should be performed before the view is rendered.
     *
     */
    protected function _init()
    {
        if (empty($this->view->image_onclick)) {
            $this->view->image_onclick = 'return lb.start(%i);';
        }

        // Attach the script and CSS files here if we aren't being called via the api
        if (empty($this->view->api)) {
            Ansel::attachStylesheet('lightbox.css');
            Horde::addScriptFile('effects.js', 'horde');
            Horde::addScriptFile('lightbox.js', 'ansel');
            Horde::addScriptFile('popup.js', 'horde');
        }
    }

    /**
     * Get the HTML representing this view.
     *
     * @return string The HTML
     */
    public function html()
    {
        global $conf, $prefs, $registry;

        $galleryOwner = $this->view->gallery->get('owner');
        $id = $this->view->gallery->getOwner();
        $owner = $id->getValue('fullname');
        if (!$owner) {
            $owner = $galleryOwner;
        }

        /* Get JSON data for view */
        // 0 == normal, 1 == by date
        if ($this->mode == 0) {
            $json = $this->view->json(array('full' => !empty($this->view->api)));
        } else {
            if (!empty($this->date['day']) && $this->numTiles) {
                $json = $this->view->json(array('full' => !empty($this->view->api)));
            } else {
                $json = '[]';
            }
        }

        /* Don't bother if we are being called from the api */
        if (empty($this->view->api)) {
            $option_edit = $this->view->gallery->hasPermission(Horde_Auth::getAuth(),
                                                         Horde_Perms::EDIT);
            $option_select = $option_delete = $this->view->gallery->hasPermission(
                Horde_Auth::getAuth(), Horde_Perms::DELETE);
            $option_move = ($option_delete && $GLOBALS['ansel_storage']->countGalleries(Horde_Perms::EDIT));
            $option_copy = ($option_edit && $GLOBALS['ansel_storage']->countGalleries(Horde_Perms::EDIT));
            /* See if we requested a show_actions change (fallback for non-js) */
            if (Horde_Util::getFormData('actionID', '') == 'show_actions') {
                $prefs->setValue('show_actions', (int)!$prefs->getValue('show_actions'));
            }
        }

        /* Set up the pager */
        $date_params = Ansel::getDateParameter(
            array('year' => !empty($this->view->year) ? $this->view->year : 0,
                  'month' => !empty($this->view->month) ? $this->view->month : 0,
                  'day' => !empty($this->view->day) ? $this->view->day : 0));

        $vars = Horde_Variables::getDefaultVariables();
        if (!empty($this->view->page)) {
            $vars->add('page', $this->view->page);
            $page = $this->view->page;
        } else {
            $page = 0;
        }
        if (!empty($this->view->gallery_view_url)) {
            $pagerurl = str_replace(array('%g', '%s'), array($this->galleryId, $this->gallerySlug), urldecode($this->view->gallery_view_url));
            $pagerurl = Horde_Util::addParameter($pagerurl, $date_params, null, false);
        } else {
            /*
             * Build the pager url. Add the needed variables directly to the
             * url instead of passing it as a preserved variable to the pager
             * since the logic to build the URL is already in getUrlFor()
             */
            $pager_params =  array_merge(
                array('gallery' => $this->galleryId,
                      'view' => 'Gallery',
                      'slug' => $this->view->gallery->get('slug')),
                $date_params);
            $pagerurl = Ansel::getUrlfor('view', $pager_params, true);
        }

        if (!empty($this->view->urlCallback)) {
            $callback = $this->view->urlCallback;
        } else {
            $callback = null;
        }
        $params = array('num' => $this->numTiles,
                        'url' => $pagerurl,
                        'perpage' => $this->perpage,
                        'url_callback' => $callback);

        $pager = new Horde_Ui_Pager('page', $vars, $params);

        /* Start buffering */
        ob_start();

        /* Create the js variables to pass to the lightbox script */
        $jsvars = array('graphics_dir' => Horde::applicationUrl($registry->getImageDir(), true, -1),
                        'image_text' => _("Photo"),
                        'of_text' => _("of"),
                        'start_page' => $page);

        $flipped = array_flip($date_params);
        if (count($flipped) == 1 && !empty($flipped[0])) {
            $jsvars['gallery_url'] = $pagerurl . '?';
        } else {
            $jsvars['gallery_url'] = $pagerurl . '&';
        }
        /* Output js/css here if we are calling via the api */
        if (!empty($this->view->api)) {
            Ansel::attachStylesheet('lightbox.css', true);
            $includes = new Horde_Script_Files();
            $includes->_add('accesskeys.js', 'horde', true, true);
            $includes->_add('effects.js', 'horde', true, true);
            $includes->_add('lightbox.js', 'ansel', true, true);
            $includes->includeFiles();
        }

        /* Needed in the template files */
        $tilesperrow = $prefs->getValue('tilesperrow');
        $cellwidth = round(100 / $tilesperrow);
        $count = 0;

        include ANSEL_TEMPLATES . '/view/gallerylightbox.inc';
        return ob_get_clean();
    }

}
