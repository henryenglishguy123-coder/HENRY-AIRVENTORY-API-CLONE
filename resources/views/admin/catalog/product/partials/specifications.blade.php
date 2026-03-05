<div class="card">
    <div class="card-header pb-0">
        <h6>{{ __('Product Specifications') }}</h6>
    </div>
    <div class="card-body">
        <div class="row">
            @php
                $otherAttributes = $attributes->where('use_for_variation', 0);
                $values = $attributeValues ?? [];
                $variantSelections = $variantAttributesSelections ?? [];
            @endphp
            @if ($otherAttributes && COUNT($otherAttributes) > 0)
                @foreach ($otherAttributes as $attribute)
                    @continue($attribute->attribute_code === 'weight')
                    <div class="col-md-6 mb-3">
                        <label class="form-label {{ $attribute->is_required ? 'required' : '' }}">
                            {{ $attribute->description->name }}
                        </label>
                        @switch($attribute->field_type)
                            @case('text')
                                @php
                                    $existing = $values[$attribute->attribute_id] ?? null;
                                    $existing = is_array($existing) ? $existing[0] ?? null : $existing;
                                @endphp
                                <input type="text" class="form-control" name="attributes[{{ $attribute->attribute_id }}]"
                                    value="{{ old('attributes.' . $attribute->attribute_id, $existing) }}"
                                    {{ $attribute->is_required ? 'required' : '' }}>
                            @break

                            @case('textarea')
                                @php
                                    $existing = $values[$attribute->attribute_id] ?? null;
                                    $existing = is_array($existing) ? $existing[0] ?? null : $existing;
                                @endphp
                                <textarea class="form-control" name="attributes[{{ $attribute->attribute_id }}]" rows="3"
                                    {{ $attribute->is_required ? 'required' : '' }}>{{ old('attributes.' . $attribute->attribute_id, $existing) }}</textarea>
                            @break

                            @case('select')
                            @case('multiple_select')
                                @php
                                    $existing = $values[$attribute->attribute_id] ?? [];
                                    $existing = is_array($existing) ? $existing : [$existing];
                                    if (empty($existing) && isset($variantSelections[$attribute->attribute_id])) {
                                        $existing = $variantSelections[$attribute->attribute_id];
                                        $existing = is_array($existing) ? $existing : [$existing];
                                    }
                                    $oldKey =
                                        $attribute->field_type === 'multiple_select'
                                            ? 'attributes.' . $attribute->attribute_id . '.*'
                                            : 'attributes.' . $attribute->attribute_id;
                                    $selectedValues = old($oldKey, $existing);
                                    $selectedValues = is_array($selectedValues) ? $selectedValues : [$selectedValues];
                                @endphp
                                <select class="form-control select2"
                                    name="attributes[{{ $attribute->attribute_id }}]{{ $attribute->field_type === 'multiple_select' ? '[]' : '' }}"
                                    {{ $attribute->field_type === 'multiple_select' ? 'multiple' : '' }}
                                    {{ $attribute->is_required ? 'required' : '' }}>
                                    <option value="">{{ __('Select Option') }}</option>
                                    @foreach ($attribute->options as $option)
                                        <option value="{{ $option->option_id }}"
                                            {{ in_array($option->option_id, $selectedValues ?? [], false) ? 'selected' : '' }}>
                                            {{ $option->key }}</option>
                                    @endforeach
                                </select>
                            @break
                        @endswitch
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
