<?php

namespace App\Http\Controllers\Api\V1\Catalog\Designer;

use App\Http\Controllers\Api\V1\Customer\Gallery\CustomerMediaGalleryController;
use App\Models\Customer\Gallery\VendorMediaGallery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProductDesignerImageController extends CustomerMediaGalleryController
{
    private const BASE_PATH = 'catalog/product/designer/images';

    private const MAX_SIZE_KB = 51200;

    private const DEFAULT_FOLDER = 'default';

    private const CACHE_MAX_AGE_SECONDS = 604800;

    public function uploadImage(Request $request): JsonResponse
    {
        $customer = Auth::guard('customer')->user();
        $validated = $request->validate([
            'image' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png',
                'max:'.self::MAX_SIZE_KB,
            ],
        ]);
        try {
            $file = $validated['image'];
            $customerId = $customer->id ?? '';
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $safeName = Str::slug($originalName) ?: 'image';
            $fileName = "{$safeName}-".Str::uuid().'.'.$file->getClientOriginalExtension();
            if ($customer) {
                $folder = self::BASE_PATH
                    ."/vendor/{$customer->id}/"
                    .now()->format('Y/m/d');
            } else {
                $folder = self::BASE_PATH
                    .'/'.self::DEFAULT_FOLDER
                    .'/'.now()->format('Y/m/d');
            }

            $path = Storage::putFileAs(
                $folder,
                $file,
                $fileName,
                [
                    'Cache-Control' => 'public, max-age='.self::CACHE_MAX_AGE_SECONDS,
                ]
            );

            if (! $path) {
                throw new \RuntimeException('Upload failed');
            }

            if ($customer) {
                VendorMediaGallery::create([
                    'vendor_id' => $customer->id,
                    'image_path' => $path,
                    'original_name' => $originalName,
                    'extension' => $file->getClientOriginalExtension(),
                ]);

                $this->bumpCacheVersion($customer->id);
            }

            return response()->json([
                'success' => true,
                'message' => __('Designer image uploaded successfully.'),
                'data' => [
                    'path' => $path,
                    'image_url' => getImageUrl($path),
                    'is_guest' => ! $customer,
                ],
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => __('Something went wrong while uploading the image.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
