<?php

namespace App\Services;

/**
 * Visa Developer API Service
 *
 * Handles mTLS + HTTP Basic Auth requests to the Visa Developer Platform.
 * Supports:
 *  - Visa Direct Pull Funds (charge a card → add funds to wallet)
 *  - Visa Direct Push Funds (send money to a card)
 *  - Visa Token Service provisioning (virtual card creation)
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

    /**
     * @param array $overrides  Optional credential overrides (from gateway DB parameters).
     */
    public function __construct(array $overrides = [])
    {
        $this->baseUrl      = rtrim($overrides['api_base_url'] ?? config('visa.api_base_url', 'https://sandbox.api.visa.com'), '/');
        $this->username     = $overrides['username']      ?? config('visa.username', '');
        $this->password     = $overrides['password']      ?? config('visa.password', '');
        $this->xPayTokenKey = $overrides['x_pay_token']   ?? config('visa.x_pay_token_key', '');
        $this->keyId        = $overrides['key_id']        ?? config('visa.key_id', '');
        $this->certPath     = base_path($overrides['cert_path'] ?? config('visa.cert_path', 'storage/app/visa/certificate.pem'));
        $this->keyPath      = base_path($overrides['key_path']  ?? config('visa.key_path', 'storage/app/visa/private_key.pem'));

        // Auto-enable mock mode when VISA_MOCK=true OR when mTLS cert files are absent
        // (cert files are required for real Visa API calls in most sandbox scenarios).
        $forcedMock = (bool) ($overrides['mock'] ?? config('visa.mock', false));
        $certsExist  = file_exists($this->certPath) && file_exists($this->keyPath);
        $this->mock  = $forcedMock || !$certsExist;
    }

    /**
     * Generate X-Pay-Token header value.
     *
     * Format: xv2:{timestamp}:{HMAC-SHA256}
     * Hash input: apiKey + timestamp + resourcePath + queryString + requestBody
     */
    public function generateXPayToken(string $resourcePath, string $queryString = '', string $requestBody = ''): string
    {
        $timestamp = (string) time();
        $message   = $this->xPayTokenKey . $timestamp . $resourcePath . $queryString . $requestBody;
        $hash      = hash_hmac('sha256', $message, $this->xPayTokenKey);

        return 'xv2:' . $timestamp . ':' . $hash;
    }

    /** Generate a 6-digit Systems Trace Audit Number */
    public function generateStan(): string
    {
        return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /** Generate a 12-digit Retrieval Reference Number */
    public function generateRrn(): string
    {
        return str_pad((string) random_int(100000000000, 999999999999), 12, '0', STR_PAD_LEFT);
    }

    /**
     * Make an authenticated API request to Visa.
     *
     * @param string $method          HTTP method (GET|POST|PUT)
     * @param string $endpoint        API endpoint path (e.g. /visadirect/fundstransfer/v1/...)
     * @param array  $data            Request payload
     * @param bool   $useXPayToken    Whether to include X-Pay-Token header
     * @return array{status:string, code:int, message:string, data:array|null}
     */
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

        // Extract path component for X-Pay-Token generation
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
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        // Attach mTLS client certificate if available
        if (file_exists($this->certPath)) {
            curl_setopt($ch, CURLOPT_SSLCERT, $this->certPath);
        }
        if (file_exists($this->keyPath)) {
            curl_setopt($ch, CURLOPT_SSLKEY, $this->keyPath);
        }

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
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
            return [
                'status'  => 'error',
                'code'    => 0,
                'message' => 'cURL error: ' . $curlError,
                'data'    => null,
            ];
        }

        $decoded = json_decode($response, true) ?? [];
        $success = ($httpCode >= 200 && $httpCode < 300);

        return [
            'status'  => $success ? 'success' : 'error',
            'code'    => $httpCode,
            'message' => $decoded['responseStatus']['message']
                ?? $decoded['errorMessage']
                ?? ($success ? 'OK' : 'API error ' . $httpCode),
            'data'    => $decoded,
        ];
    }

    /* -----------------------------------------------------------------
     * Visa Direct – Pull Funds (charge sender's card → credit platform)
     * ----------------------------------------------------------------- */

    /**
     * Pull funds from a cardholder's Visa card.
     *
     * @param array $params {card_number, expiry_month, expiry_year, amount, currency}
     */
    public function pullFundsTransaction(array $params): array
    {
        if ($this->mock) {
            return $this->mockPullFundsResponse($params);
        }

        $payload = [
            'systemsTraceAuditNumber'     => $this->generateStan(),
            'retrievalReferenceNumber'     => $this->generateRrn(),
            'localTransactionDateTime'     => now()->format('Y-m-d\TH:i:s'),
            'acquiringBin'                 => config('visa.acquiring_bin', '408999'),
            'acquirerCountryCode'          => config('visa.acquirer_country_code', '840'),
            'senderPrimaryAccountNumber'   => preg_replace('/\s+/', '', $params['card_number']),
            'amount'                       => (int) round(($params['amount'] ?? 0) * 100),
            'transactionCurrencyCode'      => $params['currency'] ?? 'USD',
            'senderCardExpiryDate'         => $params['expiry_year'] . '-' . str_pad($params['expiry_month'] ?? '12', 2, '0', STR_PAD_LEFT),
            'senderCurrencyCode'           => $params['currency'] ?? 'USD',
            'businessApplicationId'        => 'AA',
            'foreignExchangeFeeTransaction'=> 0,
            'cardAcceptor'                 => [
                'address'    => [
                    'county'  => 'San Mateo',
                    'country' => 'USA',
                    'state'   => 'CA',
                    'zipCode' => '94404',
                ],
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

    /**
     * Push funds to a cardholder's Visa card.
     *
     * @param array $params {recipient_card, amount, currency, sender_name, sender_account}
     */
    public function pushFundsTransaction(array $params): array
    {
        if ($this->mock) {
            return $this->mockPushFundsResponse($params);
        }

        $payload = [
            'systemsTraceAuditNumber'       => $this->generateStan(),
            'retrievalReferenceNumber'       => $this->generateRrn(),
            'localTransactionDateTime'       => now()->format('Y-m-d\TH:i:s'),
            'acquiringBin'                   => config('visa.acquiring_bin', '408999'),
            'acquirerCountryCode'            => config('visa.acquirer_country_code', '840'),
            'recipientPrimaryAccountNumber'  => preg_replace('/\s+/', '', $params['recipient_card']),
            'amount'                         => (int) round(($params['amount'] ?? 0) * 100),
            'transactionCurrencyCode'        => $params['currency'] ?? 'USD',
            'businessApplicationId'          => 'PP',
            'foreignExchangeFeeTransaction'  => 0,
            'senderName'                     => $params['sender_name']    ?? 'Platform',
            'senderAccountNumber'            => $params['sender_account'] ?? '4895142232120006',
            'senderAddress'                  => $params['sender_address'] ?? '123 Main Street',
            'senderCity'                     => $params['sender_city']    ?? 'San Mateo',
            'senderStateCode'                => $params['sender_state']   ?? 'CA',
            'senderCountryCode'              => $params['sender_country'] ?? 'USA',
            'cardAcceptor'                   => [
                'address'    => [
                    'county'  => 'San Mateo',
                    'country' => 'USA',
                    'state'   => 'CA',
                    'zipCode' => '94404',
                ],
                'idCode'     => 'VISAPLATFORM01',
                'name'       => config('app.name', 'Platform'),
                'terminalId' => 'TERM0001',
            ],
        ];

        return $this->request('POST', '/visadirect/fundstransfer/v1/pushfundstransactions', $payload);
    }

    /* -----------------------------------------------------------------
     * Visa Token Service – Provision Virtual Card
     * ----------------------------------------------------------------- */

    /**
     * Create a virtual card token via Visa Token Service.
     *
     * @param array $params {email, card_number, exp_month, exp_year, cvv, country_code, zip_code}
     */
    public function provisionVirtualCard(array $params): array
    {
        if ($this->mock) {
            return $this->mockVirtualCardResponse($params);
        }

        $payload = [
            'clientWalletAccountEmailAddress' => $params['email'] ?? '',
            'clientWalletProvider'            => '10000',
            'clientDeviceID'                  => 'DEVICE_' . strtoupper(substr(md5(uniqid()), 0, 12)),
            'clientWalletID'                  => 'WALLET_' . strtoupper(substr(md5(uniqid()), 0, 12)),
            'paymentInstrument'               => [
                'accountNumber'  => preg_replace('/\s+/', '', $params['card_number'] ?? '4895142232120006'),
                'expirationDate' => [
                    'month' => str_pad($params['exp_month'] ?? '12', 2, '0', STR_PAD_LEFT),
                    'year'  => substr($params['exp_year'] ?? '2025', -2),
                ],
                'securityCode'   => $params['cvv'] ?? '123',
            ],
            'cardMetaData' => [
                'countryCode' => $params['country_code'] ?? 'US',
                'zipCode'     => $params['zip_code']     ?? '94404',
            ],
        ];

        return $this->request('POST', '/vts/provisioning/v2/tokens', $payload);
    }

    /* -----------------------------------------------------------------
     * Mock / Sandbox simulated responses
     * Returned when VISA_MOCK=true (no real API call needed)
     * ----------------------------------------------------------------- */

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
            ],
        ];
    }

    private function mockVirtualCardResponse(array $params): array
    {
        // Generate realistic test Visa card data for sandbox
        $cardNumber  = '4' . str_pad((string) random_int(100000000000000, 999999999999999), 15, '0', STR_PAD_LEFT);
        $expYear     = now()->addYears(4)->format('Y');
        $expMonth    = str_pad((string) random_int(1, 12), 2, '0', STR_PAD_LEFT);
        $cvv         = str_pad((string) random_int(100, 999), 3, '0', STR_PAD_LEFT);
        $tokenRef    = 'DNITHE' . strtoupper(substr(md5(uniqid()), 0, 18));

        return [
            'status'  => 'success',
            'code'    => 200,
            'message' => 'OK',
            'data'    => [
                'vProvisionedTokenID' => 'tok_' . strtolower(substr(md5(uniqid()), 0, 20)),
                'tokenInfo'           => [
                    'tokenReferenceID' => $tokenRef,
                    'tokenNumber'      => $cardNumber,
                    'expirationDate'   => [
                        'month' => $expMonth,
                        'year'  => substr($expYear, -2),
                    ],
                ],
                'tokenStatus'         => 'ACTIVE',
                'cardMetaData'        => [
                    'cvv2'    => $cvv,
                    'brand'   => 'VISA',
                ],
                '_mock'               => true,
            ],
        ];
    }

    /* -----------------------------------------------------------------
     * Visa Direct – Query Transaction Status
     * ----------------------------------------------------------------- */

    /**
     * Query the status of a previously submitted transaction.
     *
     * @param string $statusIdentifier  The transactionIdentifier returned at submit time
     * @param string $type              'pull' or 'push'
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

    /* ----------------------------------------------------------------- */

    /**
     * Check whether a Visa API action-code indicates success.
     * Action code "00" = approved.
     */
    public static function isApproved(array $response): bool
    {
        if ($response['status'] !== 'success') {
            return false;
        }
        $data = $response['data'] ?? [];
        return isset($data['actionCode']) && $data['actionCode'] === '00';
    }
}
