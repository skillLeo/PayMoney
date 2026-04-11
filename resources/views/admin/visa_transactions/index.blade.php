@extends('admin.layouts.app')
@section('page_title', __('Visa Transactions'))

@push('style')
<style>
.visa-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: .4px;
}
.visa-badge.approved  { background: #d1fae5; color: #065f46; }
.visa-badge.declined  { background: #fee2e2; color: #991b1b; }
.visa-badge.pending   { background: #fef3c7; color: #92400e; }
.visa-badge.rejected  { background: #f3f4f6; color: #374151; }
.visa-badge.unknown   { background: #f3f4f6; color: #6b7280; }

.visa-header-banner {
    background: linear-gradient(135deg, #1A1F71 0%, #1565C0 100%);
    border-radius: 12px;
    padding: 20px 24px;
    color: #fff;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.visa-logo-admin {
    font-family: 'Times New Roman', serif;
    font-size: 32px;
    font-weight: 900;
    font-style: italic;
    color: #fff;
    letter-spacing: -1px;
}
.stat-pill {
    background: rgba(255,255,255,.15);
    border-radius: 8px;
    padding: 10px 20px;
    text-align: center;
}
.stat-pill .val { font-size: 22px; font-weight: 700; }
.stat-pill .lbl { font-size: 11px; opacity: .8; text-transform: uppercase; letter-spacing: .8px; }

.action-code-pill {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    font-family: monospace;
}
.action-code-pill.code-00  { background: #d1fae5; color: #065f46; }
.action-code-pill.code-err { background: #fee2e2; color: #991b1b; }
.action-code-pill.code-unk { background: #f3f4f6; color: #6b7280; }

.check-btn {
    padding: 3px 10px;
    font-size: 11px;
    border-radius: 6px;
    border: 1px solid #1565C0;
    color: #1565C0;
    background: transparent;
    cursor: pointer;
    transition: all .2s;
}
.check-btn:hover { background: #1565C0; color: #fff; }
</style>
@endpush

@section('content')
<div class="content container-fluid">

    {{-- Page Header --}}
    <div class="page-header">
        <div class="row align-items-end">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">@lang('Visa Transactions')</h1>
                <ol class="breadcrumb breadcrumb-no-gutter">
                    <li class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}">@lang('Dashboard')</a>
                    </li>
                    <li class="breadcrumb-item active">@lang('Visa Transactions')</li>
                </ol>
            </div>
        </div>
    </div>

    {{-- Visa Banner --}}
    <div class="visa-header-banner">
        <div>
            <div class="visa-logo-admin">VISA</div>
            <div style="font-size:13px;opacity:.8;margin-top:2px;">
                @lang('Sandbox') &bull; Visa Direct &bull; {{ config('visa.api_base_url') }}
            </div>
        </div>
        <div class="d-flex gap-3 flex-wrap">
            @php
                $total    = $deposits->total();
                $approved = $deposits->getCollection()->where('status', 1)->count();
                $pending  = $deposits->getCollection()->where('status', 0)->count();
                $declined = $deposits->getCollection()->filter(fn($d) => ($d->visa_info['visa_status'] ?? '') === 'declined')->count();
            @endphp
            <div class="stat-pill">
                <div class="val">{{ $deposits->total() }}</div>
                <div class="lbl">@lang('Total')</div>
            </div>
            <div class="stat-pill">
                <div class="val" style="color:#4ade80;">{{ $deposits->getCollection()->where('status',1)->count() }}</div>
                <div class="lbl">@lang('Approved')</div>
            </div>
            <div class="stat-pill">
                <div class="val" style="color:#fbbf24;">{{ $deposits->getCollection()->where('status',0)->count() }}</div>
                <div class="lbl">@lang('Pending')</div>
            </div>
            <div class="stat-pill">
                <div class="val" style="color:#f87171;">{{ $deposits->getCollection()->filter(fn($d) => ($d->visa_info['visa_status'] ?? '') === 'declined')->count() }}</div>
                <div class="lbl">@lang('Declined')</div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('admin.visa.transactions') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <input type="text" name="user" class="form-control form-control-sm"
                           placeholder="@lang('Search username')"
                           value="{{ request('user') }}">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">@lang('All Statuses')</option>
                        <option value="1" @selected(request('status') == '1')>@lang('Approved')</option>
                        <option value="0" @selected(request('status') == '0')>@lang('Pending')</option>
                        <option value="3" @selected(request('status') == '3')>@lang('Rejected')</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date" class="form-control form-control-sm"
                           value="{{ request('date') }}">
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi-search me-1"></i>@lang('Filter')
                    </button>
                    <a href="{{ route('admin.visa.transactions') }}" class="btn btn-outline-secondary btn-sm">
                        @lang('Reset')
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Table --}}
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="card-header-title mb-0">@lang('All Visa Deposits')</h5>
            <span class="badge bg-soft-primary text-primary">{{ $deposits->total() }} @lang('records')</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-nowrap card-table">
                <thead class="thead-light">
                    <tr>
                        <th>@lang('User')</th>
                        <th>@lang('Trx ID')</th>
                        <th>@lang('Visa Txn ID')</th>
                        <th>@lang('Action Code')</th>
                        <th>@lang('Amount')</th>
                        <th>@lang('Currency')</th>
                        <th>@lang('Deposit Status')</th>
                        <th>@lang('Visa Status')</th>
                        <th>@lang('Date')</th>
                        <th>@lang('Actions')</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($deposits as $deposit)
                    @php
                        $info       = $deposit->visa_info ?? [];
                        $visaStatus = $deposit->visa_status_label;
                        $actionCode = $info['action_code'] ?? null;
                        $codeClass  = $actionCode === '00' ? 'code-00' : ($actionCode ? 'code-err' : 'code-unk');
                        $depositStatusMap = [0 => ['Pending','warning'], 1 => ['Approved','success'], 2 => ['Request','info'], 3 => ['Rejected','danger']];
                        [$depLabel, $depColor] = $depositStatusMap[$deposit->status] ?? ['Unknown','secondary'];
                    @endphp
                    <tr id="row-{{ $deposit->id }}">
                        <td>
                            <a href="#" class="fw-semibold">{{ optional($deposit->user)->username ?? '—' }}</a><br>
                            <small class="text-muted">{{ optional($deposit->user)->email ?? '' }}</small>
                        </td>
                        <td>
                            <code style="font-size:11px;">{{ $deposit->trx_id }}</code>
                        </td>
                        <td>
                            @if($deposit->payment_id)
                                <code style="font-size:11px;color:#1565C0;" id="txnid-{{ $deposit->id }}">
                                    {{ Str::limit($deposit->payment_id, 20) }}
                                </code>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($actionCode)
                                <span class="action-code-pill {{ $codeClass }}" id="code-{{ $deposit->id }}">
                                    {{ $actionCode }}
                                </span>
                                <br>
                                <small class="text-muted" style="font-size:10px;" id="codemsg-{{ $deposit->id }}">
                                    {{ \App\Services\Gateway\visa\Payment::actionCodeMessage($actionCode) }}
                                </small>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="fw-semibold">{{ getAmount($deposit->payable_amount) }}</td>
                        <td>{{ $deposit->payment_method_currency }}</td>
                        <td>
                            <span class="badge bg-soft-{{ $depColor }} text-{{ $depColor }}">
                                {{ $depLabel }}
                            </span>
                        </td>
                        <td>
                            <span class="visa-badge {{ $visaStatus }}" id="vstatus-{{ $deposit->id }}">
                                @if($visaStatus === 'approved')   <i class="bi-check-circle-fill"></i>
                                @elseif($visaStatus === 'declined')<i class="bi-x-circle-fill"></i>
                                @elseif($visaStatus === 'pending') <i class="bi-clock-fill"></i>
                                @else                              <i class="bi-question-circle"></i>
                                @endif
                                {{ ucfirst($visaStatus) }}
                            </span>
                        </td>
                        <td>
                            <small>{{ $deposit->created_at->format('d M Y') }}<br>
                            <span class="text-muted">{{ $deposit->created_at->format('H:i') }}</span></small>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button class="check-btn"
                                        onclick="checkLiveStatus({{ $deposit->id }})"
                                        title="@lang('Re-check Visa API')">
                                    <i class="bi-arrow-clockwise"></i> @lang('Check')
                                </button>
                                <a href="{{ route('admin.visa.transaction.show', $deposit->id) }}"
                                   class="check-btn" style="text-decoration:none;">
                                    <i class="bi-eye"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center py-4 text-muted">
                            <i class="bi-credit-card fs-3 d-block mb-2"></i>
                            @lang('No Visa transactions found.')
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($deposits->hasPages())
        <div class="card-footer d-flex justify-content-center">
            {{ $deposits->links() }}
        </div>
        @endif
    </div>

    {{-- Action Code Legend --}}
    <div class="card mt-3">
        <div class="card-header"><h6 class="mb-0">@lang('Visa Action Code Reference')</h6></div>
        <div class="card-body">
            <div class="row g-2" style="font-size:13px;">
                @foreach(['00'=>'Approved','05'=>'Do Not Honor','14'=>'Invalid Card Number','51'=>'Insufficient Funds','54'=>'Card Expired','57'=>'Not Permitted','91'=>'Issuer Unavailable','96'=>'System Error'] as $code => $meaning)
                <div class="col-md-3 col-6">
                    <span class="action-code-pill {{ $code === '00' ? 'code-00' : 'code-err' }}">{{ $code }}</span>
                    <span class="text-muted ms-1">{{ $meaning }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

</div>
@endsection

@push('script')
<script>
function checkLiveStatus(id) {
    const btn = event.currentTarget;
    const origText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    btn.disabled = true;

    fetch(`{{ url('admin/visa/transaction') }}/${id}/check-status`, {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    })
    .then(r => r.json())
    .then(data => {
        // Update status badge
        const vStatus = document.getElementById(`vstatus-${id}`);
        const codeEl  = document.getElementById(`code-${id}`);
        const codeMsg = document.getElementById(`codemsg-${id}`);
        const txnEl   = document.getElementById(`txnid-${id}`);

        if (vStatus) {
            const s = data.visa_status;
            let icon = s === 'approved' ? 'check-circle-fill' : (s === 'declined' ? 'x-circle-fill' : 'clock-fill');
            vStatus.className = `visa-badge ${s}`;
            vStatus.innerHTML = `<i class="bi-${icon}"></i> ${s.charAt(0).toUpperCase()+s.slice(1)}`;
        }
        if (codeEl && data.action_code) {
            codeEl.className = `action-code-pill ${data.action_code === '00' ? 'code-00' : 'code-err'}`;
            codeEl.textContent = data.action_code;
            if (codeMsg) codeMsg.textContent = data.action_label || '';
        }
        if (txnEl && data.visa_txn_id) {
            txnEl.textContent = data.visa_txn_id.substring(0, 20);
        }

        // Toast notification
        const color = data.visa_status === 'approved' ? '#065f46' : (data.visa_status === 'declined' ? '#991b1b' : '#92400e');
        showToast(`Txn #${id}: ${data.message || data.visa_status}`, color);
    })
    .catch(e => showToast('Error checking status: ' + e.message, '#991b1b'))
    .finally(() => {
        btn.innerHTML = origText;
        btn.disabled = false;
    });
}

function showToast(msg, color = '#1565C0') {
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;bottom:24px;right:24px;background:${color};color:#fff;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:600;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.2);max-width:320px;`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}
</script>
@endpush
