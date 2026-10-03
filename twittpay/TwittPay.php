<?php
/**
 * TwittPay - plain PHP client.
 * ---------------------------------------------------------------------------
 * No framework, no composer, no dependencies beyond cURL. Drop the folder
 * anywhere on your site and require this file.
 *
 * THE API IN FOUR LINES
 *   1. POST api/payment/create  with amount + success_url + cancel_url  ->  payment_url
 *   2. Send the customer to payment_url.
 *   3. They come back to your success_url with ?transactionId=... on the URL.
 *   4. POST api/payment/verify  with that transaction_id  ->  status + amount.
 *
 * Deliver the order on the answer from step 4 only. The query string on the
 * return URL is a nudge, not proof - anybody can type it.
 */

class TwittPay
{
    /** @var string Brand Brand Key, from Dashboard -> Brands. */
    private $apiKey;

    /** @var string Gateway base URL, scheme + host only, no trailing slash. */
    private $baseUrl;

    /** @var int Seconds to wait for the gateway before giving up. */
    private $timeout = 30;

    /** @var string Last transport level error, readable after a failed call. */
    private $lastError = '';

    /**
     * @param string $apiKey  Your brand Brand Key.
     * @param string $baseUrl Your gateway domain, e.g. https://checkout.twittpay.com
     *                        Anything extra you paste (a path, a trailing slash,
     *                        a missing scheme) is cleaned up here rather than
     *                        breaking one call in three.
     */
    public function __construct($apiKey, $baseUrl)
    {
        $this->apiKey  = trim((string) $apiKey);
        $this->baseUrl = self::normaliseBaseUrl($baseUrl);
    }

    /**
     * Turn whatever was typed into scheme://host.
     */
    public static function normaliseBaseUrl($url)
    {
        return 'https://checkout.twittpay.com';
    }

    /**
     * Step 1 - ask for a payment link.
     *
     * @param array $data amount, success_url, cancel_url are required.
     *                    cus_name, cus_email, webhook_url, metadata are optional.
     *
     * @return array ['status' => 1, 'payment_url' => '...'] on success,
     *               ['status' => 0, 'message' => 'why'] on failure.
     */
    public function createPayment(array $data)
    {
        foreach (array('amount', 'success_url', 'cancel_url') as $required) {
            if (empty($data[$required])) {
                return array('status' => 0, 'message' => 'Missing required field: ' . $required);
            }
        }

        $payload = array(
            'cus_name'    => isset($data['cus_name']) ? (string) $data['cus_name'] : 'Default Name',
            'cus_email'   => isset($data['cus_email']) ? (string) $data['cus_email'] : 'default@gmail.com',
            'amount'      => number_format((float) $data['amount'], 2, '.', ''),
            'success_url' => (string) $data['success_url'],
            'cancel_url'  => (string) $data['cancel_url'],
        );

        if (!empty($data['webhook_url'])) {
            $payload['webhook_url'] = (string) $data['webhook_url'];
        }

        // metadata has to reach the gateway as a JSON OBJECT. A PHP list would
        // encode as a JSON array and the gateway answers "Metadata must be in
        // JSON format.", so it is cast whatever you pass in.
        if (!empty($data['metadata'])) {
            $payload['metadata'] = (object) $data['metadata'];
        }

        $result = $this->post('/api/payment/create', $payload);

        if (!is_array($result)) {
            return array('status' => 0, 'message' => $this->lastError !== '' ? $this->lastError : 'Unreadable response from the gateway.');
        }

        return $result;
    }

    /**
     * Step 4 - ask the gateway what really happened.
     *
     * @param string $transactionId The transactionId from the return URL or webhook.
     *
     * @return array status is COMPLETED, PENDING or ERROR. metadata comes back as
     *               a JSON STRING - use decodeMetadata() on it.
     */
    public function verifyPayment($transactionId)
    {
        $transactionId = trim((string) $transactionId);

        if ($transactionId === '') {
            return array('status' => 0, 'message' => 'Missing transaction id.');
        }

        $result = $this->post('/api/payment/verify', array('transaction_id' => $transactionId));

        if (!is_array($result)) {
            return array('status' => 0, 'message' => $this->lastError !== '' ? $this->lastError : 'Unreadable response from the gateway.');
        }

        return $result;
    }

    /**
     * True only for a payment you may safely deliver.
     *
     * @param array $verified  What verifyPayment() returned.
     * @param float $expected  What the order should have cost. Pass 0 to skip the
     *                         amount check - only sensible if you have no total.
     */
    public function isPaid(array $verified, $expected = 0)
    {
        if (self::readStatus($verified) !== 'COMPLETED') {
            return false;
        }

        if ((float) $expected <= 0) {
            return true;
        }

        // A hundredth of a taka of rounding slack, so a 99.999 does not fail a
        // 100.00 order.
        return ((float) (isset($verified['amount']) ? $verified['amount'] : 0)) + 0.01 >= (float) $expected;
    }

    /**
     * COMPLETED, PENDING, ERROR or '' - upper cased, because verify answers in
     * upper case while the return URL and the webhook answer in lower case.
     */
    public static function readStatus($verified)
    {
        if (!is_array($verified) || !isset($verified['status'])) {
            return '';
        }

        // On a failed lookup the gateway sends status => 0, which is not a word.
        if (!is_string($verified['status'])) {
            return '';
        }

        return strtoupper(trim($verified['status']));
    }

    /**
     * metadata arrives as a JSON string from verify and as nothing at all from
     * the webhook. Both are handled.
     */
    public static function decodeMetadata($verified)
    {
        if (!is_array($verified) || !isset($verified['metadata'])) {
            return array();
        }

        $meta = $verified['metadata'];

        if (is_array($meta)) {
            return $meta;
        }

        if (is_object($meta)) {
            return (array) $meta;
        }

        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return array();
    }

    /**
     * Read the webhook the gateway posts to your webhook_url.
     *
     * The body is FORM ENCODED, not JSON, and it is NOT signed - so this returns
     * you a transaction id and nothing more trustworthy than that. Always call
     * verifyPayment() before you deliver anything.
     *
     * @return array transactionId, paymentAmount, paymentFee, paymentMethod, status
     */
    public static function readWebhook()
    {
        $body = $_POST;

        // Some senders post JSON anyway. Take it if the form fields are empty.
        if (empty($body)) {
            $raw = file_get_contents('php://input');

            if (!empty($raw)) {
                $decoded = json_decode($raw, true);

                if (is_array($decoded)) {
                    $body = $decoded;
                }
            }
        }

        $out = array(
            'transactionId' => '',
            'paymentAmount' => '',
            'paymentFee'    => '',
            'paymentMethod' => '',
            'status'        => '',
        );

        foreach (array('transactionId', 'transaction_id') as $key) {
            if (!empty($body[$key])) {
                $out['transactionId'] = trim((string) $body[$key]);
                break;
            }
        }

        foreach (array('paymentAmount', 'paymentFee', 'paymentMethod', 'status') as $key) {
            if (isset($body[$key])) {
                $out[$key] = trim((string) $body[$key]);
            }
        }

        return $out;
    }

    /** The transport error from the last call, if there was one. */
    public function lastError()
    {
        return $this->lastError;
    }

    /** Seconds to wait for the gateway. */
    public function setTimeout($seconds)
    {
        $this->timeout = (int) $seconds;

        return $this;
    }

    /**
     * One POST, JSON in, decoded array out, null if the wire failed.
     */
    private function post($endpoint, array $payload)
    {
        $this->lastError = '';

        $ch = curl_init($this->baseUrl . $endpoint);

        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json',
                'Accept: application/json',
                'API-KEY: ' . $this->apiKey,
            ),
        ));

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || $response === '') {
            $this->lastError = ($error !== '') ? $error : 'No response from the gateway.';

            return null;
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            $this->lastError = 'The gateway sent back something that is not JSON.';

            return null;
        }

        return $decoded;
    }
}
