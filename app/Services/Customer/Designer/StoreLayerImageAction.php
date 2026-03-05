<?php

namespace App\Services\Customer\Designer;

use App\Models\Customer\Designer\VendorDesignLayerImage;
use Illuminate\Support\Facades\Storage;

class StoreLayerImageAction
{
    public function execute(
        int $templateId,
        int $layerId,
        int $productId,
        int $variantId,
        int $colorId,
        int $vendorId,
        string $imagePath
    ): VendorDesignLayerImage {
        $disk = config('filesystems.default');

        $existing = VendorDesignLayerImage::where([
            'template_id' => $templateId,
            'layer_id' => $layerId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'color_id' => $colorId,
            'vendor_id' => $vendorId,
        ])->first();

        if ($existing) {
            if (
                filled($existing->image) &&
                $existing->image !== $imagePath &&
                Storage::disk($disk)->exists($existing->image)
            ) {
                Storage::disk($disk)->delete($existing->image);
            }

            $existing->update([
                'image' => $imagePath,
            ]);

            return $existing;
        }

        /* =========================
         | Create new record
         ========================= */
        return VendorDesignLayerImage::create([
            'template_id' => $templateId,
            'layer_id' => $layerId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'color_id' => $colorId,
            'vendor_id' => $vendorId,
            'image' => $imagePath,
        ]);
    }
}
