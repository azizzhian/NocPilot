<?php

namespace Tests\Unit;

use App\Support\DurationFormat;
use Tests\TestCase;

class DurationFormatTest extends TestCase
{
    public function test_formats_minutes_below_one_hour(): void
    {
        $this->assertSame('<1 mnt', DurationFormat::minutes(0));
        $this->assertSame('45 mnt', DurationFormat::minutes(45));
        $this->assertSame('59 mnt', DurationFormat::minutes(59));
    }

    public function test_formats_hours_with_remaining_minutes(): void
    {
        $this->assertSame('1 jam', DurationFormat::minutes(60));
        $this->assertSame('1 jam 35 mnt', DurationFormat::minutes(95));
        $this->assertSame('2 jam 30 mnt', DurationFormat::minutes(150));
    }

    public function test_formats_days_with_remaining_hours(): void
    {
        $this->assertSame('1 hari', DurationFormat::minutes(1440));
        $this->assertSame('1 hari 1 jam', DurationFormat::minutes(1500));
        $this->assertSame('2 hari 3 jam', DurationFormat::minutes(3060));
    }

    public function test_null_stays_null(): void
    {
        $this->assertNull(DurationFormat::minutes(null));
    }
}
