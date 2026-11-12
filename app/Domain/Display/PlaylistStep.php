<?php

namespace App\Domain\Display;

use InvalidArgumentException;

final readonly class PlaylistStep
{
    public function __construct(
        public string $screen,
        public int $durationSeconds,
    ) {
        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $screen)) {
            throw new InvalidArgumentException('A playlist screen key is invalid.');
        }

        if ($durationSeconds < 1 || $durationSeconds > 3600) {
            throw new InvalidArgumentException('A playlist step must last between 1 and 3600 seconds.');
        }
    }
}
