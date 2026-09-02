<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MarketHistory;
use App\Models\MarketOffer;
use App\Services\MarketCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class CleanupLegacyTradeablesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'market:cleanup-legacy-tradeables
                            {--dry-run : Only report rows to be cleaned without deleting}';

    /**
     * @var string
     */
    protected $description = 'Clean up legacy pseudo-resource tradeable rows from market_offers and market_history';

    public function __construct(private readonly MarketCacheService $cache)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $offersQuery = MarketOffer::query()
            ->where('item_id', 'Adventure')
            ->orWhere('target_item_id', 'Adventure');

        $historyQuery = MarketHistory::query()
            ->where('item_id', 'Adventure')
            ->orWhere('target_item_id', 'Adventure');

        $offersCount = $offersQuery->count();
        $historyCount = $historyQuery->count();

        $this->table(
            ['Table', 'Rows matching legacy pseudo-resource'],
            [
                ['market_offers', $offersCount],
                ['market_history', $historyCount],
            ]
        );

        if ($isDryRun) {
            $this->warn('DRY RUN: No rows were deleted. Run without --dry-run to delete these rows.');

            return self::SUCCESS;
        }

        if ($offersCount === 0 && $historyCount === 0) {
            $this->info('No legacy rows found to delete.');

            return self::SUCCESS;
        }

        DB::transaction(function () {
            MarketOffer::query()
                ->where('item_id', 'Adventure')
                ->orWhere('target_item_id', 'Adventure')
                ->delete();

            MarketHistory::query()
                ->where('item_id', 'Adventure')
                ->orWhere('target_item_id', 'Adventure')
                ->delete();
        });

        $this->cache->bumpDataVersion(MarketCacheService::GLOBAL_SERVER);
        $this->info("Successfully deleted {$offersCount} market offers and {$historyCount} history records.");

        return self::SUCCESS;
    }
}
