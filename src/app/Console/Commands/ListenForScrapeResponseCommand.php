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
        while (true) {
            $result = Redis::blpop('scrape-result', 0);

            $message = json_decode($result[1], true);

            $this->info('Message received: ' . json_encode($message));
        }
    }
}
