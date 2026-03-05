<div class="card mb-4">
    <div class="card-header pb-0">
        <h6>{{ __('Organization') }}</h6>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <label class="form-label required">{{ __('Status') }}</label>
            <select class="form-select" name="status">
                @php
                    $currentStatus = old('status', isset($product) ? $product->status : 1);
                @endphp
                <option value="1" {{ (int) $currentStatus === 1 ? 'selected' : '' }}>{{ __('Enabled') }}</option>
                <option value="0" {{ (int) $currentStatus === 0 ? 'selected' : '' }}>{{ __('Disabled') }}</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label required">{{ __('Category') }}</label>
            <select class="form-select" id="category_id" name="category_id" required>
                <option value="" disabled selected>{{ __('Loading...') }}</option>
            </select>
        </div>
    </div>
</div>
