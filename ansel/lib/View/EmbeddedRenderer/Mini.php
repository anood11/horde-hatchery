<?php
/**
 * Ansel_View_EmbeddedRenderer_Mini
 *
 * Copyright 2008-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael J. Rubinsky <mrubinsk@horde.org>
 * @package Ansel
 */
class Ansel_View_EmbeddedRenderer_Mini extends Ansel_View_Gallery
{
    /**
     * Build the javascript that will render the view.
     *
     * @return string  A string containing valid javascript.
     */
    public function html()
    {
        /* Required */
        $node = $this->_params['container'];
        if (empty($node)) {
            return '';
        }

        /* Optional */
        $gallery_slug = !empty($this->_params['gallery_slug']) ? $this->_params['gallery_slug'] : '';
        $gallery_id = !empty($this->_params['gallery_id']) ? $this->_params['gallery_id']: null;

        $start = (isset($this->_params['start'])) ? $this->_params['start'] : 0;
        $count = (isset($this->_params['count'])) ? $this->_params['count'] : 0;
        $perpage = (isset($this->_params['perpage'])) ? $this->_params['perpage'] : 0;
        $thumbsize = (!empty($this->_params['thumbsize'])) ? $this->_params['thumbsize'] : 'mini';
        // Limit to these image views only.
        if ($thumbsize != 'mini' && $thumbsize != 'thumb' &&
            $thumbsize != 'prettythumb' && $thumbsize != 'screen') {

             $thumbsize = 'mini';
        }

        /* An image list instead of a gallery? */
        $images = (!empty($this->_params['images'])) ? $this->_params['images'] : array();
        if (!empty($images)) {
            // Images are filtered for age and password protected galleries
            // in the ::getImageJson() call since they could all be from different
            // galleries.
            $images = explode(':', $images);
        } else {
            try {
                $this->gallery = $this->_getGallery($gallery_id, $gallery_slug);
            } catch (Horde_Exception $e) {
                Horde::logMessage($e->getMessage(), __FILE__, __LINE__, PEAR_LOG_ERR);
                exit;
            }

            // We don't allow age restricted or password locked galleries to be
            // viewed via the mini embedded view since it shows *all* the images
            if (!$this->gallery->hasPermission(Horde_Auth::getAuth(), Horde_Perms::READ) ||
                !$this->gallery->isOldEnough() ||
                $this->gallery->hasPasswd()) {

                return '';
            }
        }

        if (empty($images)) {
            $json = $this->json(array('full' => true,
                                      'from' => $start,
                                      'count' => $count,
                                      'image_view' => $thumbsize,
                                      'view_links' => true));
            $json_full = $this->json(array('full' => true,
                                           'from' => $start,
                                           'count' => $count,
                                           'view_links' => true));
        } else {
            $json = $GLOBALS['ansel_storage']->getImageJson($images, null, true, $thumbsize, true);
            $json_full = $GLOBALS['ansel_storage']->getImageJson($images, null, true, 'screen', true);
        }

        /* Some paths */
        $cssurl = Horde::url($GLOBALS['registry']->get('themesuri', 'ansel') . '/embed.css', true);
        $hcssurl = Horde::url($GLOBALS['registry']->get('themesuri', 'horde') . '/embed.css', true);
        $js_path = $GLOBALS['registry']->get('jsuri', 'horde');
        $pturl = Horde::url($js_path . '/prototype.js', true);
        $hjsurl = Horde::url($js_path . '/tooltips.js', true);
        $ansel_js_path = $GLOBALS['registry']->get('jsuri', 'ansel');
        $jsurl = Horde::url($ansel_js_path . '/embed.js', true);
        $hideLinks = (bool)!empty($this->_params['hidelinks']);

        /* Lightbox specific URLs */
        if (!empty($this->_params['lightbox'])) {
            $effectsurl = Horde::url($js_path . '/effects.js', true);
            $lbjsurl = Horde::url($ansel_js_path . '/lightbox.js', true);
            $lbcssurl = Horde::url($GLOBALS['registry']->get('themesuri', 'ansel') . '/lightbox.css', true);
        }

        /* Start building the javascript */
        $html = <<<EOT
            //<![CDATA[
            // Old fashioned way to play nice with Safari 2 (Adding script inline with the
            // DOM won't work).  Need two seperate files output here since the incldued
            // files don't seem to be parsed until after the entire page is loaded, so we
            // can't include prototype on the same page it's needed.

            if (typeof anseljson == 'undefined') {
                if (typeof Prototype == 'undefined') {
                    document.write('<script type="text/javascript" src="$pturl"></script>');
                }
                if (typeof Horde_ToolTips == 'undefined') {
                    document.write('<script type="text/javascript" src="$hjsurl"></script>');
                    document.write('<link type="text/css" rel="stylesheet" href="$hcssurl" />');
                }

                anselnodes = new Array();
                anseljson = new Object();
                document.write('<link type="text/css" rel="stylesheet" href="$cssurl" />');
                document.write('<script type="text/javascript" src="$jsurl"></script>');
            }
            anselnodes[anselnodes.length] = '$node';
            anseljson['$node'] = new Object();
            anseljson['$node']['data'] = $json;
            anseljson['$node']['perpage'] = $perpage;
            anseljson['$node']['page'] = 0;
            anseljson['$node']['hideLinks'] = '$hideLinks';
            //]]>

EOT;
            /* Special requirements for lightbox */
            if (!empty($lbjsurl)) {
                $graphic_dir = Horde::applicationUrl($GLOBALS['registry']->getImageDir(), true, -1);
                $imageText = _("Photo");
                $labelOf = _("of");
                $html .= <<<EOT
                if (typeof Effect == 'undefined') {
                    document.write('<script type="text/javascript" src="$effectsurl"></script>');
                }

                /* Make sure we only include this stuff once */
                if (typeof lbOptions == 'undefined') {

                    document.write('<script type="text/javascript" src="$lbjsurl"></script>');
                    document.write('<link type="text/css" rel="stylesheet" href="$lbcssurl" />');

                    lbOptions = {
                        fileLoadingImage: '$graphic_dir/lightbox/loading.gif',
                        fileBottomNavCloseImage: '$graphic_dir/lightbox/closelabel.gif',
                        overlayOpacity: 0.8,
                        animate: true,
                        resizeSpeed: 7,
                        borderSize: 10,
                        labelImage: '$imageText',
                        labelOf: '$labelOf',
                        returnURL: '#',
                        startPage: 0
                    }
                }
                anseljson['$node']['lightbox'] = $json_full;
EOT;
        }
        return $html;
    }

}
