<div class="card mb-4">
    <div class="card-header pb-0 d-flex justify-content-between align-items-center">
        <h6>{{ __('Product Variants') }}</h6>
        <span class="badge bg-gradient-info">{{ __('Pro Generator') }}</span>
    </div>

    <div class="card-body">
        <div id="variants-section">
            <div class="table-responsive border rounded-3 bg-white mt-3">
                <table class="table variant-table align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="text-center" width="50">#</th>
                            <th width="80">{{ __('Image') }}</th>
                            <th>{{ __('Attributes') }}</th>
                            <th width="100">{{ __('Factories') }}</th>
                            <th width="50"></th>
                        </tr>
                    </thead>
                    <tbody id="variants-table-body">
                        @foreach ($existingVariants as $index => $variant)
                            <tr>
                                <td class="text-center">{{ $index + 1 }}</td>
                                <td>
                                    <label class="variant-file-label"
                                        aria-label="{{ __('Upload image for variant :id', ['id' => $variant['id']]) }}">
                                        <div class="variant-img-wrapper">
                                            <div class="variant-skeleton skeleton"></div>
                                            <img src="{{ $variant['image'] ?? getImageUrl('') }}"
                                                class="variant-img-preview" loading="lazy"
                                                alt="{{ __('Variant image for SKU :sku', ['sku' => $variant['sku']]) }}"
                                                onload="this.classList.add('loaded'); this.previousElementSibling.classList.add('hidden');">
                                        </div>
                                        <input type="file" name="variant_image_{{ $variant['id'] }}"
                                            data-variant-id="{{ $variant['id'] }}" accept="image/*"
                                            class="d-none variant-file-input"
                                            aria-label="{{ __('Upload image for variant :id', ['id' => $variant['id']]) }}">
                                    </label>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach ($variant['attributes'] ?? [] as $attrId => $optId)
                                            @php
                                                $attr = $attributes->firstWhere('attribute_id', $attrId);
                                                $opt = $attr ? $attr->options->firstWhere('option_id', $optId) : null;
                                            @endphp
                                            @if ($attr && $opt)
                                                <span class="badge bg-light text-dark border">
                                                    {{ $attr->description?->name ?? $attr->attribute_code }}:
                                                    {{ $opt->key }}
                                                </span>
                                            @endif
                                        @endforeach

                                    </div>
                                    <div class="small text-muted mt-1">
                                        {{ __('SKU') }}: <span class="fw-bold">{{ $variant['sku'] }}</span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary">
                                        {{ $variant['factories_count'] ?? 0 }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm text-danger remove-variant"
                                        data-variant-id="{{ $variant['id'] }}" title="{{ __('Delete Variant') }}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>