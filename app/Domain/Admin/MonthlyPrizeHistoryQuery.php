<?php

namespace App\Domain\Admin;

use App\Domain\Prizes\PrizeMonth;
use InvalidArgumentException;

final readonly class MonthlyPrizeHistoryQuery
{
    public function __construct(
        public ?string $sectorKey = null,
        public ?PrizeMonth $from = null,
        public ?PrizeMonth $to = null,
        public int $page = 1,
        public int $perPage = 12,
    ) {
        if ($sectorKey !== null && ! in_array($sectorKey, ['pvc', 'aluminio'], true)) {
            throw new InvalidArgumentException('The history sector is invalid.');
        }

        if ($from instanceof PrizeMonth && $to instanceof PrizeMonth && $this->periodNumber($from) > $this->periodNumber($to)) {
            throw new InvalidArgumentException('The history start period must not be after the end period.');
        }

        if ($page < 1) {
            throw new InvalidArgumentException('The history page must be positive.');
        }

        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('The history page size must be between 1 and 100.');
        }
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function fromPeriodNumber(): ?int
    {
        return $this->from instanceof PrizeMonth ? $this->periodNumber($this->from) : null;
    }

    public function toPeriodNumber(): ?int
    {
        return $this->to instanceof PrizeMonth ? $this->periodNumber($this->to) : null;
    }

    private function periodNumber(PrizeMonth $period): int
    {
        return ($period->year * 100) + $period->month;
    }
}
