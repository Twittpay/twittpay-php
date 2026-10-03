<?php
/**
 * The customer cancelled, or the payment failed.
 *
 * Nothing is verified here on purpose - a cancel is not an event you should act
 * on. Leave the order unpaid and let them try again.
 */

$orderId = isset($_GET['order']) ? (string) $_GET['order'] : '';

echo '<h3>Payment cancelled</h3>';

if ($orderId !== '') {
    echo '<p>Order ' . htmlspecialchars($orderId) . ' has not been paid.</p>';
}

echo '<p><a href="index.php">Try again</a></p>';
