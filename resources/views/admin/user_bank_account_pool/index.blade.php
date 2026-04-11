@extends('admin.layouts.app')
@section('page_title', __('Assigned Bank Accounts'))
@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-end">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">@yield('page_title')</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb breadcrumb-no-gutter">
                            <li class="breadcrumb-item"><a class="breadcrumb-link" href="{{ route('admin.dashboard') }}">@lang('Dashboard')</a></li>
                            <li class="breadcrumb-item active" aria-current="page">@yield('page_title')</li>
                        </ol>
                    </nav>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.user.bank.account.pools.create') }}" class="btn btn-primary">
                        <i class="bi-plus-circle me-1"></i> @lang('Add Pool Record')
                    </a>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="card">
                    <div class="card-body">
                        <span class="card-subtitle d-block">@lang('Total Records')</span>
                        <h3 class="mb-0">{{ $stats['total'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="card">
                    <div class="card-body">
                        <span class="card-subtitle d-block">@lang('Available')</span>
                        <h3 class="mb-0 text-success">{{ $stats['available'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <span class="card-subtitle d-block">@lang('Assigned')</span>
                        <h3 class="mb-0 text-primary">{{ $stats['assigned'] }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <input type="text" class="form-control" name="search" value="{{ $search }}"
                               placeholder="@lang('Search by IBAN, bank, holder, or currency')">
                    </div>
                    <div class="col-md-4">
                        <select class="form-select" name="availability">
                            <option value="all" {{ $availability === 'all' ? 'selected' : '' }}>@lang('All records')</option>
                            <option value="available" {{ $availability === 'available' ? 'selected' : '' }}>@lang('Available only')</option>
                            <option value="assigned" {{ $availability === 'assigned' ? 'selected' : '' }}>@lang('Assigned only')</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-white w-100" type="submit">@lang('Filter')</button>
                    </div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>@lang('IBAN')</th>
                        <th>@lang('Bank')</th>
                        <th>@lang('Holder')</th>
                        <th>@lang('Currency')</th>
                        <th>@lang('Status')</th>
                        <th>@lang('Assigned User')</th>
                        <th>@lang('Action')</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($pools as $pool)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $pool->iban }}</div>
                                @if($pool->label)
                                    <small class="text-muted">{{ $pool->label }}</small>
                                @endif
                            </td>
                            <td>{{ $pool->bank_name }}</td>
                            <td>{{ $pool->account_holder_name ?: __('N/A') }}</td>
                            <td>{{ $pool->currency_code ?: __('N/A') }}</td>
                            <td>
                                @if($pool->status == 1)
                                    <span class="badge bg-soft-success text-success">@lang('Active')</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger">@lang('Inactive')</span>
                                @endif
                            </td>
                            <td>
                                @if($pool->assignedUser)
                                    <a href="{{ route('admin.user.view.profile', $pool->assignedUser->id) }}">
                                        {{ $pool->assignedUser->firstname }} {{ $pool->assignedUser->lastname }}
                                    </a>
                                @else
                                    <span class="text-muted">@lang('Available')</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.user.bank.account.pools.edit', $pool) }}" class="btn btn-white btn-sm">
                                    <i class="bi-pencil-fill me-1"></i>@lang('Edit')
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <p class="mb-0">@lang('No pool records found.')</p>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($pools->hasPages())
                <div class="card-footer">
                    {{ $pools->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
