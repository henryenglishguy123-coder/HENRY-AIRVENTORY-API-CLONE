<div class="card mb-4">
    <div class="card-header pb-0">
        <h6>{{ __('Pricing & Inventory') }}</h6>
    </div>

    <div class="card-body">
        <div class="row g-3">

            {{-- SKU --}}
            <div class="col-md-6">
                <label for="sku" class="form-label required">
                    {{ __('Main SKU') }}
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted">
                        <i class="fas fa-barcode"></i>
                    </span>
                    <input type="text" class="form-control" id="sku" name="sku"
                        value="{{ old('sku', isset($product) ? $product->sku : '') }}"
                        placeholder="{{ __('SKU will be auto-generated if left empty') }}" required>
                </div>
            </div>

            {{-- Weight --}}
            <div class="col-md-6">
                <label for="weight" class="form-label required">
                    {{ __('Weight') }} (@storeconfig('weight_unit'))
                </label>
                <input type="number" step="0.001" min="0.001" class="form-control" id="weight" name="weight"
                    value="{{ old('weight', isset($product) ? $product->weight : '') }}"
                    placeholder="{{ __('Enter product weight') }}" required>
            </div>

            {{-- Regular Price --}}
            <div class="col-md-6">
                <label for="price" class="form-label required">
                    {{ __('Regular Price') }}
                </label>
                <div class="input-group">
                    <span class="input-group-text">{{ $defaultCurrency->symbol }}</span>
                    <input type="number" step="0.01" min="0.001" class="form-control" id="price"
                        name="price"
                        value="{{ old('price', isset($basePrice) && $basePrice ? $basePrice->regular_price : '') }}"
                        placeholder="{{ __('Enter regular price') }}" required>
                </div>
            </div>

            {{-- Sale Price --}}
            <div class="col-md-6">
                <label for="sale_price" class="form-label">
                    {{ __('Sale Price') }}
                    <span class="text-muted fw-normal">({{ __('Optional') }})</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text">{{ $defaultCurrency->symbol }}</span>
                    <input type="number" step="0.01" min="0.001" class="form-control" id="sale_price"
                        name="sale_price"
                        value="{{ old('sale_price', isset($basePrice) && $basePrice ? $basePrice->sale_price : '') }}"
                        placeholder="{{ __('Enter sale price (optional)') }}">
                </div>
            </div>

            {{-- Inventory Toggle --}}
            <div class="col-12 mt-4">
                <div class="d-flex align-items-center p-3 border rounded bg-light">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="manage_inventory"
                            name="manage_inventory" value="1"
                            {{ old('manage_inventory', isset($inventory) ? $inventory->manage_inventory : 0) ? 'checked' : '' }}>
                        <label class="form-check-label ms-2 fw-bold text-dark mb-0" for="manage_inventory">
                            {{ __('Track Inventory Quantity') }}
                        </label>
                    </div>
                </div>
            </div>

            {{-- Stock Status --}}
            <div class="col-md-6">
                <label for="stock_status" class="form-label required">
                    {{ __('Stock Status') }}
                </label>
                <select class="form-select" id="stock_status" name="stock_status" required>
                    @php
                        $currentStockStatus = old('stock_status', isset($inventory) ? $inventory->stock_status : 1);
                    @endphp
                    <option value="1" {{ (int) $currentStockStatus === 1 ? 'selected' : '' }}>
                        {{ __('In Stock') }}</option>
                    <option value="0" {{ (int) $currentStockStatus === 0 ? 'selected' : '' }}>
                        {{ __('Out Of Stock') }}</option>
                </select>
            </div>

            {{-- Quantity --}}
            <div class="col-md-6">
                <label for="quantity" class="form-label">
                    {{ __('Total Quantity') }}
                </label>
                <input type="number" class="form-control" id="quantity" name="quantity"
                    value="{{ old('quantity', isset($inventory) ? $inventory->quantity : '') }}"
                    placeholder="{{ __('Enter total available quantity') }}">
            </div>

        </div>
    </div>
</div>
