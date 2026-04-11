<div class="row g-4">
    <div class="col-md-6">
        <label for="label" class="form-label">@lang('Label')</label>
        <input type="text" class="form-control @error('label') is-invalid @enderror" id="label" name="label"
               value="{{ old('label', $userBankAccountPool->label ?? '') }}" placeholder="@lang('Primary EUR Pool')">
        @error('label')<span class="invalid-feedback">{{ $message }}</span>@enderror
    </div>

    <div class="col-md-6">
        <label for="assignment_source" class="form-label">@lang('Assignment Source')</label>
        <input type="text" class="form-control @error('assignment_source') is-invalid @enderror" id="assignment_source"
               name="assignment_source" value="{{ old('assignment_source', $userBankAccountPool->assignment_source ?? 'manual_pool') }}"
               placeholder="@lang('manual_pool')">
        @error('assignment_source')<span class="invalid-feedback">{{ $message }}</span>@enderror
    </div>

    <div class="col-md-6">
        <label for="iban" class="form-label">@lang('IBAN') *</label>
        <input type="text" class="form-control @error('iban') is-invalid @enderror" id="iban" name="iban"
               value="{{ old('iban', $userBankAccountPool->iban ?? '') }}" required>
        @error('iban')<span class="invalid-feedback">{{ $message }}</span>@enderror
    </div>

    <div class="col-md-6">
        <label for="bank_name" class="form-label">@lang('Bank Name') *</label>
        <input type="text" class="form-control @error('bank_name') is-invalid @enderror" id="bank_name" name="bank_name"
               value="{{ old('bank_name', $userBankAccountPool->bank_name ?? '') }}" required>
        @error('bank_name')<span class="invalid-feedback">{{ $message }}</span>@enderror
    </div>

    <div class="col-md-6">
        <label for="account_holder_name" class="form-label">@lang('Account Holder Name')</label>
        <input type="text" class="form-control @error('account_holder_name') is-invalid @enderror" id="account_holder_name"
               name="account_holder_name" value="{{ old('account_holder_name', $userBankAccountPool->account_holder_name ?? '') }}">
        @error('account_holder_name')<span class="invalid-feedback">{{ $message }}</span>@enderror
    </div>

    <div class="col-md-6">
        <label for="account_number" class="form-label">@lang('Account Number')</label>
        <input type="text" class="form-control @error('account_number') is-invalid @enderror" id="account_number"
               name="account_number" value="{{ old('account_number', $userBankAccountPool->account_number ?? '') }}">
        @error('account_number')<span class="invalid-feedback">{{ $message }}</span>@enderror
    </div>

    <div class="col-md-4">
        <label for="currency_code" class="form-label">@lang('Currency Code')</label>
        <input type="text" class="form-control @error('currency_code') is-invalid @enderror" id="currency_code"
               name="currency_code" value="{{ old('currency_code', $userBankAccountPool->currency_code ?? '') }}" placeholder="EUR">
        @error('currency_code')<span class="invalid-feedback">{{ $message }}</span>@enderror
    </div>

    <div class="col-md-4">
        <label for="swift_bic" class="form-label">@lang('SWIFT / BIC')</label>
        <input type="text" class="form-control @error('swift_bic') is-invalid @enderror" id="swift_bic"
               name="swift_bic" value="{{ old('swift_bic', $userBankAccountPool->swift_bic ?? '') }}">
        @error('swift_bic')<span class="invalid-feedback">{{ $message }}</span>@enderror
    </div>

    <div class="col-md-4">
        <label for="country_code" class="form-label">@lang('Country Code')</label>
        <input type="text" class="form-control @error('country_code') is-invalid @enderror" id="country_code"
               name="country_code" value="{{ old('country_code', $userBankAccountPool->country_code ?? '') }}" placeholder="DE">
        @error('country_code')<span class="invalid-feedback">{{ $message }}</span>@enderror
    </div>

    <div class="col-12">
        <label for="notes" class="form-label">@lang('Notes')</label>
        <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="4"
                  placeholder="@lang('Optional internal notes for this pool record')">{{ old('notes', $userBankAccountPool->notes ?? '') }}</textarea>
        @error('notes')<span class="invalid-feedback">{{ $message }}</span>@enderror
    </div>

    <div class="col-12">
        <label class="row form-check form-switch mt-1" for="status">
            <span class="col-8 col-sm-10 ms-0">
                <span class="d-block text-dark">@lang('Active for assignment')</span>
                <span class="d-block fs-5">@lang('Only active and unassigned records can be allocated to new users.')</span>
            </span>
            <span class="col-4 col-sm-2 text-end">
                <input type="hidden" name="status" value="0">
                <input class="form-check-input @error('status') is-invalid @enderror" type="checkbox" id="status"
                       name="status" value="1" {{ old('status', $userBankAccountPool->status ?? 1) == 1 ? 'checked' : '' }}>
            </span>
            @error('status')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
        </label>
    </div>
</div>
