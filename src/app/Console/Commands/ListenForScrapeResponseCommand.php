<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ListenForScrapeResponseCommand extends Command
{
    protected $signature = 'listen:scrape';

    protected $description = 'this command listens for scrape-response message';

    public function handle()
    {
        Redis::subscribe(
            ['scrape-result'],
            function ($message) {
                $this->info('message recieved', [$message]);
            }
        );
    }
}
