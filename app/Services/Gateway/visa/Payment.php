<?php

namespace App\Services\Gateway\visa;

use App\Models\Deposit;
use App\Services\VisaService;
use Facades\App\Services\BasicService;
use Illuminate\Support\Facades\Log;

/**
 * Visa Direct Payment Gateway
 *
 * Uses Visa Direct Pull Funds Transaction to charge the cardholder's Visa card
 * and credit their platform wallet (deposit / add-fund flow).
 *
 * Transaction statuses stored in deposits.payment_id (JSON):
 *   {"visa_txn_id":"...", "action_code":"00", "approval_code":"...", "status":"approved|declined|error"}
 */
class Payment
{
    public static function prepareData($deposit, $gateway): string
    {
        $send['view'] = 'user.payment.visa';
        return json_encode($send);
    }

    public static function ipn($request, $gateway, $deposit = null, $trx = null, $type = null): array
    {
        $cardNumber  = preg_replace('/\D/', '', (string) $request->input('card_number', ''));
        $expiryMonth = (string) $request->input('expiry_month', '12');
        $expiryYear  = (string) $request->input('expiry_year', '');
        $cardCvc     = (string) $request->input('card_cvc', '');

        if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
            return ['status' => 'error', 'msg' => 'Invalid card number.', 'redirect' => route('failed')];
        }

        if (empty($expiryYear) || (int) $expiryYear < (int) now()->format('Y')) {
            return ['status' => 'error', 'msg' => 'Card is expired.', 'redirect' => route('failed')];
        }

        if (!$deposit) {
            return ['status' => 'error', 'msg' => 'Deposit record not found.', 'redirect' => route('failed')];
        }

        $params  = $gateway->parameters ?? new \stdClass();
        $service = new VisaService([
            'username'    => $params->username    ?? null,
            'password'    => $params->password    ?? null,
            'x_pay_token' => $params->x_pay_token ?? null,
            'key_id'      => $params->key_id       ?? null,
        ]);

        $response = $service->pullFundsTransaction([
            'card_number'  => $cardNumber,
            'expiry_month' => $expiryMonth,
            'expiry_year'  => $expiryYear,
            'amount'       => $deposit->payable_amount,
            'currency'     => strtoupper($deposit->payment_method_currency ?? 'USD'),
        ]);

        $responseData = $response['data'] ?? [];
        $txnId        = $responseData['transactionIdentifier'] ?? null;
        $actionCode   = $responseData['actionCode']            ?? null;
        $approvalCode = $responseData['approvalCode']          ?? null;

        // Determine visa_status for our records
        $visaStatus = match (true) {
            VisaService::isApproved($response) => 'approved',
            isset($actionCode) && $actionCode !== '00' => 'declined',
            default => 'error',
        };

        // Store Visa transaction details in deposits.payment_id (JSON)
        $paymentInfo = json_encode([
            'visa_txn_id'   => $txnId,
            'action_code'   => $actionCode,
            'approval_code' => $approvalCode,
            'stan'          => $responseData['systemsTraceAuditNumber'] ?? null,
            'visa_status'   => $visaStatus,
            'mock'          => $responseData['_mock'] ?? false,
            'timestamp'     => now()->toISOString(),
        ]);

        // Save transaction info regardless of outcome
        $deposit->payment_id = $txnId;
        $deposit->information = $paymentInfo;
        $deposit->save();

        Log::channel('daily')->info('Visa Direct transaction', [
            'trx_id'      => $deposit->trx_id,
            'visa_txn_id' => $txnId,
            'action_code' => $actionCode,
            'visa_status' => $visaStatus,
            'amount'      => $deposit->payable_amount,
            'currency'    => $deposit->payment_method_currency,
        ]);

        if ($visaStatus === 'approved') {
            BasicService::preparePaymentUpgradation($deposit);
            return [
                'status'   => 'success',
                'msg'      => 'Payment approved. Visa Txn ID: ' . $txnId,
                'redirect' => route('success'),
            ];
        }

        // Declined – provide a human-readable message based on action code
        $msg = self::actionCodeMessage($actionCode) ?? ($response['message'] ?? 'Payment was not approved by Visa.');
        return ['status' => 'error', 'msg' => $msg, 'redirect' => route('failed')];
    }

    /**
     * Query the status of a previously submitted Visa Direct transaction.
     * Used by the admin / status-check endpoint.
     */
    public static function queryStatus(Deposit $deposit, $gateway): array
    {
        $info = json_decode($deposit->information ?? '{}', true);
        $txnId = $deposit->payment_id ?? ($info['visa_txn_id'] ?? null);

        if (!$txnId) {
            return ['status' => 'unknown', 'message' => 'No Visa transaction ID recorded.', 'data' => $info];
        }

        $params  = $gateway->parameters ?? new \stdClass();
        $service = new VisaService([
            'username'    => $params->username    ?? null,
            'password'    => $params->password    ?? null,
            'x_pay_token' => $params->x_pay_token ?? null,
            'key_id'      => $params->key_id       ?? null,
        ]);

        $response = $service->queryTransaction($txnId, 'pull');

        if ($response['status'] === 'success') {
            $d = $response['data'] ?? [];
            return [
                'status'        => VisaService::isApproved($response) ? 'approved' : 'declined',
                'visa_txn_id'   => $txnId,
                'action_code'   => $d['actionCode']    ?? ($info['action_code'] ?? null),
                'approval_code' => $d['approvalCode']  ?? ($info['approval_code'] ?? null),
                'message'       => $d['responseStatus']['message'] ?? 'OK',
                'data'          => $d,
                'cached'        => $info,
            ];
        }

        // Fallback: return what we stored locally
        return [
            'status'        => $info['visa_status'] ?? 'unknown',
            'visa_txn_id'   => $txnId,
            'action_code'   => $info['action_code']   ?? null,
            'approval_code' => $info['approval_code'] ?? null,
            'message'       => $response['message'] ?? 'Could not reach Visa API.',
            'cached'        => $info,
        ];
    }

    /**
     * Human-readable messages for Visa action codes.
     */
    public static function actionCodeMessage(?string $code): ?string
    {
        return match ($code) {
            '00' => 'Approved',
            '01' => 'Refer to issuer',
            '04' => 'Pick up card',
            '05' => 'Do not honor',
            '12' => 'Invalid transaction',
            '14' => 'Invalid card number',
            '51' => 'Insufficient funds',
            '54' => 'Card expired',
            '57' => 'Transaction not permitted',
            '61' => 'Exceeds withdrawal amount limit',
            '65' => 'Exceeds withdrawal frequency limit',
            '91' => 'Issuer unavailable',
            '96' => 'System error',
            default => $code ? 'Declined (code: ' . $code . ')' : null,
        };
    }
}
