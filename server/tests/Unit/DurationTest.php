<?php

namespace Tests\Unit;

use App\Support\Duration;
use PHPUnit\Framework\TestCase;

class DurationTest extends TestCase
{
    public function test_di_bawah_satu_jam_tetap_menit(): void
    {
        $this->assertSame('0 menit', Duration::human(0));
        $this->assertSame('1 menit', Duration::human(1));
        $this->assertSame('59 menit', Duration::human(59));
    }

    public function test_tepat_satu_jam(): void
    {
        $this->assertSame('1 jam', Duration::human(60));
        $this->assertSame('2 jam', Duration::human(120));
        $this->assertSame('24 jam', Duration::human(1440));
    }

    public function test_jam_lebih_menit(): void
    {
        $this->assertSame('1 jam 5 menit', Duration::human(65));
        $this->assertSame('2 jam 5 menit', Duration::human(125));
        $this->assertSame('12 jam 30 menit', Duration::human(750));
    }

    public function test_null_dan_negatif_dianggap_nol(): void
    {
        $this->assertSame('0 menit', Duration::human(null));
        $this->assertSame('0 menit', Duration::human(-5));
    }
}
