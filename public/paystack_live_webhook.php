<?php
/**
 * Public entrypoint for the full Paystack webhook receiver.
 *
 * Use this route for your configured live/test webhook URL when the app is
 * served from the public/ front controller setup:
 *   /paystack/webhook/live
 *
 * The actual processing logic lives in src/api/paystack/webhook-receiver.php.
 */

declare(strict_types=1);

require __DIR__ . '/../src/api/paystack/webhook-receiver.php';
