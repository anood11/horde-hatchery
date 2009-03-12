<?php
/**
 * This file specifies which mail servers people using your installation of
 * IMP can login to.
 *
 * Properties that can be set for each server:
 *
 * name: (string) This is the plaintext, English name that you want displayed
 *       if using the drop down server list.
 *
 * hostspec: (string) The hostname/IP address of the mail server to connect to.
 *
 * hordeauth: (mixed) One of the following values:
 *            true - IMP will attempt to use the user's existing credentials
 *                   (the username/password they used to log in to Horde) to
 *                   login to this source.
 *            false - Everything after and including the first @ in the
 *                    username will be stripped off before attempting
 *                    authentication. (DEFAULT)
 *            'full' - The username will be used unmodified.
 *
 * protocol: (string) Either 'pop' or 'imap' (DEFAULT).
 *
 *           'imap' requires a IMAP4rev1 (RFC 3501) compliant server.
 *
 *           'pop' requires a POP3 (RFC 1939) compliant server. All folder
 *           options will be automatically turned off (POP3 does
 *           not support folders).  Other advanced functions will also be
 *           disabled (e.g. caching, searching, sorting).
 *
 * secure: (mixed) One of the following values:
 *         'ssl' - Use SSL to connect to the server.
 *         'tls' - Use TLS to connect to the server.
 *         false - Do not use any encryption (DEFAULT).
 *
 *         The 'ssl' and 'tls' options will only work if you've compiled PHP
 *         with SSL support and the mail server supports secure connections.
 *
 * port: (integer) The port that the mail service/protocol you selected runs
 *       on. Default values:
 *         pop (unsecure or w/TLS):   110
 *         pop (w/SSL):               995
 *         imap (unsecure or w/TLS):  143
 *         imap (w/SSL):              993
 *
 * maildomain: (string) What to put after the @ when sending mail. This setting
 *             is generally useful when the sending host is different from the
 *             mail receiving host. This setting will also be used to complete
 *             unqualified addresses when composing mail.
 *             E.g. If you want all mail to look like 'From: user@example.com',
 *             set maildomain to 'example.com'.
 *
 * smtphost: (string) If specified, and $conf['mailer']['type'] is set to
 *           'smtp', IMP will use this host for outbound SMTP connections.
 *           This value overrides any existing
 *           $conf['mailer']['params']['host'] value at runtime.
 *
 * smtpport: (integer) If specified, and $conf['mailer']['type'] is set to
 *           'smtp', IMP will use this port for outbound SMTP connections.
 *           This value overrides any existing
 *           $conf['mailer']['params']['port'] value at runtime.
 *
 * realm: (string) ONLY USE REALM IF YOU ARE USING IMP FOR HORDE
 *        AUTHENTICATION AND YOU HAVE MULTIPLE SERVERS AND USERNAMES OVERLAP
 *        BETWEEN THOSE SERVERS.
 *
 *        If you only have one server, or have multiple servers with no
 *        username clashes, or have full user@example.com usernames, you DO
 *        NOT need a realm setting. If you set one, a '@' symbol plus the
 *        realm value will be appended to the username that users login to
 *        IMP with to create the username that Horde treats the user as.
 *
 *        Example: with a realm of 'example.com', the username 'jane' would
 *        be treated by Horde (NOT your IMAP/POP server) as 'jane@example.com',
 *        and the username 'jane@example.com' would be treated as
 *        'jane@example.com@example.com' - an occasion where you probably
 *        don't need a realm setting.
 *
 * preferred: (string) Only useful if you want to use the same servers.php file
 *            for different machines: if the hostname of the IMP machine is
 *            identical to one of those in the preferred list, then the
 *            corresponding option in the select box will include SELECTED
 *            (i.e. it is selected per default). Otherwise the first entry
 *            in the list is selected.
 *
 * quota: (array) Use this if you want to display a user's quota status on
 *        various IMP pages. Set 'driver' equal to the mailserver and 'params'
 *        equal to any extra parameters needed by the driver (see the
 *        comments located at the top of imp/lib/Quota/[quotadriver].php
 *        for the parameters needed for each driver). Set
 *        'hide_when_unlimited' to true if you want to hide quota output
 *        when the server reports an unlimited quota.
 *
 *        Set this to an empty value to disable quota (the DEFAULT).
 *
 *        The optional 'unit' parameter indicates what storage unit the quota
 *        messages hould be displayed in. It can be one of three possible
 *        values: 'GB', 'MB' (DEFAULT), or 'KB'.
 *
 *        The optional 'format' parameter is supported by all drivers and
 *        specifies the formats of the quota messages in the user
 *        interface. The parameter must be specified as a hash with the four
 *        possible elements 'long', 'short', 'nolimit_long', and
 *        'nolimit_short' with according versions of the quota message. The
 *        strings will be passed through sprintf().
 *        These are the built-in default values, though they may appear
 *        differently in some translations ([UNIT] will be replaced with the
 *        value of the 'unit' parameter):
 *          'long'          -- Quota status: %.2f [UNIT] / %.2f [UNIT] (%.2f%%)
 *          'short'         -- %.0f%% of %.0f [UNIT]
 *          'nolimit_long'  -- Quota status: %.2f [UNIT] / NO LIMIT
 *          'nolimit_short' -- %.0f [UNIT]
 *
 *        Currently available drivers:
 *          'command'    --  Use the UNIX quota command to handle quotas.
 *          'hook'       --  Use the _imp_hook_quota function to handle quotas.
 *          'imap'       --  Use the IMAP QUOTA extension to handle quotas.
 *                           You must be connecting to a IMAP server capable
 *                           of the QUOTAROOT command to use this driver.
 *          'logfile'    --  Allow quotas on servers where IMAP Quota
 *                           commands are not supported, but quota info
 *                           appears in the servers messages log for the IMAP
 *                           server.
 *          'maildir'    --  Use Maildir++ quota files to handle quotas.
 *          'mdaemon'    --  Use Mdaemon servers to handle quotas.
 *          'mercury32'  --  Use Mercury/32 servers to handle quotas.
 *          'sql'        --  Use arbitrary SQL queries to handle quotas.
 *
 * admin: (array) Use this if you want to enable mailbox management for
 *        administrators via Horde's user administration interface.  The
 *        mailbox management gets enabled if you let IMP handle the Horde
 *        authentication with the 'application' authentication driver.  Your
 *        IMAP server needs to support mailbox management via IMAP commands.
 *        Do not define this value if you do not want mailbox management.
 *
 * acl: (boolean) Set to true if you want to use Access Control Lists (folder
 *      sharing). Set to false to disable (DEFAULT). Not all IMAP servers
 *      support this feature.
 *
 * cache: (mixed) Enables caching for the server. This requires configuration
 *        of a Horde_Cache driver in Horde. Will be disabled on any empty
 *        value and enabled on any non-false value.
 *
 *        The following optional configuration items are available:
 *        'compress' - (string) Should the contents of the cache files be
 *                     compressed before they are stored? This results in
 *                     reduced memory usage when retrieving the data at the
 *                     expense of slightly increased CPU usage. Either
 *                     false (no compression - DEFAULT), 'gzip' or 'lzf'.
 *                     'gzip' provides greater compression, and is generally
 *                     built into PHP, but is slower. 'lzf' requires
 *                     installation of a separate PECL module and provides
 *                     less compression but is extremely fast (within 5% of
 *                     regular string operations).  If available, 'lzf' is
 *                     recommended.
 *        'lifetime' - (integer) The lifetime, in seconds, of the cached
 *                     data.
 *        'slicesize' - (integer) The number of messages stored in each
 *                      cache slice.  The default should be fine for most
 *                      everyone.
 *
 * 'debug' - (string) If set, will output debug information from the IMAP
 *                    library.  The value can be any PHP supported wrapper
 *                    that can be opened via fopen().
 *
 *
 * *** The following options should NOT be set unless you REALLY know what ***
 * *** you are doing! FOR MOST PEOPLE, AUTO-DETECTION OF THESE PARAMETERS  ***
 * *** (the default if the parameters are not set) SHOULD BE USED!         ***
 *
 * namespace: (array) The list of namespaces that exist on the server. The
 *            entries must be encoded in the UTF7-IMAP charset. Example:
 *
 *              'namespace' => array('#shared/', '#news/', '#public/')
 *
 *            This parameter should only be used if you want to allow access
 *            to namespaces that may not be publicly advertised by the IMAP
 *            server (see RFC 2342 [3]). These additional namespaces will be
 *            added to the list of available namespaces returned by the
 *            server.  This entry is only pertinent for IMAP servers.
 *
 * timeout: (integer) Set the server timeout (in seconds).
 *
 * comparator: (string) The search comparator to use instead of the default
 *             IMAP server comparator. See RFC 4790 [3.1] - "collation-id" -
 *             for the format. Your IMAP server must support the I18NLEVEL
 *             extension for this setting to have an effect. By default,
 *             the server default comparator is used.
 *
 * id: (array) Send ID information to the IMAP server. This must be a an array
 *     with the keys being the fields to send and the values being the
 *     associated values. Your IMAP server must support the ID extension for
 *     this setting to have an effect. See RFC 2971 [3.3] for a list of
 *     defined field values.
 *
 * lang: (array) A list of languages (in priority order) to be used to display
 *       display human readable messages returned by the IMAP server. Your
 *       IMAP server must support the LANGUAGE extensions for this setting to
 *       have an effect. By default, IMAP messages are output in the IMAP
 *       server default language.
 *
 * $Id: 46e9c7f0252be07183ba28c2c32ab60f6d9b6631 $
 */

/* Any entries whose key value ('foo' in $servers['foo']) begin with '_'
 * (an underscore character) will be treated as prompts, and you won't be
 * able to log in to them. The only property these entries need is 'name'.
 * This lets you put labels in the list, like this example: */
$servers['_prompt'] = array(
    'name' => _("Choose a mail server:")
);

/* Example configurations: */

if ($GLOBALS['conf']['kolab']['enabled']) {

    if (isset($_SESSION['imp']['uniquser']) && isset($_SESSION['imp']['pass'])) {
        require_once 'Horde/Kolab/Session.php';
        $session = Horde_Kolab_Session::singleton($_SESSION['imp']['uniquser'],
                                                  array('password' => Secret::read(Secret::getKey('imp'), $_SESSION['imp']['pass'])));
        $imapParams = $session->getImapParams();
        if (is_a($imapParams, 'PEAR_Error')) {
            $useDefaults = true;
        } else {
            $useDefaults = false;
        }
        $_SESSION['imp']['uniquser'] = $session->user_mail;
    } else {
        $useDefaults = true;
    }

    if ($useDefaults) {
        require_once 'Horde/Kolab.php';

        if (is_callable('Kolab', 'getServer')) {
            $server = Kolab::getServer('imap');
            if (is_a($server, 'PEAR_Error')) {
                $useDefaults = true;
            } else {
                $useDefaults = false;
            }
        } else {
            $useDefaults = true;
        }

        if ($useDefaults) {
            $server = $GLOBALS['conf']['kolab']['imap']['server'];
        }

        $imapParams = array(
			'hostspec' => $server,
			'port'     => $GLOBALS['conf']['kolab']['imap']['port'],
                        'protocol' => 'imap',
        );
    }

    $servers['kolab'] = array(
        'name'       => 'Kolab Cyrus IMAP Server',
        'hordeauth'  => 'full',
        'server'     => $imapParams['hostspec'],
        'port'       => $imapParams['port'],
        'protocol'   => $imapParams['protocol'],
        'maildomain' => $GLOBALS['conf']['kolab']['imap']['maildomain'],
        'realm'      => '',
        'preferred'  => '',
        'quota'      => array(
            'driver' => 'imap',
            'params' => array('hide_quota_when_unlimited' => true),
        ),
        'acl'        => array(
            'driver' => 'rfc2086',
        ),
        'login_tries' => 1,
    );
}
