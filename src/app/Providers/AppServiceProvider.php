<?php

namespace App\Providers;

use App\Actions\Crawler\CrawlerManager;
use App\Actions\Crawler\DigikalaCrawler;
use App\Actions\Crawler\EleleCrawler;
use App\Actions\Crawler\TorobCrawler;
use App\Services\CurrencyRate\CurrencyRateDriverInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CrawlerManager::class, function () {
            return new CrawlerManager([
                new EleleCrawler(),
                new DigikalaCrawler(),
                new TorobCrawler(),
            ]);
        });

        $this->app->bind(CurrencyRateDriverInterface::class, function (Application $app) {
            $driverKey = config('services.currency-rate.driver');
            $class = config("services.currency-rate.drivers.$driverKey");

            if (!class_exists($class)) {
                throw new InvalidArgumentException("Currency rate driver [$driverKey] not found.");
            }

            return $app->make($class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::unguard();
        HeadingRowFormatter::default('none'); // Disable automatic transformation

        Bus::pipeThrough([
            SkipIfBatchCancelled::class
        ]);

    }
}
