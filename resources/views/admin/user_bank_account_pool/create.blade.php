@extends('admin.layouts.app')
@section('page_title', __('Add Pool Record'))
@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-end">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">@yield('page_title')</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb breadcrumb-no-gutter">
                            <li class="breadcrumb-item"><a class="breadcrumb-link" href="{{ route('admin.dashboard') }}">@lang('Dashboard')</a></li>
                            <li class="breadcrumb-item"><a class="breadcrumb-link" href="{{ route('admin.user.bank.account.pools.index') }}">@lang('Assigned Bank Accounts')</a></li>
                            <li class="breadcrumb-item active" aria-current="page">@yield('page_title')</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-header-title">@lang('Create assignable bank account') </h4>
                <a href="{{ route('admin.user.bank.account.pools.index') }}" class="btn btn-info btn-sm">
                    <i class="bi-arrow-left"></i> @lang('Back')
                </a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.user.bank.account.pools.store') }}">
                    @csrf
                    @include('admin.user_bank_account_pool._form')

                    <div class="d-flex justify-content-start mt-4">
                        <button type="submit" class="btn btn-primary">@lang('Save')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
