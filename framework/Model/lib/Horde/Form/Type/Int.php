<?php
/**
 * Integer
 */
class Horde_Form_Type_Int extends Horde_Form_Type {

    public function isValid($var, $vars, $value, &$message)
    {
        if ($var->required && empty($value) && ((string)(int)$value !== $value)) {
            $message = _("This field is required.");
            return false;
        }

        if (empty($value) || preg_match('/^[0-9]+$/', $value)) {
            return true;
        }

        $message = _("This field may only contain integers.");
        return false;
    }

}
