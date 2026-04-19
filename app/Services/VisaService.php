<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Visa Developer API Service
 *
 * Handles mTLS + HTTP Basic Auth requests to the Visa Developer Platform.
 * Supports:
 *  - VisaNet Connect Issuing (create real virtual card numbers)
 *  - Visa Direct Pull Funds  (charge a card → add funds to wallet)
 *  - Visa Direct Push Funds  (send money to a card)
 */
class VisaService
{
    public string $baseUrl;
    public string $username;
    public string $password;
    public string $xPayTokenKey;
    public string $keyId;
    public string $certPath;
    public string $keyPath;
    public bool   $mock;

    public function __construct(array $overrides = [])
    {
        $this->baseUrl      = rtrim($overrides['api_base_url'] ?? config('visa.api_base_url', 'https://sandbox.api.visa.com'), '/');
        $this->username     = $overrides['username']    ?? config('visa.username', '');
        $this->password     = $overrides['password']    ?? config('visa.password', '');
        $this->xPayTokenKey = $overrides['x_pay_token'] ?? config('visa.x_pay_token_key', '');
        $this->keyId        = $overrides['key_id']      ?? config('visa.key_id', '');
        $this->certPath     = base_path($overrides['cert_path'] ?? config('visa.cert_path', 'storage/app/visa/certificate.pem'));
        $this->keyPath      = base_path($overrides['key_path']  ?? config('visa.key_path',  'storage/app/visa/private_key.pem'));

        $forcedMock  = (bool) ($overrides['mock'] ?? config('visa.mock', false));
        $certsExist  = file_exists($this->certPath) && file_exists($this->keyPath);
        $this->mock  = $forcedMock || !$certsExist;

        if (!$certsExist && !$forcedMock) {
            Log::warning('VisaService: mTLS certificate files not found — running in mock mode.', [
                'cert_path' => $this->certPath,
                'key_path'  => $this->keyPath,
            ]);
        }
    }

    /* -----------------------------------------------------------------
     * VisaNet Connect Issuing — Create a new real virtual card
     * ----------------------------------------------------------------- */

    /**
     * Issue a new virtual card via VisaNet Connect Issuing.
     *
     * Visa returns a real PAN, CVV2, and expiry date.
     * These are the numbers that work on Google Pay, PayPal, and every payment processor.
     *
     * @param array $params {
     *   cardholder_name, address, city, state, zip_code, country_code,
     *   exp_month (optional), exp_year (optional), email (optional)
     * }
     */
    public function issueVirtualCard(array $params): array
    {
        if ($this->mock) {
            return $this->mockVirtualCardResponse($params);
        }

        // VisaNet Connect Issuing uses YYMM expiry format
        $expYear  = substr($params['exp_year']  ?? now()->addYears(4)->format('Y'), -2);
        $expMonth = str_pad($params['exp_month'] ?? '12', 2, '0', STR_PAD_LEFT);
        $expDate  = $expYear . $expMonth; // e.g. "2812" for December 2028

        $msgId = strtoupper(substr(md5(uniqid('visa_', true)), 0, 24));

        $payload = [
            'RequestHeader' => [
                'MessageIdentification' => $msgId,
                'CreationDateTime'      => now()->format('Y-m-d\TH:i:s'),
                'EnvironmentDetails'    => [
                    'AcquirerBIN'         => config('visa.acquiring_bin', '408999'),
                    'AcquirerCountryCode' => config('visa.acquirer_country_code', '840'),
                ],
            ],
            'NewCardDetails' => [
                'CardholderName' => strtoupper($params['cardholder_name'] ?? 'CARD HOLDER'),
                'ExpiryDate'     => $expDate,
                'CardType'       => 'VIRTUAL',
                'BillingAddress' => [
                    'AddressLine1' => $params['address']      ?? '123 Main Street',
                    'City'         => $params['city']         ?? 'San Mateo',
                    'State'        => $params['state']        ?? 'CA',
                    'PostalCode'   => $params['zip_code']     ?? '94404',
                    'Country'      => $params['country_code'] ?? 'USA',
                ],
            ],
        ];

        if (!empty($params['email'])) {
            $payload['NewCardDetails']['CardholderEmail'] = $params['email'];
        }

        Log::info('VisaNet Connect Issuing — new card request', [
            'msg_id'  => $msgId,
            'payload' => $payload,
        ]);

        $response = $this->request(
            'POST',
            '/visanet-connect-issuing/v1/cardservices/newcard',
            $payload,
            true
        );

        Log::info('VisaNet Connect Issuing — raw response', [
            'status'   => $response['status'],
            'http_code'=> $response['code'],
            'data'     => $response['data'],
        ]);

        if ($response['status'] !== 'success') {
            return [
                'status'  => 'error',
                'message' => $response['message'] ?? 'Visa API error ' . $response['code'],
                'data'    => $response['data'],
            ];
        }

        return $this->normaliseIssueResponse($response['data'], $expMonth, $expYear);
    }

    /**
     * Map the raw VisaNet Connect Issuing response to a consistent internal format.
     */
    private function normaliseIssueResponse(array $raw, string $expMonth, string $expYear): array
    {
        // Visa may nest the card data differently depending on API version.
        // We check several possible keys so the code survives minor spec variations.
        $cardData = $raw['NewCardResponse']
            ?? $raw['CardDetails']
            ?? $raw['ResponseBody']
            ?? $raw['Body']['NewCardResp']
            ?? $raw;

        $pan   = $cardData['PrimaryAccountNumber'] ?? $cardData['PAN']    ?? $cardData['pan']    ?? $cardData['cardNumber']  ?? null;
        $cvv   = $cardData['CVV2']                 ?? $cardData['cvv2']   ?? $cardData['cvv']    ?? $cardData['securityCode'] ?? null;
        $exp   = $cardData['ExpiryDate']           ?? $cardData['expDt']  ?? $cardData['ExpDt']  ?? null;
        $ref   = $cardData['CardReferenceId']      ?? $cardData['cardRef']?? $cardData['CardRef']?? null;
        $status= $cardData['CardStatus']           ?? $cardData['status'] ?? 'ACTIVE';

        // Parse YYMM expiry from Visa if returned
        if ($exp && strlen($exp) === 4) {
            $expYear  = '20' . substr($exp, 0, 2);
            $expMonth = substr($exp, 2, 2);
        } else {
            $expYear  = '20' . $expYear;
        }

        return [
            'status'    => 'success',
            'pan'       => $pan,
            'cvv2'      => $cvv,
            'exp_month' => $expMonth,
            'exp_year'  => $expYear,
            'card_ref'  => $ref,
            'card_status'=> $status,
            '_raw'      => $raw,
        ];
    }

    /* -----------------------------------------------------------------
     * Visa Direct – Pull Funds (charge sender's card → credit platform)
     * ----------------------------------------------------------------- */

    public function pullFundsTransaction(array $params): array
    {
        if ($this->mock) {
            return $this->mockPullFundsResponse($params);
        }

        $payload = [
            'systemsTraceAuditNumber'   => $this->generateStan(),
            'retrievalReferenceNumber'   => $this->generateRrn(),
            'localTransactionDateTime'   => now()->format('Y-m-d\TH:i:s'),
            'acquiringBin'               => config('visa.acquiring_bin', '408999'),
            'acquirerCountryCode'        => config('visa.acquirer_country_code', '840'),
            'senderPrimaryAccountNumber' => preg_replace('/\s+/', '', $params['card_number']),
            'amount'                     => (int) round(($params['amount'] ?? 0) * 100),
            'transactionCurrencyCode'    => $params['currency'] ?? 'USD',
            'senderCardExpiryDate'       => ($params['expiry_year'] ?? now()->addYears(4)->format('Y'))
                                            . '-'
                                            . str_pad($params['expiry_month'] ?? '12', 2, '0', STR_PAD_LEFT),
            'senderCurrencyCode'         => $params['currency'] ?? 'USD',
            'businessApplicationId'      => 'AA',
            'foreignExchangeFeeTransaction' => 0,
            'cardAcceptor' => [
                'address'    => ['county' => 'San Mateo', 'country' => 'USA', 'state' => 'CA', 'zipCode' => '94404'],
                'idCode'     => 'VISAPLATFORM01',
                'name'       => config('app.name', 'Platform'),
                'terminalId' => 'TERM0001',
            ],
        ];

        return $this->request('POST', '/visadirect/fundstransfer/v1/pullfundstransactions', $payload);
    }

    /* -----------------------------------------------------------------
     * Visa Direct – Push Funds (debit platform → send to recipient card)
     * ----------------------------------------------------------------- */

    public function pushFundsTransaction(array $params): array
    {
        if ($this->mock) {
            return $this->mockPushFundsResponse($params);
        }

        $payload = [
            'systemsTraceAuditNumber'      => $this->generateStan(),
            'retrievalReferenceNumber'      => $this->generateRrn(),
            'localTransactionDateTime'      => now()->format('Y-m-d\TH:i:s'),
            'acquiringBin'                  => config('visa.acquiring_bin', '408999'),
            'acquirerCountryCode'           => config('visa.acquirer_country_code', '840'),
            'recipientPrimaryAccountNumber' => preg_replace('/\s+/', '', $params['recipient_card']),
            'amount'                        => (int) round(($params['amount'] ?? 0) * 100),
            'transactionCurrencyCode'       => $params['currency'] ?? 'USD',
            'businessApplicationId'         => 'PP',
            'foreignExchangeFeeTransaction' => 0,
            'senderName'                    => $params['sender_name']    ?? 'Platform',
            'senderAccountNumber'           => $params['sender_account'] ?? config('visa.platform_pan', '4895142232120006'),
            'senderAddress'                 => $params['sender_address'] ?? '123 Main Street',
            'senderCity'                    => $params['sender_city']    ?? 'San Mateo',
            'senderStateCode'               => $params['sender_state']   ?? 'CA',
            'senderCountryCode'             => $params['sender_country'] ?? 'USA',
            'cardAcceptor' => [
                'address'    => ['county' => 'San Mateo', 'country' => 'USA', 'state' => 'CA', 'zipCode' => '94404'],
                'idCode'     => 'VISAPLATFORM01',
                'name'       => config('app.name', 'Platform'),
                'terminalId' => 'TERM0001',
            ],
        ];

        return $this->request('POST', '/visadirect/fundstransfer/v1/pushfundstransactions', $payload);
    }

    /* -----------------------------------------------------------------
     * Low-level HTTP request (mTLS + Basic Auth)
     * ----------------------------------------------------------------- */

    public function request(string $method, string $endpoint, array $data = [], bool $useXPayToken = true): array
    {
        $method      = strtoupper($method);
        $url         = $this->baseUrl . $endpoint;
        $queryString = '';
        $requestBody = '';

        if ($method === 'GET' && !empty($data)) {
            $queryString = http_build_query($data);
            $url        .= '?' . $queryString;
        } else {
            $requestBody = !empty($data) ? json_encode($data, JSON_UNESCAPED_SLASHES) : '';
        }

        $resourcePath = parse_url($url, PHP_URL_PATH) ?? $endpoint;

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'keyId: ' . $this->keyId,
        ];

        if ($useXPayToken && $this->xPayTokenKey !== '') {
            $headers[] = 'x-pay-token: ' . $this->generateXPayToken($resourcePath, $queryString, $requestBody);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,            $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,     $headers);
        curl_setopt($ch, CURLOPT_USERPWD,        $this->username . ':' . $this->password);
        curl_setopt($ch, CURLOPT_TIMEOUT,        30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if (file_exists($this->certPath)) {
            curl_setopt($ch, CURLOPT_SSLCERT, $this->certPath);
        }
        if (file_exists($this->keyPath)) {
            curl_setopt($ch, CURLOPT_SSLKEY, $this->keyPath);
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST,       true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($requestBody !== '') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
            }
        }

        $response  = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Log::error('VisaService cURL error', ['endpoint' => $endpoint, 'error' => $curlError]);
            return ['status' => 'error', 'code' => 0, 'message' => 'cURL error: ' . $curlError, 'data' => null];
        }

        $decoded = json_decode($response, true) ?? [];
        $success = ($httpCode >= 200 && $httpCode < 300);

        if (!$success) {
            Log::error('VisaService API error', [
                'endpoint'  => $endpoint,
                'http_code' => $httpCode,
                'response'  => $decoded,
            ]);
        }

        return [
            'status'  => $success ? 'success' : 'error',
            'code'    => $httpCode,
            'message' => $decoded['responseStatus']['message']
                ?? $decoded['errorMessage']
                ?? $decoded['ResponseHeader']['StatusDescription']
                ?? ($success ? 'OK' : 'API error ' . $httpCode),
            'data'    => $decoded,
        ];
    }

    /* -----------------------------------------------------------------
     * Mock / Sandbox — Luhn-valid simulated responses
     * ----------------------------------------------------------------- */

    private function mockVirtualCardResponse(array $params): array
    {
        // Generate a Luhn-valid Visa number (starts with 4, 16 digits)
        $cardNumber = $this->generateLuhnValid('4', 16);
        $expYear    = now()->addYears(4)->format('Y');
        $expMonth   = str_pad((string) random_int(1, 12), 2, '0', STR_PAD_LEFT);
        $cvv2       = str_pad((string) random_int(100, 999), 3, '0', STR_PAD_LEFT);
        $cardRef    = 'VISAREF' . strtoupper(substr(md5(uniqid()), 0, 16));

        Log::info('VisaService mock: generated Luhn-valid card', [
            'card_number' => $cardNumber,
            'exp'         => $expMonth . '/' . $expYear,
            'luhn_valid'  => $this->luhnCheck($cardNumber),
        ]);

        return [
            'status'     => 'success',
            'pan'        => $cardNumber,
            'cvv2'       => $cvv2,
            'exp_month'  => $expMonth,
            'exp_year'   => $expYear,
            'card_ref'   => $cardRef,
            'card_status'=> 'ACTIVE',
            '_raw'       => ['_mock' => true],
        ];
    }

    private function mockPullFundsResponse(array $params): array
    {
        return [
            'status'  => 'success',
            'code'    => 200,
            'message' => 'OK',
            'data'    => [
                'transactionIdentifier' => (string) random_int(100000000000000, 999999999999999),
                'actionCode'            => '00',
                'approvalCode'          => strtoupper(substr(md5(uniqid()), 0, 6)),
                'responseCode'          => '5',
                'transmissionDateTime'  => now()->format('Y-m-d\TH:i:s'),
                '_mock'                 => true,
            ],
        ];
    }

    private function mockPushFundsResponse(array $params): array
    {
        return [
            'status'  => 'success',
            'code'    => 200,
            'message' => 'OK',
            'data'    => [
                'transactionIdentifier' => (string) random_int(100000000000000, 999999999999999),
                'actionCode'            => '00',
                'approvalCode'          => strtoupper(substr(md5(uniqid()), 0, 6)),
                'responseCode'          => '5',
                'transmissionDateTime'  => now()->format('Y-m-d\TH:i:s'),
                '_mock'                 => true,
            ],
        ];
    }

    /* -----------------------------------------------------------------
     * Luhn algorithm helpers
     * ----------------------------------------------------------------- */

    /**
     * Generate a Luhn-valid card number.
     *
     * @param string $prefix    Leading digits (e.g. "4" for Visa, "51"-"55" for Mastercard)
     * @param int    $length    Total card number length (default 16)
     */
    public function generateLuhnValid(string $prefix, int $length = 16): string
    {
        // Fill random digits up to length-1 (leave room for check digit)
        $number = $prefix;
        while (strlen($number) < $length - 1) {
            $number .= (string) random_int(0, 9);
        }

        // Compute Luhn check digit
        $digits   = array_reverse(str_split($number));
        $sum      = 0;
        foreach ($digits as $i => $digit) {
            $n = (int) $digit;
            if ($i % 2 === 0) {   // even positions from right-end get doubled
                $n *= 2;
                if ($n > 9) $n -= 9;
            }
            $sum += $n;
        }
        $checkDigit = (10 - ($sum % 10)) % 10;

        return $number . $checkDigit;
    }

    /**
     * Verify a card number passes the Luhn check.
     */
    public function luhnCheck(string $number): bool
    {
        $digits  = array_reverse(str_split($number));
        $sum     = 0;
        foreach ($digits as $i => $digit) {
            $n = (int) $digit;
            if ($i % 2 !== 0) {   // odd positions from right get doubled
                $n *= 2;
                if ($n > 9) $n -= 9;
            }
            $sum += $n;
        }
        return ($sum % 10) === 0;
    }

    /* -----------------------------------------------------------------
     * Utility helpers
     * ----------------------------------------------------------------- */

    public function generateXPayToken(string $resourcePath, string $queryString = '', string $requestBody = ''): string
    {
        $timestamp = (string) time();
        $message   = $this->xPayTokenKey . $timestamp . $resourcePath . $queryString . $requestBody;
        $hash      = hash_hmac('sha256', $message, $this->xPayTokenKey);
        return 'xv2:' . $timestamp . ':' . $hash;
    }

    public function generateStan(): string
    {
        return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function generateRrn(): string
    {
        return str_pad((string) random_int(100000000000, 999999999999), 12, '0', STR_PAD_LEFT);
    }

    public static function isApproved(array $response): bool
    {
        if ($response['status'] !== 'success') {
            return false;
        }
        $data = $response['data'] ?? [];
        return isset($data['actionCode']) && $data['actionCode'] === '00';
    }

    /**
     * Query the status of a Visa Direct transaction.
     */
    public function queryTransaction(string $statusIdentifier, string $type = 'pull'): array
    {
        if ($this->mock) {
            return [
                'status'  => 'success',
                'code'    => 200,
                'message' => 'OK',
                'data'    => [
                    'transactionIdentifier' => $statusIdentifier,
                    'actionCode'            => '00',
                    'approvalCode'          => strtoupper(substr(md5($statusIdentifier), 0, 6)),
                    'responseCode'          => '5',
                    'transmissionDateTime'  => now()->format('Y-m-d\TH:i:s'),
                    '_mock'                 => true,
                ],
            ];
        }

        $endpoint = $type === 'push'
            ? '/visadirect/fundstransfer/v1/pushfundstransactions/' . $statusIdentifier
            : '/visadirect/fundstransfer/v1/pullfundstransactions/' . $statusIdentifier;

        return $this->request('GET', $endpoint, [], true);
    }
}
