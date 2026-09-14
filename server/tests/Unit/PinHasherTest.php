<?php

namespace Tests\Unit;

use App\Services\PinHasher;
use App\Services\PinPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PinHasherTest extends TestCase
{
    public function test_make_and_verify_roundtrip(): void
    {
        $hashed = PinHasher::make('2468');

        $this->assertSame('pbkdf2-sha256', $hashed['algo']);
        $this->assertSame(100000, $hashed['iterations']);
        $this->assertNotEmpty($hashed['salt']);
        $this->assertNotEmpty($hashed['hash']);

        $this->assertTrue(PinHasher::verify('2468', $hashed['salt'], $hashed['iterations'], $hashed['hash']));
        $this->assertFalse(PinHasher::verify('2469', $hashed['salt'], $hashed['iterations'], $hashed['hash']));
    }

    public function test_same_pin_produces_different_hash_due_to_salt(): void
    {
        $first = PinHasher::make('2468');
        $second = PinHasher::make('2468');

        $this->assertNotSame($first['salt'], $second['salt']);
        $this->assertNotSame($first['hash'], $second['hash']);
    }

    #[DataProvider('weakPins')]
    public function test_weak_pins_are_detected(string $pin): void
    {
        $this->assertTrue(PinPolicy::isWeak($pin), "PIN {$pin} seharusnya dianggap lemah");
    }

    #[DataProvider('strongPins')]
    public function test_strong_pins_pass(string $pin): void
    {
        $this->assertFalse(PinPolicy::isWeak($pin), "PIN {$pin} seharusnya dianggap kuat");
    }

    public static function weakPins(): array
    {
        return [
            ['0000'], ['1111'], ['9999'],
            ['1234'], ['4321'], ['0123'], ['9876'],
            ['abcd'], ['12 4'],
        ];
    }

    public static function strongPins(): array
    {
        return [
            ['2468'], ['1357'], ['9182'], ['5061'],
        ];
    }
}
