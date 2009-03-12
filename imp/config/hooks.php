<?php
/**
 * IMP Hooks configuration file.
 *
 * THE HOOKS PROVIDED IN THIS FILE ARE EXAMPLES ONLY.  DO NOT ENABLE THEM
 * BLINDLY IF YOU DO NOT KNOW WHAT YOU ARE DOING.  YOU HAVE TO CUSTOMIZE THEM
 * TO MATCH YOUR SPECIFIC NEEDS AND SYSTEM ENVIRONMENT.
 *
 * For more information please see the horde/config/hooks.php.dist file.
 *
 * $Id: e3be18599bf73c669e9f10d4ba137431316885b4 $
 */

// Here is an example signature hook function to set the signature from the
// system taglines file; the string "%TAG%" (if present in a user's signature)
// will be replaced by the content of the file "/usr/share/tagline" (generated
// by the "TaRT" utility).
//
// Notice how we global in the $prefs array to get the user's current
// signature.

// if (!function_exists('_prefs_hook_signature')) {
//     function _prefs_hook_signature($username = null)
//     {
//         $sig = $GLOBALS['prefs']->getValue('signature');
//         if (preg_match('/%TAG%/', $sig)) {
//             $tag = `cat /usr/share/tagline`;
//             $sig = preg_replace('|%TAG%|', $tag, $sig);
//         }
//         return $sig;
//     }
// }

// Here is an example _imp_hook_postlogin function to redirect to a
// custom server after login.

// if (!function_exists('_imp_hook_postlogin')) {
//     function _imp_hook_postlogin($actionID, $isLogin)
//     {
//         header('Location: http://mail' . mt_rand(1, 9) . '.example.com/horde/');
//         exit;
//     }
// }

// This is an example for a post-sending hook that performs an action after
// a message has been sent successfully.
// $message = Base Horde_Mime_part object.
// $headers = Horde_Mime_Headers object.

// if (!function_exists('_imp_hook_postsent')) {
//     function _imp_hook_postsent($message, $headers)
//     {
//          // Do entire action here -- no return value from this hook.
//     }
// }

// Here is an example _imp_hook_trailer function to set the trailer from the
// system taglines file; the string "@@TAG@@" (if present in a trailer) will be
// replaced by the content of the file "/usr/share/tagline" (generated by the
// "TaRT" utility).

// if (!function_exists('_imp_hook_trailer')) {
//     function _imp_hook_trailer($trailer)
//     {
//         if (preg_match('/@@TAG@@/', $trailer)) {
//             $tag = `cat /usr/share/tagline`;
//             $trailer = preg_replace('|@@TAG@@|', $tag, $trailer);
//         }
//         return $trailer;
//     }
// }

// Here is an another example _imp_hook_trailer function to set the trailer
// from the LDAP directory for each domain. This function replaces the current
// trailer with the data it gets from ispmanDomainSignature.

// if (!function_exists('_imp_hook_trailer')) {
//     function _imp_hook_trailer($trailer)
//     {
//         $vdomain = String::lower(preg_replace('|^.*?\.|i', '', getenv('HTTP_HOST')));
//         $ldapServer = 'localhost';
//         $ldapPort = '389';
//         $searchBase = 'ispmanDomain=' . $vdomain  . ",o=ispman";
//
//         $old_error = error_reporting(0);
//         $ds = ldap_connect($ldapServer, $ldapPort);
//         $searchResult = ldap_search($ds, $searchBase, 'uid=' . $vdomain);
//         $information = ldap_get_entries($ds, $searchResult);
//         $trailer= $information[0]['ispmandomainsignature'][0];
//         ldap_close($ds);
//         error_reporting($old_error);
//
//         return $trailer;
//     }
// }

// Here is an example _imp_hook_vinfo function.
//
// If $type == 'username', this function returns a unique username composed of
// username + vdomain. $data is set to the username.
//
// If $type == 'vdomain', this function returns the HTTP_HOST variable after
// removing the 'mail.' subdomain. $data is null.
//
// ex. $HTTP_HOST = 'mail.mydomain.com', $username = 'myname':
//   $vdomain  = 'mydomain.com'
//   $username = 'myname_mydomain_com'
//
// Throw a Horde_Exception object on failure.


// if (!function_exists('_imp_hook_vinfo')) {
//     function _imp_hook_vinfo($type = 'username', $data = null)
//     {
//         $vdomain = String::lower(preg_replace('|^mail\.|i', '', getenv('HTTP_HOST')));
//
//         switch ($type) {
//         case 'username':
//             return preg_replace('|\.|', '_', $data . '_' . $vdomain);
//
//         case 'vdomain':
//             return $vdomain;
//
//         default:
//             throw new Horde_Exception('invalid type: ' . $type);
//         }
//     }
// }

// Here is an example of the _imp_hook_fetchmail_filter function to run
// SpamAssassin on email before it is written to the mailbox.
// Note: to use the spamassassin instead of spamd, change 'spamc' to
// 'spamassassin -P' and add any other important arguments, but realize spamc
// is MUCH faster than spamassassin.
// WARNING: Make sure to use the --noadd-from filter on spamd or spamassassin

// if (!function_exists('_imp_hook_fetchmail_filter')) {
//     function _imp_hook_fetchmail_filter($message)
//     {
//         // Where does SpamAssassin live, and what username should we use
//         // for preferences?
//         $cmd = '/usr/local/bin/spamc';
//         $username = Auth::getAuth();
//
//         // If you use the _sam_hook_username() hook, uncomment the next line
//         //$username = _sam_hook_username($username);
//         $username = escapeshellarg($username);
//
//         // Also, we remove the file ourselves; this hook may be called
//         // hundreds of times per run depending on how many messages we fetch
//         $file = Horde::getTempFile('horde', false);
//
//         // Call SpamAssassin; pipe the new message to our tempfile
//         $fp = popen("$cmd -u $username > $file", 'w');
//         fwrite($fp, $message);
//         pclose($fp);
//
//         // Read the new message from the temporary file
//         $message = file_get_contents($file);
//         unlink($file);
//
//         return $message;
//     }
// }

// Here is an example signature hook function to set the signature from the
// system taglines file; the string "%TAG%" (if present in a user's signature)
// will be replaced by the content of the file "/usr/share/tagline" (generated
// by the "TaRT" utility).

// if (!function_exists('_imp_hook_signature')) {
//     function _imp_hook_signature($sig)
//     {
//         if (preg_match('/%TAG%/', $sig)) {
//             $tag = `cat /usr/share/tagline`;
//             $sig = preg_replace('/%TAG%/', $tag, $sig);
//         }
//
//         return $sig;
//     }
// }

// This is an example hook function for displaying additional message
// information in the message listing screen for a mailbox.  This example hook
// will add a icon if the message contains attachments and will change the
// display of the message entry based on the X-Priority header.
//
// INPUT:
// $mbox - (string) The mailbox.
// $uids - (array) A list of UIDs.
// $mode - (string) Either 'imp' or 'dimp'.
//
// OUTPUT:
// An array of arrays, with UIDs as keys and the following array values:
//
// For IMP:
// 'class' - (array) CSS classnames that will be added to the row.
// 'flagbits' - (integer) Flag mask which will be OR'd with the current flags
//              set for the row.  The flag constants used in IMP can be
//              found at the top of lib/IMP.php.
// 'status' - (string) HTML code to add to the status column for the row.
//
// For DIMP:
// 'atc' - (string) Attachment type (either 'signed', 'encrypted', or
//         'attachment').
// 'class' - (array) CSS classnames that will be added to the row.

// if (!function_exists('_imp_hook_msglist_format')) {
//     function _imp_hook_msglist_format($mbox, $uids, $mode)
//     {
//         try {
//             $imap_res = $GLOBALS['imp_imap']->ob->fetch($mbox, array(
//                 Horde_Imap_Client::FETCH_HEADERS => array(array('headers' => array('x-priority'), 'label' => 'hdr_search', 'parse' => true, 'peek' => true)),
//                 Horde_Imap_Client::FETCH_STRUCTURE => array('parse' => true)
//             ), array('ids' => array_values($uids)));
//         } catch (Horde_Imap_Client_Exception $e) {
//             return array();
//         }
//
//         $alt_list = IMP_UI_Mailbox::getAttachmentAltList();
//         $imp_ui = new IMP_UI_Mailbox($mbox);
//         $imp_msg_ui = new IMP_UI_Message();
//         $ret = array();
//
//         foreach ($uids as $uid) {
//             $tmp = array('status' => '');
//             $res_ptr = &$imap_res[$uid];
//
//             // Add attachment information
//             if (($attachment = $imp_ui->getAttachmentType($res_ptr['structure']->getType()))) {
//                 switch ($mode) {
//                 case 'imp':
//                     $alt_text = (isset($alt_list[$attachment]))
//                         ? $alt_list[$attachment]
//                         : $alt_list['attachment'];
//                     $tmp['status'] = Horde::img($attachment . '.png', $alt_text, array('title' => $alt_text));
//                     break;
//
//                 case 'dimp':
//                     $tmp['atc'] = $attachment;
//                     break;
//                 }
//             }
//
//             // Add X-Priority information
//             switch ($imp_msg_ui->getXpriority($res_ptr['headers']['hdr_search']->getValue('x-priority'))) {
//             case 'high':
//                 if ($mode == 'imp') {
//                     $tmp['flagbits'] = IMP::FLAG_FLAGGED;
//                     $tmp['status'] .= Horde::img('mail_priority_high.png', _("High Priority"), array('title' => _("High Priority")));
//                 }
//                 $tmp['class'][] = 'important';
//                 break;
//
//             case 'low':
//                 if ($mode == 'imp') {
//                     $tmp['status'] .= Horde::img('mail_priority_low.png', _("Low Priority"), array('title' => _("Low Priority")));
//                 }
//                 $tmp['class'][] = 'unimportant';
//                 break;
//             }
//
//             if (!empty($tmp)) {
//                 $ret[$uid] = $tmp;
//             }
//         }
//
//         return $ret;
//     }
// }

// This is an example hook function for the IMP redirection scheme. This
// function is called when the user opens a mailbox in IMP, and allows the
// client to be redirected based on the mailbox name. The return value of this
// function should be a valid page within a horde application which will be
// placed in a "Location" header to redirect the client.  The only parameter
// is the name of the mailbox which the user has opened.  If an empty string
// is returned the user is not redirected.  Throw a Horde_Exception on error.

// if (!function_exists('_imp_hook_mbox_redirect')) {
//     function _imp_hook_mbox_redirect($mailbox)
//     {
//         if ((strpos($mailbox, "INBOX/Calendar") !== false) ||
//             preg_match("!^user/[^/]+/Calendar!", $mailbox)) {
//             return $GLOBALS['registry']->get('webroot', 'kronolith');
//         } elseif ((strpos($mailbox, "INBOX/Tasks") !== false) ||
//                   preg_match("!^user/[^/]+/Tasks!", $mailbox)) {
//             return $GLOBALS['registry']->get('webroot', 'nag');
//         } elseif ((strpos($mailbox, "INBOX/Notes") !== false) ||
//                   preg_match("!^user/[^/]+/Notes!", $mailbox)) {
//             return $GLOBALS['registry']->get('webroot', 'mnemo');
//         } elseif ((strpos($mailbox, "INBOX/Contacts") !== false) ||
//                   preg_match("!^user/[^/]+/Contacts!", $mailbox)) {
//             return $GLOBALS['registry']->get('webroot', 'turba');
//         }
//
//         return '';
//     }
// }

// This is an example hook function for the IMP mailbox icon scheme. This
// function is called when the folder list is created and a "standard" folder
// is to be displayed - it allows custom folder icons to be specified.
// ("Standard" means all folders except the INBOX, sent-mail folders and
// trash folders.)
// If a mailbox name doesn't appear in the below list, the default mailbox
// icon is displayed.

// if (!function_exists('_imp_hook_mbox_icons')) {
//     function _imp_hook_mbox_icons()
//     {
//         static $newmailboxes;
//
//         if (!empty($newmailboxes)) {
//             return $newmailboxes;
//         }
//
//         require_once 'Horde/Kolab.php';
//
//         $kc = new Kolab_Cyrus($GLOBALS['conf']['kolab']['server']);
//         $mailboxes = $kc->listMailBoxes();
//         $newmailboxes = array();
//
//         foreach ($mailboxes as $box) {
//             $box = preg_replace("/^{[^}]+}/", "", $box);
//             if ((strpos($box, "INBOX/Calendar") !== false) ||
//                 preg_match("!^user/[^/]+/Calendar!", $box)) {
//                 $newmailboxes[$box] = array(
//                     'icon' => 'kronolith.png',
//                     'icondir' => $GLOBALS['registry']->getImageDir('kronolith')
//                     'alt' => _("Calendar")
//                 );
//             } elseif ((strpos($box, "INBOX/Tasks") !== false) ||
//                       preg_match("!^user/[^/]+/Tasks!", $box)) {
//                 $newmailboxes[$box] = array(
//                     'icon' => 'nag.png',
//                     'icondir' => $GLOBALS['registry']->getImageDir('nag')
//                     'alt' => _("Tasks")
//                 );
//             } elseif ((strpos($box, "INBOX/Notes") !== false) ||
//                       preg_match("!^user/[^/]+/Notes!", $box)) {
//                 $newmailboxes[$box] = array(
//                     'icon' => 'mnemo.png',
//                     'icondir' => $GLOBALS['registry']->getImageDir('mnemo')
//                     'alt' => _("Notes")
//                 );
//             } elseif ((strpos($box, "INBOX/Contacts") !== false) ||
//                       preg_match("!^user/[^/]+/Contacts!", $box)) {
//                 $newmailboxes[$box] = array(
//                     'icon' => 'turba.png',
//                     'icondir' => $GLOBALS['registry']->getImageDir('turba')
//                     'alt' => _("Contacts")
//                 );
//             }
//         }
//
//         return $newmailboxes;
//     }
// }

// This is an example hook function to set a mailbox read-only. If the hook
// returns true, the given mailbox will be marked read only.

// if (!function_exists('_imp_hook_mbox_readonly')) {
//     function _imp_hook_mbox_readonly($mailbox)
//     {
//         // Make messages in the 'foo' mailbox readonly.
//         return ($mailbox == 'foo');
//     }
// }

// This is an example hook function to disable composing messages. If the hook
// returns true, message composition will be disabled.

// if (!function_exists('_imp_hook_disable_compose')) {
//     function _imp_hook_disable_compose()
//     {
//         // Entirely disable composition.
//         return false;
//     }
// }

// This is an example hook function to hide specified IMAP mailboxes in
// folder listings. If the hook returns false, the mailbox will not be
// displayed.

// if (!function_exists('_imp_hook_display_folder')) {
//     function _imp_hook_display_folder($mailbox) {
//         return ($mailbox == 'DONOTDISPLAY');
//     }
// }

// This is an example hook function for the IMP spam reporting email option.
// This function is called when the message is about to be forwarded - it
// will return the email address to forward to.  This is handy for spam
// reporting software (e.g. DSPAM) which has different e-mail aliases for
// spam reporting for each user.

// if (!function_exists('_imp_hook_spam_email')) {
//     function _imp_hook_spam_email($action)
//     {
//         $prefix = ($action == 'spam') ? 'spam-' : 'fp-';
//         return $prefix . Auth::getBareAuth() . '@example.com';
//     }
// }

// Default Kolab hooks:
if (!empty($GLOBALS['conf']['kolab']['enabled'])) {
    require_once 'Horde/Kolab.php';

    if (!function_exists('_imp_hook_mbox_redirect')) {
        function _imp_hook_mbox_redirect($mailbox)
        {
            switch (Kolab::getMailboxType($mailbox)) {
            case 'event':
                return $GLOBALS['registry']->get('webroot', 'kronolith');

            case 'task':
                return $GLOBALS['registry']->get('webroot', 'nag');

            case 'note':
                return $GLOBALS['registry']->get('webroot', 'mnemo');

            case 'contact':
                return $GLOBALS['registry']->get('webroot', 'turba');

            case 'prefs':
                return $GLOBALS['registry']->get('webroot', 'horde') . '/services/prefs.php?app=horde';

            default:
                return '';
            }
        }

        function _imp_hook_mbox_icons()
        {
            static $icons;

            if (!empty($icons)) {
                return $icons;
            }

            $folders = Kolab::listFolders();
            $icons = array();
            foreach ($folders as $folder) {
                $name = preg_replace('/^{[^}]+}/', '', $folder[0]);

                switch ($folder[1]) {
                case 'event':
                    $icons[$name] = array(
                        'icon' => 'kronolith.png',
                        'icondir' => $GLOBALS['registry']->getImageDir('kronolith'),
                        'alt' => _("Calendar")
                    );
                    break;

                case 'task':
                    $icons[$name] = array(
                        'icon' => 'nag.png',
                        'icondir' => $GLOBALS['registry']->getImageDir('nag'),
                        'alt' => _("Tasks")
                    );
                    break;

                case 'note':
                    $icons[$name] = array(
                        'icon' => 'mnemo.png',
                        'icondir' => $GLOBALS['registry']->getImageDir('mnemo'),
                        'alt' => _("Notes")
                    );
                    break;

                case 'contact':
                    $icons[$name] = array(
                        'icon' => 'turba.png',
                        'icondir' => $GLOBALS['registry']->getImageDir('turba'),
                        'alt' => _("Contacts")
                    );
                    break;

                case 'prefs':
                    $icons[$name] = array(
                        'icon' => 'prefs.png',
                        'icondir' => $GLOBALS['registry']->getImageDir('horde'),
                        'alt' => _("Preferences")
                    );
                    break;
                }
            }

            return $icons;
        }
    }

    if (!function_exists('_imp_hook_display_folder')) {
        function _imp_hook_display_folder($mailbox) {
            $type = Kolab::getMailboxType($mailbox);
            return empty($type) || ($type == 'mail');
        }
    }
}

// Sample function for returning the quota. Uses the PECL ssh2
// extension.
//
// @param array $params Parameters for the function, set in servers.php
//
// @return array Tuple with two members:
//               first: disk space used (in bytes)
//               second: maximum disk space (in bytes)
//               In case of an error, throw a Horde_Exception object.
if (!function_exists('_imp_hook_quota')) {
    function _imp_hook_quota($params = null)
    {
        $host = $_SESSION['imp']['server'];
        $user = $_SESSION['imp']['user'];
        $pass = Auth::getCredential('password');
        $command = $params[0];

        $session = ssh2_connect($host);
        if (!$session) {
            throw new Horde_Exception(_("Connection to server failed."), 'horde.error');
        }

        if (!ssh2_auth_password($session, $user, $pass)) {
            throw new Horde_Exception(_("Authentication failed."), 'horde.error');
        }

        $stream = ssh2_exec($session, $command, false);
        stream_set_blocking($stream, true);

        $quota = preg_split('/\s+/', trim(stream_get_contents($stream)), 2);
        return array($quota[1] * 1024, $quota[2] * 1024);
    }
}


// This is an example hook function for the dynamic (dimp) mailbox view. This
// function is allows additional information to be added to the array that is
// is passed to the mailbox display template -
// imp/templates/javascript/mailbox-dimp.js.  The current entry array is
// passed in, the value returned should be the altered array to use in the
// template. If you are going to add new columns, you also have to update
// imp/templates/index/dimp.inc to contain the new field in the header and
// imp/themes/screen-dimp.css to specify the column width.

// if (!function_exists('_imp_hook_dimp_mailboxarray')) {
//     function _imp_hook_dimp_mailboxarray($msgs) {
//         foreach (array_keys($msgs) as $key) {
//             $msgs[$key]['foo'] = true;
//         }
//
//         return $msg;
//     }
// }

// This is an example hook function for the dynamic (dimp) message view.  This
// function allows additional information to be added to the array that is
// passed to the message text display template -
// imp/templates/chunks/message.php. The current entry array is passed in
// (see the showMessage() function in lib/Views/ShowMessage.php for the
// format). The value returned should be the altered array to use in the
// template.

// if (!function_exists('_imp_hook_dimp_messageview')) {
//     function _imp_hook_dimp_messageview($msg) {
//         // Ex.: Add a new foo variable
//         $msg['foo'] = '<div class="foo">BAR</div>';
//         return $msg;
//     }
// }

// This is an example hook function for the dynamic (dimp) preview view.  This
// function allows additional information to be added to the preview view and
// its corresponding template - imp/templates/index/index-dimp.inc. The
// current entry array is passed in (see the showMessage() function in
// lib/Views/ShowMessage.php for the format). Since the preview pane is
// dynamically updated via javascript, all updates other than the base
// entries must be provided in javascript code to be run at update time. The
// expected return is a 2 element array - the first element is the original
// array with any changes made to the initial data. The second element is an
// array of javascript commands, one command per array value.

// if (!function_exists('_imp_hook_dimp_previewview')) {
//     function _imp_hook_dimp_previewview($msg) {
//         // Ex.: Alter the subject
//         $msg['subject'] .= 'test';
//
//         // Ex.: Update the DOM ID 'foo' with the value 'bar'. 'foo' needs
//         //      to be manually added to the HTML template.
//         $js_code = array(
//             "$('foo').update('bar')"
//         );
//
//         return array($msg, $js_code);
//     }
// }

// This is an example hook function for the address formatting in email
// message headers. The argument passed to the function is an object with the
// following possible properties:
//   'address' - Full address
//   'display' - Display address
//   'host' - Host name
//   'inner' - Trimmed, bare address
//   'personal' - Personal string
// The return value is the raw string to display for that address. This value
// must be properly escaped (i.e. htmlspecialchars() used on the portions of
// the string where appropriate).

// if (!function_exists('_imp_hook_dimp_addressformatting')) {
//     function _dimp_hook_addressformatting($ob) {
//         return empty($ob['personal']) ? $ob['address'] : $ob['personal'];
//     }
// }
