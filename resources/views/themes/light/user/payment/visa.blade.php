@extends($theme.'layouts.user')
@section('title', __('Pay with Visa'))

@push('style')
<style>
/* ── Visa Payment Page ────────────────────────────────────────────── */
.visa-pay-wrapper {
    min-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 30px 16px;
}

/* ── Card Preview ─────────────────────────────────────────────────── */
.card-preview-container {
    perspective: 1000px;
    margin-bottom: 28px;
}

.credit-card {
    width: 100%;
    max-width: 360px;
    height: 210px;
    border-radius: 18px;
    position: relative;
    transform-style: preserve-3d;
    transition: transform 0.7s cubic-bezier(.4,0,.2,1);
    cursor: pointer;
    margin: 0 auto;
    box-shadow: 0 24px 60px rgba(26,31,113,.35), 0 4px 16px rgba(0,0,0,.18);
}

.credit-card.flipped { transform: rotateY(180deg); }

/* front / back shared */
.card-face {
    position: absolute;
    inset: 0;
    border-radius: 18px;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    padding: 22px 28px;
    overflow: hidden;
}

/* FRONT */
.card-front {
    background: linear-gradient(135deg, #1A1F71 0%, #1565C0 40%, #0D47A1 70%, #1A237E 100%);
    color: #fff;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.card-front::before {
    content: '';
    position: absolute;
    width: 260px; height: 260px;
    border-radius: 50%;
    background: rgba(255,255,255,.06);
    top: -80px; right: -80px;
    pointer-events: none;
}
.card-front::after {
    content: '';
    position: absolute;
    width: 180px; height: 180px;
    border-radius: 50%;
    background: rgba(255,255,255,.04);
    bottom: -60px; left: -40px;
    pointer-events: none;
}

.card-top-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    z-index: 2;
}

.card-chip {
    width: 42px; height: 32px;
    background: linear-gradient(135deg, #ffd700 0%, #f0b800 50%, #daa520 100%);
    border-radius: 6px;
    position: relative;
    box-shadow: 0 2px 6px rgba(0,0,0,.3);
}
.card-chip::before {
    content: '';
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 28px; height: 20px;
    border: 1.5px solid rgba(0,0,0,.25);
    border-radius: 3px;
}
.card-chip::after {
    content: '';
    position: absolute;
    top: 50%; left: 0; right: 0;
    height: 1px;
    background: rgba(0,0,0,.2);
    transform: translateY(-50%);
}

.visa-logo-card {
    font-family: 'Times New Roman', serif;
    font-size: 28px;
    font-weight: 900;
    font-style: italic;
    color: #fff;
    letter-spacing: -1px;
    text-shadow: 0 2px 8px rgba(0,0,0,.3);
    line-height: 1;
}

.card-number-display {
    font-family: 'Courier New', Courier, monospace;
    font-size: 20px;
    letter-spacing: 4px;
    color: rgba(255,255,255,.95);
    text-align: center;
    text-shadow: 0 1px 4px rgba(0,0,0,.2);
    position: relative;
    z-index: 2;
    font-weight: 600;
}

.card-bottom-row {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    position: relative;
    z-index: 2;
}

.card-holder-label, .card-expiry-label {
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: rgba(255,255,255,.6);
    margin-bottom: 3px;
}

.card-holder-name, .card-expiry-value {
    font-size: 14px;
    font-weight: 600;
    color: #fff;
    text-transform: uppercase;
    letter-spacing: 1px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 160px;
}

.card-expiry-value { font-family: 'Courier New', Courier, monospace; }

/* BACK */
.card-back {
    background: linear-gradient(135deg, #1A237E 0%, #0D47A1 60%, #1565C0 100%);
    color: #fff;
    transform: rotateY(180deg);
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 0;
}

.card-stripe {
    background: #111;
    height: 46px;
    width: 100%;
    margin-bottom: 20px;
}

.card-cvc-row {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 0 28px;
    gap: 14px;
}

.cvc-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: rgba(255,255,255,.7);
}

.cvc-box {
    background: #fff;
    color: #333;
    border-radius: 5px;
    padding: 4px 16px;
    font-family: 'Courier New', Courier, monospace;
    font-size: 18px;
    font-weight: 700;
    letter-spacing: 4px;
    min-width: 80px;
    text-align: center;
}

/* ── Amount Banner ────────────────────────────────────────────────── */
.amount-banner {
    background: linear-gradient(135deg, #1A1F71, #1565C0);
    color: #fff;
    border-radius: 12px;
    padding: 14px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    box-shadow: 0 4px 16px rgba(26,31,113,.2);
}

.amount-banner .label {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    opacity: .8;
}

.amount-banner .value {
    font-size: 22px;
    font-weight: 700;
    letter-spacing: 1px;
}

.amount-banner .secure-badge {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    opacity: .85;
    background: rgba(255,255,255,.15);
    border-radius: 20px;
    padding: 4px 12px;
}

/* ── Form Inputs ──────────────────────────────────────────────────── */
.visa-form-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 40px rgba(0,0,0,.08);
    padding: 28px;
    border: 1px solid rgba(26,31,113,.07);
}

body.dark .visa-form-card {
    background: #1e2139;
    border-color: rgba(255,255,255,.07);
}

.input-group-visa {
    position: relative;
    margin-bottom: 18px;
}

.input-group-visa label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    color: #6c757d;
    margin-bottom: 7px;
}

.input-group-visa .field-icon {
    position: absolute;
    right: 14px;
    top: 38px;
    color: #1565C0;
    font-size: 15px;
    pointer-events: none;
}

.visa-input {
    width: 100%;
    border: 2px solid #e8ecf4;
    border-radius: 10px;
    padding: 12px 44px 12px 16px;
    font-size: 16px;
    font-family: 'Courier New', Courier, monospace;
    color: #1a1f71;
    background: #f8faff;
    transition: border-color .25s, box-shadow .25s, background .25s;
    outline: none;
    letter-spacing: 1px;
}

body.dark .visa-input {
    background: #252848;
    border-color: #353a5e;
    color: #e0e4ff;
}

.visa-input:focus {
    border-color: #1565C0;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(21,101,192,.12);
}

body.dark .visa-input:focus {
    background: #1a1f3c;
    box-shadow: 0 0 0 4px rgba(21,101,192,.2);
}

.visa-input.is-active {
    border-color: #1565C0;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(21,101,192,.1);
}

.row-half { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* ── Submit Button ────────────────────────────────────────────────── */
.visa-pay-btn {
    width: 100%;
    padding: 15px;
    background: linear-gradient(135deg, #1A1F71 0%, #1565C0 100%);
    color: #fff;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 700;
    letter-spacing: 1px;
    cursor: pointer;
    transition: transform .2s, box-shadow .2s, filter .2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 6px 20px rgba(26,31,113,.35);
    margin-top: 8px;
    position: relative;
    overflow: hidden;
}

.visa-pay-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,.1), rgba(255,255,255,0));
    border-radius: 12px;
}

.visa-pay-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 28px rgba(26,31,113,.45);
    filter: brightness(1.08);
}

.visa-pay-btn:active {
    transform: translateY(0);
    box-shadow: 0 4px 12px rgba(26,31,113,.3);
}

.visa-pay-btn:disabled {
    opacity: .65;
    cursor: not-allowed;
    transform: none;
}

/* ── Security Row ─────────────────────────────────────────────────── */
.security-row {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    margin-top: 16px;
    flex-wrap: wrap;
}

.security-badge {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    color: #6c757d;
    font-weight: 500;
}

.security-badge i { color: #1565C0; font-size: 13px; }

/* ── Flip hint ────────────────────────────────────────────────────── */
.flip-hint {
    text-align: center;
    font-size: 11px;
    color: #adb5bd;
    margin-top: 8px;
    letter-spacing: .5px;
}

/* ── Spinner for submit ───────────────────────────────────────────── */
.spinner {
    display: none;
    width: 18px; height: 18px;
    border: 3px solid rgba(255,255,255,.4);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin .7s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

.visa-pay-btn.loading .spinner { display: inline-block; }
.visa-pay-btn.loading .btn-text { opacity: .6; }
</style>
@endpush

@section('content')
<div class="dashboard-wrapper">
    <div class="visa-pay-wrapper">
        <div style="width:100%;max-width:480px">

            {{-- Breadcrumb --}}
            <div class="breadcrumb-area mb-3">
                <h3 class="title">@lang('Pay with Visa')</h3>
            </div>

            {{-- Amount Banner --}}
            <div class="amount-banner">
                <div>
                    <div class="label">@lang('Amount Due')</div>
                    <div class="value">
                        {{ getAmount($deposit->payable_amount) }}
                        <span style="font-size:14px;font-weight:400;opacity:.85;">{{ $deposit->payment_method_currency }}</span>
                    </div>
                </div>
                <div class="secure-badge">
                    <i class="fas fa-lock" style="font-size:13px;"></i>
                    @lang('Secure Payment')
                </div>
            </div>

            {{-- Live Card Preview --}}
            <div class="card-preview-container" id="cardPreviewContainer">
                <div class="credit-card" id="creditCard">
                    {{-- FRONT --}}
                    <div class="card-face card-front">
                        <div class="card-top-row">
                            <div class="card-chip"></div>
                            <div class="visa-logo-card">VISA</div>
                        </div>
                        <div class="card-number-display" id="previewNumber">
                            ####&nbsp;&nbsp;####&nbsp;&nbsp;####&nbsp;&nbsp;####
                        </div>
                        <div class="card-bottom-row">
                            <div>
                                <div class="card-holder-label">@lang('Card Holder')</div>
                                <div class="card-holder-name" id="previewName">@lang('FULL NAME')</div>
                            </div>
                            <div style="text-align:right">
                                <div class="card-expiry-label">@lang('Expires')</div>
                                <div class="card-expiry-value" id="previewExpiry">MM/YY</div>
                            </div>
                        </div>
                    </div>

                    {{-- BACK --}}
                    <div class="card-face card-back">
                        <div class="card-stripe"></div>
                        <div class="card-cvc-row">
                            <span class="cvc-label">CVC</span>
                            <div class="cvc-box" id="previewCvc">•••</div>
                        </div>
                        <div style="text-align:right;padding:12px 28px 0;font-style:italic;font-size:20px;font-weight:900;color:rgba(255,255,255,.8);letter-spacing:-1px;">VISA</div>
                    </div>
                </div>
                <div class="flip-hint">@lang('Card flips when you enter CVC')</div>
            </div>

            {{-- Payment Form --}}
            <div class="visa-form-card">
                <form id="visaPayForm"
                      action="{{ route('ipn', [optional($deposit->gateway)->code ?? 'visa', $deposit->trx_id]) }}"
                      method="POST">
                    @csrf

                    {{-- Card Number --}}
                    <div class="input-group-visa">
                        <label for="cardNumberInput">@lang('Card Number')</label>
                        <input type="text"
                               id="cardNumberInput"
                               name="card_number"
                               class="visa-input"
                               placeholder="0000  0000  0000  0000"
                               maxlength="22"
                               autocomplete="cc-number"
                               inputmode="numeric"
                               required>
                        <span class="field-icon"><i class="fas fa-credit-card"></i></span>
                    </div>

                    {{-- Card Name --}}
                    <div class="input-group-visa">
                        <label for="cardNameInput">@lang('Name on Card')</label>
                        <input type="text"
                               id="cardNameInput"
                               name="card_name"
                               class="visa-input"
                               placeholder="@lang('As printed on card')"
                               maxlength="26"
                               autocomplete="cc-name"
                               style="font-family: inherit; letter-spacing: 1.5px; text-transform: uppercase;"
                               required>
                        <span class="field-icon"><i class="fas fa-user"></i></span>
                    </div>

                    {{-- Expiry + CVC --}}
                    <div class="row-half">
                        <div class="input-group-visa">
                            <label for="cardExpiryInput">@lang('Expiry Date')</label>
                            <input type="text"
                                   id="cardExpiryInput"
                                   class="visa-input"
                                   placeholder="MM / YY"
                                   maxlength="7"
                                   autocomplete="cc-exp"
                                   inputmode="numeric"
                                   required>
                            {{-- hidden fields that Payment.php reads --}}
                            <input type="hidden" name="expiry_month" id="expiryMonthHidden">
                            <input type="hidden" name="expiry_year"  id="expiryYearHidden">
                            <span class="field-icon"><i class="fas fa-calendar-alt"></i></span>
                        </div>

                        <div class="input-group-visa">
                            <label for="cardCvcInput">@lang('Security Code (CVC)')</label>
                            <input type="text"
                                   id="cardCvcInput"
                                   name="card_cvc"
                                   class="visa-input"
                                   placeholder="•••"
                                   maxlength="4"
                                   autocomplete="cc-csc"
                                   inputmode="numeric"
                                   required>
                            <span class="field-icon"><i class="fas fa-lock"></i></span>
                        </div>
                    </div>

                    {{-- Submit --}}
                    <button type="submit" class="visa-pay-btn" id="payBtn">
                        <span class="spinner" id="btnSpinner"></span>
                        <span class="btn-text">
                            <i class="fas fa-lock" style="font-size:13px;"></i>
                            @lang('Pay')
                            {{ getAmount($deposit->payable_amount) }} {{ $deposit->payment_method_currency }}
                        </span>
                    </button>
                </form>

                {{-- Security Badges --}}
                <div class="security-row">
                    <span class="security-badge"><i class="fas fa-shield-alt"></i> @lang('SSL Encrypted')</span>
                    <span class="security-badge"><i class="fas fa-lock"></i> @lang('PCI DSS')</span>
                    <span class="security-badge"><i class="fas fa-check-circle"></i> @lang('Verified by Visa')</span>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection

@push('script')
<script>
(function () {
    'use strict';

    /* ── Elements ──────────────────────────────────────────────────── */
    const cardEl      = document.getElementById('creditCard');
    const numInput    = document.getElementById('cardNumberInput');
    const nameInput   = document.getElementById('cardNameInput');
    const expiryInput = document.getElementById('cardExpiryInput');
    const cvcInput    = document.getElementById('cardCvcInput');
    const monthHidden = document.getElementById('expiryMonthHidden');
    const yearHidden  = document.getElementById('expiryYearHidden');
    const payBtn      = document.getElementById('payBtn');
    const form        = document.getElementById('visaPayForm');

    const previewNum    = document.getElementById('previewNumber');
    const previewName   = document.getElementById('previewName');
    const previewExpiry = document.getElementById('previewExpiry');
    const previewCvc    = document.getElementById('previewCvc');

    /* ── Card Number ─────────────────────────────────────────────── */
    numInput.addEventListener('input', function () {
        let raw = this.value.replace(/\D/g, '').slice(0, 16);
        // Format: groups of 4 separated by two spaces
        let formatted = raw.replace(/(.{4})/g, '$1  ').trim();
        this.value = formatted;

        let padded = raw.padEnd(16, '#');
        let display = padded.replace(/(.{4})/g, '$1\u00a0\u00a0').trim();
        previewNum.textContent = display;

        // Mark active
        numInput.classList.toggle('is-active', raw.length > 0);
    });

    /* ── Cardholder Name ──────────────────────────────────────────── */
    nameInput.addEventListener('input', function () {
        let val = this.value.toUpperCase().slice(0, 26) || '@lang("FULL NAME")';
        previewName.textContent = val;
        nameInput.classList.toggle('is-active', this.value.length > 0);
    });

    /* ── Expiry ──────────────────────────────────────────────────── */
    expiryInput.addEventListener('input', function () {
        let raw = this.value.replace(/\D/g, '').slice(0, 4);

        if (raw.length >= 2) {
            let mm = raw.slice(0, 2);
            let yy = raw.slice(2);
            // Clamp month to 01–12
            if (parseInt(mm) > 12) mm = '12';
            if (parseInt(mm) < 1 && mm.length === 2) mm = '01';
            this.value = mm + (raw.length > 2 ? ' / ' + yy : (this.value.includes('/') ? ' / ' : ''));

            monthHidden.value = mm;
            yearHidden.value  = yy.length === 2 ? '20' + yy : '';
            previewExpiry.textContent = mm + '/' + (yy || 'YY');
        } else {
            this.value = raw;
            previewExpiry.textContent = 'MM/YY';
            monthHidden.value = '';
            yearHidden.value  = '';
        }

        expiryInput.classList.toggle('is-active', raw.length > 0);
    });

    // Handle backspace properly on expiry
    expiryInput.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && this.value.endsWith(' / ')) {
            e.preventDefault();
            this.value = this.value.slice(0, -3);
        }
    });

    /* ── CVC – flip card ──────────────────────────────────────────── */
    cvcInput.addEventListener('focus', () => {
        cardEl.classList.add('flipped');
    });

    cvcInput.addEventListener('blur', () => {
        cardEl.classList.remove('flipped');
    });

    cvcInput.addEventListener('input', function () {
        let raw = this.value.replace(/\D/g, '').slice(0, 4);
        this.value = raw;
        previewCvc.textContent = raw.padEnd(raw.length > 0 ? raw.length : 3, '•').slice(0, 4) || '•••';
        cvcInput.classList.toggle('is-active', raw.length > 0);
    });

    /* ── Card click – manual flip ─────────────────────────────────── */
    cardEl.addEventListener('click', () => {
        cardEl.classList.toggle('flipped');
    });

    /* ── Form submit – spinner ────────────────────────────────────── */
    form.addEventListener('submit', function (e) {
        // Basic client-side validation
        let cardNum = numInput.value.replace(/\D/g, '');
        let expMonth = monthHidden.value;
        let expYear  = yearHidden.value;
        let cvc      = cvcInput.value;

        if (cardNum.length < 13) {
            e.preventDefault();
            numInput.focus();
            numInput.style.borderColor = '#dc3545';
            setTimeout(() => numInput.style.borderColor = '', 1500);
            return;
        }

        if (!expMonth || !expYear || expYear < new Date().getFullYear()) {
            e.preventDefault();
            expiryInput.focus();
            expiryInput.style.borderColor = '#dc3545';
            setTimeout(() => expiryInput.style.borderColor = '', 1500);
            return;
        }

        if (cvc.length < 3) {
            e.preventDefault();
            cvcInput.focus();
            cvcInput.style.borderColor = '#dc3545';
            setTimeout(() => cvcInput.style.borderColor = '', 1500);
            return;
        }

        payBtn.disabled = true;
        payBtn.classList.add('loading');
    });
})();
</script>
@endpush
