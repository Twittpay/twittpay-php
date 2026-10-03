# TwittPay for PHP

Plain PHP library

Part of the [TwittPay](https://twittpay.com) addon family.

## Quick start

1. Download the latest zip from the **Releases** page of this repository.
2. Install it on your PHP following the guide below.
3. Open the TwittPay settings and enter your **Brand Key**. You can get it from [your dashboard](https://twittpay.com/user/brands).
4. Make a small test payment to confirm everything works.

Payments are always re-verified on your server before an order or invoice is marked paid.

## Detailed installation guide

```text
===========================================================================
 TWITTPAY - plain PHP library
===========================================================================

 WHERE IT GOES
   Extract this zip at the root of your site. You get one folder:

     twittpay/
       TwittPay.php   the client - the only file you actually need
       config.php        your Brand Key and URLs. THE ONLY FILE YOU EDIT
       index.php         creates a payment and redirects (demo)
       success.php       the return URL - verifies, then delivers (demo)
       cancel.php        the cancel URL (demo)
       ipn.php           the webhook handler (demo)

 SETUP
   1. Open twittpay/config.php and fill in three things:
        TWITTPAY_API_KEY    Dashboard -> Brands -> your brand
        TWITTPAY_BASE_URL   your gateway domain, e.g. https://checkout.twittpay.com
        TWITTPAY_SELF_URL   the public URL of this folder, with a trailing slash
   2. Open https://yourdomain.com/twittpay/ in a browser. You should land on
      the checkout page.

 USING IT IN YOUR OWN CODE
   require_once 'twittpay/TwittPay.php';

   $pay = new TwittPay('YOUR_API_KEY', 'https://checkout.twittpay.com');

   $r = $pay->createPayment([
       'cus_name'    => 'John Doe',
       'cus_email'   => 'john@example.com',
       'amount'      => 100,
       'success_url' => 'https://yourdomain.com/return.php',
       'cancel_url'  => 'https://yourdomain.com/cancel.php',
       'webhook_url' => 'https://yourdomain.com/hook.php',
       'metadata'    => ['order_id' => '1043'],
   ]);

   if (!empty($r['status']) && !empty($r['payment_url'])) {
       header('Location: ' . $r['payment_url']);
       exit;
   }
   exit($r['message']);

   Then on the way back:

   $v = $pay->verifyPayment($_GET['transactionId']);

   if ($pay->isPaid($v, $orderTotal)) {   // COMPLETED and enough money
       $meta = TwittPay::decodeMetadata($v);   // your order_id is in here
       // deliver the order
   }

 THE THREE THINGS PEOPLE GET WRONG
   1. Believing the return URL. ?status=completed is typed by whoever asks for
      the page. Only the verify call decides. This library will not tell you a
      payment is good without asking the gateway.
   2. Skipping webhook_url. The return URL only runs while the customer is
      sitting there. A payment the merchant approves an hour later has no
      browser left, so without a webhook that order stays unpaid forever. This
      is the single most important line in the create call.
   3. Treating PENDING as failure. The money has already been sent; the merchant
      just has not approved it yet. Leave the order unpaid, say it is being
      checked, and wait for the webhook. Never ask them to pay again.

 METADATA
   Send it as an object with named keys - ['order_id' => '1043'], not
   ['1043']. A JSON array is rejected with "Metadata must be in JSON format."
   The library casts it for you, so named keys are all you have to remember.
   On the way back it arrives as a JSON *string*; decodeMetadata() unpacks it.

 CURRENCY
   The gateway charges in BDT. If your site prices in USD, multiply before you
   send, and put the original amount and currency into metadata so your own
   code can record what the customer was actually charged in your books.

 CHECKED
   Both PHP files were checked with a lexer that balances braces only inside
   real PHP code. PHP itself was NOT run - there is no PHP binary on the
   machine this was built on, so php -l was never executed.
```
