<?php
declare(strict_types=1);

namespace App\Services\Image;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use Intervention\Image\ImageManager;

class ImageService
{
    public const CACHE_TTL = 7; // days
    public const FALLBACK_CACHE_TTL = 5; // minutes – used when returning original as thumbnail fallback
    public const FALLBACK_CACHE_KEY = 'img:fallback:svg';
    public const THUMBNAIL_CACHE_PREFIX = 'img:thumb';
    public const THUMBNAIL_LOCK_PREFIX = 'img:lock';

    private ?bool $cacheTagsSupported = null;
    private array $resolvedDisks = [];

    public function __construct(
        private readonly ImageManager $imageManager
    ) {}

    /**
     * Get the URL for an image, optionally generating a thumbnail.
     */
    public function getUrl(
        ?string $filePath,
        bool $thumbnail = false,
        array $options = []
    ): string {
        if (blank($filePath)) {
            return $this->getFallbackImageUrl();
        }

        $originalPath = ltrim($filePath, '/');

        if (str_starts_with($originalPath, 'http')) {
            return $originalPath;
        }

        if (!$thumbnail) {
            return $this->getOriginalImageUrl($originalPath, $options);
        }

        return $this->getThumbnailUrl($originalPath, $options);
    }

    /**
     * Get the URL for the original image.
     */
    public function getOriginalImageUrl(string $path, array $options = []): string
    {
        $disk = $options['disk'] ?? 's3';
        $filesystem = $this->getDisk($disk);

        $cacheTag = $this->getThumbnailCacheTag($disk, $path);
        $cacheKey = sprintf('%s:orig:%s:%s', self::THUMBNAIL_CACHE_PREFIX, $disk, md5($path));

        $cached = $this->getCacheWithTagFallback($cacheTag, $cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // S3 HEAD requests are expensive; default to direct URL generation on S3.
        if (! $this->shouldVerifyExists($options, $disk)) {
            $url = $filesystem->url($path);
            $this->putCacheWithTagFallback($cacheTag, $cacheKey, $url, now()->addDays(self::CACHE_TTL));

            return $url;
        }

        if (! $filesystem->exists($path)) {
            Log::warning('Image not found when generating original image URL', [
                'path' => $path,
                'disk' => $disk,
            ]);

            $url = $this->getFallbackImageUrl();
            $ttl = now()->addMinutes(self::FALLBACK_CACHE_TTL);
        } else {
            $url = $filesystem->url($path);
            $ttl = now()->addDays(self::CACHE_TTL);
        }

        $this->putCacheWithTagFallback($cacheTag, $cacheKey, $url, $ttl);

        return $url;
    }

    /**
     * Get or generate thumbnail URL.
     * Thumbnails are generated asynchronously but URLs are returned immediately.
     */
    public function getThumbnailUrl(string $originalPath, array $options = []): string
    {
        $width = isset($options['width']) ? (int) $options['width'] : 300;
        $height = isset($options['height']) ? (int) $options['height'] : null;
        $format = strtolower((string) ($options['format'] ?? 'webp'));
        $quality = isset($options['quality']) ? (int) $options['quality'] : 85;
        $disk = $options['disk'] ?? 's3';
        $filesystem = $this->getDisk($disk);

        $thumbPath = $this->buildThumbnailPath($originalPath, $width, $height, $format, $quality);
        $cacheKey = $this->getCacheKey($disk, $originalPath, $width, $height, $format, $quality);
        $cacheTag = $this->getThumbnailCacheTag($disk, $originalPath);

        $cached = $this->getCacheWithTagFallback($cacheTag, $cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // If thumbnail exists, return its URL with full TTL
        if ($filesystem->exists($thumbPath)) {
            $url = $filesystem->url($thumbPath);
            $this->putCacheWithTagFallback($cacheTag, $cacheKey, $url, now()->addDays(self::CACHE_TTL));

            return $url;
        }

        // Queue async thumbnail generation.
        // When existence verification is disabled, generation job will validate source availability.
        if (! $this->shouldVerifyExists($options, $disk) || $filesystem->exists($originalPath)) {
            $this->queueThumbnailGeneration($originalPath, $options);
        } else {
            Log::warning("Image not found for thumbnail generation", [
                'path' => $originalPath,
                'disk' => $disk,
            ]);

            $url = $this->getFallbackImageUrl();
            $this->putCacheWithTagFallback($cacheTag, $cacheKey, $url, now()->addMinutes(self::FALLBACK_CACHE_TTL));

            return $url;
        }

        // Return original image URL as fallback with short TTL so thumbnail is served soon after generation
        $url = $filesystem->url($originalPath);
        $this->putCacheWithTagFallback($cacheTag, $cacheKey, $url, now()->addMinutes(self::FALLBACK_CACHE_TTL));

        return $url;
    }

    /**
     * Generate thumbnail synchronously.
     * This should only be called from queued jobs to avoid blocking requests.
     */
    public function generateThumbnail(
        string $originalPath,
        array $options = []
    ): bool {
        try {
            $disk = $options['disk'] ?? 's3';
            $width = isset($options['width']) ? (int) $options['width'] : 300;
            $height = isset($options['height']) ? (int) $options['height'] : null;
            $format = strtolower((string) ($options['format'] ?? 'webp'));
            $quality = isset($options['quality']) ? (int) $options['quality'] : 85;
            $filesystem = $this->getDisk($disk);

            $thumbPath = $this->buildThumbnailPath($originalPath, $width, $height, $format, $quality);

            // Use cache lock to prevent concurrent generation of same thumbnail
            $lockKey = $this->getLockKey($originalPath, $width, $height, $format, $quality);
            return Cache::lock($lockKey, 30)->block(30, function () use (
                $disk,
                $filesystem,
                $originalPath,
                $thumbPath,
                $width,
                $height,
                $format,
                $quality
            ) {
                // Check again if thumbnail exists (race condition protection)
                if ($filesystem->exists($thumbPath)) {
                    return true;
                }

                if (! $filesystem->exists($originalPath)) {
                    Log::warning("Original image file not found during thumbnail generation", [
                        'path' => $originalPath,
                        'disk' => $disk,
                    ]);

                    return false;
                }

                $image = $this->imageManager
                    ->read($filesystem->get($originalPath))
                    ->scale(width: $width, height: $height);

                $encoded = match ($format) {
                    'webp' => $image->toWebp(quality: $quality),
                    'jpg', 'jpeg' => $image->toJpeg(quality: $quality),
                    'png' => $image->toPng(),
                    default => throw new \InvalidArgumentException("Unsupported format: {$format}")
                };

                $filesystem->put($thumbPath, (string) $encoded);

                // Clear the URL cache so next request gets the thumbnail URL directly
                $cacheTag = $this->getThumbnailCacheTag($disk, $originalPath);
                $cacheKey = $this->getCacheKey($disk, $originalPath, $width, $height, $format, $quality);
                $this->forgetCacheWithTagFallback($cacheTag, $cacheKey);

                Log::info("Thumbnail generated successfully", [
                    'original' => $originalPath,
                    'thumbnail' => $thumbPath,
                    'size' => strlen((string) $encoded),
                ]);

                return true;
            });
        } catch (\Throwable $e) {
            Log::error("Thumbnail generation failed", [
                'path' => $originalPath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Queue thumbnail generation as a background job.
     */
    public function queueThumbnailGeneration(string $originalPath, array $options = []): void
    {
        $pendingDispatch = dispatch(new \App\Jobs\GenerateThumbnailJob($originalPath, $options))
            ->onQueue('images');

        $delaySeconds = array_key_exists('delay', $options) ? (int) $options['delay'] : 2;

        if ($delaySeconds > 0) {
            $pendingDispatch->delay(now()->addSeconds($delaySeconds));
        }
    }

    /**
     * Get the fallback image URL.
     */
    public function getFallbackImageUrl(): string
    {
        return Cache::rememberForever(self::FALLBACK_CACHE_KEY, function () {
            return $this->generateFallbackSvg();
        });
    }

    /**
     * Generate the fallback SVG image.
     */
    private function generateFallbackSvg(): string
    {
        $fallbackSvg = <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
<rect width="24" height="24" fill="white"/>
<path d="M21 16V20C21 20.5523 20.5523 21 20 21H4C3.44772 21 3 20.5523 3 20V18M21 16V4C21 3.44772 20.5523 3 20 3H4C3.44772 3 3 3.44772 3 4V18M21 16L15.4829 12.3219C15.1843 12.1228 14.8019 12.099 14.4809 12.2595L3 18" stroke="#000000" stroke-linejoin="round"/>
<circle cx="8" cy="9" r="2" stroke="#000000" stroke-linejoin="round"/>
</svg>
SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($fallbackSvg);
    }

    /**
     * Build the thumbnail file path.
     */
    public function buildThumbnailPath(
        string $path,
        int $width,
        ?int $height,
        string $format,
        int $quality
    ): string {
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $directory = dirname($path);

        $suffix = "{$width}px";
        if ($height) {
            $suffix .= "x{$height}px";
        }

        return "{$directory}/{$filename}_{$suffix}_q{$quality}.{$format}";
    }

    /**
     * Generate cache key for URL caching.
     */
    private function getCacheKey(
        string $disk,
        string $originalPath,
        int $width,
        ?int $height,
        string $format,
        int $quality
    ): string {
        return sprintf(
            '%s:%s:%s:%s:%s:%s:%s',
            self::THUMBNAIL_CACHE_PREFIX,
            $disk,
            md5($originalPath),
            $width,
            $height ?? 'auto',
            $format,
            $quality
        );
    }

    /**
     * Generate lock key for race condition prevention.
     */
    private function getLockKey(
        string $originalPath,
        int $width,
        ?int $height,
        string $format,
        int $quality
    ): string {
        return sprintf(
            '%s:%s:%s:%s:%s:%s',
            self::THUMBNAIL_LOCK_PREFIX,
            md5($originalPath),
            $width,
            $height ?? 'auto',
            $format,
            $quality
        );
    }

    /**
     * Invalidate thumbnail cache for a given image.
     */
    public function invalidateThumbnailCache(string $originalPath, string $disk = 's3'): void
    {
        $tag = $this->getThumbnailCacheTag($disk, $originalPath);

        if (! $this->supportsCacheTags()) {
            // The configured cache store does not support tags. Log a warning so
            // this can be addressed in configuration or caching strategy.
            Log::warning('Thumbnail cache invalidation via tags is not supported by the current cache store.', [
                'path' => $originalPath,
                'disk' => $disk,
                'tag' => $tag,
            ]);

            return;
        }

        Cache::tags([$tag])->flush();

        Log::info('Thumbnail cache invalidated', [
            'path' => $originalPath,
            'disk' => $disk,
            'tag' => $tag,
        ]);
    }

    /**
     * Build a cache tag that groups all thumbnail cache entries for one source image.
     */
    private function getThumbnailCacheTag(string $disk, string $originalPath): string
    {
        return sprintf('%s:%s:%s', self::THUMBNAIL_CACHE_PREFIX, $disk, md5($originalPath));
    }

    /**
     * Retrieve a value from the cache, using tags when supported.
     */
    private function getCacheWithTagFallback(string $tag, string $key): ?string
    {
        if ($this->supportsCacheTags()) {
            return Cache::tags([$tag])->get($key);
        }

        return Cache::get($key);
    }

    /**
     * Store a value in the cache, using tags when supported.
     */
    private function putCacheWithTagFallback(string $tag, string $key, string $value, \DateTimeInterface|\DateInterval|int $ttl): void
    {
        if ($this->supportsCacheTags()) {
            Cache::tags([$tag])->put($key, $value, $ttl);

            return;
        }

        Cache::put($key, $value, $ttl);
    }

    /**
     * Remove a single entry from the cache, using tags when supported.
     */
    private function forgetCacheWithTagFallback(string $tag, string $key): void
    {
        if ($this->supportsCacheTags()) {
            Cache::tags([$tag])->forget($key);

            return;
        }

        Cache::forget($key);
    }

    /**
     * S3 existence checks are optional because they trigger slow HEAD requests.
     */
    private function shouldVerifyExists(array $options, string $disk): bool
    {
        if (array_key_exists('verify_exists', $options)) {
            return (bool) $options['verify_exists'];
        }

        return $disk !== 's3';
    }

    private function getDisk(string $disk): FilesystemAdapter
    {
        return $this->resolvedDisks[$disk] ??= Storage::disk($disk);
    }

    private function supportsCacheTags(): bool
    {
        if ($this->cacheTagsSupported !== null) {
            return $this->cacheTagsSupported;
        }

        try {
            Cache::tags(['img:probe']);
            $this->cacheTagsSupported = true;
        } catch (\BadMethodCallException) {
            $this->cacheTagsSupported = false;
        }

        return $this->cacheTagsSupported;
    }
}
