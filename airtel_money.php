<?php
function airtel_calculate_withdrawal_fee($amount)
{
    $amount = max(0.0, (float) $amount);

    if ($amount <= 500) {
        return 25.00;
    }
    if ($amount <= 1000) {
        return 50.00;
    }
    if ($amount <= 5000) {
        return 100.00;
    }
    if ($amount <= 10000) {
        return 150.00;
    }
    if ($amount <= 50000) {
        return 300.00;
    }

    return round($amount * 0.02, 2);
}

function airtel_total_to_send($amount)
{
    $fee = airtel_calculate_withdrawal_fee($amount);
    $total = round((float) $amount + $fee, 2);

    return array(
        'base_amount' => round((float) $amount, 2),
        'fee_amount' => round($fee, 2),
        'total_amount' => round($total, 2),
    );
}

function airtel_merchant_phone()
{
    $default = defined('AIRTEL_MERCHANT_PHONE') ? AIRTEL_MERCHANT_PHONE : CLINIC_CONTACT;
    return preg_replace('/\D+/', '', $default);
}

function airtel_is_configured()
{
    return !empty(AIRTEL_CLIENT_ID) && !empty(AIRTEL_CLIENT_SECRET);
}

function airtel_http_json($url, $headers, $payload = array(), $method = 'POST')
{
    if (!function_exists('curl_init')) {
        return array('ok' => false, 'message' => 'cURL is not available on this server. Airtel payment cannot be processed from this environment.');
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $header_list = array();
    foreach ($headers as $key => $value) {
        $header_list[] = $key . ': ' . $value;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header_list);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    } else {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (!empty($payload)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
    }

    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($errno !== 0) {
        return array('ok' => false, 'message' => 'CURL error: ' . $error);
    }

    $decoded = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE && !empty($body)) {
        return array('ok' => false, 'message' => 'Invalid JSON response from Airtel API.', 'http_code' => $http_code, 'raw' => $body);
    }

    return array(
        'ok' => true,
        'http_code' => $http_code,
        'data' => $decoded !== null ? $decoded : array()
    );
}

function airtel_get_access_token()
{
    if (!airtel_is_configured()) {
        return array('ok' => false, 'message' => 'Airtel Money credentials are missing. Set AIRTEL_CLIENT_ID and AIRTEL_CLIENT_SECRET in the environment.');
    }

    $url = rtrim(AIRTEL_API_BASE_URL, '/') . '/auth/oauth2/token';
    $basic = base64_encode(AIRTEL_CLIENT_ID . ':' . AIRTEL_CLIENT_SECRET);

    $headers = array(
        'Authorization' => 'Basic ' . $basic,
        'Content-Type' => 'application/x-www-form-urlencoded',
        'Accept' => 'application/json'
    );

    $response = airtel_http_json($url, $headers, array('grant_type' => 'client_credentials'), 'POST');
    if (!$response['ok']) {
        return $response;
    }

    $token = isset($response['data']['access_token']) ? $response['data']['access_token'] : null;
    if (!$token) {
        return array('ok' => false, 'message' => 'Airtel token request succeeded but no access token was returned.');
    }

    return array('ok' => true, 'access_token' => $token);
}

function airtel_start_collection($phone, $amount, $reference, $description = 'Clinic payment')
{
    $phone = preg_replace('/\D+/', '', $phone);
    if ($phone === '') {
        return array('ok' => false, 'status' => 'PENDING', 'message' => 'Invalid Airtel phone number.', 'transaction_id' => $reference);
    }

    $total = airtel_total_to_send($amount);
    $amount_value = number_format($total['total_amount'], 2, '.', '');
    $merchant_phone = airtel_merchant_phone();

    if (!airtel_is_configured()) {
        return array(
            'ok' => true,
            'status' => 'PENDING',
            'message' => 'Airtel API is not configured. Please send the total amount of MK ' . number_format($total['total_amount'], 0) . ' to ' . $merchant_phone . ' and confirm the payment in the system.',
            'transaction_id' => $reference,
            'manual' => true,
            'amount' => $total['total_amount'],
            'fee_amount' => $total['fee_amount'],
            'merchant_phone' => $merchant_phone,
        );
    }

    $token_result = airtel_get_access_token();
    if (!$token_result['ok']) {
        return array('ok' => false, 'status' => 'PENDING', 'message' => $token_result['message'], 'transaction_id' => $reference);
    }

    $payload = array(
        'reference' => $reference,
        'subscriber' => array(
            'country' => AIRTEL_COUNTRY,
            'currency' => AIRTEL_CURRENCY,
            'msisdn' => ltrim($phone, '0')
        ),
        'amount' => $amount_value,
        'transactionType' => 'PAYMENT',
        'description' => $description . ' | Send to ' . $merchant_phone . ' | Fee: MK ' . number_format($total['fee_amount'], 0),
        'requestDate' => gmdate('c')
    );

    $endpoints = array(
        rtrim(AIRTEL_API_BASE_URL, '/') . '/merchant/v1/payments/',
        rtrim(AIRTEL_API_BASE_URL, '/') . '/standard/v1/payments/',
        rtrim(AIRTEL_API_BASE_URL, '/') . '/merchant/v1/payments/collection',
        rtrim(AIRTEL_API_BASE_URL, '/') . '/standard/v1/payments/collection'
    );

    foreach ($endpoints as $endpoint) {
        $headers = array(
            'Authorization' => 'Bearer ' . $token_result['access_token'],
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        );

        $response = airtel_http_json($endpoint, $headers, $payload, 'POST');
        if (!$response['ok']) {
            continue;
        }

        $status = isset($response['data']['status']) ? $response['data']['status'] : (isset($response['data']['transactionStatus']) ? $response['data']['transactionStatus'] : 'PENDING');
        $transaction_id = isset($response['data']['transactionId']) ? $response['data']['transactionId'] : (isset($response['data']['id']) ? $response['data']['id'] : $reference);

        return array(
            'ok' => in_array(strtoupper((string)$status), array('SUCCESS', 'SUCCESSFUL', 'COMPLETED'), true) || ($response['http_code'] >= 200 && $response['http_code'] < 300),
            'status' => strtoupper((string)$status),
            'message' => isset($response['data']['message']) ? $response['data']['message'] : 'Airtel payment request created successfully.',
            'transaction_id' => $transaction_id,
            'raw' => $response['data']
        );
    }

    return array(
        'ok' => false,
        'status' => 'PENDING',
        'message' => 'Airtel Money API request failed or is not available in this environment.',
        'transaction_id' => $reference
    );
}

function airtel_check_collection($transaction_id)
{
    if (!airtel_is_configured()) {
        return array(
            'ok' => false,
            'status' => 'PENDING',
            'message' => 'Airtel Money is not configured on this server yet.'
        );
    }

    $token_result = airtel_get_access_token();
    if (!$token_result['ok']) {
        return array('ok' => false, 'status' => 'PENDING', 'message' => $token_result['message']);
    }

    $endpoints = array(
        rtrim(AIRTEL_API_BASE_URL, '/') . '/merchant/v1/payments/' . rawurlencode($transaction_id) . '/status',
        rtrim(AIRTEL_API_BASE_URL, '/') . '/standard/v1/payments/' . rawurlencode($transaction_id) . '/status',
        rtrim(AIRTEL_API_BASE_URL, '/') . '/merchant/v1/payments/status/' . rawurlencode($transaction_id),
        rtrim(AIRTEL_API_BASE_URL, '/') . '/standard/v1/payments/status/' . rawurlencode($transaction_id)
    );

    foreach ($endpoints as $endpoint) {
        $headers = array(
            'Authorization' => 'Bearer ' . $token_result['access_token'],
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        );

        $response = airtel_http_json($endpoint, $headers, [], 'GET');
        if (!$response['ok']) {
            continue;
        }

        $status = isset($response['data']['status']) ? $response['data']['status'] : (isset($response['data']['transactionStatus']) ? $response['data']['transactionStatus'] : 'PENDING');
        $message = isset($response['data']['message']) ? $response['data']['message'] : 'Airtel payment status check returned successfully.';

        return array(
            'ok' => true,
            'status' => strtoupper((string)$status),
            'message' => $message,
            'raw' => $response['data']
        );
    }

    return array('ok' => true, 'status' => 'PENDING', 'message' => 'Airtel payment is still pending.');
}
