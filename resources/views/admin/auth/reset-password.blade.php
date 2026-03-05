<!DOCTYPE html>
<html dir="ltr">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex,nofollow" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $setting->store_name ?? config('app.name', 'ITECH PANEL ADMIN') }}</title>
    <link rel="icon" type="image/png" sizes="16x16"
        href="{{ !empty($setting->favicon) ? getImagepath($setting->favicon) : asset('assets/images/logo-mini.svg') }}" />
    <link href="{{ asset('assets/css/style.min.css') }}" rel="stylesheet" />
</head>

<body>
    <div class="main-wrapper">
        <div class="auth-wrapper d-flex no-block justify-content-center align-items-center bg-dark"
            style="min-height:100dvh;">
            <div class="auth-box bg-dark border-secondary">
                <div id="loginform">
                    <div class="text-center pt-3 pb-3">
                        <span class="db">
                            <img width="180"
                                src="{{ !empty($setting->icon) ? getImagepath($setting->icon) : asset('assets/images/logo.svg') }}"
                                alt="{{ __('Logo') }}">
                        </span>
                    </div>
                    <div id="globalAlert" class="alert d-none" role="alert"></div>

                    <!-- Form -->
                    <form class="form-horizontal mt-3" id="reset-password-form">
                        @csrf
                        <input type="hidden" name="token" value="{{ $request->route('token') }}" />
                        <input type="hidden" name="email" value="{{ old('email', $request->email) }}" />
                        <div class="row pb-4">
                            <div class="col-12">
                                <div class="input-group mb-3">
                                    <input type="password" name="password" id="password"
                                        class="form-control form-control-lg" placeholder="{{ __('New Password') }}"
                                        aria-label="Password" required />
                                </div>
                                <div class="invalid-feedback d-block" id="passwordError"></div>
                            </div>

                            <div class="col-12">
                                <div class="input-group mb-3">
                                    <input type="password" name="password_confirmation" id="password_confirmation"
                                        class="form-control form-control-lg" placeholder="{{ __('Confirm Password') }}"
                                        aria-label="Confirm Password" required />
                                </div>
                                <div class="invalid-feedback d-block" id="passwordConfirmationError"></div>
                            </div>
                        </div>

                        <div class="row border-secondary">
                            <div class="col-12">
                                <div class="form-group">
                                    <div class="pt-3">
                                        <button id="reset-password-btn"
                                            class="btn btn-success float-end text-white d-flex align-items-center"
                                            type="submit">
                                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status"
                                                aria-hidden="true" id="resetSpinner"></span>
                                            <span id="resetBtnText">{{ __('Reset Password') }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div id="successMessage" class="alert alert-success mt-4 d-none"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const messages = {
            pleaseWait: "{{ __('Please wait...') }}",
            resetPassword: "{{ __('Reset Password') }}",
            successReset: "{{ __('Password has been reset successfully! Redirecting to login...') }}",
            unexpectedError: "{{ __('An unexpected error occurred. Please try again later.') }}",
        };
        const resetPasswordUrl = "{{ route('admin.password.store') }}";
        const loginUrl = "{{ route('admin.login') }}";
    </script>
    <script src="{{ asset('assets/js/jquery/dist/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/pages/reset-password.js') }}"></script>
</body>

</html>
