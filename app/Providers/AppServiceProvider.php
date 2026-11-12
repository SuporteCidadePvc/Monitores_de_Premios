<?php

namespace App\Providers;

use App\Console\Commands\CheckOdbcConnections;
use App\Console\Commands\CreateInitialAdminUser;
use App\Domain\Admin\AdminManagementRepository;
use App\Domain\Admin\AdminUserRepository;
use App\Domain\Admin\MonthlyPrizeHistoryRepository;
use App\Domain\Announcements\AnnouncementRepository;
use App\Domain\Display\PlaylistSchedule;
use App\Domain\Operations\OdbcConnectionProbe;
use App\Domain\Prizes\MonthlyPrizeRepository;
use App\Domain\Prizes\MonthlyProductionLookup;
use App\Domain\Prizes\PrizeCalculator;
use App\Domain\Prizes\PrizeRuleProvider;
use App\Domain\Production\LastValidProductionSnapshotStore;
use App\Domain\Production\ProductionPeriodRangeFactory;
use App\Domain\Production\ProductionRepository;
use App\Infrastructure\Announcements\AnnouncementAttachmentStore;
use App\Infrastructure\Announcements\ResilientAnnouncementRepository;
use App\Infrastructure\Auth\SqlServerAdminUserProvider;
use App\Infrastructure\Cache\FileProductionSnapshotStore;
use App\Infrastructure\Config\ConfiguredPrizeRuleProvider;
use App\Infrastructure\Prizes\ResilientPrizeRuleProvider;
use App\Infrastructure\Production\ResilientProductionRepository;
use App\Infrastructure\SqlServer\DashboardOdbcConnectionFactory;
use App\Infrastructure\SqlServer\OdbcConnectionFactory;
use App\Infrastructure\SqlServer\SqlServerAdminManagementRepository;
use App\Infrastructure\SqlServer\SqlServerAdminUserRepository;
use App\Infrastructure\SqlServer\SqlServerAnnouncementRepository;
use App\Infrastructure\SqlServer\SqlServerMonthlyPrizeHistoryRepository;
use App\Infrastructure\SqlServer\SqlServerMonthlyPrizeRepository;
use App\Infrastructure\SqlServer\SqlServerOdbcConnectionProbe;
use App\Infrastructure\SqlServer\SqlServerPrizeRuleProvider;
use App\Infrastructure\SqlServer\SqlServerProductionRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OdbcConnectionFactory::class);
        $this->app->singleton(DashboardOdbcConnectionFactory::class);
        $this->app->bind(OdbcConnectionProbe::class, SqlServerOdbcConnectionProbe::class);
        $this->app->singleton(
            LastValidProductionSnapshotStore::class,
            fn (): LastValidProductionSnapshotStore => new FileProductionSnapshotStore(
                Cache::store((string) config('operations.snapshot_cache_store')),
            ),
        );
        $this->app->singleton(PrizeCalculator::class);
        $this->app->singleton(
            PlaylistSchedule::class,
            fn (): PlaylistSchedule => new PlaylistSchedule(
                (array) config('display.playlist.steps'),
            ),
        );

        $this->app->singleton(
            PrizeRuleProvider::class,
            fn (): PrizeRuleProvider => new ResilientPrizeRuleProvider(
                new SqlServerPrizeRuleProvider($this->app->make(DashboardOdbcConnectionFactory::class)),
                new ConfiguredPrizeRuleProvider((array) config('prizes')),
            ),
        );

        $this->app->bind(
            ProductionRepository::class,
            fn (): ProductionRepository => new ResilientProductionRepository(
                new SqlServerProductionRepository($this->app->make(OdbcConnectionFactory::class)),
                $this->app->make(LastValidProductionSnapshotStore::class),
                Cache::store((string) config('operations.snapshot_cache_store')),
                (int) config('operations.query_lock_seconds'),
                (int) config('operations.query_lock_wait_seconds'),
                (int) config('operations.odbc_retry_after_failure_seconds'),
            ),
        );

        $this->app->bind(
            AnnouncementRepository::class,
            fn (): AnnouncementRepository => new ResilientAnnouncementRepository(
                new SqlServerAnnouncementRepository($this->app->make(DashboardOdbcConnectionFactory::class)),
                Cache::store((string) config('operations.snapshot_cache_store')),
                (int) config('operations.odbc_retry_after_failure_seconds'),
            ),
        );

        $this->app->singleton(
            AnnouncementAttachmentStore::class,
            fn (): AnnouncementAttachmentStore => new AnnouncementAttachmentStore(
                Storage::disk((string) config('announcements.attachments.disk')),
                (string) config('announcements.attachments.directory'),
                (int) config('announcements.attachments.max_kilobytes') * 1024,
            ),
        );

        $this->app->bind(
            AdminUserRepository::class,
            SqlServerAdminUserRepository::class,
        );

        $this->app->bind(
            AdminManagementRepository::class,
            SqlServerAdminManagementRepository::class,
        );

        $this->app->bind(
            MonthlyPrizeHistoryRepository::class,
            SqlServerMonthlyPrizeHistoryRepository::class,
        );

        $this->app->bind(
            MonthlyPrizeRepository::class,
            SqlServerMonthlyPrizeRepository::class,
        );

        $this->app->bind(
            MonthlyProductionLookup::class,
            fn (): MonthlyProductionLookup => new MonthlyProductionLookup(
                $this->app->make(ProductionRepository::class),
                $this->app->make(ProductionPeriodRangeFactory::class),
                (string) config('production.timezone'),
            ),
        );

        $this->app->singleton(
            ProductionPeriodRangeFactory::class,
            fn (): ProductionPeriodRangeFactory => new ProductionPeriodRangeFactory(
                (string) config('production.timezone'),
            ),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateInitialAdminUser::class,
                CheckOdbcConnections::class,
            ]);
        }

        Auth::provider('dashboard-odbc', function ($app): SqlServerAdminUserProvider {
            return new SqlServerAdminUserProvider(
                $app->make(AdminUserRepository::class),
                $app->make('hash'),
            );
        });
    }
}
