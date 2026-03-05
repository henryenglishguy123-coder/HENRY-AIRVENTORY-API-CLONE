<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">

<head>
    <meta charset="utf-8" />
    <title>{{ __('Airventory | Forgot Password') }}</title>
    <meta name="description" content="{{ __('Reset your password to access the Airventory Admin Panel.') }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="robots" content="noindex,nofollow" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    {{-- CSRF token for AJAX --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" type="image/png" sizes="16x16"
        href="{{ !empty($setting->favicon) ? getImagepath($setting->favicon) : asset('assets/images/logo-mini.svg') }}" />

    <link href="{{ asset('assets/css/style.min.css') }}" rel="stylesheet" />
</head>

<body>
    <div class="main-wrapper">
        <div class="auth-wrapper d-flex no-block justify-content-center align-items-center bg-dark"
            style="min-height:100dvh;">
            <div class="auth-box bg-dark">
                <div id="recoverform">
                    <div class="text-center pt-3 pb-3">
                        <span class="db">
                            <img width="180"
                                src="{{ !empty($setting->icon) ? getImagepath($setting->icon) : asset('assets/images/logo.svg') }}"
                                alt="{{ __('Logo') }}">
                        </span>
                    </div>

                    <div class="row mt-3">
                        <form class="col-12" id="password-reset-form" action="{{ route('admin.password.email') }}"
                            method="POST">
                            @csrf

                            <div class="input-group mb-3">
                                <div class="input-group-prepend">
                                    <span class="input-group-text bg-danger text-white" id="basic-addon1"
                                        style="height: 100%;">
                                        <i class="mdi mdi-email fs-4"></i>
                                    </span>
                                </div>
                                <input type="email" name="email" id="email" class="form-control form-control-lg"
                                    placeholder="{{ __('Email') }}" aria-label="Email" aria-describedby="basic-addon1"
                                    required />
                            </div>

                            <div class="row mt-3 pt-3 border-top border-secondary">
                                <div class="col-12">
                                    <a class="btn btn-success text-white" href="{{ route('admin.login') }}">
                                        {{ __('Back to Login') }}
                                    </a>

                                    <button id="reset-button" class="btn btn-info float-end d-flex align-items-center"
                                        type="submit">
                                        <span class="spinner-border spinner-border-sm me-2 d-none" role="status"
                                            aria-hidden="true" id="reset-spinner"></span>
                                        <span id="reset-text">{{ __('Recover') }}</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div id="response-message" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        const messages = {
            pleaseWait: "{{ __('Please wait...') }}",
            recover: "{{ __('Recover') }}",
            passwordResetLinkSent: "{{ __('A password reset link has been sent to your email address.') }}",
            tooManyAttempts: "{{ __('Too many attempts. Please try again later.') }}",
            errorOccurred: "{{ __('There was an error. Please try again later.') }}",
        };
    </script>
    <script src="{{ asset('assets/js/jquery/dist/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/pages/forgot-password.js') }}"></script>
</body>

</html>
