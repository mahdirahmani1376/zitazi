<?php

namespace App\Providers;

use App\Actions\Crawler\CrawlerManager;
use App\Actions\Crawler\DigikalaCrawlerManager;
use App\Actions\Crawler\EleleCrawlerManager;
use App\Actions\Crawler\TorobCrawlerManager;
use App\Actions\Crawler\TrendyolCrawlerManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::unguard();
        HeadingRowFormatter::default('none'); // Disable automatic transformation

        $this->app->singleton(CrawlerManager::class, function () {
            return new CrawlerManager([
                DigikalaCrawlerManager::class,
                TorobCrawlerManager::class,
                TrendyolCrawlerManager::class,
                EleleCrawlerManager::class
            ]);
        });

    }
}
