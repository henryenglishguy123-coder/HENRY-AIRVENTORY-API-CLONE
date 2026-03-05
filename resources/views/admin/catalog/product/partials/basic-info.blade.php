<div class="card mb-4">
    <div class="card-header pb-0">
        <h6>{{ __('Product Information') }}</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-12 mb-3">
                <label for="name" class="form-label required">{{ __('Product Name') }}</label>
                <input type="text" class="form-control" id="name" name="name"
                    value="{{ old('name', $product?->info?->name ?? '') }}" required
                    placeholder="e.g. Classic Cotton T-Shirt">
                @error('name')
                    <small class="text-danger fw-bold">{{ $message }}</small>
                @enderror
            </div>

            <div class="col-12 mb-3">
                <label class="form-label">{{ __('Short Description') }}</label>
                <input type="hidden" name="short_description" id="short_description_input"
                    value="{{ old('short_description', $product?->info?->short_description ?? '') }}">
                <div id="short_description" style="height: 120px;"></div>
            </div>

            <div class="col-12">
                <label class="form-label">{{ __('Full Description') }}</label>
                <input type="hidden" name="description" id="description_input"
                    value="{{ old('description', $product?->info?->description ?? '') }}">
                <div id="description" style="height: 150px;"></div>
            </div>
        </div>
    </div>
</div>
