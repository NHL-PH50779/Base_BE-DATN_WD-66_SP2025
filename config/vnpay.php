<?php

return [
    'vnp_TmnCode' => env('VNP_TMNCODE', 'E53K6FXV'),
    'vnp_HashSecret' => env('VNP_HASHSECRET', 'WD2X54VNM4W6PDRDNBPXUH95YV4B38NB'),
    'vnp_Url' => env('VNP_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
    'vnp_ReturnUrl' => env('VNP_RETURNURL', 'http://localhost:5174/vnpay-return'),
    'vnp_IpnUrl' => env('VNP_IPNURL', 'http://127.0.0.1:8000/api/payment/vnpay/ipn'),
    'vnp_Version' => '2.1.0',
    'vnp_Command' => 'pay',
    'vnp_CurrCode' => 'VND',
    'vnp_Locale' => 'vn',
];
