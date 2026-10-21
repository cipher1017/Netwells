<?php

namespace Tests\Unit;

use App\Models\Device;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MacNormalisationTest extends TestCase
{
    public static function macProvider(): array
    {
        return [
            'colon upper'        => ['AA:BB:CC:DD:EE:FF', 'AA:BB:CC:DD:EE:FF'],
            'colon lower'        => ['aa:bb:cc:dd:ee:ff', 'AA:BB:CC:DD:EE:FF'],
            'dash separated'     => ['AA-BB-CC-DD-EE-FF', 'AA:BB:CC:DD:EE:FF'],
            'no separators'      => ['aabbccddeeff',      'AA:BB:CC:DD:EE:FF'],
            'cisco dotted'       => ['aabb.ccdd.eeff',    'AA:BB:CC:DD:EE:FF'],
            'surrounding spaces' => ['  aa:bb:cc:dd:ee:ff  ', 'AA:BB:CC:DD:EE:FF'],
            'too short'          => ['AA:BB:CC',          null],
            'too long'          => ['AA:BB:CC:DD:EE:FF:00', null],
            'non hex'            => ['ZZ:BB:CC:DD:EE:FF',  null],
            'empty'              => ['',                   null],
            'null'               => [null,                 null],
        ];
    }

    #[DataProvider('macProvider')]
    public function test_normalise_mac(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, Device::normaliseMac($input));
    }
}
