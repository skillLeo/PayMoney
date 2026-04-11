<?php

namespace App\Services\VirtualCard\visa;

use App\Models\VirtualCardMethod;
use App\Models\VirtualCardOrder;
use App\Models\VirtualCardTransaction;
use App\Services\VisaService;
use Carbon\Carbon;

/**
 * Visa Virtual Card Service
 *
 * Uses the Visa Token Service (VTS) to provision virtual card tokens.
 * Supports create, block, unblock, and add-fund operations.
 */
class Card
{
    /**
     * Dispatch a card operation.
     *
     * @param VirtualCardOrder $cardOrder
     * @param string           $operation  create | block | unblock | fundApprove
     * @return array{status:string, data:mixed, ...}
     */
    public static function cardRequest($cardOrder, string $operation): array
    {
        $method  = VirtualCardMethod::where('code', 'visa')->first();
        $params  = $method?->parameters ?? new \stdClass();

        $service = new VisaService([
            'username'    => $params->username    ?? null,
            'password'    => $params->password    ?? null,
            'x_pay_token' => $params->x_pay_token ?? null,
            'key_id'      => $params->key_id       ?? null,
        ]);

        return match ($operation) {
            'create'      => self::createCard($service, $cardOrder),
            'block'       => self::blockCard($cardOrder),
            'unblock'     => self::unblockCard($cardOrder),
            'fundApprove' => self::fundAddCard($cardOrder),
            default       => ['status' => 'error', 'data' => 'Unsupported operation: ' . $operation],
        };
    }

    /**
     * Provision a new Visa virtual card via the Token Service.
     */
    private static function createCard(VisaService $service, VirtualCardOrder $cardOrder): array
    {
        $userInfo = $cardOrder->form_input;

        $email      = $userInfo?->CustomerEmail?->field_value ?? ($cardOrder->user?->email ?? 'user@example.com');
        $expMonth   = $userInfo?->ExpMonth?->field_value      ?? '12';
        $expYear    = $userInfo?->ExpYear?->field_value        ?? now()->addYears(4)->format('Y');
        $cvv        = $userInfo?->Cvv?->field_value            ?? '123';
        $cardNumber = $userInfo?->CardNumber?->field_value     ?? '4895142232120006';
        $countryCode= $userInfo?->CountryCode?->field_value    ?? 'US';
        $zipCode    = $userInfo?->PostalCode?->field_value     ?? '94404';

        try {
            $response = $service->provisionVirtualCard([
                'email'        => $email,
                'card_number'  => $cardNumber,
                'exp_month'    => $expMonth,
                'exp_year'     => $expYear,
                'cvv'          => $cvv,
                'country_code' => $countryCode,
                'zip_code'     => $zipCode,
            ]);

            if ($response['status'] !== 'success') {
                return [
                    'status' => 'error',
                    'data'   => $response['message'] ?? 'Visa virtual card provisioning failed.',
                ];
            }

            $tokenInfo   = $response['data']['tokenInfo']   ?? [];
            $cardMeta    = $response['data']['cardMetaData'] ?? [];
            $tokenNumber = $tokenInfo['tokenNumber']         ?? $cardNumber;
            $expData     = $tokenInfo['expirationDate']      ?? [];

            $expM    = str_pad($expData['month'] ?? $expMonth, 2, '0', STR_PAD_LEFT);
            $expY    = strlen($expData['year'] ?? $expYear) === 2
                ? '20' . ($expData['year'] ?? substr($expYear, -2))
                : ($expData['year'] ?? $expYear);
            $expDate = $expY . '-' . $expM . '-01';

            $tokenRef = $response['data']['vProvisionedTokenID']
                ?? ($tokenInfo['tokenReferenceID'] ?? uniqid('visa_'));

            return [
                'status'      => 'success',
                'card_id'     => $tokenRef,
                'brand'       => 'VISA',
                'card_number' => $tokenNumber,
                'cvv'         => $cardMeta['cvv2'] ?? $cvv,
                'expiry_date' => $expDate,
                'name_on_card'=> $userInfo?->FullName?->field_value ?? ($cardOrder->user?->name ?? 'Card Holder'),
                'balance'     => 0,
                'data'        => self::preprocessCardData($response['data'], $cardOrder),
            ];

        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'data'   => 'An error occurred: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Block a Visa virtual card (mark as inactive).
     * The VTS does not expose a direct suspend endpoint in sandbox;
     * we update the local status and mark the card blocked.
     */
    private static function blockCard(VirtualCardOrder $cardOrder): array
    {
        try {
            $cardOrder->update(['status' => 7]);

            return [
                'status' => 'success',
                'data'   => 'Card blocked successfully.',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'data'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Unblock a Visa virtual card.
     */
    private static function unblockCard(VirtualCardOrder $cardOrder): array
    {
        try {
            $cardOrder->update(['status' => 1]);

            return [
                'status' => 'success',
                'data'   => 'Card unblocked successfully.',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'data'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Approve adding funds to the virtual card balance.
     */
    private static function fundAddCard(VirtualCardOrder $cardOrder): array
    {
        try {
            if (!$cardOrder) {
                return ['status' => 'error', 'data' => 'Card order not found.'];
            }

            $newBalance = ($cardOrder->balance ?? 0) + ($cardOrder->fund_amount ?? 0);

            return [
                'status'  => 'success',
                'balance' => $newBalance,
                'data'    => 'Funds added to Visa virtual card.',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'data'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch and sync recent transactions for a Visa virtual card.
     * In sandbox, Visa does not provide live transaction data; we record
     * any local transaction entries instead.
     */
    public static function getTrx(string $cardId): void
    {
        // Visa Token Service sandbox does not expose a live transaction-list endpoint.
        // Transactions are recorded locally when push/pull operations complete.
        // This method is a no-op placeholder that is safe to call.
    }

    /**
     * Build a normalized card-data array for storage in card_info.
     */
    private static function preprocessCardData(array $apiData, VirtualCardOrder $cardOrder): array
    {
        $tokenInfo = $apiData['tokenInfo']   ?? [];
        $cardMeta  = $apiData['cardMetaData'] ?? [];
        $expData   = $tokenInfo['expirationDate'] ?? [];

        $expM = str_pad($expData['month'] ?? '12', 2, '0', STR_PAD_LEFT);
        $expY = (strlen($expData['year'] ?? '25') === 2)
            ? '20' . ($expData['year'] ?? '29')
            : ($expData['year'] ?? '2029');

        return [
            'id'              => $apiData['vProvisionedTokenID'] ?? ($tokenInfo['tokenReferenceID'] ?? ''),
            'token_reference' => $tokenInfo['tokenReferenceID']  ?? '',
            'card_number'     => $tokenInfo['tokenNumber']        ?? '',
            'cvv'             => $cardMeta['cvv2']                ?? '',
            'brand'           => 'VISA',
            'exp_month'       => $expM,
            'exp_year'        => $expY,
            'expiry_date'     => $expM . '/' . substr($expY, -2),
            'name_on_card'    => $cardOrder->user?->name ?? 'Card Holder',
            'status'          => $apiData['tokenStatus'] ?? 'ACTIVE',
            'balance'         => number_format(0, 2),
            'currency'        => strtoupper($cardOrder->currency ?? 'USD'),
        ];
    }
}
