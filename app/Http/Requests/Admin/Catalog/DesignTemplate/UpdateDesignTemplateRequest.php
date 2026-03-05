<?php

namespace App\Http\Requests\Admin\Catalog\DesignTemplate;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDesignTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'templateName' => ['required', 'string', 'max:255'],
            'templateStatus' => ['required', 'boolean'],

            'layers' => ['required', 'array', 'min:1'],

            'layers.*.id' => ['nullable', 'numeric'],
            'layers.*.layerName' => ['required', 'string'],

            'layers.*.coordinates' => ['required', 'array'],
            'layers.*.coordinates.left' => ['required', 'numeric'],
            'layers.*.coordinates.top' => ['required', 'numeric'],
            'layers.*.coordinates.width' => ['required', 'numeric'],
            'layers.*.coordinates.height' => ['required', 'numeric'],
            'layers.*.coordinates.scaleX' => ['required', 'numeric'],
            'layers.*.coordinates.scaleY' => ['required', 'numeric'],

            'layers.*.image' => ['required', 'string'],
            'layers.*.is_neck_layer' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $neckLayerCount = collect($this->layers)
                ->where('is_neck_layer', true)
                ->count();

            if ($neckLayerCount > 1) {
                $validator->errors()->add('layers', __('Only one neck layer is allowed per template.'));
            }
        });
    }

    /* ---------------------------------------------
    | Custom Validation Messages
    |----------------------------------------------*/
    public function messages(): array
    {
        return [
            'templateName.required' => __('Template name is required.'),
            'templateName.string' => __('Template name must be a valid string.'),
            'templateName.max' => __('Template name may not be greater than 255 characters.'),

            'templateStatus.required' => __('Template status is required.'),
            'templateStatus.boolean' => __('Template status must be active or inactive.'),

            'layers.required' => __('At least one design layer is required.'),
            'layers.array' => __('Layers must be provided as a valid list.'),
            'layers.min' => __('Please add at least one design layer.'),

            'layers.*.layerName.required' => __('Each layer must have a layer name.'),

            'layers.*.coordinates.required' => __('Layer coordinates are required.'),
            'layers.*.coordinates.array' => __('Layer coordinates must be a valid object.'),

            'layers.*.coordinates.left.required' => __('Layer left position is required.'),
            'layers.*.coordinates.top.required' => __('Layer top position is required.'),
            'layers.*.coordinates.width.required' => __('Layer width is required.'),
            'layers.*.coordinates.height.required' => __('Layer height is required.'),
            'layers.*.coordinates.scaleX.required' => __('Layer scaleX value is required.'),
            'layers.*.coordinates.scaleY.required' => __('Layer scaleY value is required.'),

            'layers.*.coordinates.*.numeric' => __('All layer coordinate values must be numeric.'),

            'layers.*.image.required' => __('Layer image is required.'),
            'layers.*.image.string' => __('Layer image must be a valid string path.'),
        ];
    }

    /* ---------------------------------------------
    | Friendly Attribute Names
    |----------------------------------------------*/
    public function attributes(): array
    {
        return [
            'templateName' => __('Template name'),
            'templateStatus' => __('Template status'),

            'layers' => __('design layers'),

            'layers.*.id' => __('layer ID'),
            'layers.*.layerName' => __('layer name'),

            'layers.*.coordinates.left' => __('layer left position'),
            'layers.*.coordinates.top' => __('layer top position'),
            'layers.*.coordinates.width' => __('layer width'),
            'layers.*.coordinates.height' => __('layer height'),
            'layers.*.coordinates.scaleX' => __('layer horizontal scale'),
            'layers.*.coordinates.scaleY' => __('layer vertical scale'),

            'layers.*.image' => __('layer image'),
        ];
    }
}
