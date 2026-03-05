<div class="bulk-toolbar d-flex flex-wrap gap-3 align-items-end shadow-sm">
    <div class="flex-grow-1">
        <label class="bulk-label">{{ __('Bulk Price') }}</label>
        <div class="input-group input-group-sm">
            <input type="number" id="bulk-price" class="form-control" placeholder="0.00">
            <button class="btn btn-outline-dark mb-0" type="button" data-action="bulk-price">{{ __('Set') }}</button>
        </div>
    </div>
    <div class="flex-grow-1">
        <label class="bulk-label">{{ __('Bulk Sale') }}</label>
        <div class="input-group input-group-sm">
            <input type="number" id="bulk-sale-price" class="form-control" placeholder="0.00">
            <button class="btn btn-outline-dark mb-0" type="button" data-action="bulk-sale">{{ __('Set') }}</button>
        </div>
    </div>
    <div class="flex-grow-1">
        <label class="bulk-label">{{ __('Bulk Stock') }}</label>
        <div class="input-group input-group-sm">
            <input type="number" id="bulk-stock" class="form-control" placeholder="0">
            <button class="btn btn-outline-dark mb-0" type="button" data-action="bulk-stock">{{ __('Set') }}</button>
        </div>
    </div>
    <div class="flex-grow-1">
        <label class="bulk-label">{{ __('Sync SKU') }}</label>
        <button class="btn btn-outline-primary w-100 btn-sm mb-0" type="button" data-action="bulk-sku">
            <i class="fas fa-sync me-1"></i> {{ __('Regenerate') }}
        </button>
    </div>
</div>