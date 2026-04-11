<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Visa Developer API Configuration
    |--------------------------------------------------------------------------
    | Credentials for Visa Developer sandbox / production APIs.
    | mTLS certificate and private key paths are relative to base_path().
    */

    'api_base_url'          => env('VISA_API_BASE_URL', 'https://sandbox.api.visa.com'),
    'username'              => env('VISA_USERNAME', ''),
    'password'              => env('VISA_PASSWORD', ''),
    'x_pay_token_key'       => env('VISA_X_PAY_TOKEN', ''),
    'key_id'                => env('VISA_KEY_ID', ''),
    'cert_path'             => env('VISA_CERT_PATH', 'storage/app/visa/certificate.pem'),
    'key_path'              => env('VISA_KEY_PATH', 'storage/app/visa/private_key.pem'),

    // Acquiring BIN and country used in Visa Direct requests
    'acquiring_bin'         => env('VISA_ACQUIRING_BIN', '408999'),
    'acquirer_country_code' => env('VISA_ACQUIRER_COUNTRY_CODE', '840'),

    // Toggle to disable real API calls (uses simulated sandbox responses instead)
    'mock'                  => env('VISA_MOCK', false),
];
