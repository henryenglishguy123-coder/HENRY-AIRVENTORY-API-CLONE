@extends('admin.layouts.app')
@section('css')
@section('title', __('Wallets List'))
@endsection
@section('content')
<div class="page-breadcrumb">
    <div class="row">
        <div class="col-12 d-flex no-block align-items-center">
            <h4 class="page-title">{{ __('Wallets List') }}</h4>
            <div class="ms-auto text-end">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Wallets') }}</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="walletsTable" class="table table-striped table-bordered">
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@section('js')
<script>
    window.walletsConfig = {
        walletApiurl: @json(route('customer.wallet.index')),
        walletDetailurl: @json(route('admin.customer.wallet', ':customer')),
    };

    const authToken = getCookie('jwt_token');

    $(document).ready(function () {

        if (!authToken) {
            toastr.error('Authentication token missing');
            return;
        }

        $('#walletsTable').DataTable({
            responsive: true,
            autoWidth: false,
            processing: true,
            serverSide: true,
            ordering: true,
            order : [[0, 'desc']],
            ajax: {
                url: window.walletsConfig.walletApiurl,
                type: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${authToken}`,
                },
                            data: function (d) {
                return {
                    limit : d.length,
                    page: (d.start / d.length) + 1,
                    order_column: d.columns[d.order[0].column].data,
                    order_dir: d.order[0].dir,
                    search: d.search.value,
                };
            },
                dataSrc: function (json) {
                    console.log('Wallet API response:', json);
                    if (!json || !json.success) {
                        toastr.error('Failed to load wallets');
                        return [];
                    }
                    json.recordsTotal = json.meta.total;
                    json.recordsFiltered = json.meta.total;
                    return json.data;
                },
                error: function (xhr) {
                    console.error('Wallets API error:', xhr);
                    toastr.error('Something went wrong while loading wallets');
                }
            },
            columns: [
                {
                    data: 'vendor_id',
                    name: 'vendor_id',
                    title: 'Customer ID'
                },
                {
                    data: 'vendor_name',
                    name: 'vendor_name',
                    title: 'Customer Name',
                    orderable: false,
                },
                {
                    data: 'email',
                    name: 'email',
                    title: 'Email',
                    orderable: false,
                },
                {
                    data: 'balance',
                    name: 'balance',
                    title: 'Balance'
                },
                {
                    data: null,
                    title: 'Actions',
                    orderable: false,
                    searchable: false,
                    render: function (row) {
                        return `
                            <a href="${window.walletsConfig.walletDetailurl.replace(':customer', row.vendor_id)}"
                               class="btn btn-sm btn-primary">
                                View
                            </a>
                        `;
                    }
                }
            ]
        });
    });
</script>
@endsection
