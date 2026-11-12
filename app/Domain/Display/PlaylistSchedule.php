<?php

namespace App\Domain\Display;

use InvalidArgumentException;

final readonly class PlaylistSchedule
{
    /**
     * @param  list<array{screen: mixed, duration_seconds: mixed}>  $configuredSteps
     */
    public function __construct(public array $configuredSteps)
    {
        if ($configuredSteps === []) {
            throw new InvalidArgumentException('A display playlist must have at least one step.');
        }

        $seenScreens = [];

        foreach ($configuredSteps as $configuredStep) {
            if (! is_string($configuredStep['screen']) || ! is_int($configuredStep['duration_seconds'])) {
                throw new InvalidArgumentException('A display playlist step is malformed.');
            }

            $step = new PlaylistStep($configuredStep['screen'], $configuredStep['duration_seconds']);

            if (isset($seenScreens[$step->screen])) {
                throw new InvalidArgumentException('A display playlist cannot repeat a screen.');
            }

            $seenScreens[$step->screen] = true;
        }
    }

    /**
     * @return list<PlaylistStep>
     */
    public function steps(): array
    {
        return array_map(
            fn (array $configuredStep): PlaylistStep => new PlaylistStep(
                $configuredStep['screen'],
                $configuredStep['duration_seconds'],
            ),
            $this->configuredSteps,
        );
    }

    public function cycleDurationSeconds(): int
    {
        return array_sum(array_map(
            fn (PlaylistStep $step): int => $step->durationSeconds,
            $this->steps(),
        ));
    }

    public function screenAtUnixTimestamp(int $unixTimestamp): string
    {
        $secondInCycle = $unixTimestamp % $this->cycleDurationSeconds();

        if ($secondInCycle < 0) {
            $secondInCycle += $this->cycleDurationSeconds();
        }

        $elapsedSeconds = 0;

        foreach ($this->steps() as $step) {
            $elapsedSeconds += $step->durationSeconds;

            if ($secondInCycle < $elapsedSeconds) {
                return $step->screen;
            }
        }

        throw new InvalidArgumentException('The display playlist has no active step.');
    }
}
