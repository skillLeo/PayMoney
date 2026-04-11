@extends('admin.layouts.app')
@section('page_title', __('Visa Transaction Detail'))

@push('style')
<style>
.detail-card { border-radius: 12px; border: 1px solid #e5e7eb; }
.detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
.detail-row:last-child { border-bottom: none; }
.detail-key { color: #6b7280; font-weight: 500; }
.detail-val { font-weight: 600; color: #111827; text-align: right; }
.visa-status-hero { padding: 28px; border-radius: 16px; text-align: center; margin-bottom: 20px; }
.visa-status-hero.approved { background: linear-gradient(135deg, #d1fae5, #a7f3d0); }
.visa-status-hero.declined { background: linear-gradient(135deg, #fee2e2, #fecaca); }
.visa-status-hero.pending  { background: linear-gradient(135deg, #fef3c7, #fde68a); }
.visa-status-hero.unknown  { background: linear-gradient(135deg, #f3f4f6, #e5e7eb); }
.status-icon { font-size: 48px; margin-bottom: 10px; }
.status-title { font-size: 22px; font-weight: 700; }
.action-code-big {
    display: inline-block;
    padding: 6px 18px;
    border-radius: 8px;
    font-size: 18px;
    font-weight: 800;
    font-family: monospace;
    margin-top: 8px;
}
.code-00-big { background: #065f46; color: #fff; }
.code-err-big { background: #991b1b; color: #fff; }
.live-check-btn {
    background: linear-gradient(135deg, #1A1F71, #1565C0);
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 10px 24px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: filter .2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.live-check-btn:hover { filter: brightness(1.1); }
</style>
@endpush

@section('content')
<div class="content container-fluid">

    <div class="page-header">
        <div class="row align-items-end">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">@lang('Visa Transaction Detail')</h1>
                <ol class="breadcrumb breadcrumb-no-gutter">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">@lang('Dashboard')</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.visa.transactions') }}">@lang('Visa Transactions')</a></li>
                    <li class="breadcrumb-item active">{{ $deposit->trx_id }}</li>
                </ol>
            </div>
            <div class="col-auto">
                <button class="live-check-btn" onclick="doLiveCheck()">
                    <i class="bi-arrow-clockwise" id="checkIcon"></i>
                    @lang('Re-check Live Status from Visa API')
                </button>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Left: Status Hero --}}
        <div class="col-lg-4">
            @php
                $info       = $deposit->visa_info ?? [];
                $visaStatus = !empty($liveStatus) ? $liveStatus['status'] : ($info['visa_status'] ?? 'unknown');
                $actionCode = !empty($liveStatus) ? ($liveStatus['action_code'] ?? null) : ($info['action_code'] ?? null);
                $approvalCode = !empty($liveStatus) ? ($liveStatus['approval_code'] ?? null) : ($info['approval_code'] ?? null);
                $isMock = $info['mock'] ?? false;
                $statusIcons = ['approved' => '✅', 'declined' => '❌', 'pending' => '⏳', 'unknown' => '❓'];
                $statusIcon = $statusIcons[$visaStatus] ?? '❓';
            @endphp

            <div class="visa-status-hero {{ $visaStatus }}" id="statusHero">
                <div class="status-icon" id="statusIcon">{{ $statusIcon }}</div>
                <div class="status-title" id="statusTitle">{{ ucfirst($visaStatus) }}</div>
                @if($actionCode)
                <div class="action-code-big {{ $actionCode === '00' ? 'code-00-big' : 'code-err-big' }}" id="actionCodeBig">
                    Code: {{ $actionCode }}
                </div>
                <div style="font-size:13px;margin-top:6px;color:#374151;" id="actionMsg">
                    {{ \App\Services\Gateway\visa\Payment::actionCodeMessage($actionCode) }}
                </div>
                @endif
                @if($isMock)
                <div style="margin-top:8px;font-size:11px;background:rgba(0,0,0,.08);border-radius:6px;padding:3px 10px;display:inline-block;">
                    SANDBOX MOCK
                </div>
                @endif
            </div>

            @if(!empty($liveStatus))
            <div class="alert alert-success border-0 rounded-3 mt-3" style="font-size:13px;">
                <i class="bi-check-circle-fill me-2"></i>
                @lang('Live status fetched from Visa API')
                <br><small class="text-muted">{{ now()->format('d M Y H:i:s') }}</small>
            </div>
            @endif

            {{-- Visa Branding --}}
            <div class="card detail-card mt-3" style="background:linear-gradient(135deg,#1A1F71,#1565C0);color:#fff;">
                <div class="card-body text-center py-4">
                    <div style="font-family:'Times New Roman',serif;font-size:40px;font-weight:900;font-style:italic;letter-spacing:-2px;">VISA</div>
                    <div style="font-size:11px;opacity:.7;text-transform:uppercase;letter-spacing:1px;">Direct · Sandbox</div>
                </div>
            </div>
        </div>

        {{-- Right: Details --}}
        <div class="col-lg-8">

            {{-- Platform Deposit Info --}}
            <div class="card detail-card mb-4">
                <div class="card-header"><h6 class="mb-0"><i class="bi-receipt me-2 text-primary"></i>@lang('Platform Deposit Record')</h6></div>
                <div class="card-body">
                    <div class="detail-row"><span class="detail-key">@lang('Trx ID')</span><span class="detail-val"><code>{{ $deposit->trx_id }}</code></span></div>
                    <div class="detail-row"><span class="detail-key">@lang('User')</span><span class="detail-val">{{ optional($deposit->user)->username }} ({{ optional($deposit->user)->email }})</span></div>
                    <div class="detail-row"><span class="detail-key">@lang('Amount')</span><span class="detail-val">{{ getAmount($deposit->payable_amount) }} {{ $deposit->payment_method_currency }}</span></div>
                    <div class="detail-row">
                        <span class="detail-key">@lang('Deposit Status')</span>
                        <span class="detail-val">
                            @php $statusMap = [0=>'Pending',1=>'Approved',2=>'Request',3=>'Rejected']; $colorMap=[0=>'warning',1=>'success',2=>'info',3=>'danger']; @endphp
                            <span class="badge bg-soft-{{ $colorMap[$deposit->status]??'secondary' }} text-{{ $colorMap[$deposit->status]??'secondary' }}">
                                {{ $statusMap[$deposit->status] ?? 'Unknown' }}
                            </span>
                        </span>
                    </div>
                    <div class="detail-row"><span class="detail-key">@lang('Created At')</span><span class="detail-val">{{ $deposit->created_at->format('d M Y H:i:s') }}</span></div>
                    <div class="detail-row"><span class="detail-key">@lang('Updated At')</span><span class="detail-val">{{ $deposit->updated_at->format('d M Y H:i:s') }}</span></div>
                </div>
            </div>

            {{-- Visa API Response --}}
            <div class="card detail-card mb-4" id="visaApiCard">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi-credit-card me-2 text-primary"></i>@lang('Visa API Response')</h6>
                    <span class="badge bg-soft-primary text-primary" style="font-size:11px;">Visa Direct</span>
                </div>
                <div class="card-body" id="visaApiBody">
                    @if($deposit->payment_id)
                    <div class="detail-row"><span class="detail-key">@lang('Visa Transaction ID')</span><span class="detail-val"><code style="color:#1565C0;">{{ $deposit->payment_id }}</code></span></div>
                    @endif
                    @if(!empty($info['action_code']))
                    <div class="detail-row">
                        <span class="detail-key">@lang('Action Code')</span>
                        <span class="detail-val">
                            <span class="badge {{ $info['action_code'] === '00' ? 'bg-success' : 'bg-danger' }}">{{ $info['action_code'] }}</span>
                            — {{ \App\Services\Gateway\visa\Payment::actionCodeMessage($info['action_code']) }}
                        </span>
                    </div>
                    @endif
                    @if(!empty($info['approval_code']))
                    <div class="detail-row"><span class="detail-key">@lang('Approval Code')</span><span class="detail-val"><code>{{ $info['approval_code'] }}</code></span></div>
                    @endif
                    @if(!empty($info['stan']))
                    <div class="detail-row"><span class="detail-key">@lang('STAN')</span><span class="detail-val"><code>{{ $info['stan'] }}</code></span></div>
                    @endif
                    @if(!empty($info['timestamp']))
                    <div class="detail-row"><span class="detail-key">@lang('Transaction Time')</span><span class="detail-val">{{ \Carbon\Carbon::parse($info['timestamp'])->format('d M Y H:i:s') }}</span></div>
                    @endif
                    @if(!empty($info['mock']))
                    <div class="detail-row">
                        <span class="detail-key">@lang('Mode')</span>
                        <span class="detail-val"><span class="badge bg-soft-warning text-warning">Sandbox Mock</span></span>
                    </div>
                    @endif
                    @if(empty($info))
                    <p class="text-muted mb-0">@lang('No Visa API response recorded for this deposit.')</p>
                    @endif
                </div>
            </div>

            {{-- Live Check Result --}}
            @if(!empty($liveStatus))
            <div class="card detail-card border-success">
                <div class="card-header bg-soft-success"><h6 class="mb-0 text-success"><i class="bi-wifi me-2"></i>@lang('Live Status from Visa API')</h6></div>
                <div class="card-body">
                    <pre style="font-size:12px;background:#f8fafc;border-radius:8px;padding:14px;overflow:auto;">{{ json_encode($liveStatus, JSON_PRETTY_PRINT) }}</pre>
                </div>
            </div>
            @endif

        </div>
    </div>

</div>
@endsection

@push('script')
<script>
function doLiveCheck() {
    const btn = document.querySelector('.live-check-btn');
    const icon = document.getElementById('checkIcon');
    btn.disabled = true;
    icon.className = 'spinner-border spinner-border-sm';

    fetch(`{{ url('admin/visa/transaction') }}/{{ $deposit->id }}/check-status`, {
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    })
    .then(r => r.json())
    .then(data => {
        // Update status hero
        const heroMap = { approved: ['✅','#d1fae5','#a7f3d0'], declined: ['❌','#fee2e2','#fecaca'], pending: ['⏳','#fef3c7','#fde68a'] };
        const s = data.visa_status;
        const hero = document.getElementById('statusHero');
        if (hero) {
            hero.className = `visa-status-hero ${s}`;
            const [from, to] = heroMap[s] ? [heroMap[s][1], heroMap[s][2]] : ['#f3f4f6','#e5e7eb'];
            hero.style.background = `linear-gradient(135deg,${from},${to})`;
        }
        document.getElementById('statusIcon').textContent  = (heroMap[s] || ['❓'])[0];
        document.getElementById('statusTitle').textContent = s.charAt(0).toUpperCase()+s.slice(1);

        // Show a toast
        const color = s === 'approved' ? '#065f46' : (s === 'declined' ? '#991b1b' : '#92400e');
        const t = document.createElement('div');
        t.style.cssText = `position:fixed;bottom:24px;right:24px;background:${color};color:#fff;padding:14px 22px;border-radius:12px;font-size:14px;font-weight:600;z-index:9999;box-shadow:0 6px 24px rgba(0,0,0,.2);`;
        t.innerHTML = `<strong>Visa Status: ${s.toUpperCase()}</strong><br><span style="font-size:12px;opacity:.9;">${data.action_label || data.message || ''}</span>`;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 5000);
    })
    .catch(e => alert('Error: ' + e.message))
    .finally(() => {
        btn.disabled = false;
        icon.className = 'bi-arrow-clockwise';
    });
}
</script>
@endpush
