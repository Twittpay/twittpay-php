<?php
/**
 * TwittPay - your keys, in one place.
 *
 * Everything else in this folder reads them from here, so this is the only file
 * you have to edit. Keep it out of your git repository.
 */

// Dashboard -> Brands -> your brand.
define('TWITTPAY_API_KEY', '');

/**
 * Your gateway's endpoint URL - scheme and host only, no path needed.
 * For example: https://checkout.twittpay.com
 *
 * There is no default on purpose. Put your own gateway address here; the payment
 * page the customer is sent to is decided by the gateway itself, not by you.
 */
define('TWITTPAY_BASE_URL', '');

/**
 * Where this folder lives, as a full public URL, WITH a trailing slash.
 *
 * The gateway sends the customer back here and calls ipn.php from its own
 * server, so it has to be reachable from outside - "localhost" will not do.
 */
define('TWITTPAY_SELF_URL', 'https://yourdomain.com/twittpay/');

require_once __DIR__ . '/TwittPay.php';

/** One ready-made client for every file in this folder. */
function twittpay_client()
{
    static $client = null;

    if ($client === null) {
        $client = new TwittPay(TWITTPAY_API_KEY, TWITTPAY_BASE_URL);
    }

    return $client;
}
