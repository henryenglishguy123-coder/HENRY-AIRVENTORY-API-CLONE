<?php

namespace App\Http\Controllers\Admin\Catalog\Attributes;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Attribute\CatalogAttribute;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class AttributeController extends Controller
{
    public function index()
    {
        return view('admin.catalog.attribute.index');
    }

    public function getAttributeData(Request $request)
    {
        if ($request->ajax()) {
            $attributes = CatalogAttribute::select('*');

            return DataTables::of($attributes)
                ->addColumn('select_id', fn ($attribute) => $attribute->attribute_id)
                ->addColumn('actions', function ($attribute) {
                    $url = route('admin.catalog.attributes.edit', $attribute->attribute_id);

                    return sprintf(
                        '<a href="%s" class="btn btn-sm btn-primary">Edit</a>',
                        e($url)
                    );
                })
                ->editColumn('status', function ($row) {
                    $status = $row->status ? 'Enable' : 'Disable';
                    $statusClass = $row->status ? 'bg-success' : 'bg-secondary';

                    return '<span class="badge rounded-pill '.$statusClass.'">'.$status.'</span>';
                })
                ->rawColumns(['actions', 'status'])
                ->make(true);
        }

        return response()->json(['error' => 'Invalid request'], 400);
    }
}
