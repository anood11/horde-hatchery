<?php
/**
 * Horde_Form_Type Class
 *
 * @author  Robert E. Coyle <robertecoyle@hotmail.com>
 * @package Horde_Form
 */
class Horde_Form_Type {

    function Horde_Form_Type()
    {
    }

    function getProperty($property)
    {
        $prop = '_' . $property;
        return isset($this->$prop) ? $this->$prop : null;
    }

    function __get($property)
    {
        return $this->getProperty($property);
    }

    function setProperty($property, $value)
    {
        $prop = '_' . $property;
        $this->$prop = $value;
    }

    function __set($property, $value)
    {
        return $this->setProperty($property, $value);
    }

    function init()
    {
    }

    function onSubmit()
    {
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        $message = '<strong>Error:</strong> Horde_Form_Type::isValid() called - should be overridden<br />';
        return false;
    }

    function getTypeName()
    {
        return str_replace('horde_form_type_', '', Horde_String::lower(get_class($this)));
    }

    function getValues()
    {
        return null;
    }

    function getInfo(&$vars, &$var, &$info)
    {
        $info = $var->getValue($vars);
    }

}

class Horde_Form_Type_spacer extends Horde_Form_Type {

    function isValid(&$var, &$vars, $value, &$message)
    {
        return true;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Spacer"));
    }

}

class Horde_Form_Type_header extends Horde_Form_Type {

    function isValid(&$var, &$vars, $value, &$message)
    {
        return true;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Header"));
    }

}

class Horde_Form_Type_description extends Horde_Form_Type {

    function isValid(&$var, &$vars, $value, &$message)
    {
        return true;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Description"));
    }

}

/**
 * Simply renders its raw value in both active and inactive rendering.
 */
class Horde_Form_Type_html extends Horde_Form_Type {

    function isValid(&$var, &$vars, $value, &$message)
    {
        return true;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("HTML"));
    }

}

class Horde_Form_Type_number extends Horde_Form_Type {

    var $_fraction;

    function init($fraction = null)
    {
        $this->_fraction = $fraction;
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        if ($var->isRequired() && empty($value) && ((string)(double)$value !== $value)) {
            $message = _("This field is required.");
            return false;
        } elseif (empty($value)) {
            return true;
        }

        /* If matched, then this is a correct numeric value. */
        if (preg_match($this->_getValidationPattern(), $value)) {
            return true;
        }

        $message = _("This field must be a valid number.");
        return false;
    }

    function _getValidationPattern()
    {
        static $pattern = '';
        if (!empty($pattern)) {
            return $pattern;
        }

        /* Get current locale information. */
        $linfo = Horde_Nls::getLocaleInfo();

        /* Build the pattern. */
        $pattern = '(-)?';

        /* Only check thousands separators if locale has any. */
        if (!empty($linfo['mon_thousands_sep'])) {
            /* Regex to check for correct thousands separators (if any). */
            $pattern .= '((\d+)|((\d{0,3}?)([' . $linfo['mon_thousands_sep'] . ']\d{3})*?))';
        } else {
            /* No locale thousands separator, check for only digits. */
            $pattern .= '(\d+)';
        }
        /* If no decimal point specified default to dot. */
        if (empty($linfo['mon_decimal_point'])) {
            $linfo['mon_decimal_point'] = '.';
        }
        /* Regex to check for correct decimals (if any). */
        if (empty($this->_fraction)) {
            $fraction = '*';
        } else {
            $fraction = '{0,' . $this->_fraction . '}';
        }
        $pattern .= '([' . $linfo['mon_decimal_point'] . '](\d' . $fraction . '))?';

        /* Put together the whole regex pattern. */
        $pattern = '/^' . $pattern . '$/';

        return $pattern;
    }

    function getInfo(&$vars, &$var, &$info)
    {
        $value = $vars->get($var->getVarName());
        $linfo = Horde_Nls::getLocaleInfo();
        $value = str_replace($linfo['mon_thousands_sep'], '', $value);
        $info = str_replace($linfo['mon_decimal_point'], '.', $value);
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Number"));
    }

}

class Horde_Form_Type_int extends Horde_Form_Type {

    function isValid(&$var, &$vars, $value, &$message)
    {
        if ($var->isRequired() && empty($value) && ((string)(int)$value !== $value)) {
            $message = _("This field is required.");
            return false;
        }

        if (empty($value) || preg_match('/^[0-9]+$/', $value)) {
            return true;
        }

        $message = _("This field may only contain integers.");
        return false;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Integer"));
    }

}

class Horde_Form_Type_octal extends Horde_Form_Type {

    function isValid(&$var, &$vars, $value, &$message)
    {
        if ($var->isRequired() && empty($value) && ((string)(int)$value !== $value)) {
            $message = _("This field is required.");
            return false;
        }

        if (empty($value) || preg_match('/^[0-7]+$/', $value)) {
            return true;
        }

        $message = _("This field may only contain octal values.");
        return false;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Octal"));
    }

}

class Horde_Form_Type_intlist extends Horde_Form_Type {

    function isValid(&$var, &$vars, $value, &$message)
    {
        if (empty($value) && $var->isRequired()) {
            $message = _("This field is required.");
            return false;
        }

        if (empty($value) || preg_match('/^[0-9 ,]+$/', $value)) {
            return true;
        }

        $message = _("This field must be a comma or space separated list of integers");
        return false;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Integer list"));
    }

}

class Horde_Form_Type_text extends Horde_Form_Type {

    var $_regex;
    var $_size;
    var $_maxlength;

    /**
     * The initialisation function for the text variable type.
     *
     * @access private
     *
     * @param string $regex       Any valid PHP PCRE pattern syntax that
     *                            needs to be matched for the field to be
     *                            considered valid. If left empty validity
     *                            will be checked only for required fields
     *                            whether they are empty or not.
     *                            If using this regex test it is advisable
     *                            to enter a description for this field to
     *                            warn the user what is expected, as the
     *                            generated error message is quite generic
     *                            and will not give any indication where
     *                            the regex failed.
     * @param integer $size       The size of the input field.
     * @param integer $maxlength  The max number of characters.
     */
    function init($regex = '', $size = 40, $maxlength = null)
    {
        $this->_regex     = $regex;
        $this->_size      = $size;
        $this->_maxlength = $maxlength;
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        $valid = true;

        if (!empty($this->_maxlength) && Horde_String::length($value) > $this->_maxlength) {
            $valid = false;
            $message = sprintf(_("Value is over the maximum length of %d."), $this->_maxlength);
        } elseif ($var->isRequired() && empty($this->_regex)) {
            $valid = strlen(trim($value)) > 0;

            if (!$valid) {
                $message = _("This field is required.");
            }
        } elseif (!empty($this->_regex)) {
            $valid = preg_match($this->_regex, $value);

            if (!$valid) {
                $message = _("You must enter a valid value.");
            }
        }

        return $valid;
    }

    function getSize()
    {
        return $this->_size;
    }

    function getMaxLength()
    {
        return $this->_maxlength;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("Text"),
            'params' => array(
                'regex'     => array('label' => _("Regex"),
                                     'type'  => 'text'),
                'size'      => array('label' => _("Size"),
                                     'type'  => 'int'),
                'maxlength' => array('label' => _("Maximum length"),
                                     'type'  => 'int')));
    }

}

class Horde_Form_Type_stringlist extends Horde_Form_Type_text {

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("String list"),
            'params' => array(
                'regex'     => array('label' => _("Regex"),
                                     'type'  => 'text'),
                'size'      => array('label' => _("Size"),
                                     'type'  => 'int'),
                'maxlength' => array('label' => _("Maximum length"),
                                     'type'  => 'int')),
        );
    }

}

/**
 * @since Horde 3.3
 */
class Horde_Form_Type_stringarray extends Horde_Form_Type_stringlist {

    function getInfo(&$vars, &$var, &$info)
    {
        $info = array_map('trim', explode(',', $vars->get($var->getVarName())));
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("String list returning an array"),
            'params' => array(
                'regex'     => array('label' => _("Regex"),
                                     'type'  => 'text'),
                'size'      => array('label' => _("Size"),
                                     'type'  => 'int'),
                'maxlength' => array('label' => _("Maximum length"),
                                     'type'  => 'int')),
        );
    }

}

/**
 * @since Horde 3.2
 */
class Horde_Form_Type_phone extends Horde_Form_Type {

    function isValid(&$var, &$vars, $value, &$message)
    {
        if (!strlen(trim($value))) {
            if ($var->isRequired()) {
                $message = _("This field is required.");
                return false;
            }
        } elseif (!preg_match('/^\+?[\d()\-\/. ]*$/', $value)) {
            $message = _("You must enter a valid phone number, digits only with an optional '+' for the international dialing prefix.");
            return false;
        }

        return true;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Phone number"));
    }

}

class Horde_Form_Type_cellphone extends Horde_Form_Type_phone {

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Mobile phone number"));
    }

}

class Horde_Form_Type_ipaddress extends Horde_Form_Type_text {

    function isValid(&$var, &$vars, $value, &$message)
    {
        $valid = true;

        if (strlen(trim($value)) > 0) {
            $ip = explode('.', $value);
            $valid = (count($ip) == 4);
            if ($valid) {
                foreach ($ip as $part) {
                    if (!is_numeric($part) ||
                        $part > 255 ||
                        $part < 0) {
                        $valid = false;
                        break;
                    }
                }
            }

            if (!$valid) {
                $message = _("Please enter a valid IP address.");
            }
        } elseif ($var->isRequired()) {
            $valid = false;
            $message = _("This field is required.");
        }

        return $valid;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("IP address"));
    }

}

class Horde_Form_Type_longtext extends Horde_Form_Type_text {

    var $_rows;
    var $_cols;
    var $_helper = array();

    function init($rows = 8, $cols = 80, $helper = array())
    {
        if (!is_array($helper)) {
            $helper = array($helper);
        }

        $this->_rows = $rows;
        $this->_cols = $cols;
        $this->_helper = $helper;
    }

    function getRows()
    {
        return $this->_rows;
    }

    function getCols()
    {
        return $this->_cols;
    }

    function hasHelper($option = '')
    {
        if (empty($option)) {
            /* No option specified, check if any helpers have been
             * activated. */
            return !empty($this->_helper);
        } elseif (empty($this->_helper)) {
            /* No helpers activated at all, return false. */
            return false;
        } else {
            /* Check if given helper has been activated. */
            return in_array($option, $this->_helper);
        }
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("Long text"),
            'params' => array(
                'rows'   => array('label' => _("Number of rows"),
                                  'type'  => 'int'),
                'cols'   => array('label' => _("Number of columns"),
                                  'type'  => 'int'),
                'helper' => array('label' => _("Helpers"),
                                  'type'  => 'array')));
    }

}

class Horde_Form_Type_countedtext extends Horde_Form_Type_longtext {

    var $_chars;

    function init($rows = null, $cols = null, $chars = 1000)
    {
        parent::init($rows, $cols);
        $this->_chars = $chars;
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        $valid = true;

        $length = Horde_String::length(trim($value));

        if ($var->isRequired() && $length <= 0) {
            $valid = false;
            $message = _("This field is required.");
        } elseif ($length > $this->_chars) {
            $valid = false;
            $message = sprintf(ngettext("There are too many characters in this field. You have entered %d character; ", "There are too many characters in this field. You have entered %d characters; ", $length), $length)
                . sprintf(_("you must enter less than %d."), $this->_chars);
        }

        return $valid;
    }

    function getChars()
    {
        return $this->_chars;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("Counted text"),
            'params' => array(
                'rows'  => array('label' => _("Number of rows"),
                                 'type'  => 'int'),
                'cols'  => array('label' => _("Number of columns"),
                                 'type'  => 'int'),
                'chars' => array('label' => _("Number of characters"),
                                 'type'  => 'int')));
    }

}

class Horde_Form_Type_address extends Horde_Form_Type_longtext {

    function parse($address)
    {
        $info = array();
        $aus_state_regex = '(?:ACT|NSW|NT|QLD|SA|TAS|VIC|WA)';

        if (preg_match('/(?s)(.*?)(?-s)\r?\n(?:(.*?)\s+)?((?:A[BL]|B[ABDHLNRST]?|C[ABFHMORTVW]|D[ADEGHLNTY]|E[CHNX]?|F[KY]|G[LUY]?|H[ADGPRSUX]|I[GMPV]|JE|K[ATWY]|L[ADELNSU]?|M[EKL]?|N[EGNPRW]?|O[LX]|P[AEHLOR]|R[GHM]|S[AEGKLMNOPRSTWY]?|T[ADFNQRSW]|UB|W[ACDFNRSV]?|YO|ZE)\d(?:\d|[A-Z])? \d[A-Z]{2})/', $address, $addressParts)) {
            /* UK postcode detected. */
            $info = array('country' => 'uk', 'zip' => $addressParts[3]);
            if (!empty($addressParts[1])) {
                $info['street'] = $addressParts[1];
            }
            if (!empty($addressParts[2])) {
                $info['city'] = $addressParts[2];
            }
        } elseif (preg_match('/\b' . $aus_state_regex . '\b/', $address)) {
            /* Australian state detected. */
            /* Split out the address, line-by-line. */
            $addressLines = preg_split('/\r?\n/', $address);
            $info = array('country' => 'au');
            for ($i = 0; $i < count($addressLines); $i++) {
                /* See if it's the street number & name. */
                if (preg_match('/(\d+\s*\/\s*)?(\d+|\d+[a-zA-Z])\s+([a-zA-Z ]*)/', $addressLines[$i], $lineParts)) {
                    $info['street'] = $addressLines[$i];
                    $info['streetNumber'] = $lineParts[2];
                    $info['streetName'] = $lineParts[3];
                }
                /* Look for "Suburb, State". */
                if (preg_match('/([a-zA-Z ]*),?\s+(' . $aus_state_regex . ')/', $addressLines[$i], $lineParts)) {
                    $info['city'] = $lineParts[1];
                    $info['state'] = $lineParts[2];
                }
                /* Look for "State <4 digit postcode>". */
                if (preg_match('/(' . $aus_state_regex . ')\s+(\d{4})/', $addressLines[$i], $lineParts)) {
                    $info['state'] = $lineParts[1];
                    $info['zip'] = $lineParts[2];
                }
            }
        } elseif (preg_match('/(?s)(.*?)(?-s)\r?\n(.*)\s*,\s*(\w+)\.?\s+(\d+|[a-zA-Z]\d[a-zA-Z]\s?\d[a-zA-Z]\d)/', $address, $addressParts)) {
            /* American/Canadian address style. */
            $info = array('country' => 'us');
            if (!empty($addressParts[4]) &&
                preg_match('|[a-zA-Z]\d[a-zA-Z]\s?\d[a-zA-Z]\d|', $addressParts[4])) {
                $info['country'] = 'ca';
            }
            if (!empty($addressParts[1])) {
                $info['street'] = $addressParts[1];
            }
            if (!empty($addressParts[2])) {
                $info['city'] = $addressParts[2];
            }
            if (!empty($addressParts[3])) {
                $info['state'] = $addressParts[3];
            }
            if (!empty($addressParts[4])) {
                $info['zip'] = $addressParts[4];
            }
        } elseif (preg_match('/(?:(?s)(.*?)(?-s)(?:\r?\n|,\s*))?(?:([A-Z]{1,3})-)?(\d{4,5})\s+(.*)(?:\r?\n(.*))?/i', $address, $addressParts)) {
            /* European address style. */
            $info = array();
            if (!empty($addressParts[1])) {
                $info['street'] = $addressParts[1];
            }
            if (!empty($addressParts[2])) {
                include 'Horde/NLS/carsigns.php';
                $country = array_search(Horde_String::upper($addressParts[2]), $carsigns);
                if ($country) {
                    $info['country'] = $country;
                }
            }
            if (!empty($addressParts[5])) {
                include 'Horde/NLS/countries.php';
                $country = array_search($addressParts[5], $countries);
                if ($country) {
                    $info['country'] = Horde_String::lower($country);
                } elseif (!isset($info['street'])) {
                    $info['street'] = trim($addressParts[5]);
                } else {
                    $info['street'] .= "\n" . $addressParts[5];
                }
            }
            if (!empty($addressParts[3])) {
                $info['zip'] = $addressParts[3];
            }
            if (!empty($addressParts[4])) {
                $info['city'] = trim($addressParts[4]);
            }
        }

        return $info;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("Address"),
            'params' => array(
                'rows' => array('label' => _("Number of rows"),
                                'type'  => 'int'),
                'cols' => array('label' => _("Number of columns"),
                                'type'  => 'int')));
    }

}

class Horde_Form_Type_addresslink extends Horde_Form_Type_address {

    function isValid(&$var, &$vars, $value, &$message)
    {
        return true;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Address Link"));
    }

}

/**
 * @since Horde 3.3
 */
class Horde_Form_Type_pgp extends Horde_Form_Type_longtext {

    /**
     * Path to the GnuPG binary.
     *
     * @var string
     */
    var $_gpg;

    /**
     * A temporary directory.
     *
     * @var string
     */
    var $_temp;

    function init($gpg, $temp_dir = null, $rows = null, $cols = null)
    {
        $this->_gpg = $gpg;
        $this->_temp = $temp_dir;
        parent::init($rows, $cols);
    }

    /**
     * Returns a parameter hash for the Horde_Crypt_pgp constructor.
     *
     * @return array  A parameter hash.
     */
    function getPGPParams()
    {
        return array('program' => $this->_gpg, 'temp' => $this->_temp);
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("PGP Key"),
            'params' => array(
                'gpg'      => array('label' => _("Path to the GnuPG binary"),
                                    'type'  => 'string'),
                'temp_dir' => array('label' => _("A temporary directory"),
                                    'type'  => 'string'),
                'rows'     => array('label' => _("Number of rows"),
                                    'type'  => 'int'),
                'cols'     => array('label' => _("Number of columns"),
                                    'type'  => 'int')));
    }

}

/**
 * @since Horde 3.3
 */
class Horde_Form_Type_smime extends Horde_Form_Type_longtext {

    /**
     * A temporary directory.
     *
     * @var string
     */
    var $_temp;

    function init($temp_dir = null, $rows = null, $cols = null)
    {
        $this->_temp = $temp_dir;
        parent::init($rows, $cols);
    }

    /**
     * Returns a parameter hash for the Horde_Crypt_smime constructor.
     *
     * @return array  A parameter hash.
     */
    function getSMIMEParams()
    {
        return array('temp' => $this->_temp);
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("S/MIME Key"),
            'params' => array(
                'temp_dir' => array('label' => _("A temporary directory"),
                                    'type'  => 'string'),
                'rows'     => array('label' => _("Number of rows"),
                                    'type'  => 'int'),
                'cols'     => array('label' => _("Number of columns"),
                                    'type'  => 'int')));
    }

}

/**
 * @since Horde 3.2
 */
class Horde_Form_Type_country extends Horde_Form_Type_enum {

    function init($prompt = null)
    {
        parent::init(Horde_Nls::getCountryISO(), $prompt);
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("Country drop down list"),
            'params' => array(
                'prompt' => array('label' => _("Prompt text"),
                                  'type'  => 'text')));
    }

}

class Horde_Form_Type_file extends Horde_Form_Type {

    function isValid(&$var, &$vars, $value, &$message)
    {
        if ($var->isRequired()) {
            $uploaded = Horde_Browser::wasFileUploaded($var->getVarName());
            if (is_a($uploaded, 'PEAR_Error')) {
                $message = $uploaded->getMessage();
                return false;
            }
        }

        return true;
    }

    function getInfo(&$vars, &$var, &$info)
    {
        $name = $var->getVarName();
        $uploaded = Horde_Browser::wasFileUploaded($name);
        if ($uploaded === true) {
            $info['name'] = Horde_Util::dispelMagicQuotes($_FILES[$name]['name']);
            $info['type'] = $_FILES[$name]['type'];
            $info['tmp_name'] = $_FILES[$name]['tmp_name'];
            $info['file'] = $_FILES[$name]['tmp_name'];
            $info['error'] = $_FILES[$name]['error'];
            $info['size'] = $_FILES[$name]['size'];
        }
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("File upload"));
    }

}

class Horde_Form_Type_image extends Horde_Form_Type {

    /**
     * Has a file been uploaded on this form submit?
     *
     * @var boolean
     */
    var $_uploaded = null;

    /**
     * Show the upload button?
     *
     * @var boolean
     */
    var $_show_upload = true;

    /**
     * Show the option to upload also original non-modified image?
     *
     * @var boolean
     */
    var $_show_keeporig = false;

    /**
     * Limit the file size?
     *
     * @var integer
     */
    var $_max_filesize = null;

    /**
     * Hash containing the previously uploaded image info.
     *
     * @var array
     */
    var $_img;

    /**
     * A random id that identifies the image information in the session data.
     *
     * @var string
     */
    var $_random;

    function init($show_upload = true, $show_keeporig = false, $max_filesize = null)
    {
        $this->_show_upload   = $show_upload;
        $this->_show_keeporig = $show_keeporig;
        $this->_max_filesize  = $max_filesize;
    }

    function onSubmit(&$var, &$vars)
    {
        /* Get the upload. */
        $this->getImage($vars, $var);

        /* If this was done through the upload button override the submitted
         * value of the form. */
        if ($vars->get('_do_' . $var->getVarName())) {
            $var->form->setSubmitted(false);
            if (is_a($this->_uploaded, 'PEAR_Error')) {
                $this->_img = array('hash' => $this->getRandomId(),
                                    'error' => $this->_uploaded->getMessage());
            }
        }
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        /* Get the upload. */
        $this->getImage($vars, $var);
        $field = $vars->get($var->getVarName());

        /* The upload generated a PEAR Error. */
        if (is_a($this->_uploaded, 'PEAR_Error')) {
            /* Not required and no image upload attempted. */
            if (!$var->isRequired() && empty($field['hash']) &&
                $this->_uploaded->getCode() == UPLOAD_ERR_NO_FILE) {
                return true;
            }

            if (($this->_uploaded->getCode() == UPLOAD_ERR_NO_FILE) &&
                empty($field['hash'])) {
                /* Nothing uploaded and no older upload. */
                $message = _("This field is required.");
                return false;
            } elseif (!empty($field['hash'])) {
                if ($this->_img && isset($this->_img['error'])) {
                    $message = $this->_img['error'];
                    return false;
                }
                /* Nothing uploaded but older upload present. */
                return true;
            } else {
                /* Some other error message. */
                $message = $this->_uploaded->getMessage();
                return false;
            }
        } elseif (empty($this->_img['img']['size'])) {
            $message = _("The image file size could not be determined or it was 0 bytes. The upload may have been interrupted.");
            return false;
        } elseif ($this->_max_filesize &&
                  $this->_img['img']['size'] > $this->_max_filesize) {
            $message = sprintf(_("The image file was larger than the maximum allowed size (%d bytes)."), $this->_max_filesize);
            return false;
        }

        return true;
    }

    function getInfo(&$vars, &$var, &$info)
    {
        /* Get the upload. */
        $this->getImage($vars, $var);

        /* Get image params stored in the hidden field. */
        $value = $var->getValue($vars);
        $info = $this->_img['img'];
        if (empty($info['file'])) {
            unset($info['file']);
            return;
        }
        if ($this->_show_keeporig) {
            $info['keep_orig'] = !empty($value['keep_orig']);
        }

        /* Set the uploaded value (either true or PEAR_Error). */
        $info['uploaded'] = &$this->_uploaded;

        /* If a modified file exists move it over the original. */
        if ($this->_show_keeporig && $info['keep_orig']) {
            /* Requested the saving of original file also. */
            $info['orig_file'] = Horde::getTempDir() . '/' . $info['file'];
            $info['file'] = Horde::getTempDir() . '/mod_' . $info['file'];
            /* Check if a modified file actually exists. */
            if (!file_exists($info['file'])) {
                $info['file'] = $info['orig_file'];
                unset($info['orig_file']);
            }
        } else {
            /* Saving of original not required. */
            $mod_file = Horde::getTempDir() . '/mod_' . $info['file'];
            $info['file'] = Horde::getTempDir() . '/' . $info['file'];

            if (file_exists($mod_file)) {
                /* Unlink first (has to be done on Windows machines?) */
                unlink($info['file']);
                rename($mod_file, $info['file']);
            }
        }
    }

    /**
     * Gets the upload and sets up the upload data array. Either
     * fetches an upload done with this submit or retries stored
     * upload info.
     */
    function _getUpload(&$vars, &$var)
    {
        /* Don't bother with this function if already called and set
         * up vars. */
        if (!empty($this->_img)) {
            return true;
        }

        /* Check if file has been uploaded. */
        $varname = $var->getVarName();
        $this->_uploaded = Horde_Browser::wasFileUploaded($varname . '[new]');

        if ($this->_uploaded === true) {
            /* A file has been uploaded on this submit. Save to temp dir for
             * preview work. */
            $this->_img['img']['type'] = $this->getUploadedFileType($varname . '[new]');

            /* Get the other parts of the upload. */
            require_once 'Horde/Array.php';
            Horde_Array::getArrayParts($varname . '[new]', $base, $keys);

            /* Get the temporary file name. */
            $keys_path = array_merge(array($base, 'tmp_name'), $keys);
            $this->_img['img']['file'] = Horde_Array::getElement($_FILES, $keys_path);

            /* Get the actual file name. */
            $keys_path = array_merge(array($base, 'name'), $keys);
            $this->_img['img']['name'] = Horde_Array::getElement($_FILES, $keys_path);

            /* Get the file size. */
            $keys_path = array_merge(array($base, 'size'), $keys);
            $this->_img['img']['size'] = Horde_Array::getElement($_FILES, $keys_path);

            /* Get any existing values for the image upload field. */
            $upload = $vars->get($var->getVarName());
            if (!empty($upload['hash'])) {
                $upload['img'] = $_SESSION['horde_form'][$upload['hash']];
                unset($_SESSION['horde_form'][$upload['hash']]);
            }

            /* Get the temp file if already one uploaded, otherwise create a
             * new temporary file. */
            if (!empty($upload['img']['file'])) {
                $tmp_file = Horde::getTempDir() . '/' . $upload['img']['file'];
            } else {
                $tmp_file = Horde::getTempFile('Horde', false);
            }

            /* Move the browser created temp file to the new temp file. */
            move_uploaded_file($this->_img['img']['file'], $tmp_file);
            $this->_img['img']['file'] = basename($tmp_file);
        } elseif ($this->_uploaded) {
            /* File has not been uploaded. */
            $upload = $vars->get($var->getVarName());
            if ($this->_uploaded->getCode() == 4 &&
                !empty($upload['hash']) &&
                isset($_SESSION['horde_form'][$upload['hash']])) {
                $this->_img['img'] = $_SESSION['horde_form'][$upload['hash']];
                unset($_SESSION['horde_form'][$upload['hash']]);
                if (isset($this->_img['error'])) {
                    $this->_uploaded = PEAR::raiseError($this->_img['error']);
                }
            }
        }
        if (isset($this->_img['img'])) {
            $_SESSION['horde_form'][$this->getRandomId()] = $this->_img['img'];
        }
    }

    function getUploadedFileType($field)
    {
        /* Get any index on the field name. */
        $index = Horde_Array::getArrayParts($field, $base, $keys);

        if ($index) {
            /* Index present, fetch the mime type var to check. */
            $keys_path = array_merge(array($base, 'type'), $keys);
            $type = Horde_Array::getElement($_FILES, $keys_path);
            $keys_path = array_merge(array($base, 'tmp_name'), $keys);
            $tmp_name = Horde_Array::getElement($_FILES, $keys_path);
        } else {
            /* No index, simple set up of vars to check. */
            $type = $_FILES[$field]['type'];
            $tmp_name = $_FILES[$field]['tmp_name'];
        }

        if (empty($type) || ($type == 'application/octet-stream')) {
            /* Type wasn't set on upload, try analising the upload. */
            if (!($type = Horde_Mime_Magic::analyzeFile($tmp_name, isset($GLOBALS['conf']['mime']['magic_db']) ? $GLOBALS['conf']['mime']['magic_db'] : null))) {
                if ($index) {
                    /* Get the name value. */
                    $keys_path = array_merge(array($base, 'name'), $keys);
                    $name = Horde_Array::getElement($_FILES, $keys_path);

                    /* Work out the type from the file name. */
                    $type = Horde_Mime_Magic::filenameToMime($name);

                    /* Set the type. */
                    $keys_path = array_merge(array($base, 'type'), $keys);
                    Horde_Array::getElement($_FILES, $keys_path, $type);
                } else {
                    /* Work out the type from the file name. */
                    $type = Horde_Mime_Magic::filenameToMime($_FILES[$field]['name']);

                    /* Set the type. */
                    $_FILES[$field]['type'] = Horde_Mime_Magic::filenameToMime($_FILES[$field]['name']);
                }
            }
        }

        return $type;
    }

    /**
     * Returns the current image information.
     *
     * @return array  The current image hash.
     */
    function getImage($vars, $var)
    {
        $this->_getUpload($vars, $var);
        if (!isset($this->_img)) {
            $image = $vars->get($var->getVarName());
            if ($image) {
                $this->loadImageData($image);
                if (isset($image['img'])) {
                    $this->_img = $image;
                    $_SESSION['horde_form'][$this->getRandomId()] = $this->_img['img'];
                }
            }
        }
        return $this->_img;
    }

    /**
     * Loads any existing image data into the image field. Requires that the
     * array $image passed to it contains the structure:
     *   $image['load']['file'] - the filename of the image;
     *   $image['load']['data'] - the raw image data.
     *
     * @param array $image  The image array.
     */
    function loadImageData(&$image)
    {
        /* No existing image data to load. */
        if (!isset($image['load'])) {
            return;
        }

        /* Save the data to the temp dir. */
        $tmp_file = Horde::getTempDir() . '/' . $image['load']['file'];
        if ($fd = fopen($tmp_file, 'w')) {
            fwrite($fd, $image['load']['data']);
            fclose($fd);
        }

        $image['img'] = array('file' => $image['load']['file']);
        unset($image['load']);
    }

    function getRandomId()
    {
        if (!isset($this->_random)) {
            $this->_random = uniqid(mt_rand());
        }
        return $this->_random;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("Image upload"),
            'params' => array(
                'show_upload'   => array('label' => _("Show upload?"),
                                         'type'  => 'boolean'),
                'show_keeporig' => array('label' => _("Show option to keep original?"),
                                         'type'  => 'boolean'),
                'max_filesize'  => array('label' => _("Maximum file size in bytes"),
                                         'type'  => 'int')));
    }

}

class Horde_Form_Type_boolean extends Horde_Form_Type {

    function isValid(&$var, &$vars, $value, &$message)
    {
        return true;
    }

    function getInfo(&$vars, &$var, &$info)
    {
        $info = Horde_String::lower($vars->get($var->getVarName())) == 'on';
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("True or false"));
    }

}

class Horde_Form_Type_link extends Horde_Form_Type {

    /**
     * List of hashes containing link parameters. Possible keys: 'url', 'text',
     * 'target', 'onclick', 'title', 'accesskey'.
     *
     * @var array
     */
    var $values;

    function init($values)
    {
        $this->values = $values;
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        return true;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("Link"),
            'params' => array(
                'url' => array(
                    'label' => _("Link URL"),
                    'type' => 'text'),
                'text' => array(
                    'label' => _("Link text"),
                    'type' => 'text'),
                'target' => array(
                    'label' => _("Link target"),
                    'type' => 'text'),
                'onclick' => array(
                    'label' => _("Onclick event"),
                    'type' => 'text'),
                'title' => array(
                    'label' => _("Link title attribute"),
                    'type' => 'text'),
                'accesskey' => array(
                    'label' => _("Link access key"),
                    'type' => 'text')));
    }

}

class Horde_Form_Type_email extends Horde_Form_Type {

    /**
     * Allow multiple addresses?
     *
     * @var boolean
     */
    var $_allow_multi = false;

    /**
     * Protect address from spammers?
     *
     * @var boolean
     */
    var $_strip_domain = false;

    /**
     * Link the email address to the compose page when displaying?
     *
     * @var boolean
     */
    var $_link_compose = false;

    /**
     * Whether to check the domain's SMTP server whether the address exists.
     *
     * @var boolean
     */
    var $_check_smtp = false;

    /**
     * The name to use when linking to the compose page
     *
     * @var boolean
     */
    var $_link_name;

    /**
     * A string containing valid delimiters (default is just comma).
     *
     * @var string
     */
    var $_delimiters = ',';

    /**
     * @param boolean $allow_multi   Allow multiple addresses?
     * @param boolean $strip_domain  Protect address from spammers?
     * @param boolean $link_compose  Link the email address to the compose page
     *                               when displaying?
     * @param string $link_name      The name to use when linking to the
                                     compose page.
     * @param string $delimiters     Character to split multiple addresses with.
     */
    function init($allow_multi = false, $strip_domain = false,
                  $link_compose = false, $link_name = null,
                  $delimiters = ',')
    {
        $this->_allow_multi = $allow_multi;
        $this->_strip_domain = $strip_domain;
        $this->_link_compose = $link_compose;
        $this->_link_name = $link_name;
        $this->_delimiters = $delimiters;
    }

    /**
     */
    function isValid(&$var, &$vars, $value, &$message)
    {
        // Split into individual addresses.
        $emails = $this->splitEmailAddresses($value);

        // Check for too many.
        if (!$this->_allow_multi && count($emails) > 1) {
            $message = _("Only one email address is allowed.");
            return false;
        }

        // Check for all valid and at least one non-empty.
        $nonEmpty = 0;
        foreach ($emails as $email) {
            if (!strlen($email)) {
                continue;
            }
            if (!$this->validateEmailAddress($email)) {
                $message = sprintf(_("\"%s\" is not a valid email address."), $email);
                return false;
            }
            ++$nonEmpty;
        }

        if (!$nonEmpty && $var->isRequired()) {
            if ($this->_allow_multi) {
                $message = _("You must enter at least one email address.");
            } else {
                $message = _("You must enter an email address.");
            }
            return false;
        }

        return true;
    }

    /**
     * Explodes an RFC 2822 string, ignoring a delimiter if preceded
     * by a "\" character, or if the delimiter is inside single or
     * double quotes.
     *
     * @param string $string     The RFC 822 string.
     *
     * @return array  The exploded string in an array.
     */
    function splitEmailAddresses($string)
    {
        $quotes = array('"', "'");
        $emails = array();
        $pos = 0;
        $in_quote = null;
        $in_group = false;
        $prev = null;

        if (!strlen($string)) {
            return array();
        }

        $char = $string[0];
        if (in_array($char, $quotes)) {
            $in_quote = $char;
        } elseif ($char == ':') {
            $in_group = true;
        } elseif (strpos($this->_delimiters, $char) !== false) {
            $emails[] = '';
            $pos = 1;
        }

        for ($i = 1, $iMax = strlen($string); $i < $iMax; ++$i) {
            $char = $string[$i];
            if (in_array($char, $quotes)) {
                if ($prev !== '\\') {
                    if ($in_quote === $char) {
                        $in_quote = null;
                    } elseif (is_null($in_quote)) {
                        $in_quote = $char;
                    }
                }
            } elseif ($in_group) {
                if ($char == ';') {
                    $emails[] = substr($string, $pos, $i - $pos + 1);
                    $pos = $i + 1;
                    $in_group = false;
                }
            } elseif ($char == ':') {
                $in_group = true;
            } elseif (strpos($this->_delimiters, $char) !== false &&
                      $prev !== '\\' &&
                      is_null($in_quote)) {
                $emails[] = substr($string, $pos, $i - $pos);
                $pos = $i + 1;
            }
            $prev = $char;
        }

        if ($pos != $i) {
            /* The string ended without a delimiter. */
            $emails[] = substr($string, $pos, $i - $pos);
        }

        return $emails;
    }

    /**
     * RFC(2)822 Email Parser.
     *
     * By Cal Henderson <cal@iamcal.com>
     * This code is licensed under a Creative Commons Attribution-ShareAlike 2.5 License
     * http://creativecommons.org/licenses/by-sa/2.5/
     *
     * http://code.iamcal.com/php/rfc822/
     *
     * http://iamcal.com/publish/articles/php/parsing_email
     *
     * Revision 4
     *
     * @param string $email An individual email address to validate.
     *
     * @return boolean
     */
    function validateEmailAddress($email)
    {
        static $comment_regexp, $email_regexp;
        if ($comment_regexp === null) {
            $this->_defineValidationRegexps($comment_regexp, $email_regexp);
        }

        // We need to strip comments first (repeat until we can't find
        // any more).
        while (true) {
            $new = preg_replace("!$comment_regexp!", '', $email);
            if (strlen($new) == strlen($email)){
                break;
            }
            $email = $new;
        }

        // Now match what's left.
        $result = (bool)preg_match("!^$email_regexp$!", $email);
        if ($result && $this->_check_smtp) {
            $result = $this->validateEmailAddressSmtp($email);
        }

        return $result;
    }

    /**
     * Attempt partial delivery of mail to an address to validate it.
     *
     * @param string $email An individual email address to validate.
     *
     * @return boolean
     */
    function validateEmailAddressSmtp($email)
    {
        list(, $maildomain) = explode('@', $email, 2);

        // Try to get the real mailserver from MX records.
        if (function_exists('getmxrr') &&
            @getmxrr($maildomain, $mxhosts, $mxpriorities)) {
            // MX record found.
            array_multisort($mxpriorities, $mxhosts);
            $mailhost = $mxhosts[0];
        } else {
            // No MX record found, try the root domain as the mail
            // server.
            $mailhost = $maildomain;
        }

        $fp = @fsockopen($mailhost, 25, $errno, $errstr, 5);
        if (!$fp) {
            return false;
        }

        // Read initial response.
        fgets($fp, 4096);

        // HELO
        fputs($fp, "HELO $mailhost\r\n");
        fgets($fp, 4096);

        // MAIL FROM
        fputs($fp, "MAIL FROM: <root@example.com>\r\n");
        fgets($fp, 4096);

        // RCPT TO - gets the result we want.
        fputs($fp, "RCPT TO: <$email>\r\n");
        $result = trim(fgets($fp, 4096));

        // QUIT
        fputs($fp, "QUIT\r\n");
        fgets($fp, 4096);
        fclose($fp);

        return substr($result, 0, 1) == '2';
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("Email"),
            'params' => array(
                'allow_multi' => array(
                    'label' => _("Allow multiple addresses?"),
                    'type'  => 'boolean'),
                'strip_domain' => array(
                    'label' => _("Protect address from spammers?"),
                    'type' => 'boolean'),
                'link_compose' => array(
                    'label' => _("Link the email address to the compose page when displaying?"),
                    'type' => 'boolean'),
                'link_name' => array(
                    'label' => _("The name to use when linking to the compose page"),
                    'type' => 'text'),
                'delimiters' => array(
                    'label' => _("Character to split multiple addresses with"),
                    'type' => 'text'),
            ),
        );
    }

    /**
     * RFC(2)822 Email Parser.
     *
     * By Cal Henderson <cal@iamcal.com>
     * This code is licensed under a Creative Commons Attribution-ShareAlike 2.5 License
     * http://creativecommons.org/licenses/by-sa/2.5/
     *
     * http://code.iamcal.com/php/rfc822/
     *
     * http://iamcal.com/publish/articles/php/parsing_email
     *
     * Revision 4
     *
     * @param string &$comment The regexp for comments.
     * @param string &$addr_spec The regexp for email addresses.
     */
    function _defineValidationRegexps(&$comment, &$addr_spec)
    {
        /**
         * NO-WS-CTL       =       %d1-8 /         ; US-ASCII control characters
         *                         %d11 /          ;  that do not include the
         *                         %d12 /          ;  carriage return, line feed,
         *                         %d14-31 /       ;  and white space characters
         *                         %d127
         * ALPHA          =  %x41-5A / %x61-7A   ; A-Z / a-z
         * DIGIT          =  %x30-39
         */
        $no_ws_ctl  = "[\\x01-\\x08\\x0b\\x0c\\x0e-\\x1f\\x7f]";
        $alpha      = "[\\x41-\\x5a\\x61-\\x7a]";
        $digit      = "[\\x30-\\x39]";
        $cr         = "\\x0d";
        $lf         = "\\x0a";
        $crlf       = "($cr$lf)";

        /**
         * obs-char        =       %d0-9 / %d11 /          ; %d0-127 except CR and
         *                         %d12 / %d14-127         ;  LF
         * obs-text        =       *LF *CR *(obs-char *LF *CR)
         * text            =       %d1-9 /         ; Characters excluding CR and LF
         *                         %d11 /
         *                         %d12 /
         *                         %d14-127 /
         *                         obs-text
         * obs-qp          =       "\" (%d0-127)
         * quoted-pair     =       ("\" text) / obs-qp
         */
        $obs_char       = "[\\x00-\\x09\\x0b\\x0c\\x0e-\\x7f]";
        $obs_text       = "($lf*$cr*($obs_char$lf*$cr*)*)";
        $text           = "([\\x01-\\x09\\x0b\\x0c\\x0e-\\x7f]|$obs_text)";
        $obs_qp         = "(\\x5c[\\x00-\\x7f])";
        $quoted_pair    = "(\\x5c$text|$obs_qp)";

        /**
         * obs-FWS         =       1*WSP *(CRLF 1*WSP)
         * FWS             =       ([*WSP CRLF] 1*WSP) /   ; Folding white space
         *                         obs-FWS
         * ctext           =       NO-WS-CTL /     ; Non white space controls
         *                         %d33-39 /       ; The rest of the US-ASCII
         *                         %d42-91 /       ;  characters not including "(",
         *                         %d93-126        ;  ")", or "\"
         * ccontent        =       ctext / quoted-pair / comment
         * comment         =       "(" *([FWS] ccontent) [FWS] ")"
         * CFWS            =       *([FWS] comment) (([FWS] comment) / FWS)
         *
         * @note: We translate ccontent only partially to avoid an
         * infinite loop. Instead, we'll recursively strip comments
         * before processing the input.
         */
        $wsp        = "[\\x20\\x09]";
        $obs_fws    = "($wsp+($crlf$wsp+)*)";
        $fws        = "((($wsp*$crlf)?$wsp+)|$obs_fws)";
        $ctext      = "($no_ws_ctl|[\\x21-\\x27\\x2A-\\x5b\\x5d-\\x7e])";
        $ccontent   = "($ctext|$quoted_pair)";
        $comment    = "(\\x28($fws?$ccontent)*$fws?\\x29)";
        $cfws       = "(($fws?$comment)*($fws?$comment|$fws))";
        $cfws       = "$fws*";

        /**
         * atext           =       ALPHA / DIGIT / ; Any character except controls,
         *                         "!" / "#" /     ;  SP, and specials.
         *                         "$" / "%" /     ;  Used for atoms
         *                         "&" / "'" /
         *                         "*" / "+" /
         *                         "-" / "/" /
         *                         "=" / "?" /
         *                         "^" / "_" /
         *                         "`" / "{" /
         *                         "|" / "}" /
         *                         "~"
         * atom            =       [CFWS] 1*atext [CFWS]
         */
        $atext      = "($alpha|$digit|[\\x21\\x23-\\x27\\x2a\\x2b\\x2d\\x2e\\x3d\\x3f\\x5e\\x5f\\x60\\x7b-\\x7e])";
        $atom       = "($cfws?$atext+$cfws?)";

        /**
         * qtext           =       NO-WS-CTL /     ; Non white space controls
         *                         %d33 /          ; The rest of the US-ASCII
         *                         %d35-91 /       ;  characters not including "\"
         *                         %d93-126        ;  or the quote character
         * qcontent        =       qtext / quoted-pair
         * quoted-string   =       [CFWS]
         *                         DQUOTE *([FWS] qcontent) [FWS] DQUOTE
         *                         [CFWS]
         * word            =       atom / quoted-string
         */
        $qtext      = "($no_ws_ctl|[\\x21\\x23-\\x5b\\x5d-\\x7e])";
        $qcontent   = "($qtext|$quoted_pair)";
        $quoted_string  = "($cfws?\\x22($fws?$qcontent)*$fws?\\x22$cfws?)";
        $word       = "($atom|$quoted_string)";

        /**
         * obs-local-part  =       word *("." word)
         * obs-domain      =       atom *("." atom)
         */
        $obs_local_part = "($word(\\x2e$word)*)";
        $obs_domain = "($atom(\\x2e$atom)*)";

        /**
         * dot-atom-text   =       1*atext *("." 1*atext)
         * dot-atom        =       [CFWS] dot-atom-text [CFWS]
         */
        $dot_atom_text  = "($atext+(\\x2e$atext+)*)";
        $dot_atom   = "($cfws?$dot_atom_text$cfws?)";

        /**
         * domain-literal  =       [CFWS] "[" *([FWS] dcontent) [FWS] "]" [CFWS]
         * dcontent        =       dtext / quoted-pair
         * dtext           =       NO-WS-CTL /     ; Non white space controls
         *
         *                         %d33-90 /       ; The rest of the US-ASCII
         *                         %d94-126        ;  characters not including "[",
         *                                         ;  "]", or "\"
         */
        $dtext      = "($no_ws_ctl|[\\x21-\\x5a\\x5e-\\x7e])";
        $dcontent   = "($dtext|$quoted_pair)";
        $domain_literal = "($cfws?\\x5b($fws?$dcontent)*$fws?\\x5d$cfws?)";

        /**
         * local-part      =       dot-atom / quoted-string / obs-local-part
         * domain          =       dot-atom / domain-literal / obs-domain
         * addr-spec       =       local-part "@" domain
         */
        $local_part = "($dot_atom|$quoted_string|$obs_local_part)";
        $domain     = "($dot_atom|$domain_literal|$obs_domain)";
        $addr_spec  = "($local_part\\x40$domain)";
    }

}

class Horde_Form_Type_matrix extends Horde_Form_Type {

    var $_cols;
    var $_rows;
    var $_matrix;
    var $_new_input;

    /**
     * Initializes the variable.
     *
     * Example:
     * <code>
     * init(array('Column A', 'Column B'),
     *      array(1 => 'Row One', 2 => 'Row 2', 3 => 'Row 3'),
     *      array(array(true, true, false),
     *            array(true, false, true),
     *            array(fasle, true, false)),
     *      array('Row 4', 'Row 5'));
     * </code>
     *
     * @param array $cols               A list of column headers.
     * @param array $rows               A hash with row IDs as the keys and row
     *                                  labels as the values.
     * @param array $matrix             A two dimensional hash with the field
     *                                  values.
     * @param boolean|array $new_input  If true, a free text field to add a new
     *                                  row is displayed on the top, a select
     *                                  box if this parameter is a value.
     */
    function init($cols, $rows = array(), $matrix = array(), $new_input = false)
    {
        $this->_cols       = $cols;
        $this->_rows       = $rows;
        $this->_matrix     = $matrix;
        $this->_new_input  = $new_input;
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        return true;
    }

    function getCols()     { return $this->_cols; }
    function getRows()     { return $this->_rows; }
    function getMatrix()   { return $this->_matrix; }
    function getNewInput() { return $this->_new_input; }

    function getInfo(&$vars, &$var, &$info)
    {
        $values = $vars->get($var->getVarName());
        if (!empty($values['n']['r']) && isset($values['n']['v'])) {
            $new_row = $values['n']['r'];
            $values['r'][$new_row] = $values['n']['v'];
            unset($values['n']);
        }

        $info = (isset($values['r']) ? $values['r'] : array());
    }

    function about()
    {
        return array(
            'name' => _("Field matrix"),
            'params' => array(
                'cols' => array('label' => _("Column titles"),
                                'type'  => 'stringarray')));
    }

}

class Horde_Form_Type_emailConfirm extends Horde_Form_Type {

    function isValid(&$var, &$vars, $value, &$message)
    {
        if ($var->isRequired() && empty($value['original'])) {
            $message = _("This field is required.");
            return false;
        }

        if ($value['original'] != $value['confirm']) {
            $message = _("Email addresses must match.");
            return false;
        } else {
            $parsed_email = Horde_Mime_Address::parseAddressList($value['original'],
                                                                 array('validate' => true));
            if (is_a($parsed_email, 'PEAR_Error')) {
                $message = $parsed_email->getMessage();
                return false;
            }
            if (count($parsed_email) > 1) {
                $message = _("Only one email address allowed.");
                return false;
            }
            if (empty($parsed_email[0]->mailbox)) {
                $message = _("You did not enter a valid email address.");
                return false;
            }
        }

        return true;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Email with confirmation"));
    }

}

class Horde_Form_Type_password extends Horde_Form_Type {

    function isValid(&$var, &$vars, $value, &$message)
    {
        $valid = true;

        if ($var->isRequired()) {
            $valid = strlen(trim($value)) > 0;

            if (!$valid) {
                $message = _("This field is required.");
            }
        }

        return $valid;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Password"));
    }

}

class Horde_Form_Type_passwordconfirm extends Horde_Form_Type {

    function isValid(&$var, &$vars, $value, &$message)
    {
        if ($var->isRequired() && empty($value['original'])) {
            $message = _("This field is required.");
            return false;
        }

        if ($value['original'] != $value['confirm']) {
            $message = _("Passwords must match.");
            return false;
        }

        return true;
    }

    function getInfo(&$vars, &$var, &$info)
    {
        $value = $vars->get($var->getVarName());
        $info = $value['original'];
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Password with confirmation"));
    }

}

class Horde_Form_Type_enum extends Horde_Form_Type {

    var $_values;
    var $_prompt;

    function init($values, $prompt = null)
    {
        $this->setValues($values);

        if ($prompt === true) {
            $this->_prompt = _("-- select --");
        } else {
            $this->_prompt = $prompt;
        }
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        if ($var->isRequired() && $value == '' && !isset($this->_values[$value])) {
            $message = _("This field is required.");
            return false;
        }

        if (count($this->_values) == 0 || isset($this->_values[$value]) ||
            ($this->_prompt && empty($value))) {
            return true;
        }

        $message = _("Invalid data submitted.");
        return false;
    }

    function getValues()
    {
        return $this->_values;
    }

    /**
     * @since Horde 3.2
     */
    function setValues($values)
    {
        $this->_values = $values;
    }

    function getPrompt()
    {
        return $this->_prompt;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("Drop down list"),
            'params' => array(
                'values' => array('label' => _("Values to select from"),
                                  'type'  => 'stringarray'),
                'prompt' => array('label' => _("Prompt text"),
                                  'type'  => 'text')));
    }

}

class Horde_Form_Type_mlenum extends Horde_Form_Type {

    var $_values;
    var $_prompts;

    function init(&$values, $prompts = null)
    {
        $this->_values = &$values;

        if ($prompts === true) {
            $this->_prompts = array(_("-- select --"), _("-- select --"));
        } elseif (!is_array($prompts)) {
            $this->_prompts = array($prompts, $prompts);
        } else {
            $this->_prompts = $prompts;
        }
    }

    function onSubmit(&$var, &$vars)
    {
        $varname = $var->getVarName();
        $value = $vars->get($varname);

        if ($value['1'] != $value['old']) {
            $var->form->setSubmitted(false);
        }
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        if ($var->isRequired() && (empty($value['1']) || empty($value['2']))) {
            $message = _("This field is required.");
            return false;
        }

        if (!count($this->_values) || isset($this->_values[$value['1']]) ||
            (!empty($this->_prompts) && empty($value['1']))) {
            return true;
        }

        $message = _("Invalid data submitted.");
        return false;
    }

    function getValues()
    {
        return $this->_values;
    }

    function getPrompts()
    {
        return $this->_prompts;
    }

    function getInfo(&$vars, &$var, &$info)
    {
        $info = $vars->get($var->getVarName());
        return $info['2'];
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("Multi-level drop down lists"),
            'params' => array(
                'values' => array('label' => _("Values to select from"),
                                  'type'  => 'stringarray'),
                'prompt' => array('label' => _("Prompt text"),
                                  'type'  => 'text')));
    }

}

class Horde_Form_Type_multienum extends Horde_Form_Type_enum {

    var $size = 5;

    function init($values, $size = null)
    {
        if (!is_null($size)) {
            $this->size = (int)$size;
        }

        parent::init($values);
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        if (is_array($value)) {
            foreach ($value as $val) {
                if (!$this->isValid($var, $vars, $val, $message)) {
                    return false;
                }
            }
            return true;
        }

        if (empty($value) && ((string)(int)$value !== $value)) {
            if ($var->isRequired()) {
                $message = _("This field is required.");
                return false;
            } else {
                return true;
            }
        }

        if (count($this->_values) == 0 || isset($this->_values[$value])) {
            return true;
        }

        $message = _("Invalid data submitted.");
        return false;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("Multiple selection"),
            'params' => array(
                'values' => array('label' => _("Values"),
                                  'type'  => 'stringarray'),
                'size'   => array('label' => _("Size"),
                                  'type'  => 'int'))
        );
    }

}

class Horde_Form_Type_keyval_multienum extends Horde_Form_Type_multienum {

    function getInfo(&$vars, &$var, &$info)
    {
        $value = $vars->get($var->getVarName());
        $info = array();
        foreach ($value as $key) {
            $info[$key] = $this->_values[$key];
        }
    }

}

class Horde_Form_Type_radio extends Horde_Form_Type_enum {

    /* Entirely implemented by Horde_Form_Type_enum; just a different
     * view. */

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("Radio selection"),
            'params' => array(
                'values' => array('label' => _("Values"),
                                  'type'  => 'stringarray')));
    }

}

class Horde_Form_Type_set extends Horde_Form_Type {

    var $_values;
    var $_checkAll = false;

    function init($values, $checkAll = false)
    {
        $this->_values = $values;
        $this->_checkAll = $checkAll;
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        if (count($this->_values) == 0 || count($value) == 0) {
            return true;
        }
        foreach ($value as $item) {
            if (!isset($this->_values[$item])) {
                $error = true;
                break;
            }
        }
        if (!isset($error)) {
            return true;
        }

        $message = _("Invalid data submitted.");
        return false;
    }

    function getValues()
    {
        return $this->_values;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("Set"),
            'params' => array(
                'values' => array('label' => _("Values"),
                                  'type'  => 'stringarray')));
    }

}

class Horde_Form_Type_date extends Horde_Form_Type {

    var $_format;

    function init($format = '%a %d %B')
    {
        $this->_format = $format;
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        $valid = true;

        if ($var->isRequired()) {
            $valid = strlen(trim($value)) > 0;

            if (!$valid) {
                $message = sprintf(_("%s is required"), $var->getHumanName());
            }
        }

        return $valid;
    }

    /**
     * @static
     *
     * @param mixed $date  The date to calculate the difference from. Can be
     *                     either a timestamp integer value, or an array
     *                     with date parts: 'day', 'month', 'year'.
     *
     * @return string
     */
    function getAgo($date)
    {
        if ($date === null) {
            return '';
        } elseif (!is_array($date)) {
            /* Date is not array, so assume timestamp. Work out the component
             * parts using date(). */
            $date = array('day'   => date('j', $date),
                          'month' => date('n', $date),
                          'year'  => date('Y', $date));
        }

        require_once 'Date/Calc.php';
        $diffdays = Date_Calc::dateDiff((int)$date['day'],
                                        (int)$date['month'],
                                        (int)$date['year'],
                                        date('j'), date('n'), date('Y'));

        /* An error occured. */
        if ($diffdays == -1) {
            return;
        }

        $ago = $diffdays * Date_Calc::compareDates((int)$date['day'],
                                                   (int)$date['month'],
                                                   (int)$date['year'],
                                                   date('j'), date('n'),
                                                   date('Y'));
        if ($ago < -1) {
            return sprintf(_(" (%s days ago)"), $diffdays);
        } elseif ($ago == -1) {
            return _(" (yesterday)");
        } elseif ($ago == 0) {
            return _(" (today)");
        } elseif ($ago == 1) {
            return _(" (tomorrow)");
        } else {
            return sprintf(_(" (in %s days)"), $diffdays);
        }
    }

    function getFormattedTime($timestamp, $format = null, $showago = true)
    {
        if (empty($format)) {
            $format = $this->_format;
        }
        if (!empty($timestamp)) {
            return strftime($format, $timestamp) . ($showago ? Horde_Form_Type_date::getAgo($timestamp) : '');
        } else {
            return '';
        }
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Date"));
    }

}

class Horde_Form_Type_time extends Horde_Form_Type {

    function isValid(&$var, &$vars, $value, &$message)
    {
        if ($var->isRequired() && empty($value) && ((string)(double)$value !== $value)) {
            $message = _("This field is required.");
            return false;
        }

        if (empty($value) || preg_match('/^[0-2]?[0-9]:[0-5][0-9]$/', $value)) {
            return true;
        }

        $message = _("This field may only contain numbers and the colon.");
        return false;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Time"));
    }

}

class Horde_Form_Type_hourminutesecond extends Horde_Form_Type {

    var $_show_seconds;

    function init($show_seconds = false)
    {
        $this->_show_seconds = $show_seconds;
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        $time = $vars->get($var->getVarName());
        if (!$this->_show_seconds && count($time) && !isset($time['second'])) {
            $time['second'] = 0;
        }

        if (!$this->emptyTimeArray($time) && !$this->checktime($time['hour'], $time['minute'], $time['second'])) {
            $message = _("Please enter a valid time.");
            return false;
        } elseif ($this->emptyTimeArray($time) && $var->isRequired()) {
            $message = _("This field is required.");
            return false;
        }

        return true;
    }

    function checktime($hour, $minute, $second)
    {
        if (!isset($hour) || $hour == '' || ($hour < 0 || $hour > 23)) {
            return false;
        }
        if (!isset($minute) || $minute == '' || ($minute < 0 || $minute > 60)) {
            return false;
        }
        if (!isset($second) || $second === '' || ($second < 0 || $second > 60)) {
            return false;
        }

        return true;
    }

    /**
     * Return the time supplied as a Horde_Date object.
     *
     * @param string $time_in  Date in one of the three formats supported by
     *                         Horde_Form and Horde_Date (ISO format
     *                         YYYY-MM-DD HH:MM:SS, timestamp YYYYMMDDHHMMSS and
     *                         UNIX epoch).
     *
     * @return Date  The time object.
     */
    function getTimeOb($time_in)
    {
        require_once 'Horde/Date.php';

        if (is_array($time_in)) {
            if (!$this->emptyTimeArray($time_in)) {
                $time_in = sprintf('1970-01-01 %02d:%02d:%02d', $time_in['hour'], $time_in['minute'], $this->_show_seconds ? $time_in['second'] : 0);
            }
        }

        return new Horde_Date($time_in);
    }

    /**
     * Return the time supplied split up into an array.
     *
     * @param string $time_in  Time in one of the three formats supported by
     *                         Horde_Form and Horde_Date (ISO format
     *                         YYYY-MM-DD HH:MM:SS, timestamp YYYYMMDDHHMMSS and
     *                         UNIX epoch).
     *
     * @return array  Array with three elements - hour, minute and seconds.
     */
    function getTimeParts($time_in)
    {
        if (is_array($time_in)) {
            /* This is probably a failed isValid input so just return the
             * parts as they are. */
            return $time_in;
        } elseif (empty($time_in)) {
            /* This is just an empty field so return empty parts. */
            return array('hour' => '', 'minute' => '', 'second' => '');
        }
        $time = $this->getTimeOb($time_in);
        return array('hour' => $time->hour,
                     'minute' => $time->min,
                     'second' => $time->sec);
    }

    function emptyTimeArray($time)
    {
        return (is_array($time)
                && (!isset($time['hour']) || !strlen($time['hour']))
                && (!isset($time['minute']) || !strlen($time['minute']))
                && (!$this->_show_seconds || !strlen($time['second'])));
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("Time selection"),
            'params' => array(
                'seconds' => array('label' => _("Show seconds?"),
                                   'type'  => 'boolean')));
    }

}

class Horde_Form_Type_monthyear extends Horde_Form_Type {

    var $_start_year;
    var $_end_year;

    function init($start_year = null, $end_year = null)
    {
        if (empty($start_year)) {
            $start_year = 1920;
        }
        if (empty($end_year)) {
            $end_year = date('Y');
        }

        $this->_start_year = $start_year;
        $this->_end_year = $end_year;
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        if (!$var->isRequired()) {
            return true;
        }

        if (!$vars->get($this->getMonthVar($var)) ||
            !$vars->get($this->getYearVar($var))) {
            $message = _("Please enter a month and a year.");
            return false;
        }

        return true;
    }

    function getMonthVar($var)
    {
        return $var->getVarName() . '[month]';
    }

    function getYearVar($var)
    {
        return $var->getVarName() . '[year]';
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Month and year"),
                     'params' => array(
                         'start_year' => array('label' => _("Start year"),
                                               'type'  => 'int'),
                         'end_year'   => array('label' => _("End year"),
                                               'type'  => 'int')));
    }

}

class Horde_Form_Type_monthdayyear extends Horde_Form_Type {

    var $_start_year;
    var $_end_year;
    var $_picker;
    var $_format_in = null;
    var $_format_out = '%x';

    /**
     * Return the date supplied as a Horde_Date object.
     *
     * @param integer $start_year  The first available year for input.
     * @param integer $end_year    The last available year for input.
     * @param boolean $picker      Do we show the DHTML calendar?
     * @param integer $format_in   The format to use when sending the date
     *                             for storage. Defaults to Unix epoch.
     *                             Similar to the strftime() function.
     * @param integer $format_out  The format to use when displaying the
     *                             date. Similar to the strftime() function.
     */
    function init($start_year = '', $end_year = '', $picker = true,
                  $format_in = null, $format_out = '%x')
    {
        if (empty($start_year)) {
            $start_year = date('Y');
        }
        if (empty($end_year)) {
            $end_year = date('Y') + 10;
        }

        $this->_start_year = $start_year;
        $this->_end_year = $end_year;
        $this->_picker = $picker;
        $this->_format_in = $format_in;
        $this->_format_out = $format_out;
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        $date = $vars->get($var->getVarName());
        $empty = $this->emptyDateArray($date);

        if ($empty == 1 && $var->isRequired()) {
            $message = _("This field is required.");
            return false;
        } elseif ($empty == 0 && !checkdate($date['month'],
                                            $date['day'],
                                            $date['year'])) {
            $message = _("Please enter a valid date, check the number of days in the month.");
            return false;
        } elseif ($empty == -1) {
            $message = _("Select all date components.");
            return false;
        }

        return true;
    }

    /**
     * Determine if the provided date value is completely empty, partially empty
     * or non-empty.
     *
     * @param mixed $date  String or date part array representation of date.
     *
     * @return integer  0 for non-empty, 1 for completely empty or -1 for
     *                  partially empty.
     */
    function emptyDateArray($date)
    {
        if (!is_array($date)) {
            return (int)empty($date);
        }
        $empty = 0;
        /* Check each date array component. */
        foreach (array('day', 'month', 'year') as $key) {
            if (empty($date[$key])) {
                $empty++;
            }
        }

        /* Check state of empty. */
        if ($empty == 0) {
            /* If no empty parts return 0. */
            return 0;
        } elseif ($empty == 3) {
            /* If all empty parts return 1. */
            return 1;
        } else {
            /* If some empty parts return -1. */
            return -1;
        }
    }

    /**
     * Return the date supplied split up into an array.
     *
     * @param string $date_in  Date in one of the three formats supported by
     *                         Horde_Form and Horde_Date (ISO format
     *                         YYYY-MM-DD HH:MM:SS, timestamp YYYYMMDDHHMMSS
     *                         and UNIX epoch) plus the fourth YYYY-MM-DD.
     *
     * @return array  Array with three elements - year, month and day.
     */
    function getDateParts($date_in)
    {
        if (is_array($date_in)) {
            /* This is probably a failed isValid input so just return
             * the parts as they are. */
            return $date_in;
        } elseif (empty($date_in)) {
            /* This is just an empty field so return empty parts. */
            return array('year' => '', 'month' => '', 'day' => '');
        }

        $date = $this->getDateOb($date_in);
        return array('year' => $date->year,
                     'month' => $date->month,
                     'day' => $date->mday);
    }

    /**
     * Return the date supplied as a Horde_Date object.
     *
     * @param string $date_in  Date in one of the three formats supported by
     *                         Horde_Form and Horde_Date (ISO format
     *                         YYYY-MM-DD HH:MM:SS, timestamp YYYYMMDDHHMMSS
     *                         and UNIX epoch) plus the fourth YYYY-MM-DD.
     *
     * @return Date  The date object.
     */
    function getDateOb($date_in)
    {
        require_once 'Horde/Date.php';

        if (is_array($date_in)) {
            /* If passed an array change it to the ISO format. */
            if ($this->emptyDateArray($date_in) == 0) {
                $date_in = sprintf('%04d-%02d-%02d 00:00:00',
                                   $date_in['year'],
                                   $date_in['month'],
                                   $date_in['day']);
            }
        } elseif (preg_match('/^\d{4}-?\d{2}-?\d{2}$/', $date_in)) {
            /* Fix the date if it is the shortened ISO. */
            $date_in = $date_in . ' 00:00:00';
        }

        return new Horde_Date($date_in);
    }

    /**
     * Return the date supplied as a Horde_Date object.
     *
     * @param string $date  Either an already set up Horde_Date object or a
     *                      string date in one of the three formats supported
     *                      by Horde_Form and Horde_Date (ISO format
     *                      YYYY-MM-DD HH:MM:SS, timestamp YYYYMMDDHHMMSS and
     *                      UNIX epoch) plus the fourth YYYY-MM-DD.
     *
     * @return string  The date formatted according to the $format_out
     *                 parameter when setting up the monthdayyear field.
     */
    function formatDate($date)
    {
        if (!is_a($date, 'Date')) {
            $date = $this->getDateOb($date);
        }

        return $date->strftime($this->_format_out);
    }

    /**
     * Insert the date input through the form into $info array, in the format
     * specified by the $format_in parameter when setting up monthdayyear
     * field.
     */
    function getInfo(&$vars, &$var, &$info)
    {
        $info = $this->_validateAndFormat($var->getValue($vars), $var);
    }

    /**
     * Validate/format a date submission.
     */
    function _validateAndFormat($value, &$var)
    {
        /* If any component is empty consider it a bad date and return the
         * default. */
        if ($this->emptyDateArray($value) == 1) {
            return $var->getDefault();
        } else {
            $date = $this->getDateOb($value);
            if ($this->_format_in === null) {
                return $date->timestamp();
            } else {
                return $date->strftime($this->_format_in);
            }
        }
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("Date selection"),
            'params' => array(
                'start_year' => array('label' => _("Start year"),
                                      'type'  => 'int'),
                'end_year'   => array('label' => _("End year"),
                                      'type'  => 'int'),
                'picker'     => array('label' => _("Show picker?"),
                                      'type'  => 'boolean'),
                'format_in'  => array('label' => _("Storage format"),
                                      'type'  => 'text'),
                'format_out' => array('label' => _("Display format"),
                                      'type'  => 'text')));
    }

}

/**
 * @since Horde 3.2
 */
class Horde_Form_Type_datetime extends Horde_Form_Type {

    var $_mdy;
    var $_hms;
    var $_show_seconds;

    /**
     * Return the date supplied as a Horde_Date object.
     *
     * @param integer $start_year  The first available year for input.
     * @param integer $end_year    The last available year for input.
     * @param boolean $picker      Do we show the DHTML calendar?
     * @param integer $format_in   The format to use when sending the date
     *                             for storage. Defaults to Unix epoch.
     *                             Similar to the strftime() function.
     * @param integer $format_out  The format to use when displaying the
     *                             date. Similar to the strftime() function.
     * @param boolean $show_seconds Include a form input for seconds.
     */
    function init($start_year = '', $end_year = '', $picker = true,
                  $format_in = null, $format_out = '%x', $show_seconds = false)
    {
        $this->_mdy = new Horde_Form_Type_monthdayyear();
        $this->_mdy->init($start_year, $end_year, $picker, $format_in, $format_out);

        $this->_hms = new Horde_Form_Type_hourminutesecond();
        $this->_hms->init($show_seconds);
        $this->_show_seconds = $show_seconds;
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        $date = $vars->get($var->getVarName());
        if (!$this->_show_seconds && !isset($date['second'])) {
            $date['second'] = '';
        }
        $mdy_empty = $this->emptyDateArray($date);
        $hms_empty = $this->emptyTimeArray($date);

        $valid = true;

        /* Require all fields if one field is not empty */
        if ($var->isRequired() || $mdy_empty != 1 || !$hms_empty) {
            $old_required = $var->required;
            $var->required = true;

            $mdy_valid = $this->_mdy->isValid($var, $vars, $value, $message);
            $hms_valid = $this->_hms->isValid($var, $vars, $value, $message);
            $var->required = $old_required;

            $valid = $mdy_valid && $hms_valid;
            if ($mdy_valid && !$hms_valid) {
                $message = _("You must choose a time.");
            } elseif ($hms_valid && !$mdy_valid) {
                $message = _("You must choose a date.");
            }
        }

        return $valid;
    }

    function getInfo(&$vars, &$var, &$info)
    {
        /* If any component is empty consider it a bad date and return the
         * default. */
        $value = $var->getValue($vars);
        if ($this->emptyDateArray($value) == 1 || $this->emptyTimeArray($value)) {
            $info = $var->getDefault();
            return;
        }

        $date = $this->getDateOb($value);
        $time = $this->getTimeOb($value);
        $date->hour = $time->hour;
        $date->min = $time->min;
        $date->sec = $time->sec;
        if ($this->getProperty('format_in') === null) {
            $info = $date->timestamp();
        } else {
            $info = $date->strftime($this->getProperty('format_in'));
        }
    }

    function getProperty($property)
    {
        if ($property == 'show_seconds') {
            return $this->_hms->getProperty($property);
        } else {
            return $this->_mdy->getProperty($property);
        }
    }

    function setProperty($property, $value)
    {
        if ($property == 'show_seconds') {
            $this->_hms->setProperty($property, $value);
        } else {
            $this->_mdy->setProperty($property, $value);
        }
    }

    function checktime($hour, $minute, $second)
    {
        return $this->_hms->checktime($hour, $minute, $second);
    }

    function getTimeOb($time_in)
    {
        return $this->_hms->getTimeOb($time_in);
    }

    function getTimeParts($time_in)
    {
        return $this->_hms->getTimeParts($time_in);
    }

    function emptyTimeArray($time)
    {
        return $this->_hms->emptyTimeArray($time);
    }

    function emptyDateArray($date)
    {
        return $this->_mdy->emptyDateArray($date);
    }

    function getDateParts($date_in)
    {
        return $this->_mdy->getDateParts($date_in);
    }

    function getDateOb($date_in)
    {
        return $this->_mdy->getDateOb($date_in);
    }

    function formatDate($date)
    {
        if ($this->_mdy->emptyDateArray($date)) {
            return '';
        }
        return $this->_mdy->formatDate($date);
    }

    function about()
    {
        return array(
            'name' => _("Date and time selection"),
            'params' => array(
                'start_year' => array('label' => _("Start year"),
                                      'type'  => 'int'),
                'end_year'   => array('label' => _("End year"),
                                      'type'  => 'int'),
                'picker'     => array('label' => _("Show picker?"),
                                      'type'  => 'boolean'),
                'format_in'  => array('label' => _("Storage format"),
                                      'type'  => 'text'),
                'format_out' => array('label' => _("Display format"),
                                      'type'  => 'text'),
                'seconds'    => array('label' => _("Show seconds?"),
                                      'type'  => 'boolean')));
    }

}

class Horde_Form_Type_colorpicker extends Horde_Form_Type {

    function isValid(&$var, &$vars, $value, &$message)
    {
        if ($var->isRequired() && empty($value)) {
            $message = _("This field is required.");
            return false;
        }

        if (empty($value) || preg_match('/^#([0-9a-z]){6}$/i', $value)) {
            return true;
        }

        $message = _("This field must contain a color code in the RGB Hex format, for example '#1234af'.");
        return false;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Colour selection"));
    }

}

class Horde_Form_Type_sound extends Horde_Form_Type {

    var $_sounds = array();

    function init()
    {
        foreach (glob($GLOBALS['registry']->get('themesfs', 'horde') . '/sounds/*.wav') as $sound) {
            $this->_sounds[] = basename($sound);
        }
    }

    function getSounds()
    {
        return $this->_sounds;
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        if ($var->isRequired() && empty($value)) {
            $message = _("This field is required.");
            return false;
        }

        if (empty($value) || in_array($value, $this->_sounds)) {
            return true;
        }

        $message = _("Please choose a sound.");
        return false;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Sound selection"));
    }

}

class Horde_Form_Type_sorter extends Horde_Form_Type {

    var $_instance;
    var $_values;
    var $_size;
    var $_header;

    function init($values, $size = 8, $header = '')
    {
        static $horde_sorter_instance = 0;

        /* Get the next progressive instance count for the horde
         * sorter so that multiple sorters can be used on one page. */
        $horde_sorter_instance++;
        $this->_instance = 'horde_sorter_' . $horde_sorter_instance;
        $this->_values = $values;
        $this->_size   = $size;
        $this->_header = $header;
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        return true;
    }

    function getValues()
    {
        return $this->_values;
    }

    function getSize()
    {
        return $this->_size;
    }

    function getHeader()
    {
        if (!empty($this->_header)) {
            return $this->_header;
        }
        return '';
    }

    function getOptions($keys = null)
    {
        $html = '';
        if ($this->_header) {
            $html .= '<option value="">' . htmlspecialchars($this->_header) . '</option>';
        }

        if (empty($keys)) {
            $keys = array_keys($this->_values);
        } else {
            $keys = explode("\t", $keys['array']);
        }
        foreach ($keys as $sl_key) {
            $html .= '<option value="' . $sl_key . '">' . htmlspecialchars($this->_values[$sl_key]) . '</option>';
        }

        return $html;
    }

    function getInfo(&$vars, &$var, &$info)
    {
        $value = $vars->get($var->getVarName());
        $info = explode("\t", $value['array']);
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("Sort order selection"),
            'params' => array(
                'values' => array('label' => _("Values"),
                                  'type'  => 'stringarray'),
                'size'   => array('label' => _("Size"),
                                  'type'  => 'int'),
                'header' => array('label' => _("Header"),
                                  'type'  => 'text')));
    }

}

class Horde_Form_Type_selectfiles extends Horde_Form_Type {

    /**
     * The text to use in the link.
     *
     * @var string
     */
    var $_link_text;

    /**
     * The style to use for the link.
     *
     * @var string
     */
    var $_link_style;

    /**
     *  Create the link with an icon instead of text?
     *
     * @var boolean
     */
    var $_icon;

    /**
     * Contains gollem selectfile selectionID
     *
     * @var string
     */
    var $_selectid;

    function init($selectid, $link_text = null, $link_style = '',
                  $icon = false)
    {
        $this->_selectid = $selectid;
        if (is_null($link_text)) {
            $link_text = _("Select Files");
        }
        $this->_link_text = $link_text;
        $this->_link_style = $link_style;
        $this->_icon = $icon;
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        return true;
    }

    function getInfo(&$var, &$vars, &$info)
    {
        $value = $vars->getValue($var);
        $info = $GLOBALS['registry']->call('files/selectlistResults', array($value));
    }

    function about()
    {
        return array(
            'name' => _("File selection"),
            'params' => array(
                'selectid'   => array('label' => _("Id"),
                                      'type' => 'text'),
                'link_text'  => array('label' => _("Link text"),
                                      'type' => 'text'),
                'link_style' => array('label' => _("Link style"),
                                      'type' => 'text'),
                'icon'       => array('label' => _("Show icon?"),
                                      'type' => 'boolean')));
    }

}

class Horde_Form_Type_assign extends Horde_Form_Type {

    var $_leftValues;
    var $_rightValues;
    var $_leftHeader;
    var $_rightHeader;
    var $_size;
    var $_width;

    function init($leftValues, $rightValues, $leftHeader = '',
                  $rightHeader = '', $size = 8, $width = '200px')
    {
        $this->_leftValues = $leftValues;
        $this->_rightValues = $rightValues;
        $this->_leftHeader = $leftHeader;
        $this->_rightHeader = $rightHeader;
        $this->_size = $size;
        $this->_width = $width;
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        return true;
    }

    function getValues($side)
    {
        return $side ? $this->_rightValues : $this->_leftValues;
    }

    function setValues($side, $values)
    {
        if ($side) {
            $this->_rightValues = $values;
        } else {
            $this->_leftValues = $values;
        }
    }

    function getHeader($side)
    {
        return $side ? $this->_rightHeader : $this->_leftHeader;
    }

    function getSize()
    {
        return $this->_size;
    }

    function getWidth()
    {
        return $this->_width;
    }

    function getOptions($side, $formname, $varname)
    {
        $html = '';
        $headers = false;
        if ($side) {
            $values = $this->_rightValues;
            if (!empty($this->_rightHeader)) {
                $values = array('' => $this->_rightHeader) + $values;
                $headers = true;
            }
        } else {
            $values = $this->_leftValues;
            if (!empty($this->_leftHeader)) {
                $values = array('' => $this->_leftHeader) + $values;
                $headers = true;
            }
        }

        foreach ($values as $key => $val) {
            $html .= '<option value="' . htmlspecialchars($key) . '"';
            if ($headers) {
                $headers = false;
            } else {
                $html .= ' ondblclick="Horde_Form_Assign.move(\'' . $formname . '\', \'' . $varname . '\', ' . (int)$side . ');"';
            }
            $html .= '>' . htmlspecialchars($val) . '</option>';
        }

        return $html;
    }

    function getInfo(&$vars, &$var, &$info)
    {
        $value = $vars->get($var->getVarName() . '__values');
        if (strpos($value, "\t\t") === false) {
            $left = $value;
            $right = '';
        } else {
            list($left, $right) = explode("\t\t", $value);
        }
        if (empty($left)) {
            $info['left'] = array();
        } else {
            $info['left'] = explode("\t", $left);
        }
        if (empty($right)) {
            $info['right'] = array();
        } else {
            $info['right'] = explode("\t", $right);
        }
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("Assignment columns"),
            'params' => array(
                'leftValues'  => array('label' => _("Left values"),
                                       'type'  => 'stringarray'),
                'rightValues' => array('label' => _("Right values"),
                                       'type'  => 'stringarray'),
                'leftHeader'  => array('label' => _("Left header"),
                                       'type'  => 'text'),
                'rightHeader' => array('label' => _("Right header"),
                                       'type'  => 'text'),
                'size'        => array('label' => _("Size"),
                                       'type'  => 'int'),
                'width'       => array('label' => _("Width in CSS units"),
                                       'type'  => 'text')));
    }

}

class Horde_Form_Type_creditcard extends Horde_Form_Type {

    function isValid(&$var, &$vars, $value, &$message)
    {
        if (empty($value) && $var->isRequired()) {
            $message = _("This field is required.");
            return false;
        }

        if (!empty($value)) {
            /* getCardType() will also verify the checksum. */
            $type = $this->getCardType($value);
            if ($type === false || $type == 'unknown') {
                $message = _("This does not seem to be a valid card number.");
                return false;
            }
        }

        return true;
    }

    function getChecksum($ccnum)
    {
        $len = strlen($ccnum);
        if (!is_long($len / 2)) {
            $weight = 2;
            $digit = $ccnum[0];
        } elseif (is_long($len / 2)) {
            $weight = 1;
            $digit = $ccnum[0] * 2;
        }
        if ($digit > 9) {
            $digit = $digit - 9;
        }
        $i = 1;
        $checksum = $digit;
        while ($i < $len) {
            if ($ccnum[$i] != ' ') {
                $digit = $ccnum[$i] * $weight;
                $weight = ($weight == 1) ? 2 : 1;
                if ($digit > 9) {
                    $digit = $digit - 9;
                }
                $checksum += $digit;
            }
            $i++;
        }

        return $checksum;
    }

    function getCardType($ccnum)
    {
        $sum = $this->getChecksum($ccnum);
        $l = strlen($ccnum);

        // Screen checksum.
        if (($sum % 10) != 0) {
            return false;
        }

        // Check for Visa.
        if ((($l == 16) || ($l == 13)) &&
            ($ccnum[0] == 4)) {
            return 'visa';
        }

        // Check for MasterCard.
        if (($l == 16) &&
            ($ccnum[0] == 5) &&
            ($ccnum[1] >= 1) &&
            ($ccnum[1] <= 5)) {
            return 'mastercard';
        }

        // Check for Amex.
        if (($l == 15) &&
            ($ccnum[0] == 3) &&
            (($ccnum[1] == 4) || ($ccnum[1] == 7))) {
            return 'amex';
        }

        // Check for Discover (Novus).
        if (strlen($ccnum) == 16 &&
            substr($ccnum, 0, 4) == '6011') {
            return 'discover';
        }

        // If we got this far, then no card matched.
        return 'unknown';
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Credit card number"));
    }

}

class Horde_Form_Type_obrowser extends Horde_Form_Type {

    function isValid(&$var, &$vars, $value, &$message)
    {
        return true;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Relationship browser"));
    }

}

class Horde_Form_Type_dblookup extends Horde_Form_Type_enum {

    function init($dsn, $sql, $prompt = null)
    {
        require_once 'DB.php';
        $values = array();
        $db = DB::connect($dsn);
        if (!is_a($db, 'PEAR_Error')) {
            // Set DB portability options.
            switch ($db->phptype) {
            case 'mssql':
                $db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS | DB_PORTABILITY_RTRIM);
                break;
            default:
                $db->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS);
            }

            $col = $db->getCol($sql);
            if (!is_a($col, 'PEAR_Error')) {
                $values = array_combine($col, $col);
            }
        }
        parent::init($values, $prompt);
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("Database lookup"),
            'params' => array(
                'dsn' => array('label' => _("DSN (see http://pear.php.net/manual/en/package.database.db.intro-dsn.php)"),
                               'type'  => 'text'),
                'sql' => array('label' => _("SQL statement for value lookups"),
                               'type'  => 'text'),
                'prompt' => array('label' => _("Prompt text"),
                                  'type'  => 'text'))
            );
    }

}

class Horde_Form_Type_figlet extends Horde_Form_Type {

    var $_text;
    var $_font;

    function init($text, $font)
    {
        $this->_text = $text;
        $this->_font = $font;
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        if (empty($value) && $var->isRequired()) {
            $message = _("This field is required.");
            return false;
        }

        if (Horde_String::lower($value) != Horde_String::lower($this->_text)) {
            $message = _("The text you entered did not match the text on the screen.");
            return false;
        }

        return true;
    }

    function getFont()
    {
        return $this->_font;
    }

    function getText()
    {
        return $this->_text;
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("Figlet CAPTCHA"),
            'params' => array(
                'text' => array('label' => _("Text"),
                                'type'  => 'text'),
                'font' => array('label' => _("Figlet font"),
                                'type'  => 'text'))
            );
    }

}

class Horde_Form_Type_captcha extends Horde_Form_Type_figlet {

    /**
     * Return info about field type.
     */
    function about()
    {
        return array(
            'name' => _("Image CAPTCHA"),
            'params' => array(
                'text' => array('label' => _("Text"),
                                'type'  => 'text'),
                'font' => array('label' => _("Font"),
                                'type'  => 'text'))
            );
    }

}

/**
 * @since Horde 3.2
 */
class Horde_Form_Type_category extends Horde_Form_Type {

    function getInfo(&$vars, &$var, &$info)
    {
        $info = $var->getValue($vars);
        if ($info == '*new*') {
            $info = array('new' => true,
                          'value' => $vars->get('new_category'));
        } else {
            $info = array('new' => false,
                          'value' => $info);
        }
    }

    /**
     * Return info about field type.
     */
    function about()
    {
        return array('name' => _("Category"));
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        if (empty($value) && $var->isRequired()) {
            $message = _("This field is required.");
            return false;
        }

        return true;
    }

}

class Horde_Form_Type_invalid extends Horde_Form_Type {

    var $message;

    function init($message)
    {
        $this->message = $message;
    }

    function isValid(&$var, &$vars, $value, &$message)
    {
        return false;
    }

}
