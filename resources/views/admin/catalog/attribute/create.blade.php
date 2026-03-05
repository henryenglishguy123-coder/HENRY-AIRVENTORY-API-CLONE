@extends('admin.layouts.app')

@section('content')
    <div class="page-breadcrumb">
        <div class="row">
            <div class="col-12 d-flex no-block align-items-center">
                <h4 class="page-title">{{ __('createAttribute') }}</h4>
                <div class="ms-auto text-end">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('home') }}</a></li>
                            <li class="breadcrumb-item"><a
                                    href="{{ route('admin.catalog.attributes.index') }}">{{ __('attributes') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('create') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        {{-- Success Message --}}
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        {{-- Error Message --}}
        @if (session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif
        <div class="row">
            <div class="col-12">
                <form id="attribute-form" class="form-horizontal" action="{{ route('admin.catalog.attributes.store') }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="label" class="control-label col-form-label">{{ __('label') }}</label>
                                    <input type="text" class="form-control @error('label') is-invalid @enderror" id="label"
                                        name="label" value="{{ old('label') }}" required>
                                    @error('label')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="attribute_code"
                                        class="control-label col-form-label">{{ __('urlKey') }}</label>
                                    <input type="text" class="form-control @error('attribute_code') is-invalid @enderror"
                                        id="attribute_code" name="attribute_code" value="{{ old('attribute_code') }}"
                                        readonly>
                                    @error('attribute_code')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="industry_id"
                                        class="control-label col-form-label">{{ __('industry') }}</label>
                                    <select class="form-select" id="industry_id" name="industry_id" required>
                                        @foreach ($industries as $industry)
                                            <option value="{{ $industry->id }}">
                                                {{ optional($industry->meta)->name ?? $industry->id }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="attribute_type"
                                        class="control-label col-form-label">{{ __('type') }}</label>
                                    <select class="form-select" id="attribute_type" name="attribute_type" required>
                                        <option value="text">{{ __('Text Field') }}</option>
                                        <option value="textarea">{{ __('Text Area') }}</option>
                                        <option value="visual_swatch">{{ __('Visual Swatch') }}</option>
                                        <option value="text_swatch">{{ __('Text Swatch') }}</option>
                                        <option value="select">{{ __('Select') }}</option>
                                        <option value="multiple_select">{{ __('Multiple Select') }}</option>
                                    </select>
                                </div>

                                @php
                                    $selectOptions = ['1' => __('enable'), '0' => __('disable')];
                                @endphp

                                <div class="col-md-6 form-group">
                                    <label for="is_required"
                                        class="control-label col-form-label">{{ __('required') }}</label>
                                    <select class="form-select" id="is_required" name="is_required">
                                        @foreach ($selectOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="use_for_variation"
                                        class="control-label col-form-label">{{ __('useForVariation') }}</label>
                                    <select class="form-select" id="use_for_variation" name="use_for_variation">
                                        @foreach ($selectOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="use_for_filter"
                                        class="control-label col-form-label">{{ __('useForFilter') }}</label>
                                    <select class="form-select" id="use_for_filter" name="use_for_filter">
                                        @foreach ($selectOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="status" class="control-label col-form-label">{{ __('status') }}</label>
                                    <select class="form-select" id="status" name="status">
                                        @foreach ($selectOptions as $value => $label)
                                            <option value="{{ $value }}" {{ $value == '1' ? 'selected' : '' }}>{{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div id="key-value-container">

                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">{{ __('submit') }}</button>
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
        window.attributeConfig = {
            storeUrl: "{{ route('admin.catalog.attributes.store') }}",
            redirectUrl: "{{ route('admin.catalog.attributes.index') }}",
            submitText: "{{ __('submit') }}"
        };
    </script>
    <script src="{{ asset('assets/js/pages/catalog/attribute/create.js') }}"></script>
@endsection