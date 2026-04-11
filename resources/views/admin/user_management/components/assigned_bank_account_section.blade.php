<div id="assignedBankAccountSection" class="card">
    <div class="card-header card-header-content-between">
        <h2 class="card-title h4">@lang('Assigned Bank Account')</h2>
        @if($bankAccount)
            <span class="badge bg-soft-success text-success">@lang('Assigned')</span>
        @else
            <span class="badge bg-soft-warning text-warning">@lang('Unassigned')</span>
        @endif
    </div>
    <div class="card-body">
        @if($bankAccount)
            <div class="row mb-4">
                <label class="col-sm-3 col-form-label form-label">@lang('Current assignment')</label>
                <div class="col-sm-9">
                    <div class="border rounded p-3 bg-soft-light">
                        <div class="d-flex flex-column gap-2">
                            <div>
                                <span class="card-subtitle d-block">@lang('IBAN')</span>
                                <span class="fw-semibold">{{ $bankAccount->iban }}</span>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-2 mb-md-0">
                                    <span class="card-subtitle d-block">@lang('Bank')</span>
                                    <span>{{ $bankAccount->bank_name }}</span>
                                </div>
                                <div class="col-md-6">
                                    <span class="card-subtitle d-block">@lang('Account Holder')</span>
                                    <span>{{ $bankAccount->account_holder_name ?: __('N/A') }}</span>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-2 mb-md-0">
                                    <span class="card-subtitle d-block">@lang('Currency')</span>
                                    <span>{{ $bankAccount->currency_code ?: __('N/A') }}</span>
                                </div>
                                <div class="col-md-6">
                                    <span class="card-subtitle d-block">@lang('Assigned At')</span>
                                    <span>{{ $bankAccount->assigned_at ? dateTime($bankAccount->assigned_at) : __('N/A') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="alert alert-soft-warning" role="alert">
                @lang('This user does not currently have an assigned bank account. Choose an active available pool record below to assign one.')
            </div>
        @endif

        <form action="{{ route('admin.user.bank.account.assign', $user->id) }}" method="post">
            @csrf
            <div class="row mb-4">
                <label for="userBankAccountPoolId" class="col-sm-3 col-form-label form-label">
                    {{ $bankAccount ? __('Reassign from pool') : __('Assign from pool') }}
                </label>
                <div class="col-sm-9">
                    <select
                        class="js-select form-select @error('user_bank_account_pool_id') is-invalid @enderror"
                        id="userBankAccountPoolId"
                        name="user_bank_account_pool_id"
                        data-hs-tom-select-options='{
                          "searchInDropdown": true,
                          "placeholder": "{{ __('Select an available bank account') }}"
                        }'>
                        <option value="">@lang('Select an available bank account')</option>
                        @foreach($availableBankAccountPools as $poolAccount)
                            <option value="{{ $poolAccount->id }}" {{ old('user_bank_account_pool_id') == $poolAccount->id ? 'selected' : '' }}>
                                {{ $poolAccount->iban }} - {{ $poolAccount->bank_name }}{{ $poolAccount->currency_code ? ' - ' . $poolAccount->currency_code : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('user_bank_account_pool_id')
                    <span class="invalid-feedback d-block">{{ $message }}</span>
                    @enderror
                    @if($availableBankAccountPools->isEmpty())
                        <span class="text-muted d-block mt-2">@lang('No active available pool records are currently ready for assignment.')</span>
                    @else
                        <span class="text-muted d-block mt-2">@lang('Only active, unassigned pool records are selectable here.')</span>
                    @endif
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary" {{ $availableBankAccountPools->isEmpty() ? 'disabled' : '' }}>
                    {{ $bankAccount ? __('Save reassignment') : __('Assign bank account') }}
                </button>
                @if($bankAccount)
                    <button
                        type="submit"
                        class="btn btn-white"
                        formaction="{{ route('admin.user.bank.account.release', $user->id) }}"
                        formnovalidate
                        onclick="return confirm('{{ __('Release the current assigned bank account from this user?') }}')">
                        @lang('Release assignment')
                    </button>
                @endif
            </div>
        </form>
    </div>
</div>
