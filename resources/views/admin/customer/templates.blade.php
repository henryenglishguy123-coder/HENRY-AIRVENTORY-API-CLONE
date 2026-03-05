@extends('admin.layouts.app')
@section('title', __('Customer Templates'))
@section('content')
    <div class="page-breadcrumb">
        <div class="row">
            <div class="col-12 d-flex align-items-center">
                <h4 class="page-title">
                    <i class="mdi mdi-book-open-page-variant me-1"></i>
                    {{ __('Customer Templates') }}
                </h4>
                <div class="ms-auto text-end">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.customer.index') }}">{{ __('Customers') }}</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.customer.show', $customer->id) }}">
                                {{ $customer->first_name }} {{ $customer->last_name }}
                            </a>
                        </li>
                        <li class="breadcrumb-item active">
                            {{ __('Templates') }}
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-3">
                @include('admin.customer._menu', ['id' => $customer->id])
            </div>
            <div class="col-lg-9">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="mdi mdi-book-open-page-variant me-1"></i>
                            {{ __('Customer Templates') }}
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="templatesTable" class="table table-hover align-middle w-100">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('Product') }}</th>
                                        <th>{{ __('Designs') }}</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endsection
    @section('js')
        <script>
            window.customerTemplate = {
                customer_id: @json($customer->id),
                customerTemplateApiUrl: @json(route('customer.templates.index'))
            }
        </script>
        <script src="{{ asset('assets/js/pages/customer/templates.js') }}"></script>
    @endsection
