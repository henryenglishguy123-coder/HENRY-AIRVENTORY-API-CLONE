<div class="card mb-4">
    <div class="card-header pb-0">
        <h6>{{ __('SEO Configuration') }}</h6>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label">{{ __('Meta Title') }}</label>
            <div class="input-group">
                <input type="text" class="form-control" id="meta_title" name="meta_title" maxlength="60"
                    value="{{ old('meta_title', $product?->info?->meta_title ?? '') }}">
                <span class="input-group-text text-xs"><span id="title-count">0</span>/60</span>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">{{ __('Meta Description') }}</label>
            <textarea class="form-control" id="meta_description" name="meta_description" maxlength="160" rows="3">{{ old('meta_description', $product?->info?->meta_description ?? '') }}</textarea>
            <div class="text-end text-xs text-muted mt-1"><span id="desc-count">0</span>/160</div>
        </div>
    </div>
</div>
