<?php
define('MIDTRANS_SERVER_KEY', 'SB-Mid-server-1_nd3YDHXLDw1I0G4SZR24E1');
define('MIDTRANS_IS_PRODUCTION', false);
define('MIDTRANS_API_URL', 'https://api.sandbox.midtrans.com/v2/charge');
define('MIDTRANS_STATUS_URL', 'https://api.sandbox.midtrans.com/v2/');

function midtrans_charge_qris(string $order_id, int $gross_amount): array {
    $payload = [
        'payment_type' => 'qris',
        'transaction_details' => [
            'order_id' => $order_id,
            'gross_amount' => $gross_amount,
        ],
        'qris' => [
            'acquirer' => 'gopay'
        ]
    ];

    $auth = base64_encode(MIDTRANS_SERVER_KEY . ':');
    
    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n" .
                         "Accept: application/json\r\n" .
                         "Authorization: Basic " . $auth . "\r\n",
            'content' => json_encode($payload),
            'ignore_errors' => true
        ]
    ];

    $context  = stream_context_create($options);
    $response = @file_get_contents(MIDTRANS_API_URL, false, $context);
    
    if ($response === false) {
        throw new Exception('Gagal terhubung ke Midtrans.');
    }

    $http_code = 0;
    if (isset($http_response_header[0])) {
        preg_match('/\d{3}/', $http_response_header[0], $matches);
        if (isset($matches[0])) {
            $http_code = (int) $matches[0];
        }
    }

    if ($http_code !== 201 && $http_code !== 200) {
        throw new Exception('Gagal membuat QRIS (HTTP ' . $http_code . '): ' . $response);
    }

    return json_decode($response, true);
}

function midtrans_get_status(string $order_id): array {
    $url = MIDTRANS_STATUS_URL . $order_id . '/status';
    $auth = base64_encode(MIDTRANS_SERVER_KEY . ':');
    
    $options = [
        'http' => [
            'method'  => 'GET',
            'header'  => "Accept: application/json\r\n" .
                         "Authorization: Basic " . $auth . "\r\n",
            'ignore_errors' => true
        ]
    ];
    
    $context  = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return [];
    }
    
    return json_decode($response, true) ?: [];
}

// Membatalkan pesanan yang masih pending di Midtrans. Mengembalikan balasan Midtrans, atau [] bila gagal terhubung.
// Balasan berhasil memuat transaction_status "cancel"; status_code "412" berarti pesanan tidak bisa lagi dibatalkan.
function midtrans_cancel(string $order_id): array {
    $url = MIDTRANS_STATUS_URL . rawurlencode($order_id) . '/cancel';
    $auth = base64_encode(MIDTRANS_SERVER_KEY . ':');

    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n" .
                         "Accept: application/json\r\n" .
                         "Authorization: Basic " . $auth . "\r\n",
            'content' => '{}',
            'ignore_errors' => true
        ]
    ];

    $context  = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return [];
    }

    return json_decode($response, true) ?: [];
}