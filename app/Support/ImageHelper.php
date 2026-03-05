<?php

use App\Services\Image\ImageService;

if (! function_exists('getImageUrl')) {
    /**
     * Get the URL for an image with optional thumbnail generation.
     *
     * @param string|null $filePath The path to the image file
     * @param bool $thumbnail Whether to return a thumbnail URL
     * @param array $options Image options (width, height, format, quality, disk, verify_exists)
     * @return string The image URL
     *
     * @example
     * // Get original image URL
     * $url = getImageUrl('products/image.jpg');
     *
     * // Get thumbnail URL (300x300, webp, quality 85)
     * $url = getImageUrl('products/image.jpg', true);
     *
     * // Get custom thumbnail
     * $url = getImageUrl('products/image.jpg', true, [
     *     'width' => 150,
     *     'height' => 150,
     *     'format' => 'jpg',
     *     'quality' => 90,
     *     'disk' => 's3'
     * ]);
     */
    function getImageUrl(?string $filePath, bool $thumbnail = false, array $options = []): string
    {
        static $imageService = null;

        $imageService ??= app(ImageService::class);

        return $imageService->getUrl($filePath, $thumbnail, $options);
    }
}
