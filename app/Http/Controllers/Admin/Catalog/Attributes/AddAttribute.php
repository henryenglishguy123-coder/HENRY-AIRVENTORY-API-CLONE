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
use Illuminate\Validation\ValidationException;

class AddAttribute extends Controller
{
    public function create()
    {
        $industries = CatalogIndustry::with('meta')->get();

        return view('admin.catalog.attribute.create', compact('industries'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'label' => 'required|string|max:255',
            'attribute_code' => 'required|string|max:255|unique:catalog_attributes,attribute_code',
            'attribute_type' => 'required|string|in:text_swatch,visual_swatch,multiple_select,select',
            'options.key.*' => 'nullable|string|max:255',
            'options.value_text.*' => 'nullable|string|max:255',
            'options.value_color.*' => 'nullable|string|max:255',
            'options.value_file.*' => [
                'nullable',
                'file',
                'max:2048', // 2MB
                'mimetypes:image/jpeg,image/png',
                'mimes:jpeg,jpg,png',
            ],
            'is_required' => 'nullable|boolean',
            'industry_id' => 'required|integer|exists:catalog_industries,id',
            'is_global' => 'nullable|boolean',
            'use_for_variation' => 'nullable|boolean',
            'use_for_filter' => 'nullable|boolean',
            'status' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        DB::beginTransaction();
        try {
            $attribute = new CatalogAttribute;
            $attribute->attribute_code = $request->attribute_code;
            $attribute->field_type = $request->attribute_type;
            $attribute->is_required = $request->is_required ?? 0;
            $attribute->use_for_variation = $request->use_for_variation ?? 0;
            $attribute->use_for_filter = $request->use_for_filter ?? 0;
            $attribute->status = $request->status ?? 0;
            $attribute->is_global = $request->is_global ?? 0;
            $attribute->added_by = auth()->id();
            $attribute->catalog_industry_id = $request->industry_id ?? 0;
            $attribute->save();

            CatalogAttributeDescription::create([
                'attribute_id' => $attribute->attribute_id,
                'name' => $request->label,
            ]);

            if (in_array($request->attribute_type, ['text_swatch', 'visual_swatch', 'multiple_select', 'select'])) {
                if (isset($request->options['key']) && is_array($request->options['key'])) {
                    foreach ($request->options['key'] as $index => $key) {
                        if (! empty($key)) {
                            $type = 'text';
                            $value = $request->options['value_text'][$index] ?? null;

                            if ($request->attribute_type === 'visual_swatch') {
                                $file = $request->file('options.value_file.'.$index);

                                if ($file && $file->isValid()) {
                                    $realMime = $file->getMimeType();
                                    if (! in_array($realMime, ['image/jpeg', 'image/png'])) {
                                        throw ValidationException::withMessages([
                                            'options.value_file.'.$index => ['Invalid image content'],
                                        ]);
                                    }

                                    if (! @getimagesize($file->getPathname())) {
                                        throw ValidationException::withMessages([
                                            'options.value_file.'.$index => ['Invalid or corrupt image file'],
                                        ]);
                                    }

                                    $filename = uniqid('swatch_').'.'.$file->extension();
                                    $path = Storage::putFileAs('catalog/attribute', $file, $filename);

                                    if ($path === false) {
                                        throw new \Exception('Failed to upload image to S3');
                                    }

                                    $value = $path;
                                    $type = 'image';
                                } elseif (! empty($request->options['value_color'][$index])) {
                                    $value = $request->options['value_color'][$index];
                                    $type = 'color';
                                }
                            }

                            $attribute->options()->create([
                                'key' => $key,
                                'option_value' => $value,
                                'type' => $type,
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Attribute created successfully!']);
        } catch (ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Failed to create attribute: '.$e->getMessage(), [
                'exception' => $e,
                'request_data' => $request->except(['options.value_file']),
            ]);

            return response()->json(['error' => 'Failed to create attribute. Please try again.'], 500);
        }
    }
}
