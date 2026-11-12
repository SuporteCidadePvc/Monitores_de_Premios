<?php

namespace App\Domain\Prizes;

use InvalidArgumentException;

final readonly class WorkedHours
{
    private const MAX_HUNDREDTHS = 100_000_000;

    private function __construct(public int $hundredths) {}

    public static function fromDecimal(string $hours): self
    {
        $normalized = str_replace(',', '.', trim($hours));

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $normalized, $matches)) {
            throw new InvalidArgumentException('Hours must be a non-negative number with at most two decimal places.');
        }

        $wholeHours = (int) $matches[1];
        $fraction = str_pad($matches[2] ?? '', 2, '0');

        $hundredths = ($wholeHours * 100) + (int) $fraction;

        if ($hundredths > self::MAX_HUNDREDTHS) {
            throw new InvalidArgumentException('The hours value is too large.');
        }

        return new self($hundredths);
    }

    public function toDecimal(): string
    {
        return sprintf('%d.%02d', intdiv($this->hundredths, 100), $this->hundredths % 100);
    }
}
