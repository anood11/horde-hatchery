<?php
/**
 * Decide which output drivers you want to activate for the IMP application.
 * Settings in this file override settings in horde/config/mime_drivers.php.
 * All drivers configured in that file, but not configured here, will also
 * be used to display MIME content.
 *
 * Additional settings for IMP:
 * + If you want to limit the display of message data inline for large
 *   messages of a certain type, add a 'limit_inline_size' parameter to the
 *   desired mime type to the maximum size of the displayed message in bytes
 *   (see example under text/plain below).  If set, the user will only be able
 *   to download the part.  Don't set the parameter, or set to 0, to disable
 *   this check.
 *
 * $Id: 7c731034bee6180440ad550f9252c813ebec0a05 $
 */

/**
 * The available drivers are:
 * --------------------------
 * alternative    multipart/alternative parts
 * appledouble    multipart/appledouble parts
 * enriched       Enriched text messages
 * html           HTML messages
 * images         Images
 * itip           iCalendar Transport-Independent Interoperability Protocol
 * mdn            Message disposition notification messages
 * partial        message/partial parts
 * pdf            Portable Document Format (PDF) files
 * pgp            PGP signed/encrypted messages
 * plain          URL syntax highlighting for text/plain parts
 * related        multipart/related parts
 * smil           SMIL documents
 * smime          S/MIME signed/encrypted messages
 * status         Mail delivery status messages
 * tnef           MS-TNEF attachments
 * zip            ZIP attachments
 */
$mime_drivers_map['imp']['registered'] = array(
    'alternative', 'appledouble', 'enriched', 'html', 'images', 'itip',
    'mdn', 'partial', 'pdf', 'pgp', 'plain', 'related', 'smil', 'smime',
    'status', 'tnef', 'zip'
);

/**
 * If you want to specifically override any MIME type to be handled by
 * a specific driver, then enter it here.  Normally, this is safe to
 * leave, but it's useful when multiple drivers handle the same MIME
 * type, and you want to specify exactly which one should handle it.
 */
$mime_drivers_map['imp']['overrides'] = array();

/**
 * Driver specific settings. See horde/config/mime_drivers.php for
 * the format.
 */

/**
 * Text driver settings
 */
$mime_drivers['imp']['plain'] = array(
    'inline' => true,
    'handles' => array(
        'text/plain', 'text/rfc822-headers', 'application/pgp'
    ),
    /* If you want to limit the display of message data inline for large
     * messages, set the maximum size of the displayed message here (in
     * bytes).  If exceeded, the user will only be able to download the part.
     * Set to 0 to disable this check. */
    'limit_inline_size' => 1048576,
    /* If you want to scan ALL incoming text/plain messages for UUencoded
     * data, set the following to true. This is very performance intensive and
     * can take a long time for large messages. It is not recommended (as
     * UUencoded data is very rare anymore) and is disabled by default. */
    'uudecode' => false
);

/**
 * HTML driver settings
 */
$mime_drivers['imp']['html'] = array(
    /* NOTE: Inline HTML display is turned OFF by default. */
    'inline' => false,
    'handles' => array(
        'text/html'
    ),
    'icons' => array(
        'default' => 'html.png'
    ),
    /* If you want to limit the display of message data inline for large
     * messages, set the maximum size of the displayed message here (in
     * bytes).  If exceeded, the user will only be able to download the part.
     * Set to 0 to disable this check. */
    'limit_inline_size' => 1048576,
    /* Run 'tidy' on all HTML output? This requires at least version 2.0 of the
     * PECL 'tidy' extension to be installed on your system. */
    'tidy' => false,
    /* Check for phishing exploits? */
    'phishing_check' => true
);

/**
 * Default smil driver settings
 */
$mime_drivers['imp']['smil'] = array(
    'inline' => true,
    'handles' => array(
        'application/smil'
    )
);

/**
 * Image driver settings
 */
$mime_drivers['imp']['images'] = array(
    'inline' => true,
    'handles' => array(
        'image/*'
    ),
    'icons' => array(
        'default' => 'image.png'
    ),
    /* Display thumbnails for all images, not just large images? */
    'allthumbs' => true,
    /* Display images inline that are less than this size (in bytes). */
    'inlinesize' => 262144
);

/**
 * Enriched text driver settings
 */
$mime_drivers['imp']['enriched'] = array(
    'inline' => true,
    'handles' => array(
        'text/enriched'
    ),
    'icons' => array(
        'default' => 'text.png'
    )
);

/**
 * PDF settings
 */
$mime_drivers['imp']['pdf'] = array(
    'inline' => false,
    'handles' => array(
        'application/pdf', 'application/x-pdf', 'image/pdf'
    ),
    'icons' => array(
        'default' => 'pdf.png'
    )
);

/**
 * PGP settings
 */
$mime_drivers['imp']['pgp'] = array(
    'inline' => true,
    'handles' => array(
        'application/pgp-encrypted', 'application/pgp-keys',
        'application/pgp-signature'
    ),
    'icons' => array(
        'default' => 'encryption.png'
    )
);

/**
 * S/MIME settings
 */
$mime_drivers['imp']['smime'] = array(
    'inline' => true,
    'handles' => array(
        'application/x-pkcs7-signature', 'application/x-pkcs7-mime',
        'application/pkcs7-signature', 'application/pkcs7-mime'
    ),
    'icons' => array(
        'default' => 'encryption.png'
    )
);

/**
 * Zip File Attachments settings
 */
$mime_drivers['imp']['zip'] = array(
    'inline' => false,
    'handles' => array(
        'application/zip', 'application/x-compressed',
        'application/x-zip-compressed'
    ),
    'icons' => array(
        'default' => 'compressed.png'
    )
);

/**
 * Delivery Status messages settings
 */
$mime_drivers['imp']['status'] = array(
    'inline' => true,
    'handles' => array(
        'message/delivery-status'
    )
);

/**
 * Disposition Notification message settings
 */
$mime_drivers['imp']['mdn'] = array(
    'inline' => true,
    'handles' => array(
        'message/disposition-notification'
    )
);

/**
 * multipart/appledouble settings
 */
$mime_drivers['imp']['appledouble'] = array(
    'inline' => true,
    'handles' => array(
        'multipart/appledouble'
    )
);

/**
 * iCalendar Transport-Independent Interoperability Protocol
 */
$mime_drivers['imp']['itip'] = array(
    'inline' => true,
    'handles' => array(
        'text/calendar', 'text/x-vcalendar'
    ),
    'icons' => array(
        'default' => 'itip.png'
    )
);

/**
 * multipart/alternative settings
 */
$mime_drivers['imp']['alternative'] = array(
    /* The 'inline' setting should normally not be changed. */
    'inline' => true,
    'handles' => array(
        'multipart/alternative'
    )
);

/**
 * multipart/related settings
 * YOU SHOULD NOT NORMALLY ALTER THIS SETTING.
 */
$mime_drivers['imp']['related'] = array(
    'inline' => true,
    'handles' => array(
        'multipart/related'
    ),
    'icons' => array(
        'default' => 'html.png'
    )
);

/**
 * message/partial settings
 * YOU SHOULD NOT NORMALLY ALTER THIS SETTING.
 */
$mime_drivers['imp']['partial'] = array(
    'handles' => array(
        'message/partial'
    )
);

/**
 * MS-TNEF Attachment (application/ms-tnef) settings
 * YOU SHOULD NOT NORMALLY ALTER THIS SETTING.
 */
$mime_drivers['imp']['tnef'] = array(
    'inline' => false,
    'handles' => array(
        'application/ms-tnef'
    ),
    'icons' => array(
        'default' => 'binary.png'
    )
);
