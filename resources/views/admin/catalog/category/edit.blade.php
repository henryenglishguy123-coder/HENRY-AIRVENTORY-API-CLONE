@extends('admin.layouts.app')

@section('content')

<div class="page-breadcrumb">
    <div class="row align-items-center">
        <div class="col-6">
            <h4 class="page-title">{{ __('Edit Category') }}</h4>
        </div>
        <div class="col-6 text-end">
            <a href="{{ route('admin.catalog.categories.index') }}" class="btn btn-outline-secondary">
                <i class="mdi mdi-arrow-left"></i> {{ __('Back to Categories') }}
            </a>
        </div>
    </div>
</div>

<div class="container-fluid">

    <x-alerts />

    <form id="categoryForm" action="{{ route('admin.catalog.categories.update') }}"
          method="POST" enctype="multipart/form-data">

        @csrf

        <input type="hidden" id="category_id" value="{{ $category->id }}">

        <div class="row">

            <!-- LEFT SECTION -->
            <div class="col-lg-8 mb-3">
                <div class="card shadow-sm">
                    <div class="card-body">

                        <div class="row">

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Industry') }}</label>
                                <input type="text"
                                       class="form-control"
                                       id="industry_id"
                                       readonly
                                       data-id="{{ $category->industry->id }}"
                                       value="{{ $category->industry->meta->name }}">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Category Name') }}</label>
                                <input type="text" id="name" name="name"
                                       class="form-control"
                                       value="{{ old('name', $category->meta->name ?? '') }}">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Slug') }}</label>
                                <input type="text" id="slug"
                                       class="form-control"
                                       value="{{ $category->slug }}"
                                       readonly disabled>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Parent Category') }}</label>
                                <select id="parent_id" name="parent_id" class="form-select">
                                    <option value="">{{ __('None') }}</option>
                                </select>
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label">{{ __('Description') }}</label>
                                <textarea name="description" id="description" rows="4" class="form-control">{{ old('description', $category->meta->description ?? '') }}</textarea>
                            </div>

                        </div>

                        <hr class="my-4">

                        <h5 class="fw-bold text-primary">{{ __('SEO Details') }}</h5>
                        <div class="row mt-3">

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Meta Title') }}</label>
                                <input name="meta_title"
                                       type="text"
                                       class="form-control"
                                       value="{{ old('meta_title', $category->meta->meta_title ?? '') }}">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Status') }}</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="1" @selected(old('status', $category->meta->status ?? '0') == '1')>{{ __('Enabled') }}</option>
                                    <option value="0" @selected(old('status', $category->meta->status ?? '0') == '0')>{{ __('Disabled') }}</option>
                                </select>
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label">{{ __('Meta Description') }}</label>
                                <textarea name="meta_description" id="meta_description" rows="3" class="form-control">{{ old('meta_description', $category->meta->meta_description ?? '') }}</textarea>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-primary px-4">
                                    <i class="mdi mdi-content-save"></i>
                                    {{ __('Update Category') }}
                                </button>
                            </div>

                        </div>

                    </div>
                </div>
            </div>

            <!-- RIGHT SECTION -->
            <div class="col-lg-4 mb-3">
                <div class="card shadow-sm">
                    <div class="card-body text-center">

                        <label class="form-label">{{ __('Category Image') }}</label>

                        @php
                            $img = $category->meta->image ?? null;
                            $url = getImageUrl($img);
                        @endphp

                        <img id="previewImg"
                             src="{{ $url }}"
                             class="img-fluid rounded shadow-sm mb-3"
                             style="max-height:220px; cursor:pointer;"
                             title="{{ __('Click to change image') }}">

                        <input type="file" name="image" id="image" accept="image/*" class="d-none">

                        <button type="button" class="btn btn-primary w-100" id="btnUpload">
                            <i class="mdi mdi-upload"></i> {{ __('Upload New Image') }}
                        </button>

                        <p class="text-muted small mt-2">
                            {{ __('Max image size: 10MB. Allowed formats: JPG, PNG, JPEG.') }}
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
    window.editCfg = {
        routes: {
            industryCats: "{{ route('admin.catalog.categories.industries.categories', ':id') }}",
            redirect: "{{ route('admin.catalog.categories.index') }}",
            update: "{{ route('admin.catalog.categories.update') }}"
        },
        categoryId: "{{ $category->id }}",
        parentId: "{{ $category->parent_id }}",
        labels: {
            none: "{{ __('None') }}",
            loading: "{{ __('Loading...') }}",
            fetchError: "{{ __('Failed to fetch categories.') }}",
            processing: "{{ __('processing') }}"
        }
    };
</script>

<script src="{{ asset('assets/js/pages/catalog/category/edit.js') }}"></script>

@endsection
