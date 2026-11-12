<?php

namespace Tests;

use App\Domain\Admin\AdminManagementRepository;
use App\Domain\Admin\AdminUserRepository;
use App\Domain\Admin\MonthlyPrizeHistoryRepository;
use App\Domain\Announcements\AnnouncementRepository;
use App\Domain\Prizes\PrizeRuleProvider;
use App\Domain\Prizes\MonthlyPrizeClosing;
use App\Domain\Prizes\MonthlyPrizeRepository;
use App\Domain\Prizes\MonthlyPrizeService;
use App\Domain\Prizes\PrizeCalculator;
use App\Domain\Production\ProductionRepository;
use App\Domain\Production\ProductionPeriodRangeFactory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fakes\FakeAdminManagementRepository;
use Tests\Fakes\FakeAdminUserRepository;
use Tests\Fakes\FakeAnnouncementRepository;
use Tests\Fakes\FakeMonthlyPrizeHistoryRepository;
use Tests\Fakes\FakeProductionRepository;
use Tests\Fakes\FakePrizeRuleProvider;
use Tests\Fakes\InMemoryMonthlyPrizeRepository;

abstract class TestCase extends BaseTestCase
{
    protected FakeProductionRepository $productionRepository;

    protected FakeAnnouncementRepository $announcementRepository;

    protected FakeAdminUserRepository $adminUserRepository;

    protected FakeAdminManagementRepository $adminManagementRepository;

    protected FakeMonthlyPrizeHistoryRepository $monthlyPrizeHistoryRepository;

    protected FakePrizeRuleProvider $prizeRuleProvider;

    protected InMemoryMonthlyPrizeRepository $monthlyPrizeRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productionRepository = new FakeProductionRepository;
        $this->app->instance(ProductionRepository::class, $this->productionRepository);

        $this->announcementRepository = new FakeAnnouncementRepository;
        $this->app->instance(AnnouncementRepository::class, $this->announcementRepository);

        $this->adminUserRepository = new FakeAdminUserRepository;
        $this->app->instance(AdminUserRepository::class, $this->adminUserRepository);

        $this->adminManagementRepository = new FakeAdminManagementRepository;
        $this->app->instance(AdminManagementRepository::class, $this->adminManagementRepository);

        $this->monthlyPrizeHistoryRepository = new FakeMonthlyPrizeHistoryRepository;
        $this->app->instance(MonthlyPrizeHistoryRepository::class, $this->monthlyPrizeHistoryRepository);

        $this->prizeRuleProvider = new FakePrizeRuleProvider;
        $this->app->instance(PrizeRuleProvider::class, $this->prizeRuleProvider);

        $this->monthlyPrizeRepository = new InMemoryMonthlyPrizeRepository;
        $this->app->instance(MonthlyPrizeRepository::class, $this->monthlyPrizeRepository);

        $this->app->instance(
            MonthlyPrizeClosing::class,
            new MonthlyPrizeClosing(
                $this->productionRepository,
                $this->app->make(ProductionPeriodRangeFactory::class),
                new MonthlyPrizeService(
                    $this->prizeRuleProvider,
                    $this->app->make(PrizeCalculator::class),
                    $this->monthlyPrizeRepository,
                ),
                (string) config('production.timezone'),
                static fn (\DateTimeZone $timezone): \DateTimeImmutable => new \DateTimeImmutable('2026-09-25 12:00:00', $timezone),
            ),
        );
    }
}
