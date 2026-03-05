@extends('admin.layouts.app')
@section('title', __('Edit Production Technique'))
@section('content')
    <div class="page-breadcrumb">
        <div class="row">
            <div class="col-12 d-flex no-block align-items-center">
                <h4 class="page-title">{{ __('Edit Production Technique') }}</h4>
                <div class="ms-auto text-end">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('Home') }}</a></li>
                            <li class="breadcrumb-item"><a
                                    href="{{ route('admin.catalog.production-techniques.index') }}">{{ __('Production Techniques') }}</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('Edit') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <x-alerts />
        <div class="row">
            <div class="col-12">
                <form id="technique-form" class="form-horizontal" action="#" method="POST">
                    @csrf
                    <input type="hidden" name="id" value="{{ $technique->id }}">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">{{ __('Edit Information') }}</h4>
                            <div class="row mt-3">
                                <div class="col-md-6 form-group">
                                    <label for="name" class="control-label col-form-label required">{{ __('Name') }}</label>
                                    <input type="text" class="form-control" id="name" name="name"
                                        value="{{ $technique->name }}" required>
                                    <div class="invalid-feedback text-danger error-name"></div>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="status"
                                        class="control-label col-form-label required">{{ __('Status') }}</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="1" {{ $technique->status ? 'selected' : '' }}>
                                            {{ __('Enable') }}
                                        </option>
                                        <option value="0" {{ !$technique->status ? 'selected' : '' }}>
                                            {{ __('Disable') }}
                                        </option>
                                    </select>
                                    <div class="invalid-feedback text-danger error-status"></div>
                                </div>
                            </div>
                            <div class="row mt-4">
                                <div class="col-md-12 d-flex justify-content-end">
                                    <a href="{{ route('admin.catalog.production-techniques.index') }}"
                                        class="btn btn-secondary me-2">{{ __('Cancel') }}</a>
                                    <button type="submit" class="btn btn-primary" id="submitBtn">
                                        {{ __('Update') }}
                                        <span class="spinner-border spinner-border-sm d-none ms-1" role="status"
                                            aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        window.productionTechniqueConfig = {
            updateUrl: "{{ route('admin.production-techniques.update') }}",
            redirectUrl: "{{ route('admin.catalog.production-techniques.index') }}",
            submitText: "{{ __('Update') }}"
        };
    </script>
    <script src="{{ asset('assets/js/pages/catalog/production-technique/edit.js') }}"></script>
@endsection