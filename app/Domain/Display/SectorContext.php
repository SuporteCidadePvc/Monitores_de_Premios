<?php

namespace App\Domain\Display;

use InvalidArgumentException;

final readonly class SectorContext
{
    public function __construct(
        public string $key,
        public string $name,
        public string $productionQueryKey,
        public bool $usesProvisionalProductionData,
        public ?string $provisionalNotice,
        public string $accentColor,
    ) {}

    /**
     * @param  array<string, mixed>  $configuration
     */
    public static function fromConfiguration(string $key, array $configuration): self
    {
        $name = $configuration['name'] ?? null;
        $productionQueryKey = $configuration['production_query_key'] ?? null;
        $usesProvisionalProductionData = $configuration['uses_provisional_production_data'] ?? null;
        $provisionalNotice = $configuration['provisional_notice'] ?? null;
        $accentColor = $configuration['accent_color'] ?? null;

        if (! is_string($name) || $name === '') {
            throw new InvalidArgumentException("The sector [{$key}] must have a name.");
        }

        if (! is_string($productionQueryKey) || $productionQueryKey === '') {
            throw new InvalidArgumentException("The sector [{$key}] must have a production query key.");
        }

        if (! is_bool($usesProvisionalProductionData)) {
            throw new InvalidArgumentException("The sector [{$key}] must declare whether its production data is provisional.");
        }

        if ($provisionalNotice !== null && ! is_string($provisionalNotice)) {
            throw new InvalidArgumentException("The provisional notice for sector [{$key}] must be a string or null.");
        }

        if ($usesProvisionalProductionData && ($provisionalNotice === null || $provisionalNotice === '')) {
            throw new InvalidArgumentException("The provisional sector [{$key}] must provide a notice.");
        }

        if (! is_string($accentColor) || ! preg_match('/^#[0-9a-f]{6}$/i', $accentColor)) {
            throw new InvalidArgumentException("The sector [{$key}] must provide a valid hexadecimal accent color.");
        }

        return new self(
            key: $key,
            name: $name,
            productionQueryKey: $productionQueryKey,
            usesProvisionalProductionData: $usesProvisionalProductionData,
            provisionalNotice: $provisionalNotice,
            accentColor: $accentColor,
        );
    }
}
