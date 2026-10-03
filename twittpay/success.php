<?php
/**
 * Steps 3 and 4 - the customer is back. Ask the gateway what happened.
 *
 * The query string on this URL is a nudge, not proof: anybody can type
 * ?status=completed. Nothing here trusts it. The only thing read off the URL is
 * the transaction id, and even that is only used to ask the gateway.
 */

require_once __DIR__ . '/config.php';

$transactionId = isset($_GET['transactionId']) ? trim((string) $_GET['transactionId']) : '';

if ($transactionId === '') {
    http_response_code(400);
    exit('No transaction id on the return URL.');
}

$pay      = twittpay_client();
$verified = $pay->verifyPayment($transactionId);
$status   = TwittPay::readStatus($verified);
$meta     = TwittPay::decodeMetadata($verified);
$orderId  = isset($meta['order_id']) ? $meta['order_id'] : (isset($_GET['order']) ? $_GET['order'] : '');

// What the order was supposed to cost. Look this up in your own database by
// $orderId - never take it from the browser.
$expected = 100;

if ($pay->isPaid($verified, $expected)) {
    // deliver_order($orderId);  <- your code here. Make it safe to run twice:
    // the webhook can bring the same COMPLETED a second time.
    echo '<h3>Payment received</h3>';
    echo '<p>Order ' . htmlspecialchars((string) $orderId) . ' is paid.</p>';
    echo '<p>Transaction ' . htmlspecialchars($transactionId) . '</p>';
    exit;
}

if ($status === 'PENDING') {
    // The money has been sent but the merchant has not approved it yet. Do NOT
    // ask the customer to pay again - the webhook will bring the answer.
    echo '<h3>Payment is being checked</h3>';
    echo '<p>Your payment has been received and is waiting for confirmation. ';
    echo 'You will not need to pay again.</p>';
    exit;
}

echo '<h3>Payment not completed</h3>';
echo '<p>Status: ' . htmlspecialchars($status !== '' ? $status : 'unknown') . '</p>';

if (!empty($verified['message'])) {
    echo '<p>' . htmlspecialchars((string) $verified['message']) . '</p>';
}
