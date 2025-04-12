<?php

namespace App\Providers;

use App\Actions\Crawler\BaseProductCrawler;
use App\Actions\Crawler\DigikalaCrawler;
use App\Actions\Crawler\EleleCrawler;
use App\Actions\Crawler\TorobCrawler;
use App\Actions\Crawler\TrendyolCrawler;
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

        $this->app->singleton(BaseProductCrawler::class, function () {
            return new BaseProductCrawler([
                DigikalaCrawler::class,
                TorobCrawler::class,
                TrendyolCrawler::class,
                EleleCrawler::class
            ]);
        });

    }
}
