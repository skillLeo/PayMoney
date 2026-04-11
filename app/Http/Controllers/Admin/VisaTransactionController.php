<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Gateway;
use App\Services\Gateway\visa\Payment as VisaPayment;
use Illuminate\Http\Request;

class VisaTransactionController extends Controller
{
    private function visaGateway(): ?Gateway
    {
        return Gateway::where('code', 'visa')->first();
    }

    /**
     * List all Visa deposits with their stored status.
     */
    public function index(Request $request)
    {
        $gateway = $this->visaGateway();

        $query = Deposit::with('user')
            ->where('payment_method_id', optional($gateway)->id)
            ->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // Filter by user
        if ($request->filled('user')) {
            $query->whereHas('user', fn ($q) => $q->where('username', 'like', '%' . $request->user . '%'));
        }

        $deposits = $query->paginate(20)->withQueryString();

        // Decode stored Visa info for each deposit
        $deposits->getCollection()->transform(function ($deposit) {
            $deposit->visa_info = json_decode($deposit->information ?? '{}', true);
            $deposit->visa_status_label = $this->resolveVisaStatusLabel($deposit);
            return $deposit;
        });

        return view('admin.visa_transactions.index', compact('deposits', 'gateway'));
    }

    /**
     * Show single deposit detail + live status re-check from Visa API.
     */
    public function show(Request $request, $id)
    {
        $gateway = $this->visaGateway();
        $deposit = Deposit::with('user')->findOrFail($id);
        $deposit->visa_info = json_decode($deposit->information ?? '{}', true);

        $liveStatus = null;
        if ($request->has('check')) {
            $liveStatus = VisaPayment::queryStatus($deposit, $gateway);
        }

        return view('admin.visa_transactions.show', compact('deposit', 'gateway', 'liveStatus'));
    }

    /**
     * AJAX: Re-check live transaction status from Visa API.
     */
    public function checkStatus($id)
    {
        $gateway = $this->visaGateway();
        $deposit = Deposit::findOrFail($id);

        $result = VisaPayment::queryStatus($deposit, $gateway);

        return response()->json([
            'deposit_status' => $deposit->status,
            'visa_status'    => $result['status'],
            'visa_txn_id'    => $result['visa_txn_id']   ?? null,
            'action_code'    => $result['action_code']   ?? null,
            'approval_code'  => $result['approval_code'] ?? null,
            'message'        => $result['message']        ?? null,
            'action_label'   => VisaPayment::actionCodeMessage($result['action_code'] ?? null),
            'cached'         => $result['cached']         ?? [],
        ]);
    }

    private function resolveVisaStatusLabel(Deposit $deposit): string
    {
        $info = $deposit->visa_info ?? [];
        $visaStatus = $info['visa_status'] ?? null;

        if ($visaStatus === 'approved' || $deposit->status == 1) return 'approved';
        if ($visaStatus === 'declined')  return 'declined';
        if ($deposit->status == 3)       return 'rejected';
        if ($deposit->status == 0)       return 'pending';
        return 'unknown';
    }
}
