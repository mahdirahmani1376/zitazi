<?php

namespace App\Providers;

use App\Actions\Crawler\CrawlerManager;
use App\Actions\Crawler\DigikalaCrawler;
use App\Actions\Crawler\EleleCrawler;
use App\Actions\Crawler\TorobCrawler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\Bus;
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
                new EleleCrawler(),
                new DigikalaCrawler(),
                new TorobCrawler(),
            ]);
        });

        Bus::pipeThrough([
            SkipIfBatchCancelled::class
        ]);

    }
}
