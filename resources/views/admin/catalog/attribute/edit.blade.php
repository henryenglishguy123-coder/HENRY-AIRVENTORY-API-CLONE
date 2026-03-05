@extends('admin.layouts.app')

@section('content')
    <div class="page-breadcrumb">
        <div class="row">
            <div class="col-12 d-flex no-block align-items-center">
                <h4 class="page-title">{{ __('editAttribute') }}</h4>
                <div class="ms-auto text-end">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('home') }}</a></li>
                            <li class="breadcrumb-item"><a
                                    href="{{ route('admin.catalog.attributes.index') }}">{{ __('attributes') }}</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('edit') }}</li>
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
                <form id="attribute-form" class="form-horizontal" action="{{ route('admin.catalog.attributes.update') }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="attribute_id" value="{{ $attribute->attribute_id }}">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="label" class="control-label col-form-label">{{ __('label') }}</label>
                                    <input type="text" class="form-control" id="label" name="label" placeholder=""
                                        value="{{ $attribute->description->name }}" required>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="attribute_code"
                                        class="control-label col-form-label">{{ __('urlKey') }}</label>
                                    <input type="text" class="form-control" id="attribute_code"
                                        name="attribute_code" placeholder="" value="{{ $attribute->attribute_code }}"
                                        readonly>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="industry_id"
                                        class="control-label col-form-label">{{ __('industry') }}</label>
                                    <select class="form-select" id="industry_id" name="industry_id" required>
                                        @foreach ($industries as $industry)
                                            <option value="{{ $industry->id }}"
                                                {{ $industry->id == $attribute->catalog_industry_id ? 'selected' : '' }}>
                                                {{ $industry->meta->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="attribute_type"
                                        class="control-label col-form-label">{{ __('type') }}</label>
                                        <select class="form-select" id="attribute_type" name="attribute_type" required disabled>
    @if ($attribute->field_type == 'text')
        <option value="text">{{ __('Text Field') }}</option>
    @elseif($attribute->field_type == 'textarea')
        <option value="textarea">{{ __('Text Area') }}</option>
    @elseif($attribute->field_type == 'visual_swatch')
        <option value="visual_swatch">{{ __('Visual Swatch') }}</option>
    @elseif($attribute->field_type == 'text_swatch')
        <option value="text_swatch">{{ __('Text Swatch') }}</option>
    @elseif($attribute->field_type == 'select')
        <option value="select">{{ __('Select') }}</option>
    @elseif($attribute->field_type == 'multiple_select')
        <option value="multiple_select">{{ __('Multiple Select') }}</option>
    @endif
</select>

                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="is_required"
                                        class="control-label col-form-label">{{ __('required') }}</label>
                                    <select class="form-select" id="is_required" name="is_required">
                                        <option value="1" {{ $attribute->is_required ? 'selected' : '' }}>
                                            {{ __('yes') }}
                                        </option>
                                        <option value="0" {{ !$attribute->is_required ? 'selected' : '' }}>
                                            {{ __('no') }}
                                        </option>
                                    </select>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="use_for_variation"
                                        class="control-label col-form-label">{{ __('useForVariation') }}</label>
                                    <select class="form-select" id="use_for_variation" name="use_for_variation">
                                        <option value="1" {{ $attribute->use_for_variation ? 'selected' : '' }}>
                                            {{ __('yes') }}</option>
                                        <option value="0" {{ !$attribute->use_for_variation ? 'selected' : '' }}>
                                            {{ __('no') }}</option>
                                    </select>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="use_for_filter"
                                        class="control-label col-form-label">{{ __('useForFilter') }}</label>
                                    <select class="form-select" id="use_for_filter" name="use_for_filter">
                                        <option value="1" {{ $attribute->use_for_filter ? 'selected' : '' }}>
                                            {{ __('yes') }}
                                        </option>
                                        <option value="0" {{ !$attribute->use_for_filter ? 'selected' : '' }}>
                                            {{ __('no') }}
                                        </option>
                                    </select>
                                </div>

                                <div class="col-md-6 form-group">
                                    <label for="status" class="control-label col-form-label">{{ __('status') }}</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="1" {{ $attribute->status ? 'selected' : '' }}>
                                            {{ __('enable') }}
                                        </option>
                                        <option value="0" {{ !$attribute->status ? 'selected' : '' }}>
                                            {{ __('disable') }}
                                        </option>
                                    </select>
                                </div>

                                <div id="key-value-container">
                                    @if (in_array($attribute->field_type, ['visual_swatch', 'text_swatch', 'multiple_select', 'select']))
                                        <label for="options" class="form-label">{{ __('Options') }}</label>
                                        <div id="key-value-fields">
                                            @foreach ($attribute->options as $option)
                                                @php
                                                    $optionIndex = $option->option_id;
                                                @endphp
                                                <div class="row mb-2 key-value-row" data-option-index="{{ $optionIndex }}">
                                                    <div class="col-md-5">
                                                        <input type="text" class="form-control" name="options[{{ $optionIndex }}][key]"
                                                            value="{{ $option->key }}" placeholder="Key">
                                                        <input type="hidden" class="form-control" name="options[{{ $optionIndex }}][id]"
                                                            value="{{ $option->option_id }}" placeholder="Key">
                                                        <span class="text-danger error-message error-key"></span>
                                                    </div>

                                                    @if ($attribute->field_type == 'visual_swatch')
                                                        <div class="col-md-5">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="form-group"
                                                                        style="margin-bottom: 0rem !important;">
                                                                        <div class="input-group">
                                                                            <input type="color"
                                                                                class="form-control color-input"
                                                                                name="options[{{ $optionIndex }}][value_color]"
                                                                                value="{{ $option->type == 'color' ? $option->option_value : '' }}"
                                                                                onchange="updateColorInput(this)"
                                                                                style="padding: 0.4rem 0.4rem !important; max-width: 3rem;">
                                                                            <input type="text" class="form-control"
                                                                                name="options[{{ $optionIndex }}][color_code]" placeholder="Hex Code"
                                                                                value="{{ $option->type == 'color' ? $option->option_value : '' }}"
                                                                                readonly>
                                                                        </div>
                                                                        <span class="text-danger error-message error-value"></span>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-2 d-flex align-items-center">
                                                                    <span
                                                                        style="font-size: small; color: #d88aff;">OR</span>
                                                                </div>

                                                                <div class="col-md-4">
                                                                    <div class="image-preview"
                                                                        style="background-image: url('{{ $option->type == 'image' ? getImageUrl($option->option_value) : '' }}');width: 70px; height: 37px; border: 1px solid #c7c8cb; display: flex; align-items: center; justify-content: center; background-color: #fff; cursor: pointer; padding: 4px; background-size: cover;background-position: center;background-repeat: no-repeat;"
                                                                        onclick="this.nextElementSibling.click();">
                                                                        @if ($option->type != 'image')
                                                                            <span class="text-center"
                                                                                style="padding: 4px; background: #f6f3f3; font-size: 12px;">{{ __('No
                                                                                                                                                            Image') }}</span>
                                                                        @endif
                                                                    </div>
                                                                    <input type="file" class="form-control mt-2"
                                                                        name="options[{{ $optionIndex }}][value_file]" accept="image/*"
                                                                        onchange="previewImage(this)"
                                                                        style="display: none;">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @elseif (in_array($attribute->field_type, ['text_swatch', 'select', 'multiple_select']))
                                                        <div class="col-md-5">
                                                            <input type="text" class="form-control" name="options[{{ $optionIndex }}][value_text]"
                                                                value="{{ $option->option_value }}" placeholder="Value">
                                                            <span class="text-danger error-message error-value"></span>
                                                        </div>
                                                    @endif

                                                    <div class="col-md-2 d-flex align-items-center">
                                                        <button type="button" class="btn btn-danger remove-key-value-btn"
                                                            data-id="{{ $option->option_id }}"><i
                                                                class="mdi mdi-minus"></i></button>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                        <button type="button" class="btn btn-outline-primary" id="add-more-btn"><i
                                                class="mdi mdi-plus"></i> {{ __('Add More') }}</button>
                                    @endif
                                </div>

                            </div>
                            <div class="row">
                                <div class="col-md-12 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary">{{ __('update') }}</button>
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
        window.attributeEditConfig = {
            updateUrl: "{{ route('admin.catalog.attributes.update') }}",
            redirectUrl: "{{ route('admin.catalog.attributes.index') }}",
            deleteOptionUrl: "{{ route('admin.catalog.attributes.option.value.delete') }}",
            attributeId: "{{ $attribute->attribute_id }}",
            csrfToken: "{{ csrf_token() }}",

            texts: {
                processing: "{{ __('processing') }}",
                update: "{{ __('update') }}",
                error: "{{ __('An error occurred. Please try again.') }}",
                confirmTitle: "{{ __('Are you sure?') }}",
                confirmText: "{{ __('You would not be able to revert this!') }}",
                proceed: "{{ __('Proceed') }}",
                cancel: "{{ __('Cancel') }}"
            }
        };
    </script>

    <script src="{{ asset('assets/js/pages/catalog/attribute/edit.js') }}"></script>
@endsection
