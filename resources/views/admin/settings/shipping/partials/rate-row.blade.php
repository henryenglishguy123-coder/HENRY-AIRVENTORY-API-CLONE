<div class="shipping-rate card shadow-sm border-0 mb-3 animate__animated animate__fadeIn">
    <div class="card-body p-3">
        
        {{-- Hidden ID for Update/Delete --}}
        <input type="hidden" name="rate_id[]" value="">

        <div class="row g-3 align-items-end">

            {{-- 1. Shipping Title --}}
            <div class="col-md-2">
                <label class="form-label small text-muted text-uppercase fw-bold mb-1">
                    {{ __('Shipping Title') }} <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fas fa-shipping-fast"></i></span>
                    <input type="text" 
                           name="shipping_title[]" 
                           class="form-control" 
                           placeholder="e.g. Express (2-3 Days)" 
                           required>
                </div>
            </div>

            {{-- 2. Factory --}}
            <div class="col-md-3">
                <label class="form-label small text-muted text-uppercase fw-bold mb-1">
                    {{ __('Factory') }} <span class="text-danger">*</span>
                </label>
                <select name="factory_id[]" 
                        class="form-select factory-select" 
                        required></select>
            </div>

            {{-- 3. Country --}}
            <div class="col-md-2">
                <label class="form-label small text-muted text-uppercase fw-bold mb-1">
                    {{ __('Country') }} <span class="text-danger">*</span>
                </label>
                <select name="country_code[]" 
                        class="form-select country-select" 
                        required></select>
            </div>

            {{-- 4. Min Qty --}}
            <div class="col-md-2">
                <label class="form-label small text-muted text-uppercase fw-bold mb-1">
                    {{ __('Min Qty') }} <span class="text-danger">*</span>
                </label>
                <input type="number" 
                       name="min_qty[]" 
                       class="form-control text-center" 
                       min="1" 
                       placeholder="1" 
                       required>
            </div>

            {{-- 5. Price --}}
            <div class="col-md-2">
                <label class="form-label small text-muted text-uppercase fw-bold mb-1">
                    {{ __('Price') }} <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text">{{ $defaultCurrency->symbol }}</span>
                    <input type="number" 
                           name="price[]" 
                           class="form-control" 
                           step="0.01" 
                           min="0" 
                           placeholder="0.00" 
                           required>
                </div>
            </div>

            {{-- 6. Remove Button --}}
            <div class="col-md-1 text-end">
                <button type="button" 
                        class="btn btn-outline-danger remove-rate w-100" 
                        data-bs-toggle="tooltip" 
                        title="{{ __('Remove Rate') }}">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>

        </div>
    </div>
</div>