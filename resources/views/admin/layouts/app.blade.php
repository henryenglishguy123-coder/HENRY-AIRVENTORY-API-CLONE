<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }} | @yield('title', '')</title>
    <link rel="shortcut icon" href="{{ asset('assets/images/logo-mini.svg') }}" />
    <meta name="title" content="{{ config('app.name', 'Laravel') }} |  @yield('title', '')">
    <meta name="robots" content="noindex,nofollow">
    <link rel="stylesheet" href="{{ asset('assets/css/style.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.css" />
    <link rel="stylesheet" href="{{ asset('assets/datatables/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/quill/dist/quill.snow.css') }}">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />

    @yield('css')
</head>

<body>
    <div class="mobile_header d-lg-none">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <img src="{{ asset('assets/images/logo-mini.svg') }}" alt="Logo" width="24" class="invert-image">
                <span class="text-white store_name">@storeconfig('store_name')</span>
            </div>
            <div class="menu_icon">
                <div class="hamburger-menu" id="mobileSidebarToggle">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </div>
        </div>
    </div>
    <div id="main-wrapper" data-layout="vertical" data-navbarbg="skin5" data-sidebartype="full"
        data-sidebar-position="absolute" data-header-position="absolute" data-boxed-layout="full">
        @include('admin.partials.sidebar')
        <div class="page-wrapper">
            @yield('content')
        </div>
    </div>
    <script>
        const ajaxErrorTranslations = @json(__('ajaxError'));
        const loginUrl = "{{ route('admin.login') }}";
        const logoutUrl = "{{ route('admin.logout') }}";
        const getCookie = (name) => {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) {
                return decodeURIComponent(parts.pop().split(';').shift());
            }
            return null;
        };
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.querySelector('.left-sidebar');
            const toggle = document.getElementById('mobileSidebarToggle');
            if (sidebar && toggle) {
                toggle.addEventListener('click', function () {
                    sidebar.classList.toggle('active');
                    document.body.classList.toggle('active');
                    toggle.classList.toggle('active');
                });
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/dayjs@1/dayjs.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('assets/js/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/perfect-scrollbar.jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/waves.js') }}"></script>
    <script src="{{ asset('assets/js/sidebarmenu.js') }}"></script>
    <script src="{{ asset('assets/js/custom.min.js') }}"></script>
    <script src="{{ asset('assets/select2/js/select2.full.min.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    <script src="{{ asset('assets/datatables/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/quill/dist/quill.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.1/jquery.validate.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

    @yield('js')
</body>

</html>