<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Variation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulateDecathlonSourceIdsCommand extends Command
{
    protected $signature = 'app:populate-decathlon-source-ids';

    protected $description = 'Backfill source_product_id and source_variation_id on decathlon variations from their url';

    public function handle(): void
    {
        $variations = Variation::query()
            ->where('source', Product::SOURCE_DECATHLON)
            ->where(function ($query) {
                $query
                    ->whereNull('source_product_id')
                    ->orWhereNull('source_variation_id');
            })
            ->select(['id', 'url', 'source_product_id', 'source_variation_id']);

        $bar = $this->output->createProgressBar($variations->count());

        $variations->chunkById(500, function ($chunk) use ($bar) {
            $rows = [];

            foreach ($chunk as $variation) {
                $rows[] = [
                    'id' => $variation->id,
                    'source_product_id' => $variation->source_product_id ?? $this->extractSourceProductId($variation->url),
                    'source_variation_id' => $variation->source_variation_id ?? $this->extractSourceVariationId($variation->url),
                    'updated_at' => now()->toDateTimeString(),
                ];

                $bar->advance();
            }

            DB::table('variations')->upsert(
                $rows,
                ['id'],
                ['source_product_id', 'source_variation_id', 'updated_at']
            );
        });

        $bar->finish();
        $this->newLine();
        $this->info('Decathlon source ids populated successfully.');
    }

    private function extractSourceProductId(?string $url): ?string
    {
        if (preg_match('/\/R-p-([^?\/]+)/', $url ?? '', $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractSourceVariationId(?string $url): ?string
    {
        if (preg_match('/[\?&]mc=([^&]+)/', $url ?? '', $matches)) {
            return $matches[1];
        }

        return null;
    }
}
