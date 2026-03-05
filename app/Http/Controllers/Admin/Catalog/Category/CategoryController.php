<?php

namespace App\Http\Controllers\Admin\Catalog\Category;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Category\CatalogCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class CategoryController extends Controller
{
    public function index()
    {
        return view('admin.catalog.category.index');
    }

    public function getCategoryData(Request $request)
    {
        $query = CatalogCategory::with(['meta', 'parent.meta', 'industry.meta'])
            ->leftJoin('catalog_category_metas as cm', 'catalog_categories.id', '=', 'cm.catalog_category_id')
            ->leftJoin('catalog_industry_metas as ci', 'catalog_categories.catalog_industry_id', '=', 'ci.catalog_industry_id')
            ->select([
                'catalog_categories.id',
                'catalog_categories.parent_id',
                'catalog_categories.catalog_industry_id',
                'catalog_categories.created_at',
                DB::raw("COALESCE(cm.name, '') as categoryName"),
                DB::raw("COALESCE(ci.name, '') as industry_name"),
            ]);

        return DataTables::of($query)
            ->filter(function ($query) use ($request) {
                if ($keyword = $request->get('search')['value']) {
                    $query->where(function ($q) use ($keyword) {
                        $q->where('cm.name', 'like', "%{$keyword}%")
                            ->orWhere('ci.name', 'like', "%{$keyword}%");
                    });
                }
            })
            ->addColumn('select_id', fn ($category) => $category->id)
            ->addColumn('categoryName', function ($category) {
                return $this->getCategoryNameHierarchy($category);
            })
            ->addColumn('industry_name', function ($category) {
                return $category->industry->meta->name ?? '-';
            })
            ->addColumn('image', function ($category) {
                $image = $category->meta->image;
                $imagePath = getImageUrl($image, true, ['width' => 50, 'height' => 50, 'quality' => '100']);
                $altText = $category->meta->name ?? 'No Image';

                return '<img src="'.htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8').'" alt="'.htmlspecialchars($altText, ENT_QUOTES, 'UTF-8').'" class="img-thumbnail" style="width: 50px; height: 50px;">';
            })
            ->editColumn('status', function ($row) {
                $status = $row->meta && $row->meta->status ? 'Enable' : 'Disable';
                $statusClass = $row->meta && $row->meta->status ? 'bg-success' : 'bg-secondary';

                return '<span class="badge rounded-pill '.$statusClass.'" disabled>'.$status.'</span>';
            })
            ->editColumn('created_at', function ($row) {
                return formatDateTime($row->created_at);
            })
            ->addColumn('createdDate', fn ($category) => $category->created_at ? $category->created_at->format('Y-m-d') : '-')
            ->addColumn('action', function ($category) {
                return '
                    <div class="btn-group g-2" role="group">
                        <a href="'.route('admin.catalog.categories.edit', $category->id).'" class="btn btn-black btn-sm">
                            <i class="mdi mdi-pencil"></i>
                        </a>
                    </div>';
            })
            ->orderColumn('status', 'cm.status $1')
            ->orderColumn('categoryName', 'cm.name $1')
            ->orderColumn('industry_name', 'ci.name $1')
            ->orderColumn('created_at', 'catalog_categories.created_at $1')
            ->rawColumns(['image', 'status', 'action'])
            ->make(true);
    }

    public function getCategoryNameHierarchy($category, $separator = ' > ', $maxDepth = 5, $visited = [])
    {
        if (! $category) {
            return '-';
        }
        $id = $category->id ?? null;
        if ($id && in_array($id, $visited)) {
            return $category->meta->name.' (circular)';
        }
        if ($maxDepth <= 0) {
            return $category->meta->name ?? '-';
        }
        $visited[] = $id;
        $categoryName = $category->meta->name ?? '-';
        if ($category->parent) {
            $parentName = $this->getCategoryNameHierarchy($category->parent, $separator, $maxDepth - 1, $visited);

            return $parentName.$separator.$categoryName;
        }

        return $categoryName;
    }
}
