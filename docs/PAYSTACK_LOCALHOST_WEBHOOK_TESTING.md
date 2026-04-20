# 1. Simple explanation

Paystack cannot send webhooks directly to a normal `localhost` URL because `localhost` only exists on your own machine. Paystack's servers on the internet cannot reach a private local address unless you expose it or use a forwarding tool.

Paystack CLI solves that problem for local testing. The CLI listens for Paystack test webhook events and forwards them to your local PHP endpoint, so you can keep your app on `localhost` while still testing webhook delivery.

This repo now supports two Paystack webhook endpoints:

- Local CLI testing endpoint: `http://localhost:8000/paystack/webhook`
- Full app/live-style endpoint: `/paystack/webhook/live`

Typical local flow:

1. Start your PHP app locally.
2. Start the Paystack CLI listener.
3. Make a Paystack test payment.
4. Paystack emits a webhook event such as `charge.success`.
5. The CLI forwards that event to your local endpoint.
6. Your PHP webhook verifies the signature and payload.
7. Your app updates the transaction only after validation.

# 2. Installation commands

Node.js must already be installed.

Install the Paystack CLI globally:

```bash
npm install -g @paystack-oss/dev-cli
```

Confirm installation:

```bash
paystack --help
```

Official references:

- Paystack CLI blog post: https://paystack.com/blog/product/cli
- Paystack webhook docs: https://paystack.com/docs/payments/webhooks/

# 3. Local server command

Start your PHP app locally with the built-in server and the `public/` folder as the document root:

```bash
php -S localhost:8000 -t public
```

Your local app will be available at:

```text
http://localhost:8000
```

The local CLI webhook endpoint is:

```text
http://localhost:8000/paystack/webhook
```

That route is handled by:

- `public/index.php`
- `public/paystack_webhook.php`

# 4. Paystack CLI listener command

Start the listener with:

```bash
webhook listen localhost:8000/paystack/webhook
```

This forwards Paystack test webhook events to your local PHP endpoint.

# 5. Webhook endpoints in this project

## Local testing endpoint

File:

- `public/paystack_webhook.php`

Purpose:

- simple localhost testing with Paystack CLI
- accepts `POST` only
- reads `php://input`
- reads `x-paystack-signature`
- verifies the HMAC SHA512 signature with your Paystack test secret key
- decodes JSON safely
- handles `charge.success`
- logs incoming payloads to `public/logs/paystack-webhook.log`

## Full app/live-style endpoint

Files:

- `public/paystack_live_webhook.php`
- `src/api/paystack/webhook-receiver.php`

Purpose:

- use this when you want the app's real Paystack webhook processing path
- accepts `POST` only
- validates the raw signature before processing
- decodes JSON safely
- handles `charge.success`, `charge.failed`, and subscription events
- verifies successful charges with Paystack server-side before updating the database
- sends receipt emails through the app's existing email path

Recommended public/live webhook URL:

```text
/paystack/webhook/live
```

# 6. Test flow steps

1. Start your PHP server:

```bash
php -S localhost:8000 -t public
```

2. Start the Paystack CLI listener:

```bash
webhook listen localhost:8000/paystack/webhook
```

3. Make a Paystack test payment from your app.

4. Paystack sends a test event such as `charge.success`.

5. The CLI forwards the event to `http://localhost:8000/paystack/webhook`.

6. Your PHP webhook verifies the signature and decodes the JSON body.

7. Your app updates the payment record only after validation.

# 7. Manual CLI test command

You can test the listener manually with:

```bash
webhook ping --event transfer.success --domain test
```

This sends a sample test webhook event through the CLI to confirm your local route works.

For actual payment confirmations, the event you will usually care about most is:

```text
charge.success
```

# 8. Security rules

The implementation now follows these rules:

- use your Paystack test secret key for localhost testing
- accept `POST` requests only
- read the raw request body before hashing
- verify every webhook signature with `hash_hmac('sha512', ...)`
- compare signatures with `hash_equals()`
- reject invalid signatures with `401`
- decode JSON safely
- return `200 OK` only after successful validation/handling
- log payloads safely for debugging
- verify successful transactions server-side before granting value
- do not rely only on callback pages or frontend redirects

Set your local test secret key in `.env`:

```text
PAYSTACK_SECRET_KEY=sk_test_your_test_secret_key
```

# 9. Troubleshooting

Common reasons webhook testing fails locally:

- Wrong secret key: the signature check fails.
- Route not accepting `POST`: the endpoint returns `405`.
- Invalid signature: the `x-paystack-signature` header does not match the raw payload.
- Webhook listener not running: the CLI has nothing to forward through.
- PHP server not running: `localhost:8000` is unreachable.
- Wrong localhost endpoint: the CLI points to the wrong path or port.
- App returning non-200 responses: Paystack or the CLI sees the webhook as failed.
- Test and live keys mixed up: a test event signed with one key will fail against the other key.
- JSON parsing failure: the payload is empty or malformed.

Useful local checks:

- review `public/logs/paystack-webhook.log` for the local endpoint
- review PHP error logs for the full app endpoint
- confirm the exact URL you are listening on
- confirm the request method is `POST`
- confirm `PAYSTACK_SECRET_KEY` is the test key in local development
