@extends('admin.layouts.app')

@section('title', __('Create Categories'))

@section('content')
<div class="page-breadcrumb">
    <div class="row align-items-center">
        <div class="col-6">
            <h4 class="page-title mb-0">{{ __('New Category') }}</h4>
        </div>
        <div class="col-6 text-end">
            <a href="{{ route('admin.catalog.categories.index') }}" class="btn btn-outline-secondary">
                <i class="mdi mdi-arrow-left"></i> {{ __('back') }}
            </a>
        </div>
    </div>
</div>

<div class="container-fluid">

    <x-alerts />

    <form id="categoryForm" action="{{ route('admin.catalog.categories.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="row">

            <!-- LEFT -->
            <div class="col-md-8">
                <div class="card shadow-sm mb-3">
                    <div class="card-body">

                        <h5 class="fw-bold text-primary mb-3">{{ __('Basic Details') }}</h5>

                        <div class="row">

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Industry') }}</label>
                                <select name="industry_id" id="industry_id" class="form-select">
                                    <option value="">{{ __('Select Industry') }}</option>
                                    @foreach($industries as $industry)
                                        <option value="{{ $industry->id }}" @selected(old('industry_id')==$industry->id)>
                                            {{ $industry->meta?->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Category Name') }}</label>
                                <input type="text" id="name" name="name" class="form-control"
                                       value="{{ old('name') }}" autocomplete="off">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Slug') }}</label>
                                <input type="text" id="slug" name="slug" class="form-control"
                                       value="{{ old('slug') }}" autocomplete="off">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Parent Category') }}</label>
                                <select id="parent_id" name="parent_id" class="form-select">
                                    <option value="">{{ __('None') }}</option>
                                </select>
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label">{{ __('Description') }}</label>
                                <textarea id="description" name="description" rows="4" class="form-control">{{ old('description') }}</textarea>
                            </div>

                        </div>

                        <hr class="my-4">

                        <h5 class="fw-bold text-primary mb-3">{{ __('SEO Details') }}</h5>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Meta Title') }}</label>
                                <input type="text" name="meta_title" class="form-control"
                                       value="{{ old('meta_title') }}"
                                       placeholder="{{ __('Enter SEO title for search engines') }}">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Status') }}</label>
                                <select name="status" class="form-select">
                                    <option value="1" @selected(old('status')=='1')>{{ __('Enabled') }}</option>
                                    <option value="0" @selected(old('status')=='0')>{{ __('Disabled') }}</option>
                                </select>
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label">{{ __('Meta Description') }}</label>
                                <textarea name="meta_description" rows="4" class="form-control"
                                placeholder="{{ __('Enter a brief description for search engines') }}">{{ old('meta_description') }}</textarea>
                            </div>

                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary px-4 py-2">
                                    <i class="mdi mdi-content-save"></i> {{ __('Create Category') }}
                                </button>
                            </div>

                        </div>

                    </div>
                </div>
            </div>

            <!-- RIGHT -->
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body text-center">

                        <h6 class="mb-3">{{ __('Category Image') }}</h6>

                        <div class="mb-3">
                            <img id="previewImg"
                                src="{{ getImageUrl('') }}"
                                class="img-fluid rounded shadow-sm"
                                style="max-height: 220px; cursor:pointer;"
                                title="{{ __('Click to change image') }}">
                        </div>

                        <input type="file" name="image" id="image" accept="image/*" class="d-none">

                        <button type="button" class="btn btn-primary w-100" id="btnUploadImage">
                            <i class="mdi mdi-upload"></i> {{ __('Upload New Image') }}
                        </button>

                        <p class="text-muted small mt-2">
                            {{ __('Max 10MB · JPG · PNG · JPEG') }}
                        </p>

                    </div>
                </div>
            </div>

        </div>
    </form>

</div>
@endsection


@section('js')

<script>
    window.categoryConfig = {
        csrf: "{{ csrf_token() }}",
        routes: {
            categoryByIndustry: "{{ route('admin.catalog.categories.industries.categories', ':id') }}",
        },
        labels: {
            loading: "{{ __('Loading...') }}",
            none: "{{ __('None') }}",
            fetchError: "{{ __('Failed to fetch categories.') }}",
        }
    }
</script>

<script src="{{ asset('assets/js/pages/catalog/category/create.js') }}"></script>
@endsection
