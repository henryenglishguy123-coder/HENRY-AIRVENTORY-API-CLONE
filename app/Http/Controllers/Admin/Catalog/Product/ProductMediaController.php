<?php

namespace App\Http\Controllers\Admin\Catalog\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Catalog\Product\ProductMediaUploadRequest;
use App\Models\Catalog\Product\CatalogProductFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ProductMediaController extends Controller
{
    public function uploadFiles(ProductMediaUploadRequest $request)
    {
        $cacheLifetimeSeconds = 31536000;
        try {

            $file = $request->file('file');
            $directory = 'catalog/product/'.date('Y/m').'/'.uniqid();
            $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $filePath = $directory.'/'.$filename;
            $options = ['CacheControl' => 'max-age='.$cacheLifetimeSeconds.', public, immutable'];
            $uploadedPath = Storage::putFileAs($directory, $file, $filename, $options);
            if ($uploadedPath) {
                $url = Storage::url($filePath);

                return response()->json([
                    'name' => $filename,
                    'path' => $url,
                ], Response::HTTP_OK);
            }

            return response()->json([
                'message' => 'File upload failed. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Server error during upload: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function removeFile(Request $request)
    {
        $filenameUrl = $request->input('filename');
        if (empty($filenameUrl)) {
            return response()->json(['message' => 'File path/URL is required.'], Response::HTTP_BAD_REQUEST);
        }
        try {
            $filePath = str_replace(Storage::url('/'), '', $filenameUrl);
            $fileDeleted = false;
            if (Storage::exists($filePath)) {
                Storage::delete($filePath);
                $fileDeleted = true;
            }
            $dbRecordsDeleted = CatalogProductFile::where('image', $filePath)->delete();
            if ($fileDeleted || $dbRecordsDeleted) {
                return response()->json([
                    'success' => true,
                    'message' => __('Media item and associated records removed successfully!'),
                ], Response::HTTP_OK);
            }

            return response()->json([
                'success' => false,
                'message' => __('Media item not found in storage or database.'),
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error during removal: '.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
