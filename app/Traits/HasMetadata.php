<?php

namespace App\Traits;

trait HasMetadata
{
    /**
     * Get metadata for the current enum case.
     * 
     * @return array{value: int|string, label: string, color: string, icon: string, description: string}
     */
    public function getMetadata(): array
    {
        $metadata = [
            'value' => $this->value,
            'label' => $this->label(),
            'color' => $this->color(),
            'icon' => $this->icon(),
            'description' => $this->description(),
        ];

        return $this->validateMetadata($metadata);
    }

    /**
     * Validate the metadata structure and types.
     *
     * @param array $metadata
     * @return array{value: int|string, label: string, color: string, icon: string, description: string}
     *
     * @throws \InvalidArgumentException If the metadata structure is invalid.
     */
    private function validateMetadata(array $metadata): array
    {
        $requiredKeys = ['value', 'label', 'color', 'icon', 'description'];
        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $metadata)) {
                throw new \InvalidArgumentException(sprintf('Missing required metadata key: %s', $key));
            }
        }

        if (! is_int($metadata['value']) && ! is_string($metadata['value'])) {
            throw new \InvalidArgumentException('Metadata "value" must be of type int|string.');
        }

        foreach (['label', 'color', 'icon', 'description'] as $stringKey) {
            if (! is_string($metadata[$stringKey])) {
                throw new \InvalidArgumentException(sprintf('Metadata "%s" must be of type string.', $stringKey));
            }
        }

        return $metadata;
    }

    /**
     * Get all enum cases with their metadata.
     * 
     * @return array<int, array>
     */
    public static function getAllMetadata(): array
    {
        return array_map(
            fn($case) => $case->getMetadata(),
            static::cases()
        );
    }
    
    /**
     * Get metadata for a specific value.
     * 
     * @param int|string|null $value
     * @return array|null
     */
    public static function getMetadataFor($value): ?array
    {
        if ($value === null) {
            return null;
        }

        $case = static::tryFrom($value);
        return $case ? $case->getMetadata() : null;
    }
}
