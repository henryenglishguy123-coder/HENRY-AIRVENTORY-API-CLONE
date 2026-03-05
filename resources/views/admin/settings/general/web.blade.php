@extends('admin.layouts.app')
@section('title', __('Web Settings'))

@section('content')
    <div class="page-breadcrumb">
        <div class="row">
            <div class="col-12 d-flex no-block align-items-center">
                <h4 class="page-title">{{ __('Web Settings') }}</h4>
                <div class="ms-auto text-end">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ __('Dashboard') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ __('Web Settings') }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <form class="forms-sample" id="settings-form" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group text-center">
                                <label for="icon" class="fw-bold w-100">{{ __('Logo') }}</label>
                                <input type="file" name="store_logo" class="form-control d-none" id="icon" accept="image/*">
                                <img id="icon-preview" class="profile-image mt-2"
                                    src="{{ getImageUrl($store->icon) }}"
                                    alt="Icon" style="cursor: pointer; max-width: 100%; height: auto;">
                                <div id="store_logoError" class="text-danger error"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group text-center">
                                <label for="favicon" class="fw-bold w-100">{{ __('Favicon') }}</label>
                                <input type="file" name="store_favicon" class="form-control d-none" id="favicon" accept="image/*">
                                <img id="favicon-preview" class="profile-image mt-2"
                                    src="{{ getImageUrl($store->favicon) }}"
                                    alt="Favicon" style="cursor: pointer; max-width: 100%; height: auto;">
                                <div id="store_faviconError" class="text-danger error"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="store_name">{{ __('Store Name') }}</label>
                                <input type="text" name="store_name" class="form-control" id="store_name"
                                    placeholder="{{ __('Store Name') }}"
                                    value="{{ isset($store) ? $store->store_name : '' }}" required>
                                <div id="store_nameError" class="text-danger error"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="mobile">{{ __('Mobile') }}</label>
                                <input type="text" name="mobile" class="form-control number-only" id="mobile"
                                    placeholder="{{ __('Mobile') }}"
                                    value="{{ isset($store) ? $store->mobile : '' }}">
                                <div id="mobileError" class="text-danger error"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="default_country_id">{{ __('Default Country') }}</label>
                                <select name="default_country_id" class="form-select" id="default_country_id" required>
                                    <option value="">{{ __('select') }}</option>
                                    
                                </select>
                                <div id="default_country_idError" class="text-danger error"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="allowed_country_id">{{ __('Allowed Countries') }}</label>
                                <select name="allowed_country_id[]" multiple size="8" class="form-select"
                                    id="allowed_country_id" required>
                                   
                                </select>
                                <div id="allowed_country_idError" class="text-danger error"></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mt-3 mb-2">
                            <h4>{{ __('Meta Information') }}</h4>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="meta_title">{{ __('Meta Title') }}</label>
                                <input type="text" name="meta_title" class="form-control" id="meta_title"
                                    placeholder="{{ __('Meta Title') }}"
                                    value="{{ isset($store) ? $store->meta_title : '' }}">
                                <div id="meta_titleError" class="text-danger error"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="meta_description">{{ __('Meta Description') }}</label>
                                <textarea class="form-control" name="meta_description" id="meta_description" rows="4"
                                    placeholder="{{ __('Meta Description') }}">{{ isset($store) ? $store->meta_description : '' }}</textarea>
                                <div id="meta_descriptionError" class="text-danger error"></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary"
                                id="setting-form-btn">{{ __('Save Changes') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
@section('js')
<script>
    const getCountriesUrl = "{{ route('location.countries.index') }}";
    const uodateWebSettingsUrl = "{{ route('admin.settings.general.web.update') }}";
</script>
<script src="{{ asset('assets/js/pages/settings/general/web.js') }}"></script>
@endsection
