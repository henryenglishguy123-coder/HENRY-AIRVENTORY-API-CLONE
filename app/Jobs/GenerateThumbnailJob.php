<?php
declare(strict_types=1);

namespace App\Jobs;

use App\Services\Image\ImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * The number of seconds to wait before retrying a failed job.
     *
     * @var int[]
     */
    public $backoff = [10, 60, 300];

    /**
     * Unique ID to prevent duplicate processing.
     */
    public function uniqueId(): string
    {
        return sprintf(
            'thumbnail:%s:%s:%s:%s:%s:%s',
            md5($this->originalPath),
            $this->options['width'] ?? 300,
            $this->options['height'] ?? 'auto',
            strtolower($this->options['format'] ?? 'webp'),
            $this->options['quality'] ?? 85,
            $this->options['disk'] ?? 's3'
        );
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly string $originalPath,
        private readonly array $options = []
    ) {
        $this->onQueue('images');
    }

    /**
     * Execute the job.
     */
    public function handle(ImageService $imageService): void
    {
        $imageService->generateThumbnail($this->originalPath, $this->options);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error('GenerateThumbnailJob failed', [
            'original_path' => $this->originalPath,
            'options' => $this->options,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
