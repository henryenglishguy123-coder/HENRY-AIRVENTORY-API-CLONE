<?php

namespace App\Http\Controllers\Admin\Catalog\Category;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Category\CatalogCategory;
use App\Models\Catalog\Industry\CatalogIndustry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UpdateCategory extends Controller
{
    public function update(Request $request)
    {

        $category_id = $request->input('category_id');
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:catalog_categories,id',
            'image' => 'nullable|image|max:10240',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'status' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();
        $category = CatalogCategory::with('meta')->findOrFail($category_id);
        $category->update([
            'parent_id' => $validated['parent_id'],
        ]);

        $metaData = [
            'catalog_category_id' => $category->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? $category->meta->description,
            'status' => $validated['status'] ?? $category->meta->status,
            'meta_title' => $validated['meta_title'] ?? $category->meta->meta_title,
            'meta_description' => $validated['meta_description'] ?? $category->meta->meta_description,
        ];

        if ($request->hasFile('image')) {
            if ($category->meta && $category->meta->image) {
                $filePath = str_replace(Storage::url('/'), '', $category->meta->image);
                if (Storage::exists($filePath)) {
                    Storage::delete($filePath);
                }
            }

            $file = $request->file('image');
            $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $filePath = 'catalog/category/'.$filename;

            Storage::put($filePath, file_get_contents($file));
            $metaData['image'] = $filePath;
        }

        $category->meta()->updateOrCreate(['catalog_category_id' => $category->id], $metaData);

        return response()->json(['success' => true, 'message' => 'Category updated successfully!']);
    }

    public function edit($id)
    {
        $industries = CatalogIndustry::with('meta')->get();
        $category = CatalogCategory::with('meta', 'industry.meta', 'children.meta')->findOrFail($id);

        $categories = CatalogCategory::with('meta', 'children.meta')
            ->where('catalog_industry_id', $category->industry->id)
            ->where('id', '!=', $id)
            ->get();

        return view('admin.catalog.category.edit', compact('category', 'industries'));
    }
}
