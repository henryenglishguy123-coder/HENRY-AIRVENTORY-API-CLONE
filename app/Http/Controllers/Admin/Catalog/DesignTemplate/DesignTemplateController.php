<?php

namespace App\Http\Controllers\Admin\Catalog\DesignTemplate;

use App\Http\Controllers\Controller;
use App\Models\Catalog\DesignTemplate\CatalogDesignTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class DesignTemplateController extends Controller
{
    public function designTemplate()
    {
        return view('admin.catalog.design-template.index');
    }

    public function getDesignTemplateData(Request $request)
    {
        $query = CatalogDesignTemplate::select('id', 'name', 'status', 'created_at')->with([
            'layers' => function ($q) {
                $q->select('id', 'catalog_design_template_id', 'layer_name');
            },
        ])
            ->orderBy('created_at', 'desc');

        return DataTables::of($query)
            ->addColumn('actions', function ($row) {
                return view('admin.catalog.design-template.partials._action', compact('row'))->render();
            })
            ->addColumn('template-layers', function ($row) {
                return view('admin.catalog.design-template.partials._template-layers', compact('row'))->render();
            })
            ->rawColumns(['actions', 'template-layers'])
            ->make(true);
    }

    public function uploadMockup(Request $request)
    {
        $validated = $this->validateMockup($request);

        $path = $this->storeSvgMockup($validated['file']);

        return response()->json([
            'success' => true,
            'url' => Storage::url($path),
            'path' => $path, // useful for backend save later
        ], Response::HTTP_OK);
    }

    protected function validateMockup(Request $request): array
    {
        return $request->validate([
            'file' => [
                'required',
                'file',
                'mimetypes:image/svg+xml',
                'max:5120', // 5MB
            ],
        ]);
    }

    protected function storeSvgMockup(\Illuminate\Http\UploadedFile $file): string
    {
        $directory = 'catalog/design-template';
        $filename = sprintf('mockup_%s.svg', now()->format('YmdHis').'_'.uniqid());
        $path = $directory.'/'.$filename;
        Storage::put($path, $this->sanitizeSvg($file), $this->svgHeaders());

        return $path;
    }

    protected function sanitizeSvg(\Illuminate\Http\UploadedFile $file): string
    {
        $content = file_get_contents($file->getRealPath());
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
        $content = preg_replace('/<foreignObject\b[^>]*>(.*?)<\/foreignObject>/is', '', $content);

        return $content;
    }

    protected function svgHeaders(): array
    {
        return [
            'ContentType' => 'image/svg+xml',
            'CacheControl' => 'public, max-age=31536000, immutable',
            'Expires' => gmdate('D, d M Y H:i:s T', strtotime('+1 year')),
        ];
    }

    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:delete'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:catalog_design_template,id'],
        ]);
        try {
            DB::transaction(function () use ($validated) {
                match ($validated['action']) {
                    'delete' => $this->bulkDelete($validated['ids']),
                };
            });

            return response()->json([
                'success' => true,
                'message' => __('Templates deleted successfully.'),
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    protected function bulkDelete(array $ids): void
    {
        CatalogDesignTemplate::whereIn('id', $ids)->delete();
    }
}
