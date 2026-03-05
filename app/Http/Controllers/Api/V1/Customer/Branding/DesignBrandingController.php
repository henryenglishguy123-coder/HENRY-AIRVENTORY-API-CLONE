<?php

namespace App\Http\Controllers\Api\V1\Customer\Branding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Branding\StoreVendorBrandingRequest;
use App\Models\Customer\Branding\VendorDesignBranding;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DesignBrandingController extends Controller
{
    protected int $cacheTtlSeconds;

    public function __construct()
    {
        $this->cacheTtlSeconds = (int) config('cache.branding_ttl', 300);
    }

    /**
     * Get all branding for the authenticated vendor with pagination
     */
    public function index(Request $request)
    {
        try {
            $vendorId = auth('customer')->id();
            $perPage = max(1, min((int) $request->get('per_page', 15), 100));
            $page = max(1, (int) $request->get('page', 1));
            $type = $request->get('type');

            Cache::add("vendor:{$vendorId}:branding:version", 1);

            $cacheKey = $this->cacheKey($vendorId, $page, $perPage, $type);

            $result = Cache::remember($cacheKey, $this->cacheTtlSeconds, function () use ($vendorId, $perPage, $page, $type) {
                $brandings = VendorDesignBranding::where('vendor_id', $vendorId)
                    ->when($type, function ($query) use ($type) {
                        $query->where('type', $type);
                    })
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage, ['*'], 'page', $page);

                $transformedData = $brandings->through(function ($branding) {
                    return [
                        'id' => $branding->id,
                        'name' => $branding->name,
                        'url' => Storage::url($branding->image),
                        'width' => $branding->width,
                        'height' => $branding->height,
                        'back_url' => $branding->image_back ? Storage::url($branding->image_back) : null,
                        'back_width' => $branding->width_back,
                        'back_height' => $branding->height_back,
                        'created_at' => $branding->created_at,
                    ];
                });

                $array = $transformedData->toArray();
                $array['items'] = $array['data'];
                unset($array['data']);

                return $array;
            });

            return response()->json([
                'success' => true,
                'data' => $result,
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Failed to fetch brandings: '.$e->getMessage(), [
                'vendor_id' => auth('customer')->id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('An error occurred while fetching the brandings.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreVendorBrandingRequest $request)
    {
        $file = $request->file('image');
        $backFile = $request->file('back_image');

        $extension = $file->guessExtension();
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = Str::slug($originalName).'-'.Str::uuid();
        $fileName = "{$safeName}.{$extension}";
        $folderPath = 'vendor/design-branding/'.now()->format('Y/m/d');
        $fullPath = "{$folderPath}/{$fileName}";
        $dimensions = $this->getImageDimensions($file);

        $backFullPath = null;
        $backDimensions = ['width' => null, 'height' => null];

        if ($backFile) {
            $backExtension = $backFile->guessExtension();
            $backOriginalName = pathinfo($backFile->getClientOriginalName(), PATHINFO_FILENAME);
            $backSafeName = Str::slug($backOriginalName).'-'.Str::uuid();
            $backFileName = "{$backSafeName}.{$backExtension}";
            $backFullPath = "{$folderPath}/{$backFileName}";
            $backDimensions = $this->getImageDimensions($backFile);
        }

        try {
            return DB::transaction(function () use ($request, $file, $backFile, $fullPath, $backFullPath, $folderPath, $dimensions, $backDimensions) {
                $uploadSuccessFront = Storage::putFileAs($folderPath, $file, basename($fullPath), ['Cache-Control' => 'public, max-age=604800']);
                if (! $uploadSuccessFront) {
                    throw new Exception(__('Failed to upload image to storage.'));
                }

                if ($backFile) {
                    $uploadSuccessBack = Storage::putFileAs($folderPath, $backFile, basename($backFullPath), ['Cache-Control' => 'public, max-age=604800']);
                    if (! $uploadSuccessBack) {
                        throw new Exception(__('Failed to upload back image to storage.'));
                    }
                }

                $branding = VendorDesignBranding::create([
                    'vendor_id' => $request->user('customer')->id,
                    'name' => $request->name,
                    'type' => $request->input('type', 'branding'),
                    'image' => $fullPath,
                    'width' => $dimensions['width'],
                    'height' => $dimensions['height'],
                    'image_back' => $backFullPath,
                    'width_back' => $backDimensions['width'],
                    'height_back' => $backDimensions['height'],
                ]);

                $this->clearVendorCache($request->user('customer')->id);

                return response()->json([
                    'success' => true,
                    'message' => __('Branding uploaded successfully.'),
                    'data' => [
                        'id' => $branding->id,
                        'name' => $branding->name,
                        'url' => Storage::url($fullPath),
                        'width' => $branding->width,
                        'height' => $branding->height,
                        'back_url' => $branding->image_back ? Storage::url($branding->image_back) : null,
                        'back_width' => $branding->width_back,
                        'back_height' => $branding->height_back,
                    ],
                ], Response::HTTP_CREATED);
            });
        } catch (Exception $e) {
            Log::error('Branding upload failed: '.$e->getMessage(), [
                'vendor_id' => $request->user('customer')->id,
                'trace' => $e->getTraceAsString(),
            ]);
            if (Storage::exists($fullPath)) {
                Storage::delete($fullPath);
            }
            if ($backFullPath && Storage::exists($backFullPath)) {
                Storage::delete($backFullPath);
            }

            return response()->json([
                'success' => false,
                'message' => __('An error occurred while uploading the branding.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function getImageDimensions($file): array
    {
        $width = null;
        $height = null;
        if (in_array(strtolower($file->guessExtension()), ['jpg', 'jpeg', 'png', 'webp'])) {
            try {
                [$width, $height] = getimagesize($file->getRealPath());
            } catch (Exception $e) {
                // Silently ignore if image dimensions cannot be determined
                // This is acceptable as dimensions are optional metadata
            }
        }

        return ['width' => $width, 'height' => $height];
    }

    /**
     * Delete branding (only owner can delete)
     */
    public function destroy($id)
    {
        try {
            $vendorId = auth('customer')->id();

            $branding = VendorDesignBranding::where('id', $id)
                ->where('vendor_id', $vendorId)
                ->first();

            if (! $branding) {
                return response()->json([
                    'success' => false,
                    'message' => __('Branding not found or you do not have permission to delete it.'),
                ], Response::HTTP_NOT_FOUND);
            }

            return DB::transaction(function () use ($branding, $vendorId) {
                if ($branding->image && Storage::exists($branding->image)) {
                    Storage::delete($branding->image);
                }

                if ($branding->image_back && Storage::exists($branding->image_back)) {
                    Storage::delete($branding->image_back);
                }

                $branding->delete();

                // Clear cache for this vendor
                $this->clearVendorCache($vendorId);

                return response()->json([
                    'success' => true,
                    'message' => __('Branding deleted successfully.'),
                ], Response::HTTP_OK);
            });
        } catch (Exception $e) {
            Log::error('Failed to delete branding: '.$e->getMessage(), [
                'vendor_id' => auth('customer')->id(),
                'branding_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('An error occurred while deleting the branding.'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate cache key for vendor branding list
     */
    protected function cacheKey($vendorId, $page, $perPage, $type = null): string
    {
        $version = Cache::get("vendor:{$vendorId}:branding:version", 1);

        $typeKey = $type ?: 'all';

        return sprintf('vendor:%d:branding:v%d:type:%s:page:%d:per_page:%d', $vendorId, $version, $typeKey, $page, $perPage);
    }

    /**
     * Clear all cache entries for a vendor's branding
     */
    protected function clearVendorCache($vendorId): void
    {
        Cache::increment("vendor:{$vendorId}:branding:version");
    }
}
