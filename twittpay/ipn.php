<?php
/**
 * The webhook - the gateway calling your server directly.
 *
 * THIS is the file that makes pending payments work. The return URL only ever
 * runs while the customer is still sitting there; a payment the merchant
 * approves an hour later has no browser left to come back with. Without this
 * file that order stays unpaid forever even though the money is in.
 *
 * The body is form encoded and it is NOT signed, so it is treated as a nudge
 * only: it tells us which transaction to go and ask about, nothing more.
 *
 * Expect it more than once for the same payment - pending first, then completed
 * or failed. Whatever you do below has to be safe to run twice.
 */

require_once __DIR__ . '/config.php';

$hook          = TwittPay::readWebhook();
$transactionId = $hook['transactionId'];

if ($transactionId === '') {
    http_response_code(400);
    exit('No transaction id.');
}

$pay      = twittpay_client();
$verified = $pay->verifyPayment($transactionId);
$status   = TwittPay::readStatus($verified);
$meta     = TwittPay::decodeMetadata($verified);
$orderId  = isset($meta['order_id']) ? (string) $meta['order_id'] : '';
$amount   = isset($verified['amount']) ? (float) $verified['amount'] : 0;

// A log while you are wiring this up. Delete it, or point it somewhere outside
// the web root, before you go live.
@file_put_contents(
    __DIR__ . '/webhook_log.txt',
    date('c') . ' ' . $transactionId . ' ' . $status . ' order=' . $orderId . ' amount=' . $amount . PHP_EOL,
    FILE_APPEND
);

switch ($status) {
    case 'COMPLETED':
        // mark_order_paid($orderId, $transactionId, $amount);   <- your code.
        // Guard it: if this order is already paid, do nothing and still answer 200.
        break;

    case 'PENDING':
        // The merchant has not decided yet. Leave the order alone. Do not cancel
        // it and do not ask for payment again - this file will be called again.
        break;

    default:
        // Rejected, or never paid. Cancel the order or leave it unpaid.
        break;
}

// Always 200 once you have handled it, so the gateway stops retrying.
http_response_code(200);
echo 'OK';
