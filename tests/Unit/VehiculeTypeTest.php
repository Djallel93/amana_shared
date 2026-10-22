<?php
// tests/Unit/VehiculeTypeTest.php

declare(strict_types=1);

namespace Amana\Shared\Tests\Unit;

use Amana\Shared\Models\VehiculeType;
use Amana\Shared\Tests\TestCase;

class VehiculeTypeTest extends TestCase
{
    public function testLesTypesSansVehiculeSontProteges(): void
    {
        foreach (VehiculeType::IDS_PROTEGES as $id) {
            $type = new VehiculeType();
            $type->id = $id;

            $this->assertTrue($type->estProtege());
        }
    }

    public function testUnTypeOrdinaireNEstPasProtege(): void
    {
        $type = new VehiculeType();
        $type->id = 1;

        $this->assertFalse($type->estProtege());
    }

    public function testActifEstCasteEnBooleen(): void
    {
        $type = new VehiculeType(['type' => 'Citadine', 'actif' => 0]);

        $this->assertFalse($type->actif);
    }

    public function testActifEstMassAssignable(): void
    {
        $this->assertContains('actif', (new VehiculeType())->getFillable());
    }
}
