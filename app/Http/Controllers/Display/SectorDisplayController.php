<?php

namespace App\Http\Controllers\Display;

use App\Domain\Announcements\Announcement;
use App\Domain\Announcements\AnnouncementRepository;
use App\Domain\Announcements\Exceptions\AnnouncementDataUnavailable;
use App\Domain\Display\PlaylistSchedule;
use App\Domain\Display\PlaylistStep;
use App\Domain\Display\SectorContext;
use App\Domain\Prizes\PrizeCalculator;
use App\Domain\Prizes\PrizePoolResult;
use App\Domain\Prizes\PrizeRuleProvider;
use App\Domain\Production\Exceptions\ProductionDataUnavailable;
use App\Domain\Production\ProductionPeriod;
use App\Domain\Production\ProductionPeriodRangeFactory;
use App\Domain\Production\ProductionRepository;
use App\Domain\Production\ProductionSnapshot;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class SectorDisplayController extends Controller
{
    public function __construct(
        private readonly ProductionRepository $productionRepository,
        private readonly ProductionPeriodRangeFactory $rangeFactory,
        private readonly PrizeRuleProvider $prizeRuleProvider,
        private readonly PrizeCalculator $prizeCalculator,
        private readonly PlaylistSchedule $playlistSchedule,
        private readonly AnnouncementRepository $announcementRepository,
    ) {}

    public function __invoke(Request $request, string $sector, string $screen = 'principal'): View
    {
        $sectorContext = $request->attributes->get(SectorContext::class);
        $screens = config('sectors.screens');

        abort_unless($sectorContext instanceof SectorContext, 500);
        abort_unless($sectorContext->key === $sector, 404);
        abort_unless(is_array($screens) && isset($screens[$screen]), 404);

        $period = ProductionPeriod::fromScreen($screen);
        $snapshot = null;
        $snapshotIsStale = false;
        $productionDataUnavailable = false;
        $prizePool = null;
        $prizeTiers = [];
        $announcement = null;
        $announcementDataUnavailable = false;

        try {
            $announcement = $this->announcementRepository->activeFor(
                $sectorContext,
                now((string) config('production.timezone'))->toImmutable(),
            );
        } catch (AnnouncementDataUnavailable $exception) {
            $announcementDataUnavailable = true;

            if ($exception->stage !== 'backoff') {
                Log::warning('Announcement data is temporarily unavailable.', [
                    'sector' => $sectorContext->key,
                    'stage' => $exception->stage,
                    'odbc_sqlstate' => $exception->odbcSqlState,
                ]);
            }
        }

        if ($period instanceof ProductionPeriod) {
            try {
                $snapshot = $this->productionRepository->summarize(
                    $sectorContext,
                    $this->rangeFactory->for($period),
                );

                if ($period === ProductionPeriod::Month && $snapshot->hasData) {
                    $prizeRules = $this->prizeRuleProvider->forSector($sectorContext->key);
                    $prizePool = $this->prizeCalculator->selectPool($prizeRules, $snapshot->squares);
                    $prizeTiers = $prizeRules->tiers;
                }
            } catch (ProductionDataUnavailable $exception) {
                $productionDataUnavailable = true;

                if ($exception->stage !== 'backoff') {
                    Log::warning('Production data is temporarily unavailable.', [
                        'sector' => $sectorContext->key,
                        'period' => $period->value,
                        'stage' => $exception->stage,
                        'odbc_sqlstate' => $exception->odbcSqlState,
                    ]);
                }
            }
        }

        assert($snapshot === null || $snapshot instanceof ProductionSnapshot);
        assert($prizePool === null || $prizePool instanceof PrizePoolResult);
        assert($announcement === null || $announcement instanceof Announcement);

        if ($snapshot instanceof ProductionSnapshot) {
            $snapshotIsStale = $snapshot->isCached && $this->isStaleSnapshot($snapshot);
        }

        $playlistSteps = $this->playlistSchedule->steps();

        if ($announcement instanceof Announcement) {
            $playlistSteps[] = new PlaylistStep(
                screen: 'comunicado',
                durationSeconds: (int) config('display.playlist.announcement_duration_seconds'),
            );
        }

        $playlist = array_map(
            function (PlaylistStep $step) use ($sectorContext): array {
                $url = $step->screen === 'principal'
                    ? route('display.home', ['sector' => $sectorContext->key])
                    : route('display.screen', ['sector' => $sectorContext->key, 'screen' => $step->screen]);

                return [
                    'screen' => $step->screen,
                    'url' => $url,
                    'duration_seconds' => $step->durationSeconds,
                ];
            },
            $playlistSteps,
        );

        return view('display.sector', [
            'sector' => $sectorContext,
            'screen' => $screen,
            'screens' => $screens,
            'period' => $period,
            'snapshot' => $snapshot,
            'snapshotIsStale' => $snapshotIsStale,
            'productionDataUnavailable' => $productionDataUnavailable,
            'prizePool' => $prizePool,
            'prizeTiers' => $prizeTiers,
            'playlist' => $playlist,
            'serverEpochMilliseconds' => now(config('app.timezone'))->getTimestamp() * 1000,
            'announcement' => $announcement,
            'announcementDataUnavailable' => $announcementDataUnavailable,
        ]);
    }

    private function isStaleSnapshot(ProductionSnapshot $snapshot): bool
    {
        $ageSeconds = max(
            0,
            now((string) config('production.timezone'))->getTimestamp() - $snapshot->retrievedAt->getTimestamp(),
        );

        return $ageSeconds >= (int) config('operations.stale_snapshot_after_seconds');
    }
}
