<?php
/**
 * The IMP_Crypt_Smime:: class contains all functions related to handling
 * S/MIME messages within IMP.
 *
 * Copyright 2002-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Mike Cochrane <mike@graftonhall.co.nz>
 * @package IMP
 */
class IMP_Crypt_Smime extends Horde_Crypt_Smime
{
    /* Name of the S/MIME public key field in addressbook. */
    const PUBKEY_FIELD = 'smimePublicKey';

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(array('temp' => Horde::getTempDir()));
    }

    /**
     * Add the personal public key to the prefs.
     *
     * @param mixed $key  The public key to add (either string or array).
     */
    public function addPersonalPublicKey($key)
    {
        $GLOBALS['prefs']->setValue('smime_public_key', (is_array($key)) ? implode('', $key) : $key);
    }

    /**
     * Add the personal private key to the prefs.
     *
     * @param mixed $key  The private key to add (either string or array).
     */
    public function addPersonalPrivateKey($key)
    {
        $GLOBALS['prefs']->setValue('smime_private_key', (is_array($key)) ? implode('', $key) : $key);
    }

    /**
     * Add the list of additional certs to the prefs.
     *
     * @param mixed $key  The private key to add (either string or array).
     */
    public function addAdditionalCert($key)
    {
        $GLOBALS['prefs']->setValue('smime_additional_cert', (is_array($key)) ? implode('', $key) : $key);
    }

    /**
     * Get the personal public key from the prefs.
     *
     * @return string  The personal S/MIME public key.
     */
    public function getPersonalPublicKey()
    {
        return $GLOBALS['prefs']->getValue('smime_public_key');
    }

    /**
     * Get the personal private key from the prefs.
     *
     * @return string  The personal S/MIME private key.
     */
    public function getPersonalPrivateKey()
    {
        return $GLOBALS['prefs']->getValue('smime_private_key');
    }

    /**
     * Get any additional certificates from the prefs.
     *
     * @return string  Additional signing certs for inclusion.
     */
    public function getAdditionalCert()
    {
        return $GLOBALS['prefs']->getValue('smime_additional_cert');
    }

    /**
     * Deletes the specified personal keys from the prefs.
     */
    public function deletePersonalKeys()
    {
        $GLOBALS['prefs']->setValue('smime_public_key', '');
        $GLOBALS['prefs']->setValue('smime_private_key', '');
        $GLOBALS['prefs']->setValue('smime_additional_cert', '');
        $this->unsetPassphrase();
    }

    /**
     * Add a public key to an address book.
     *
     * @param string $cert  A public certificate to add.
     *
     * @throws Horde_Exception
     */
    public function addPublicKey($cert)
    {
        /* Make sure the certificate is valid. */
        $key_info = openssl_x509_parse($cert);
        if (!is_array($key_info) || !isset($key_info['subject'])) {
            throw new Horde_Exception(_("Not a valid public key."));
        }

        /* Add key to the user's address book. */
        $email = $this->getEmailFromKey($cert);
        if (is_null($email)) {
            throw new Horde_Exception(_("No email information located in the public key."));
        }

        /* Get the name corresponding to this key. */
        if (isset($key_info['subject']['CN'])) {
            $name = $key_info['subject']['CN'];
        } elseif (isset($key_info['subject']['OU'])) {
            $name = $key_info['subject']['OU'];
        } else {
            throw new Horde_Exception(_("Not a valid public key."));
        }

        $GLOBALS['registry']->call('contacts/addField', array($email, $name, self::PUBKEY_FIELD, $cert, $GLOBALS['prefs']->getValue('add_source')));
    }

    /**
     * Returns the params needed to encrypt a message being sent to the
     * specified email address.
     *
     * @param string $address  The e-mail address of the recipient.
     *
     * @return array  The list of parameters needed by encrypt().
     * @throws Horde_Exception
     */
    protected function _encryptParameters($address)
    {
        /* We can only encrypt if we are sending to a single person. */
        $addrOb = Horde_Mime_Address::bareAddress($address, $_SESSION['imp']['maildomain'], true);
        $key_addr = array_pop($addrOb);

        $public_key = $this->getPublicKey($key_addr);

        return array(
            'pubkey' => $public_key,
            'type' => 'message'
        );
    }

    /**
     * Retrieves a public key by e-mail.
     * The key will be retrieved from a user's address book(s).
     *
     * @param string $address  The e-mail address to search for.
     *
     * @return string  The S/MIME public key requested.
     * @throws Horde_Exception
     */
    public function getPublicKey($address)
    {
        try {
            $key = Horde::callHook('smime_key', array($address), 'imp');
            if ($key) {
                return $key;
            }
        } catch (Horde_Exception_HookNotSet $e) {
        }

        $params = IMP_Compose::getAddressSearchParams();

        try {
            $key = $GLOBALS['registry']->call('contacts/getField', array($address, self::PUBKEY_FIELD, $params['sources'], false, true));
        } catch (Horde_Exception $e) {
            /* See if the address points to the user's public key. */
            $identity = Horde_Prefs_Identity::singleton(array('imp', 'imp'));
            $personal_pubkey = $this->getPersonalPublicKey();
            if (!empty($personal_pubkey) && $identity->hasAddress($address)) {
                return $personal_pubkey;
            }

            throw $e;
        }

        /* If more than one public key is returned, just return the first in
         * the array. There is no way of knowing which is the "preferred" key,
         * if the keys are different. */
        return is_array($key) ? reset($key) : $key;
    }

    /**
     * Retrieves all public keys from a user's address book(s).
     *
     * @return array  All PGP public keys available.
     * @throws Horde_Exception
     */
    public function listPublicKeys()
    {
        $params = IMP_Compose::getAddressSearchParams();
        if (empty($params['sources'])) {
            return array();
        }
        return $GLOBALS['registry']->call('contacts/getAllAttributeValues', array(self::PUBKEY_FIELD, $params['sources']));
    }

    /**
     * Deletes a public key from a user's address book(s) by e-mail.
     *
     * @param string $email  The e-mail address to delete.
     *
     * @throws Horde_Exception
     */
    public function deletePublicKey($email)
    {
        $params = IMP_Compose::getAddressSearchParams();
        $GLOBALS['registry']->call('contacts/deleteField', array($email, self::PUBKEY_FIELD, $params['sources']));
    }

    /**
     * Returns the parameters needed for signing a message.
     *
     * @return array  The list of parameters needed by encrypt().
     */
    protected function _signParameters()
    {
        return array(
            'type' => 'signature',
            'pubkey' => $this->getPersonalPublicKey(),
            'privkey' => $this->getPersonalPrivateKey(),
            'passphrase' => $this->getPassphrase(),
            'sigtype' => 'detach',
            'certs' => $this->getAdditionalCert()
        );
    }

    /**
     * Verifies a signed message with a given public key.
     *
     * @param string $text  The text to verify.
     *
     * @return stdClass  See Horde_Crypt_Smime::verify().
     * @throws Horde_Exception
     */
    public function verifySignature($text)
    {
        return $this->verify($text, empty($GLOBALS['conf']['openssl']['cafile']) ? array() : $GLOBALS['conf']['openssl']['cafile']);
    }


    /**
     * Decrypt a message with user's public/private keypair.
     *
     * @param string $text  The text to decrypt.
     *
     * @return string  See Horde_Crypt_Smime::decrypt().
     * @throws Horde_Exception
     */
    public function decryptMessage($text)
    {
        return $this->decrypt($text, array('type' => 'message', 'pubkey' => $this->getPersonalPublicKey(), 'privkey' => $this->getPersonalPrivateKey(), 'passphrase' => $this->getPassphrase()));
    }

    /**
     * Gets the user's passphrase from the session cache.
     *
     * @return mixed  The passphrase, if set.  Returns false if the passphrase
     *                has not been loaded yet.  Returns null if no passphrase
     *                is needed.
     */
    public function getPassphrase()
    {
        $private_key = $GLOBALS['prefs']->getValue('smime_private_key');
        if (empty($private_key)) {
            return false;
        }

        if (isset($_SESSION['imp']['smime']['passphrase'])) {
            return Horde_Secret::read(Horde_Secret::getKey('imp'), $_SESSION['imp']['smime']['passphrase']);
        } elseif (isset($_SESSION['imp']['smime']['null_passphrase'])) {
            return ($_SESSION['imp']['smime']['null_passphrase']) ? null : false;
        } else {
            $result = $this->verifyPassphrase($private_key, null);
            if (!isset($_SESSION['imp']['smime'])) {
                $_SESSION['imp']['smime'] = array();
            }
            $_SESSION['imp']['smime']['null_passphrase'] = ($result) ? null : false;
            return $_SESSION['imp']['smime']['null_passphrase'];
        }
    }

    /**
     * Store's the user's passphrase in the session cache.
     *
     * @param string $passphrase  The user's passphrase.
     *
     * @return boolean  Returns true if correct passphrase, false if incorrect.
     */
    public function storePassphrase($passphrase)
    {
        if ($this->verifyPassphrase($this->getPersonalPrivateKey(), $passphrase) === false) {
            return false;
        }

        if (!isset($_SESSION['imp']['smime'])) {
            $_SESSION['imp']['smime'] = array();
        }
        $_SESSION['imp']['smime']['passphrase'] = Horde_Secret::write(Horde_Secret::getKey('imp'), $passphrase);

        return true;
    }

    /**
     * Clear the passphrase from the session cache.
     */
    public function unsetPassphrase()
    {
        unset($_SESSION['imp']['smime']['null_passphrase'], $_SESSION['imp']['smime']['passphrase']);
    }

    /**
     * Generates the javascript code for saving public keys.
     *
     * @param string $mailbox  The mailbox of the message.
     * @param integer $uid     The UID of the message.
     * @param string $id       The MIME ID of the message.
     *
     * @return string  The URL for saving public keys.
     */
    public function savePublicKeyURL($mailbox, $uid, $id)
    {
        $params = array(
            'actionID' => 'save_attachment_public_key',
            'mailbox' => $mailbox,
            'uid' => $uid,
            'mime_id' => $id
        );
        return Horde::popupJs(Horde::applicationUrl('smime.php'), array('params' => $params, 'height' => 200, 'width' => 450));
    }

    /**
     * Encrypt a MIME_Part using S/MIME using IMP defaults.
     *
     * @param MIME_Part $mime_part  The MIME_Part object to encrypt.
     * @param mixed $to_address     The e-mail address of the key to use for
     *                              encryption.
     *
     * @return MIME_Part  See Horde_Crypt_Smime::encryptMIMEPart().
     * @throws Horde_Exception
     */
    public function IMPencryptMIMEPart($mime_part, $to_address)
    {
        return $this->encryptMIMEPart($mime_part, $this->_encryptParameters($to_address));
    }

    /**
     * Sign a MIME_Part using S/MIME using IMP defaults.
     *
     * @param MIME_Part $mime_part  The MIME_Part object to sign.
     *
     * @return MIME_Part  See Horde_Crypt_Smime::signMIMEPart().
     * @throws Horde_Exception
     */
    public function IMPsignMIMEPart($mime_part)
    {
        return $this->signMIMEPart($mime_part, $this->_signParameters());
    }

    /**
     * Sign and encrypt a MIME_Part using S/MIME using IMP defaults.
     *
     * @param MIME_Part $mime_part  The MIME_Part object to sign and encrypt.
     * @param string $to_address    The e-mail address of the key to use for
     *                              encryption.
     *
     * @return MIME_Part  See Horde_Crypt_Smime::signAndencryptMIMEPart().
     * @throws Horde_Exception
     */
    public function IMPsignAndEncryptMIMEPart($mime_part, $to_address)
    {
        return $this->signAndEncryptMIMEPart($mime_part, $this->_signParameters(), $this->_encryptParameters($to_address));
    }

    /**
     * Store the public/private/additional certificates in the preferences
     * from a given PKCS 12 file.
     *
     * @param string $pkcs12    The PKCS 12 data.
     * @param string $password  The password of the PKCS 12 file.
     * @param string $pkpass    The password to use to encrypt the private key.
     *
     * @throws Horde_Exception
     */
    public function addFromPKCS12($pkcs12, $password, $pkpass = null)
    {
        $sslpath = empty($GLOBALS['conf']['openssl']['path'])
            ? null
            : $GLOBALS['conf']['openssl']['path'];

        $params = array('sslpath' => $sslpath, 'password' => $password);
        if (!empty($pkpass)) {
            $params['newpassword'] = $pkpass;
        }

        $result = $this->parsePKCS12Data($pkcs12, $params);
        $this->addPersonalPrivateKey($result->private);
        $this->addPersonalPublicKey($result->public);
        $this->addAdditionalCert($result->certs);
    }

    /**
     * Extract the contents from signed S/MIME data.
     *
     * @param string $data  The signed S/MIME data.
     *
     * @return string  The contents embedded in the signed data.
     * @throws Horde_Exception
     */
    public function extractSignedContents($data)
    {
        $sslpath = empty($GLOBALS['conf']['openssl']['path'])
            ? null
            : $GLOBALS['conf']['openssl']['path'];

        return parent::extractSignedContents($data, $sslpath);
    }

}
