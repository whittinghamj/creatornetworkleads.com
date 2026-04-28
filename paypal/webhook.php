<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/paypal.php';

$db = getDB();
ensureBillingSchema($db);

$rawBody = file_get_contents('php://input') ?: '';
$payload = json_decode($rawBody, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo 'Invalid payload';
    exit;
}

$headers = [];
if (function_exists('getallheaders')) {
    $headers = getallheaders() ?: [];
}

$verified = paypalVerifyWebhookSignature($rawBody, $headers);
$eventId = (string)($payload['id'] ?? '');
$eventType = (string)($payload['event_type'] ?? 'unknown');
$resourceType = (string)($payload['resource_type'] ?? '');

if ($eventId === '') {
    http_response_code(400);
    echo 'Missing event id';
    exit;
}

$eventInsert = $db->prepare(
    'INSERT INTO paypal_webhook_events
        (paypal_event_id, event_type, resource_type, verification_status, processing_status, raw_payload)
     VALUES (?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        verification_status = VALUES(verification_status),
    raw_payload = VALUES(raw_payload)'
);

try {
    $eventInsert->execute([
        $eventId,
        $eventType,
        $resourceType !== '' ? $resourceType : null,
        $verified ? 'SUCCESS' : 'FAILED',
        'received',
        $rawBody,
    ]);
} catch (Throwable $e) {
    // Continue processing to avoid blocking retries on storage issues.
}

if (!$verified) {
    $db->prepare('UPDATE paypal_webhook_events SET processing_status = ?, error_message = ? WHERE paypal_event_id = ?')
        ->execute(['failed', 'Webhook signature verification failed', $eventId]);

    http_response_code(400);
    echo 'Webhook verification failed';
    exit;
}

try {
    $resource = (array)($payload['resource'] ?? []);

    if (in_array($eventType, ['BILLING.SUBSCRIPTION.ACTIVATED', 'BILLING.SUBSCRIPTION.UPDATED', 'BILLING.SUBSCRIPTION.RE-ACTIVATED', 'BILLING.SUBSCRIPTION.CANCELLED', 'BILLING.SUBSCRIPTION.SUSPENDED', 'BILLING.SUBSCRIPTION.EXPIRED'], true)) {
        $subscriptionId = (string)($resource['id'] ?? '');
        if ($subscriptionId !== '') {
            paypalSyncSubscriptionById($db, $subscriptionId, null, 'webhook:' . $eventType);
        }
    }

    if (in_array($eventType, ['BILLING.SUBSCRIPTION.PAYMENT.COMPLETED', 'PAYMENT.SALE.COMPLETED', 'PAYMENT.SALE.DENIED', 'BILLING.SUBSCRIPTION.PAYMENT.FAILED'], true)) {
        $payment = paypalExtractPaymentRecordFromWebhook($payload);
        $subscriptionId = (string)($payment['paypal_subscription_id'] ?? '');

        if ($subscriptionId !== '') {
            $sync = paypalSyncSubscriptionById($db, $subscriptionId, null, 'webhook:' . $eventType);
            if (!empty($sync['ok'])) {
                $payment['user_id'] = (int)$sync['user_id'];
            }
        }

        if (in_array($eventType, ['PAYMENT.SALE.DENIED', 'BILLING.SUBSCRIPTION.PAYMENT.FAILED'], true)) {
            $payment['status'] = 'failed';
            if (!empty($payment['user_id'])) {
                $db->prepare(
                    'UPDATE users
                     SET status = "inactive",
                         subscription_status = "payment_failed",
                         subscription_updated_at = NOW()
                     WHERE id = ?'
                )->execute([(int)$payment['user_id']]);
            }
        } elseif ($eventType === 'BILLING.SUBSCRIPTION.PAYMENT.COMPLETED') {
            $payment['status'] = 'completed';
        }

        paypalRecordPayment($db, $payment);
    }

    $db->prepare('UPDATE paypal_webhook_events SET processing_status = ?, error_message = NULL WHERE paypal_event_id = ?')
        ->execute(['processed', $eventId]);

    http_response_code(200);
    echo 'OK';
} catch (Throwable $e) {
    $db->prepare('UPDATE paypal_webhook_events SET processing_status = ?, error_message = ? WHERE paypal_event_id = ?')
        ->execute(['failed', mb_substr($e->getMessage(), 0, 500), $eventId]);

    http_response_code(500);
    echo 'Webhook processing failed';
}
