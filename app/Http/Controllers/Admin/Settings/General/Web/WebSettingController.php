<?php

namespace App\Http\Controllers\Admin\Settings\General\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\Web\WebSettingUpdateRequest;
use App\Models\Admin\Store\Store;
use App\Models\Admin\Store\StoreMeta;
use App\Models\Location\Country;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class WebSettingController extends Controller
{
    private const CACHE_KEY_STORE = Store::PANEL_CONFIG_CACHE_KEY;

    private const CACHE_KEY_META = StoreMeta::CACHE_KEY;

    private const CACHE_TTL = 86400;

    public function index()
    {
        $store = Cache::remember(self::CACHE_KEY_STORE, self::CACHE_TTL, fn () => Store::first());

        $store_meta = Cache::remember(self::CACHE_KEY_META, self::CACHE_TTL, fn () => StoreMeta::where('type', 'web')->pluck('value', 'key')->toArray());

        return view('admin.settings.general.web', compact('store', 'store_meta'));
    }

    public function update(WebSettingUpdateRequest $request): JsonResponse
    {
        try {
            DB::transaction(function () use ($request): void {
                /** @var Store $store */
                $store = Store::query()->firstOrFail();
                $store->fill([
                    'store_name' => $request->store_name,
                    'mobile' => $request->mobile,
                    'meta_title' => $request->meta_title,
                    'meta_description' => $request->meta_description,
                ]);
                $this->handleUploads($request, $store);
                $this->handleDefaultCountry($request->input('default_country_id'));
                $this->handleAllowedCountries($request->input('allowed_country_id', []));
                $store->save();
                Cache::forget(self::CACHE_KEY_STORE);
                Cache::forget(self::CACHE_KEY_META);
            });

            return response()->json([
                'success' => true,
                'message' => __('Web settings updated successfully.'),
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('An error occurred while updating web settings.'),
                'error' => app()->hasDebugModeEnabled() ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function handleUploads(WebSettingUpdateRequest $request, Store $store): void
    {
        $uploadFields = [
            'store_logo' => 'icon',
            'store_favicon' => 'favicon',
        ];
        foreach ($uploadFields as $input => $column) {
            if (! $request->hasFile($input)) {
                continue;
            }
            /** @var UploadedFile $file */
            $file = $request->file($input);
            $store->$column = $this->uploadToS3(
                file: $file,
                oldFilePath: $store->$column ?? null,
                prefix: $column === 'favicon' ? 'favicon_' : 'logo_'
            );
        }
    }

    private function handleDefaultCountry(?int $defaultCountryId): void
    {
        if (! $defaultCountryId) {
            return;
        }
        Country::query()->where('is_default', 1)->update(['is_default' => 0]);
        $defaultCountry = Country::query()->find($defaultCountryId);
        if ($defaultCountry) {
            $defaultCountry->is_default = 1;
            $defaultCountry->save();
        }
        Country::clearCache();
    }

    private function handleAllowedCountries($allowedCountryIds): void
    {
        if (! $allowedCountryIds) {
            return;
        }
        $ids = (array) $allowedCountryIds;
        Country::query()->where('is_allowed', 1)->update(['is_allowed' => 0]);
        Country::query()->whereIn('id', $ids)->update(['is_allowed' => 1]);
        Country::clearCache();
    }

    /**
     * Upload a file to S3 with auto-delete old file
     */
    private function uploadToS3(UploadedFile $file, ?string $oldFilePath = null, string $prefix = ''): string
    {
        $directory = 'public/setting/web_setting';
        $filename = $prefix.now()->timestamp.'_'.uniqid().'.'.$file->getClientOriginalExtension();
        $newPath = Storage::putFileAs($directory, $file, $filename);
        if ($oldFilePath && Storage::exists($oldFilePath)) {
            Storage::delete($oldFilePath);
        }

        return $newPath;
    }
}
