<?php

namespace App\Services\Communication;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileUploadService
{
    protected $allowedMimes = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain', 'application/zip',
    ];

    protected $maxFileSize = 10485760; // 10MB in bytes

    /**
     * Upload a single file
     */
    public function uploadFile(UploadedFile $file, string $directory = 'order-messages'): ?array
    {
        try {
            // Validate file
            if (! $file->isValid() || ! $this->validateFile($file)) {
                Log::warning('File validation failed or invalid file', [
                    'filename' => $file->getClientOriginalName(),
                    'mimeType' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'error' => $file->getError(),
                ]);

                return null;
            }

            // Generate unique filename
            $fileName = time().'_'.uniqid().'.'.$file->guessExtension();

            // Store the file
            $filePath = $file->storeAs($directory, $fileName);

            return [
                'name' => $file->getClientOriginalName(),
                'stored_name' => $fileName,
                'path' => $filePath,
                'url' => Storage::url($filePath),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'extension' => $file->guessExtension(),
            ];
        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'error' => $e->getMessage(),
                'filename' => $file->getClientOriginalName(),
            ]);

            return null;
        }
    }

    /**
     * Upload multiple files
     */
    public function uploadMultipleFiles(array $files, string $directory = 'order-messages'): array
    {
        $uploadedFiles = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $result = $this->uploadFile($file, $directory);
                if ($result) {
                    $uploadedFiles[] = $result;
                }
            }
        }

        return $uploadedFiles;
    }

    /**
     * Validate file based on size and mime type
     */
    protected function validateFile(UploadedFile $file): bool
    {
        // Check file size
        $size = $file->getSize();
        if ($size === false || $size > $this->maxFileSize) {
            return false;
        }

        // Check mime type
        if (! in_array($file->getMimeType(), $this->allowedMimes)) {
            return false;
        }

        return true;
    }

    /**
     * Set max file size limit (in bytes)
     */
    public function setMaxFileSize(int $maxSize): self
    {
        $this->maxFileSize = $maxSize;

        return $this;
    }

    /**
     * Set allowed mime types
     */
    public function setAllowedMimes(array $mimes): self
    {
        $this->allowedMimes = $mimes;

        return $this;
    }

    /**
     * Delete a file from storage.
     *
     * @param string $path The file path to delete
     * @return bool True if deleted, false otherwise
     */
    public function deleteFile(string $path): bool
    {
        try {
            if (Storage::exists($path)) {
                return Storage::delete($path);
            }
            return false;
        } catch (\Exception $e) {
            Log::error('File deletion failed', [
                'error' => $e->getMessage(),
                'path' => $path,
            ]);
            return false;
        }
    }
}
