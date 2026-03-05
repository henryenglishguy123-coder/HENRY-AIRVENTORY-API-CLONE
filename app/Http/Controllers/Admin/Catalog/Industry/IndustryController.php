<?php

namespace App\Http\Controllers\Admin\Catalog\Industry;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Industry\CatalogIndustry;
use App\Models\Catalog\Industry\CatalogIndustryMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class IndustryController extends Controller
{
    public function index()
    {
        return view('admin.catalog.industry.index');
    }

    public function store(Request $request)
    {
        $industryId = (int) $request->input('id');
        $isEdit = $industryId > 0;
        $rules = [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:250',
                Rule::unique('catalog_industry_metas', 'name')
                    ->ignore($industryId, 'catalog_industry_id'),
            ],
            'status' => ['required', Rule::in([0, 1])],
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => __('Validation errors occurred.'),
                'errors' => $validator->errors(),
            ], 422);
        }
        try {
            DB::transaction(function () use ($request, $industryId, $isEdit) {
                $industry = new CatalogIndustry;
                if ($isEdit) {
                    $industry = CatalogIndustry::find($industryId);

                    if (! $industry) {
                        return response()->json([
                            'status' => false,
                            'message' => __('Industry not found.'),
                        ], 404);
                    }
                }
                $currentName = optional($industry->meta)->name;
                if (! $isEdit || $currentName !== $request->name) {
                    $slug = $this->generateSlug($request->name);
                    $industry->slug = $this->makeSlugUnique($slug, $industryId);
                    $industry->save();
                } elseif (! $industry->exists) {
                    $industry->save();
                }
                CatalogIndustryMeta::updateOrCreate(
                    ['catalog_industry_id' => $industry->id],
                    [
                        'name' => $request->name,
                        'status' => (int) $request->status,
                    ]
                );
            });

            return response()->json([
                'status' => true,
                'message' => $isEdit
                    ? __('Industry updated successfully!')
                    : __('Industry created successfully!'),
            ], $isEdit ? 200 : 201);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'message' => __('Something went wrong while processing the industry.'),
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    private function generateSlug($name)
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    }

    private function makeSlugUnique($slug, $excludeId = null)
    {
        $originalSlug = $slug;
        $count = 1;

        $query = CatalogIndustry::where('slug', $slug);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $originalSlug.'-'.$count;
            $query = CatalogIndustry::where('slug', $slug);

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            $count++;
        }

        return $slug;
    }

    public function bulkAction(Request $request)
    {
        $ids = $request->ids;
        $action = $request->action;

        switch ($action) {
            case 'enable':
                CatalogIndustryMeta::whereIn('catalog_industry_id', $ids)->update(['status' => 1]);
                $message = 'Selected industries have been enabled.';
                break;
            case 'disable':
                CatalogIndustryMeta::whereIn('catalog_industry_id', $ids)->update(['status' => 0]);
                $message = 'Selected industries have been disabled.';
                break;
            case 'delete':
                CatalogIndustry::whereIn('id', $ids)->delete();
                $message = 'Selected industries have been deleted.';
                break;
            default:
                return response()->json(['message' => 'Invalid action'], 400);
        }
        CatalogIndustry::flushIndustryCache();

        return response()->json(['message' => $message]);
    }

    public function checkCategoryCount(Request $request)
    {
        $ids = $request->ids;
        $industries = CatalogIndustry::whereIn('id', $ids)->get();

        foreach ($industries as $industry) {
            if ($industry->category->count() > 0) {
                return response()->json(['canProceed' => false]);
            }
        }

        return response()->json(['canProceed' => true]);
    }
}
