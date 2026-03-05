<?php

namespace App\Http\Controllers\Admin\Catalog\Category;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Category\CatalogCategory;
use App\Models\Catalog\Industry\CatalogIndustry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AddCategory extends Controller
{
    public function create()
    {
        $industries = CatalogIndustry::with('meta')->get();
        $parentCategories = [];

        return view('admin.catalog.category.create', compact('parentCategories', 'industries'));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:catalog_categories,id',
            'image' => 'nullable|image|max:10240',
            'description' => 'nullable|string',
            'status' => 'boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'industry_id' => 'required|integer',
        ]);
        $categoryData = [
            'parent_id' => $validatedData['parent_id'] ?? null,
            'slug' => $this->generateUniqueSlug($validatedData['slug']),
            'catalog_industry_id' => $validatedData['industry_id'] ?? null,
        ];
        $metaData = [
            'name' => $validatedData['name'],
            'description' => $validatedData['description'] ?? null,
            'status' => $request->boolean('status', false),
            'meta_title' => $validatedData['meta_title'] ?? null,
            'meta_description' => $validatedData['meta_description'] ?? null,
        ];

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $filePath = 'catalog/category/'.$filename;

            Storage::put($filePath, file_get_contents($file));
            $metaData['image'] = $filePath;
        }
        try {
            DB::transaction(function () use ($categoryData, $metaData) {
                $category = CatalogCategory::create($categoryData);
                $category->meta()->create($metaData);
            });

            return redirect()
                ->route('admin.catalog.categories.index')
                ->with('success', 'Category created successfully.');
        } catch (\Throwable $e) {
            if (! empty($metaData['image']) && Storage::exists($metaData['image'])) {
                Storage::delete($metaData['image']);
            }

            return redirect()
                ->back()
                ->withErrors('Something went wrong while creating the category.')
                ->withInput();
        }
    }

    private function generateUniqueSlug($slug, $ignoreId = null)
    {
        $originalSlug = Str::slug($slug);
        $uniqueSlug = $originalSlug;
        $count = 1;
        $maxAttempts = 100;
        while (
            CatalogCategory::where('slug', $uniqueSlug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            if ($count > $maxAttempts) {
                throw new \RuntimeException('Unique slug generation failed after multiple attempts');
            }
            $uniqueSlug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $uniqueSlug;
    }
}
