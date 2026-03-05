<div class="card mb-4">
    <div class="card-header pb-0 d-flex justify-content-between align-items-center">
        <h6>{{ __('Product Variants') }}</h6>
        <span class="badge bg-gradient-info">{{ __('Pro Generator') }}</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @php
                $variantAttributes = $attributes->where('use_for_variation', 1);
            @endphp
            @forelse ($variantAttributes as $attribute)
                <div class="col-md-6">
                    <label class="form-label">{{ $attribute->description->name ?? $attribute->attribute_code }}</label>
                    <select class="form-control select2 variant-select" id="attr_options_{{ $attribute->attribute_id }}"
                        multiple data-attr-id="{{ $attribute->attribute_id }}"
                        data-attr-name="{{ $attribute->description->name ?? $attribute->attribute_code }}">
                        @foreach ($attribute->options as $opt)
                            <option value="{{ $opt->option_id }}">{{ $opt->key }}</option>
                        @endforeach
                    </select>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-light border text-center">
                        {{ __('No variation attributes found. Configure attributes first.') }}
                    </div>
                </div>
            @endforelse
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <button type="button" id="clear-variant-selection"
                class="btn btn-outline-secondary btn-sm mb-0">{{ __('Reset') }}</button>
            <button type="button" id="generate-combinations" class="btn btn-primary btn-sm mb-0">
                <i class="fas fa-magic me-1"></i> {{ __('Generate Variants') }}
            </button>
        </div>

        <div id="variants-section" class="d-none mt-4">
            <hr>
            <div class="table-responsive border rounded-3 bg-white mt-3">
                <table class="table variant-table align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="text-center" width="50">#</th>
                            <th width="80">{{ __('Image') }}</th>
                            <th>{{ __('Variation') }}</th>
                            <th width="200">{{ __('SKU') }}</th>
                            <th width="100">{{ __('Stock') }}</th>
                            <th width="50"></th>
                        </tr>
                    </thead>
                    <tbody id="variants-table-body"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>