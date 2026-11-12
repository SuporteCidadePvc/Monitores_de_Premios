<?php

namespace Tests\Unit;

use App\Domain\Display\PlaylistSchedule;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PlaylistScheduleTest extends TestCase
{
    public function test_it_selects_every_screen_from_one_shared_server_clock(): void
    {
        $playlist = $this->playlist();

        $this->assertSame(150, $playlist->cycleDurationSeconds());
        $this->assertSame('principal', $playlist->screenAtUnixTimestamp(0));
        $this->assertSame('principal', $playlist->screenAtUnixTimestamp(59));
        $this->assertSame('hoje', $playlist->screenAtUnixTimestamp(60));
        $this->assertSame('hoje', $playlist->screenAtUnixTimestamp(89));
        $this->assertSame('semana', $playlist->screenAtUnixTimestamp(90));
        $this->assertSame('semana', $playlist->screenAtUnixTimestamp(119));
        $this->assertSame('mes', $playlist->screenAtUnixTimestamp(120));
        $this->assertSame('mes', $playlist->screenAtUnixTimestamp(149));
        $this->assertSame('principal', $playlist->screenAtUnixTimestamp(150));
    }

    public function test_it_handles_timestamps_before_the_unix_epoch(): void
    {
        $this->assertSame('mes', $this->playlist()->screenAtUnixTimestamp(-1));
    }

    public function test_duplicate_screens_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PlaylistSchedule([
            ['screen' => 'principal', 'duration_seconds' => 30],
            ['screen' => 'principal', 'duration_seconds' => 15],
        ]);
    }

    public function test_zero_duration_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PlaylistSchedule([
            ['screen' => 'principal', 'duration_seconds' => 0],
        ]);
    }

    private function playlist(): PlaylistSchedule
    {
        return new PlaylistSchedule([
            ['screen' => 'principal', 'duration_seconds' => 60],
            ['screen' => 'hoje', 'duration_seconds' => 30],
            ['screen' => 'semana', 'duration_seconds' => 30],
            ['screen' => 'mes', 'duration_seconds' => 30],
        ]);
    }
}
