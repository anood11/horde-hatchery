<?php
/**
 * Ansel_Ajax_Imple_GallerySlugCheck:: class for performing Ajax validation of
 * gallery slugs.
 *
 * Copyright 2008-2009 The Horde Project (http://www.horde.org/)
 *
 * @author Michael J. Rubinsky <mrubinsk@horde.org>
 * @package Ansel
 */
class Ansel_Ajax_Imple_GallerySlugCheck extends Horde_Ajax_Imple_Base
{
    public function attach()
    {
        // Include the js
        Horde::addScriptFile('slugcheck.js');

        $url = $this->_getUrl('GallerySlugCheck', 'ansel', array('input' => 'slug'));

        $js = array();
        $js[] = "Ansel.ajax['gallerySlugCheck'] = {slugText:'" . $this->_params['slug'] . "', 'url': '" . $url . "', bindTo: '" . $this->_params['bindTo'] . "'};";
        $js[] = "Event.observe(Ansel.ajax.gallerySlugCheck.bindTo, 'change', checkSlug);";

        Horde::addInlineScript($js, 'dom');
    }

    public function handle($args, $post)
    {
        include_once dirname(__FILE__) . '/../../base.php';
        if (empty($args['input'])) {
            return array('response' => '1');
         }
        $slug = Horde_Util::getPost($args['input']);
        if (empty($slug)) {
            return array('response' => '1');
        }
        $valid = preg_match('/^[a-zA-Z0-9_-]*$/', $slug);
        if (!$valid) {
            return array('response' => '0');
        }

        $exists = $GLOBALS['ansel_storage']->slugExists($slug) ? 0 : 1;
        return array('response' => $exists);
    }

}
