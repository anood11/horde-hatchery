<?php
/**
 * ImageView to create the prettythumb view (rounded, shadowed thumbnails).
 *
 * @author Michael J. Rubinsky <mrubinsk@horde.org>
 * @package Ansel
 */
class Ansel_ImageView_prettythumb extends Ansel_ImageView {

    var $need = array('RoundCorners', 'DropShadow');

    function _create()
    {
        $this->_image->_image->resize(min($GLOBALS['conf']['thumbnail']['width'], $this->_dimensions['width']),
                                      min($GLOBALS['conf']['thumbnail']['height'], $this->_dimensions['height']),
                                      true);

        /* Don't bother with these effects for a stack image
         * (which will have a negative gallery_id). */
        if ($this->_image->gallery > 0) {
            if (is_null($this->_style)) {
                $gal = $GLOBALS['ansel_storage']->getGallery($this->_image->gallery);
                $styleDef = $gal->getStyle();
            } else {
                $styleDef = Ansel::getStyleDefinition($this->_style);
            }

            try {
                /* Apply the effects - continue on error, but be sure to log */
                $this->_image->_image->addEffect('RoundCorners', array('border' => 2,
                                                                       'bordercolor' => '#333'));

                $this->_image->_image->addEffect('DropShadow', array('background' => $styleDef['background'],
                                                                     'padding' => 5,
                                                                     'distance' => 5,
                                                                     'fade' => 3));
            } catch (Horde_Image_Exception $e) {
                return false;
            }

            return $this->_image->_image->applyEffects();
        }
    }

}
