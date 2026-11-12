<?php

namespace Tests\Unit;

use App\Domain\Display\SectorContext;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SectorContextTest extends TestCase
{
    public function test_it_builds_an_immutable_sector_context_from_valid_configuration(): void
    {
        $context = SectorContext::fromConfiguration('pvc', [
            'name' => 'PVC',
            'production_query_key' => 'pvc_production_current',
            'uses_provisional_production_data' => false,
            'provisional_notice' => null,
            'accent_color' => '#0a6b4f',
        ]);

        $this->assertSame('pvc', $context->key);
        $this->assertSame('PVC', $context->name);
        $this->assertFalse($context->usesProvisionalProductionData);
    }

    public function test_a_provisional_sector_requires_a_visible_notice(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SectorContext::fromConfiguration('aluminio', [
            'name' => 'Alumínio',
            'production_query_key' => 'pvc_production_current',
            'uses_provisional_production_data' => true,
            'provisional_notice' => null,
            'accent_color' => '#526478',
        ]);
    }
}
