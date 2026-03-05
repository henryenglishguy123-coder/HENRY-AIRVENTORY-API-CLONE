<?php

namespace App\Http\Controllers\Admin\Catalog\Attributes;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Attribute\CatalogAttribute;
use App\Models\Catalog\Attribute\CatalogAttributeDescription;
use App\Models\Catalog\Industry\CatalogIndustry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UpdateAttribute extends Controller
{
    public function edit($id)
    {
        $industries = CatalogIndustry::with('meta')->get();
        $attribute = CatalogAttribute::with('description', 'options')->findOrFail($id);

        $existingOptions = $attribute->options->map(function ($option) {
            return [
                'option_id' => $option->option_id,
                'key' => $option->key,
                'value' => $option->option_value,
                'type' => $option->type,
                'color' => $option->type == 'color' ? $option->option_value : null,
                'file' => $option->type == 'image' ? $option->option_value : null,
            ];
        })->toArray();

        return view('admin.catalog.attribute.edit', compact('attribute', 'industries', 'existingOptions'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'attribute_id' => 'required|numeric|exists:catalog_attributes,attribute_id',
            'is_global' => 'nullable|boolean',
        ]);

        $id = $validated['attribute_id'];

        // Build validation rules for nested options array
        $optionsRules = [];
        if ($request->has('options') && is_array($request->options)) {
            foreach ($request->options as $index => $option) {
                $optionsRules['options.'.$index.'.key'] = 'nullable|string|max:255';
                $optionsRules['options.'.$index.'.id'] = 'nullable|numeric';
                $optionsRules['options.'.$index.'.value_text'] = 'nullable|string|max:255';
                $optionsRules['options.'.$index.'.value_color'] = 'nullable|string|max:255';
                $optionsRules['options.'.$index.'.value_file'] = 'nullable|file|mimes:jpeg,jpg,png|max:2048';
                $optionsRules['options.'.$index.'.color_code'] = 'nullable|string|max:255';
            }
        }

        $validator = Validator::make($request->all(), array_merge([
            'label' => 'required|string|max:255|unique:catalog_attribute_description,name,'.$id.',attribute_id',
            'is_required' => 'nullable|boolean',
            'industry_id' => 'required|numeric',
            'use_for_variation' => 'nullable|boolean',
            'use_for_filter' => 'nullable|boolean',
            'status' => 'nullable|boolean',
        ], $optionsRules));

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        DB::beginTransaction();
        try {
            $attribute = CatalogAttribute::find($id);

            if (! $attribute) {
                DB::rollBack();

                return response()->json(['error' => 'Attribute not found'], 404);
            }
            $attribute->is_required = $request->is_required ?? 0;
            $attribute->use_for_variation = $request->use_for_variation ?? 0;
            $attribute->use_for_filter = $request->use_for_filter ?? 0;
            $attribute->status = $request->status ?? 0;
            $attribute->is_global = $request->is_global ?? 0;
            $attribute->added_by = auth()->id();
            $attribute->catalog_industry_id = $request->industry_id ?? 0;
            $attribute->save();
            CatalogAttributeDescription::updateOrCreate(
                [
                    'attribute_id' => $id,
                ],
                [
                    'name' => $request->label,
                ]
            );

            if (in_array($attribute->field_type, ['text_swatch', 'visual_swatch', 'multiple_select', 'select'])) {
                // Track which option IDs were submitted
                $submittedOptionIds = [];

                // Process nested options array: options[INDEX][field]
                if ($request->has('options') && is_array($request->options)) {
                    foreach ($request->options as $index => $optionData) {
                        $key = $optionData['key'] ?? null;
                        if (! empty($key)) {
                            $type = 'text';
                            $value = $optionData['value_text'] ?? null;
                            $option_id = isset($optionData['id']) && ! empty($optionData['id']) ? $optionData['id'] : null;

                            if ($attribute->field_type === 'visual_swatch') {
                                // Handle file upload for nested structure
                                // Laravel receives nested file arrays as options.INDEX.value_file
                                $file = null;
                                if ($request->hasFile('options.'.$index.'.value_file')) {
                                    $file = $request->file('options.'.$index.'.value_file');
                                } elseif (isset($optionData['value_file']) && $optionData['value_file'] instanceof \Illuminate\Http\UploadedFile) {
                                    $file = $optionData['value_file'];
                                }

                                if ($file && $file->isValid()) {
                                    $realMime = $file->getMimeType();
                                    if (! in_array($realMime, ['image/jpeg', 'image/png'])) {
                                        DB::rollBack();

                                        return response()->json(['error' => 'Invalid image content'], 422);
                                    }
                                    $imageInfo = getimagesize($file->getPathname());
                                    if ($imageInfo === false) {
                                        DB::rollBack();

                                        return response()->json(['error' => 'Invalid or corrupt image file'], 422);
                                    }
                                    if ($option_id) {
                                        $existingOption = $attribute->options()->find($option_id);
                                        if ($existingOption && $existingOption->type === 'image' && $existingOption->option_value) {
                                            try {
                                                Storage::delete($existingOption->option_value);
                                            } catch (\Exception $e) {
                                                \Illuminate\Support\Facades\Log::warning('Failed to delete old image: '.$e->getMessage());
                                            }
                                        }
                                    }

                                    $filename = 'swatch_'.\Illuminate\Support\Str::uuid().'.'.$file->extension();
                                    $path = Storage::putFileAs('catalog/attribute', $file, $filename);
                                    if (! $path) {
                                        DB::rollBack();

                                        return response()->json(['error' => 'Failed to upload image'], 500);
                                    }
                                    $value = $path;
                                    $type = 'image';
                                } elseif (! empty($optionData['value_color'])) {
                                    $value = $optionData['value_color'];
                                    $type = 'color';
                                }
                            }

                            if ($option_id) {
                                $submittedOptionIds[] = $option_id;
                                $existingOption = $attribute->options()->find($option_id);
                                if ($existingOption) {
                                    $existingOption->update([
                                        'attribute_id' => $id,
                                        'key' => $key,
                                        'option_value' => $value,
                                        'type' => $type,
                                    ]);
                                }
                            } else {
                                $newOption = $attribute->options()->create([
                                    'attribute_id' => $id,
                                    'key' => $key,
                                    'option_value' => $value,
                                    'type' => $type,
                                ]);
                                $submittedOptionIds[] = $newOption->option_id;
                            }
                        }
                    }
                }

                // Delete options that were not included in the update
                $existingOptionIds = $attribute->options()->pluck('option_id')->toArray();
                $optionsToDelete = array_diff($existingOptionIds, $submittedOptionIds);

                if (! empty($optionsToDelete)) {
                    $optionsToDeleteCollection = $attribute->options()->whereIn('option_id', $optionsToDelete)->get();
                    $imagePaths = $optionsToDeleteCollection->filter(fn ($opt) => $opt->type === 'image' && $opt->option_value)->pluck('option_value')->toArray();
                    if (! empty($imagePaths)) {
                        try {
                            Storage::delete($imagePaths);
                        } catch (\Exception $e) {
                        }
                    }
                    $attribute->options()->whereIn('option_id', $optionsToDelete)->delete();
                }
            }
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Attribute updated successfully!']);
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Failed to update attribute: '.$e->getMessage(), [
                'exception' => $e,
                'attribute_id' => $id,
                'request_data' => $request->except(['options.value_file']),
            ]);

            return response()->json(['error' => 'Failed to update attribute. Please try again.'], 500);
        }
    }
}
