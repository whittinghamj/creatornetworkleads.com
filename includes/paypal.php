<?php
/**
 * PayPal subscription + webhook helpers.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function paypalApiBaseUrl(): string
{
    return PAYPAL_MODE === 'live'
        ? 'https://api-m.paypal.com'
        : 'https://api-m.sandbox.paypal.com';
}

function paypalIsConfigured(): bool
{
    return PAYPAL_CLIENT_ID !== '' && PAYPAL_CLIENT_SECRET !== '';
}

function paypalGetAccessToken(): string
{
    if (!paypalIsConfigured()) {
        throw new RuntimeException('PayPal API credentials are not configured.');
    }

    $ch = curl_init(paypalApiBaseUrl() . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_USERPWD => PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        throw new RuntimeException('PayPal token request failed: ' . $curlError);
    }

    $body = json_decode((string)$response, true);
    if ($httpCode < 200 || $httpCode >= 300 || empty($body['access_token'])) {
        throw new RuntimeException('PayPal token request was rejected.');
    }

    return (string)$body['access_token'];
}

function paypalApiRequest(string $method, string $path, string $accessToken, ?array $payload = null, string $requestId = ''): array
{
    $ch = curl_init(paypalApiBaseUrl() . $path);
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/json',
        'Content-Type: application/json',
    ];
    if ($requestId !== '') {
        $headers[] = 'PayPal-Request-Id: ' . $requestId;
    }

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ];

    if ($payload !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError !== '') {
        throw new RuntimeException('PayPal API request failed: ' . $curlError);
    }

    $decoded = json_decode((string)$response, true);
    return [
        'status' => $httpCode,
        'body' => is_array($decoded) ? $decoded : [],
        'raw' => (string)$response,
    ];
}

function paypalParseCustomId(?string $customId): array
{
    $result = ['user_id' => 0, 'package_id' => 0];
    $customId = trim((string)$customId);
    if ($customId === '') {
        return $result;
    }

    if (preg_match('/user:(\d+):package:(\d+)/', $customId, $m)) {
        $result['user_id'] = (int)$m[1];
        $result['package_id'] = (int)$m[2];
    }

    return $result;
}

function paypalNormalizeSubscriptionStatus(string $status): string
{
    $normalized = strtolower(trim($status));
    if ($normalized === '') {
        return 'none';
    }

    $map = [
        'approval_pending' => 'pending',
        'approved' => 'pending',
        'active' => 'active',
        'suspended' => 'suspended',
        'cancelled' => 'cancelled',
        'expired' => 'expired',
    ];

    return $map[$normalized] ?? $normalized;
}

function paypalMapStatusToUserState(PDO $db, int $userId, string $status, int $packageId, string $subscriptionId): void
{
    $status = paypalNormalizeSubscriptionStatus($status);

    if ($status === 'active') {
        $stmt = $db->prepare(
            'UPDATE users
             SET status = "active",
                 package_id = ?,
                 paypal_subscription_id = ?,
                 subscription_status = ?,
                 subscription_package_id = ?,
                 subscription_updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $packageId > 0 ? $packageId : null,
            $subscriptionId,
            $status,
            $packageId > 0 ? $packageId : null,
            $userId,
        ]);
        return;
    }

    $disableStatuses = ['suspended', 'cancelled', 'expired', 'payment_failed', 'failed'];
    $newAccountStatus = in_array($status, $disableStatuses, true) ? 'inactive' : 'pending';

    $stmt = $db->prepare(
        'UPDATE users
         SET status = ?,
             paypal_subscription_id = ?,
             subscription_status = ?,
             subscription_package_id = ?,
             subscription_updated_at = NOW()
         WHERE id = ?'
    );
    $stmt->execute([
        $newAccountStatus,
        $subscriptionId,
        $status,
        $packageId > 0 ? $packageId : null,
        $userId,
    ]);
}

function paypalCreateSubscriptionForUser(PDO $db, array $user, int $packageId): array
{
    ensureBillingSchema($db);

    if (!paypalIsConfigured()) {
        return ['ok' => false, 'error' => 'PayPal credentials are not configured on the server.'];
    }

    $pkgStmt = $db->prepare('SELECT id, name, paypal_plan_id FROM packages WHERE id = ? LIMIT 1');
    $pkgStmt->execute([$packageId]);
    $package = $pkgStmt->fetch();

    if (!$package) {
        return ['ok' => false, 'error' => 'Selected package was not found.'];
    }
    if (trim((string)($package['paypal_plan_id'] ?? '')) === '') {
        return ['ok' => false, 'error' => 'This package does not have a PayPal plan configured yet.'];
    }

    try {
        $accessToken = paypalGetAccessToken();
        $requestId = 'cnl-sub-' . (int)$user['id'] . '-' . $packageId . '-' . time();

        $payload = [
            'plan_id' => (string)$package['paypal_plan_id'],
            'custom_id' => 'user:' . (int)$user['id'] . ':package:' . (int)$package['id'],
            'subscriber' => [
                'name' => [
                    'given_name' => trim((string)($user['name'] ?? 'Customer')),
                ],
                'email_address' => trim((string)($user['email'] ?? '')),
            ],
            'application_context' => [
                'brand_name' => APP_NAME,
                'user_action' => 'SUBSCRIBE_NOW',
                'shipping_preference' => 'NO_SHIPPING',
                'return_url' => PAYPAL_RETURN_URL,
                'cancel_url' => PAYPAL_CANCEL_URL,
            ],
        ];

        $apiResult = paypalApiRequest('POST', '/v1/billing/subscriptions', $accessToken, $payload, $requestId);
        if ($apiResult['status'] < 200 || $apiResult['status'] >= 300) {
            return ['ok' => false, 'error' => 'PayPal rejected the subscription request.'];
        }

        $body = $apiResult['body'];
        $subscriptionId = (string)($body['id'] ?? '');
        if ($subscriptionId === '') {
            return ['ok' => false, 'error' => 'PayPal did not return a subscription ID.'];
        }

        $approveUrl = '';
        foreach ((array)($body['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $approveUrl = (string)($link['href'] ?? '');
                break;
            }
        }
        if ($approveUrl === '') {
            return ['ok' => false, 'error' => 'PayPal did not return an approval URL.'];
        }

        $subStmt = $db->prepare(
            'INSERT INTO paypal_subscriptions
                (user_id, package_id, paypal_subscription_id, paypal_plan_id, status, subscriber_email, raw_payload)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                package_id = VALUES(package_id),
                paypal_plan_id = VALUES(paypal_plan_id),
                status = VALUES(status),
                subscriber_email = VALUES(subscriber_email),
                raw_payload = VALUES(raw_payload),
                updated_at = NOW()'
        );
        $subStmt->execute([
            (int)$user['id'],
            (int)$package['id'],
            $subscriptionId,
            (string)$package['paypal_plan_id'],
            paypalNormalizeSubscriptionStatus((string)($body['status'] ?? 'APPROVAL_PENDING')),
            trim((string)($user['email'] ?? '')),
            json_encode($body),
        ]);

        $db->prepare(
            'UPDATE users
             SET paypal_subscription_id = ?,
                 subscription_status = ?,
                 subscription_package_id = ?,
                 subscription_updated_at = NOW()
             WHERE id = ?'
        )->execute([
            $subscriptionId,
            'pending',
            (int)$package['id'],
            (int)$user['id'],
        ]);

        return [
            'ok' => true,
            'approve_url' => $approveUrl,
            'subscription_id' => $subscriptionId,
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
 }

function paypalSyncSubscriptionById(PDO $db, string $subscriptionId, ?int $forceUserId = null, string $source = 'manual'): array
{
    ensureBillingSchema($db);

    if (trim($subscriptionId) === '') {
        return ['ok' => false, 'error' => 'Missing subscription ID.'];
    }

    try {
        $accessToken = paypalGetAccessToken();
        $apiResult = paypalApiRequest('GET', '/v1/billing/subscriptions/' . rawurlencode($subscriptionId), $accessToken);
        if ($apiResult['status'] < 200 || $apiResult['status'] >= 300) {
            return ['ok' => false, 'error' => 'Failed to fetch subscription from PayPal.'];
        }

        $body = $apiResult['body'];
        $status = paypalNormalizeSubscriptionStatus((string)($body['status'] ?? 'none'));
        $customData = paypalParseCustomId((string)($body['custom_id'] ?? ''));

        $existingStmt = $db->prepare('SELECT user_id, package_id FROM paypal_subscriptions WHERE paypal_subscription_id = ? LIMIT 1');
        $existingStmt->execute([$subscriptionId]);
        $existing = $existingStmt->fetch() ?: [];

        $userId = $forceUserId ?: (int)($existing['user_id'] ?? 0) ?: (int)$customData['user_id'];
        $packageId = (int)($existing['package_id'] ?? 0) ?: (int)$customData['package_id'];

        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'Could not map PayPal subscription to a user.'];
        }

        $nextBilling = null;
        if (!empty($body['billing_info']['next_billing_time'])) {
            $nextBilling = date('Y-m-d H:i:s', strtotime((string)$body['billing_info']['next_billing_time']));
        }
        $startedAt = null;
        if (!empty($body['start_time'])) {
            $startedAt = date('Y-m-d H:i:s', strtotime((string)$body['start_time']));
        }

        $subscriberEmail = (string)($body['subscriber']['email_address'] ?? '');
        $paypalPlanId = (string)($body['plan_id'] ?? '');

        $subStmt = $db->prepare(
            'INSERT INTO paypal_subscriptions
                (user_id, package_id, paypal_subscription_id, paypal_plan_id, status, subscriber_email, next_billing_time, raw_payload)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                package_id = VALUES(package_id),
                paypal_plan_id = VALUES(paypal_plan_id),
                status = VALUES(status),
                subscriber_email = VALUES(subscriber_email),
                next_billing_time = VALUES(next_billing_time),
                raw_payload = VALUES(raw_payload),
                updated_at = NOW()'
        );
        $subStmt->execute([
            $userId,
            $packageId > 0 ? $packageId : null,
            $subscriptionId,
            $paypalPlanId !== '' ? $paypalPlanId : null,
            $status,
            $subscriberEmail !== '' ? $subscriberEmail : null,
            $nextBilling,
            json_encode($body),
        ]);

        paypalMapStatusToUserState($db, $userId, $status, $packageId, $subscriptionId);

        $db->prepare(
            'UPDATE users
             SET subscription_started_at = COALESCE(subscription_started_at, ?),
                 subscription_ends_at = ?,
                 subscription_updated_at = NOW()
             WHERE id = ?'
        )->execute([
            $startedAt,
            $nextBilling,
            $userId,
        ]);

        return [
            'ok' => true,
            'status' => $status,
            'user_id' => $userId,
            'package_id' => $packageId,
            'source' => $source,
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function paypalRecordPayment(PDO $db, array $payment): void
{
    ensureBillingSchema($db);

    if (trim((string)($payment['paypal_transaction_id'] ?? '')) === '') {
        return;
    }

    $stmt = $db->prepare(
        'INSERT INTO paypal_payments
            (user_id, paypal_subscription_id, paypal_transaction_id, status, amount, currency_code, payer_email, paid_at, raw_payload)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            amount = VALUES(amount),
            currency_code = VALUES(currency_code),
            payer_email = VALUES(payer_email),
            paid_at = VALUES(paid_at),
            raw_payload = VALUES(raw_payload)'
    );
    $stmt->execute([
        isset($payment['user_id']) ? (int)$payment['user_id'] : null,
        $payment['paypal_subscription_id'] ?? null,
        $payment['paypal_transaction_id'],
        $payment['status'] ?? 'unknown',
        isset($payment['amount']) ? (float)$payment['amount'] : null,
        $payment['currency_code'] ?? null,
        $payment['payer_email'] ?? null,
        $payment['paid_at'] ?? null,
        isset($payment['raw_payload']) ? json_encode($payment['raw_payload']) : null,
    ]);
}

function paypalExtractPaymentRecordFromWebhook(array $event): array
{
    $resource = (array)($event['resource'] ?? []);

    $subscriptionId = (string)($resource['billing_agreement_id'] ?? $resource['id'] ?? '');
    $transactionId = (string)($resource['id'] ?? '');
    $status = strtolower((string)($resource['status'] ?? 'completed'));
    $amount = null;
    $currency = null;

    if (!empty($resource['amount']['total'])) {
        $amount = (float)$resource['amount']['total'];
        $currency = (string)($resource['amount']['currency'] ?? '');
    } elseif (!empty($resource['amount']['value'])) {
        $amount = (float)$resource['amount']['value'];
        $currency = (string)($resource['amount']['currency_code'] ?? '');
    }

    $paidAt = null;
    if (!empty($resource['create_time'])) {
        $paidAt = date('Y-m-d H:i:s', strtotime((string)$resource['create_time']));
    }

    return [
        'paypal_subscription_id' => $subscriptionId !== '' ? $subscriptionId : null,
        'paypal_transaction_id' => $transactionId,
        'status' => $status,
        'amount' => $amount,
        'currency_code' => $currency !== '' ? $currency : null,
        'payer_email' => (string)($resource['payer']['email_address'] ?? $resource['payer_email'] ?? ''),
        'paid_at' => $paidAt,
        'raw_payload' => $event,
    ];
}

function paypalHeadersLowercase(array $headers): array
{
    $result = [];
    foreach ($headers as $k => $v) {
        $result[strtolower((string)$k)] = is_array($v) ? implode(', ', $v) : (string)$v;
    }
    return $result;
}

function paypalVerifyWebhookSignature(string $rawBody, array $headers): bool
{
    if (!paypalIsConfigured() || trim(PAYPAL_WEBHOOK_ID) === '') {
        return false;
    }

    $headers = paypalHeadersLowercase($headers);
    $required = [
        'paypal-transmission-id',
        'paypal-transmission-time',
        'paypal-transmission-sig',
        'paypal-cert-url',
        'paypal-auth-algo',
    ];

    foreach ($required as $h) {
        if (empty($headers[$h])) {
            return false;
        }
    }

    $event = json_decode($rawBody, true);
    if (!is_array($event)) {
        return false;
    }

    try {
        $accessToken = paypalGetAccessToken();
        $payload = [
            'transmission_id' => $headers['paypal-transmission-id'],
            'transmission_time' => $headers['paypal-transmission-time'],
            'cert_url' => $headers['paypal-cert-url'],
            'auth_algo' => $headers['paypal-auth-algo'],
            'transmission_sig' => $headers['paypal-transmission-sig'],
            'webhook_id' => PAYPAL_WEBHOOK_ID,
            'webhook_event' => $event,
        ];

        $result = paypalApiRequest('POST', '/v1/notifications/verify-webhook-signature', $accessToken, $payload);
        return $result['status'] >= 200
            && $result['status'] < 300
            && strtoupper((string)($result['body']['verification_status'] ?? '')) === 'SUCCESS';
    } catch (Throwable $e) {
        return false;
    }
}
