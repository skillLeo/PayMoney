<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserBankAccountPool;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserBankAccountPoolController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search'));
        $availability = $request->input('availability', 'all');

        $pools = UserBankAccountPool::query()
            ->with('assignedUser:id,firstname,lastname')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('iban', 'LIKE', "%{$search}%")
                        ->orWhere('bank_name', 'LIKE', "%{$search}%")
                        ->orWhere('account_holder_name', 'LIKE', "%{$search}%")
                        ->orWhere('currency_code', 'LIKE', "%{$search}%");
                });
            })
            ->when($availability === 'available', fn($query) => $query->whereNull('assigned_user_id'))
            ->when($availability === 'assigned', fn($query) => $query->whereNotNull('assigned_user_id'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => UserBankAccountPool::count(),
            'available' => UserBankAccountPool::whereNull('assigned_user_id')->count(),
            'assigned' => UserBankAccountPool::whereNotNull('assigned_user_id')->count(),
        ];

        return view('admin.user_bank_account_pool.index', compact('pools', 'stats', 'search', 'availability'));
    }

    public function create()
    {
        return view('admin.user_bank_account_pool.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validatePool($request);

        UserBankAccountPool::query()->create($this->formatPayload($validated));

        return redirect()
            ->route('admin.user.bank.account.pools.index')
            ->with('success', 'Bank account pool record created successfully.');
    }

    public function edit(UserBankAccountPool $userBankAccountPool)
    {
        return view('admin.user_bank_account_pool.edit', compact('userBankAccountPool'));
    }

    public function update(Request $request, UserBankAccountPool $userBankAccountPool)
    {
        $validated = $this->validatePool($request, $userBankAccountPool->id);

        $userBankAccountPool->update($this->formatPayload($validated));

        return redirect()
            ->route('admin.user.bank.account.pools.index')
            ->with('success', 'Bank account pool record updated successfully.');
    }

    protected function validatePool(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'label' => 'nullable|string|max:255',
            'iban' => ['required', 'string', 'max:255', Rule::unique('user_bank_account_pools', 'iban')->ignore($id)],
            'account_holder_name' => 'nullable|string|max:255',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'currency_code' => 'nullable|string|max:20',
            'swift_bic' => 'nullable|string|max:50',
            'country_code' => 'nullable|string|max:10',
            'assignment_source' => 'nullable|string|max:50',
            'status' => 'nullable|integer|in:0,1',
            'notes' => 'nullable|string',
        ]);
    }

    protected function formatPayload(array $validated): array
    {
        $validated['iban'] = strtoupper(str_replace(' ', '', $validated['iban']));
        $validated['currency_code'] = isset($validated['currency_code']) ? Str::upper($validated['currency_code']) : null;
        $validated['country_code'] = isset($validated['country_code']) ? Str::upper($validated['country_code']) : null;
        $validated['swift_bic'] = isset($validated['swift_bic']) ? Str::upper($validated['swift_bic']) : null;
        $validated['assignment_source'] = $validated['assignment_source'] ?: 'manual_pool';
        $validated['status'] = $validated['status'] ?? 0;

        return $validated;
    }
}
