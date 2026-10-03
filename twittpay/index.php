<?php
/**
 * Step 1 and 2 - create the payment and send the customer to it.
 *
 * In a real site the amount and the order id come from your own database. They
 * are hard coded here so the folder works the moment you fill in config.php.
 */

require_once __DIR__ . '/config.php';

$orderId = 'ORD-' . date('Ymd-His');
$amount  = 100;

$pay = twittpay_client();

$result = $pay->createPayment(array(
    'cus_name'    => 'Demo Customer',
    'cus_email'   => 'demo@example.com',
    'amount'      => $amount,
    'success_url' => TWITTPAY_SELF_URL . 'success.php?order=' . urlencode($orderId),
    'cancel_url'  => TWITTPAY_SELF_URL . 'cancel.php?order=' . urlencode($orderId),

    // Without this, a payment the merchant approves an hour later can never
    // reach your site: by then the customer's browser is long gone.
    'webhook_url' => TWITTPAY_SELF_URL . 'ipn.php',

    // The webhook carries none of your query strings, so your own order number
    // has to travel inside metadata. This is the only place it survives.
    'metadata'    => array(
        'order_id' => $orderId,
        'source'   => 'php-library',
    ),
));

if (!empty($result['status']) && !empty($result['payment_url'])) {
    header('Location: ' . $result['payment_url']);
    exit;
}

http_response_code(502);

echo '<h3>Could not start the payment</h3>';
echo '<p>' . htmlspecialchars(isset($result['message']) ? $result['message'] : 'Unknown error') . '</p>';
