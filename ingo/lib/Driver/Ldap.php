<?php
/**
 * Ingo_Driver_Ldap:: implements the Sieve_Driver api to allow scripts to be
 * installed and set active via an LDAP server.
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author  Jason M. Felice <jason.m.felice@gmail.com>
 * @package Ingo
 */
class Ingo_Driver_Ldap extends Ingo_Driver
{
    /**
     * Constructor.
     *
     * @throws Horde_Exception
     */
    public function __construct($params = array())
    {
        if (!Horde_Util::extensionExists('ldap')) {
            throw new Horde_Exception(_("LDAP support is required but the LDAP module is not available or not loaded."));
        }

        $default_params = array(
            'hostspec' => 'localhost',
            'port' => 389,
            'script_attribute' => 'mailSieveRuleSource'
        );

        parent::__construct(array_merge($default_params, $params));
    }

    /**
     * Create a DN from a DN template.
     * This is done by substituting the username for %u and the 'dc='
     * components for %d.
     *
     * @param string $templ  The DN template (from the config).
     *
     * @return string  The resulting DN.
     */
    protected function _substUser($templ)
    {
        $domain = '';
        $username = $this->_params['username'];

        if (strpos($username, '@') !== false) {
            list($username, $domain) = explode('@', $username);
        }
        $domain = implode(', dc=', explode('.', $domain));
        if (!empty($domain)) {
            $domain = 'dc=' . $domain;
        }

        if (preg_match('/^\s|\s$|\s\s|[,+="\r\n<>#;]/', $username)) {
            $username = '"' . str_replace('"', '\\"', $username) . '"';
        }

        return str_replace(array('%u', '%d'),
                           array($username, $domain),
                           $templ);
    }

    /**
     * Connect and bind to ldap server.
     */
    protected function _connect()
    {
        if (!($ldapcn = @ldap_connect($this->_params['hostspec'],
                                      $this->_params['port']))) {
            return PEAR::raiseError(_("Connection failure"));
        }

        /* Set the LDAP protocol version. */
        if (!empty($this->_params['version'])) {
            @ldap_set_option($ldapcn,
                             LDAP_OPT_PROTOCOL_VERSION,
                             $this->_params['version']);
        }

        /* Start TLS if we're using it. */
        if (!empty($this->_params['tls'])) {
            if (!@ldap_start_tls($ldapcn)) {
                return PEAR::raiseError(sprintf(_("STARTTLS failed: (%s) %s"),
                                                ldap_errno($ldapcn),
                                                ldap_error($ldapcn)));
            }
        }

        /* Bind to the server. */
        if (isset($this->_params['bind_dn'])) {
            $bind_dn = $this->_substUser($this->_params['bind_dn']);
            if (is_a($bind_dn, 'PEAR_Error')) {
                return $bind_dn;
            }

            if (isset($this->_params['bind_password'])) {
                $password = $this->_params['bind_password'];
            } else {
                $password = $this->_params['password'];
            }

            if (!@ldap_bind($ldapcn, $bind_dn, $password)) {
                return PEAR::raiseError(sprintf(_("Bind failed: (%s) %s"),
                                                ldap_errno($ldapcn),
                                                ldap_error($ldapcn)));
            }
        } elseif (!(@ldap_bind($ldapcn))) {
            return PEAR::raiseError(sprintf(_("Bind failed: (%s) %s"),
                                            ldap_errno($ldapcn),
                                            ldap_error($ldapcn)));
        }

        return $ldapcn;
    }

    /**
     * Retrieve current user's scripts.
     *
     * @param resource $ldapcn  The connection to the LDAP server.
     * @param string $userDN    Set to the user object's real DN.
     *
     * @return mixed  Array of script sources, or PEAR_Error on failure.
     */
    protected function _getScripts($ldapcn, &$userDN)
    {
        $attrs = array($this->_params['script_attribute'], 'dn');
        $filter = $this->_substUser($this->_params['script_filter']);

        /* Find the user object. */
        $sr = @ldap_search($ldapcn, $this->_params['script_base'], $filter,
                           $attrs);
        if ($sr === false) {
            return PEAR::raiseError(sprintf(_("Error retrieving current script: (%d) %s"),
                                            ldap_errno($ldapcn),
                                            ldap_error($ldapcn)));
        }
        if (@ldap_count_entries($ldapcn, $sr) != 1) {
            return PEAR::raiseError(sprintf(_("Expected 1 object, got %d."),
                                            ldap_count_entries($ldapcn, $sr)));
        }
        $ent = @ldap_first_entry($ldapcn, $sr);
        if ($ent === false) {
            return PEAR::raiseError(sprintf(_("Error retrieving current script: (%d) %s"),
                                            ldap_errno($ldapcn),
                                            ldap_error($ldapcn)));
        }

        /* Retrieve the user's DN. */
        $v = @ldap_get_dn($ldapcn, $ent);
        if ($v === false) {
            @ldap_free_result($sr);
            return PEAR::raiseError(sprintf(_("Error retrieving current script: (%d) %s"),
                                            ldap_errno($ldapcn),
                                            ldap_error($ldapcn)));
        }
        $userDN = $v;

        /* Retrieve the user's scripts. */
        $attrs = @ldap_get_attributes($ldapcn, $ent);
        @ldap_free_result($sr);
        if ($attrs === false) {
            return PEAR::raiseError(sprintf(_("Error retrieving current script: (%d) %s"),
                                            ldap_errno($ldapcn),
                                            ldap_error($ldapcn)));
        }

        /* Attribute can be in any case, and can have a ";binary"
         * specifier. */
        $regexp = '/^' . preg_quote($this->_params['script_attribute'], '/') .
                  '(?:;.*)?$/i';
        unset($attrs['count']);
        foreach ($attrs as $name => $values) {
            if (preg_match($regexp, $name)) {
                unset($values['count']);
                return array_values($values);
            }
        }

        return array();
    }

    /**
     * Sets a script running on the backend.
     *
     * @param string $script  The sieve script.
     *
     * @return mixed  True on success, PEAR_Error on error.
     */
    protected function setScriptActive($script)
    {
        $ldapcn = $this->_connect();
        if (is_a($ldapcn, 'PEAR_Error')) {
            return $ldapcn;
        }

        $values = $this->_getScripts($ldapcn, $userDN);
        if (is_a($values, 'PEAR_Error')) {
            return $values;
        }

        $found = false;
        foreach ($values as $i => $value) {
            if (strpos($value, "# Sieve Filter\n") !== false) {
                if (empty($script)) {
                    unset($values[$i]);
                } else {
                    $values[$i] = $script;
                }
                $found = true;
                break;
            }
        }
        if (!$found && !empty($script)) {
            $values[] = $script;
        }

        $replace = array(Horde_String::lower($this->_params['script_attribute']) => $values);
        if (empty($values)) {
            $r = @ldap_mod_del($ldapcn, $userDN, $replace);
        } else {
            $r = @ldap_mod_replace($ldapcn, $userDN, $replace);
        }
        if (!$r) {
            return PEAR::raiseError(sprintf(_("Activating the script for \"%s\" failed: (%d) %s"),
                                            $userDN,
                                            ldap_errno($ldapcn),
                                            ldap_error($ldapcn)));
        }

        @ldap_close($ldapcn);
        return true;
    }

    /**
     * Returns the content of the currently active script.
     *
     * @return string  The complete ruleset of the specified user.
     */
    public function getScript()
    {
        $ldapcn = $this->_connect();
        if (is_a($ldapcn, 'PEAR_Error')) {
            return $ldapcn;
        }

        $values = $this->_getScripts($ldapcn, $userDN);
        if (is_a($values, 'PEAR_Error')) {
            return $values;
        }

        $script = '';
        foreach ($values as $value) {
            if (strpos($value, "# Sieve Filter\n") !== false) {
                $script = $value;
                break;
            }
        }

        @ldap_close($ldapcn);
        return $script;
    }

}
