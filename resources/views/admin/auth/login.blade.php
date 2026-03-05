<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <title>{{ __('Airventory | Admin Login') }}</title>
    <meta name="description" content="{{ __('Login to the Airventory Admin Panel.') }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="robots" content="noindex,nofollow" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" type="image/png" sizes="16x16"
          href="{{ !empty($setting->favicon) ? getImagepath($setting->favicon) : asset('assets/images/logo-mini.svg') }}" />

    <link href="{{ asset('assets/css/style.min.css') }}" rel="stylesheet" />
</head>

<body>
    <div class="main-wrapper">
        <div class="auth-wrapper d-flex no-block justify-content-center align-items-center bg-dark" style="min-height:100dvh;">
            <div class="auth-box bg-dark">

                <div id="login-container">
                    <div class="text-center pt-3 pb-3">
                        <span class="db">
                            <img width="180"
                                 src="{{ !empty($setting->icon) ? getImagepath($setting->icon) : asset('assets/images/logo.svg') }}"
                                 alt="{{ __('Logo') }}">
                        </span>
                    </div>

                    {{-- Alert box for errors / info --}}
                    <div id="login-alert" class="alert d-none" role="alert"></div>

                    {{-- Login Form --}}
                    <form class="form-horizontal mt-3" id="login-form" method="POST" action="{{ route('admin.login.store') }}" novalidate>
                        @csrf
                        <div class="row">
                            <div class="col-12">

                                {{-- Username / Email --}}
                                <div class="mb-3">
                                    <label for="username" class="form-label text-white">{{ __('Username') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-success text-white" id="username-addon">
                                            <i class="mdi mdi-account fs-4"></i>
                                        </span>
                                        <input
                                            type="text"
                                            id="username"
                                            name="username"
                                            class="form-control form-control-lg"
                                            placeholder="{{ __('Enter your username') }}"
                                            autocomplete="username"
                                            required
                                            aria-describedby="username-addon"
                                        >
                                    </div>
                                    <small class="text-danger d-none" id="username-error"></small>
                                </div>

                                {{-- Password --}}
                                <div class="mb-3">
                                    <label for="password" class="form-label text-white">{{ __('Password') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-warning text-white" id="password-addon">
                                            <i class="mdi mdi-lock fs-4"></i>
                                        </span>
                                        <input
                                            type="password"
                                            id="password"
                                            name="password"
                                            class="form-control form-control-lg"
                                            placeholder="{{ __('Enter your password') }}"
                                            autocomplete="current-password"
                                            required
                                            aria-describedby="password-addon"
                                        >
                                    </div>
                                    <small class="text-danger d-none" id="password-error"></small>
                                </div>

                            </div>
                        </div>

                        <div class="row border-top border-secondary">
                            <div class="col-12">
                                <div class="form-group d-flex justify-content-between align-items-center pt-3">

                                    <a class="btn btn-info" href="{{ route('admin.password.request') }}">
                                        {{ __('Lost password?') }}
                                    </a>

                                    <button
                                        class="btn btn-success text-white"
                                        type="submit"
                                        id="login-btn"
                                        data-default-text="{{ __('Login') }}"
                                        data-loading-text="{{ __('Logging in...') }}"
                                    >
                                        {{ __('Login') }}
                                    </button>

                                </div>
                            </div>
                        </div>
                    </form>
                </div> {{-- /#login-container --}}
            </div>
        </div>
    </div>

    {{-- Scripts --}}
    <script>
        const messages = {
            pleaseWaitLogin : '{{ __("Please wait, logging you in...") }}',
            somethingWentWrong : '{{ __("Something went wrong. Please try again.") }}',
            fixErrors : '{{ __("Please fix the highlighted errors and try again.") }}',
            invalidCredentials : '{{ __("Invalid credentials. Please try again.") }}'
        };
        const dashboardUrl = '{{ url("/admin/dashboard") }}';
        const adminMintTokenUrl = '{{ route("admin.mint-token") }}';
    </script>
    <script src="{{ asset('assets/js/jquery/dist/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/pages/login.js') }}"></script>
</body>
</html>
