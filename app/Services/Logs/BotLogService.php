<?php

declare(strict_types=1);

namespace App\Services\Logs;

use App\Models\Account;
use App\Models\BotLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class BotLogService
{
    /**
     * Get paginated logs with filters.
     *
     * @return LengthAwarePaginator<int, BotLog>
     */
    public function paginate(int $perPage = 100, ?int $accountId = null, ?string $level = null): LengthAwarePaginator
    {
        return $this->query($accountId, $level)
            ->latest('created_at')
            ->paginate($perPage);
    }

    /**
     * Fetch new logs created after a specific ID for streaming.
     *
     * @return Collection<int, BotLog>
     */
    public function getNewLogsAfter(int $afterId, ?int $accountId = null, ?string $level = null, int $limit = 50): Collection
    {
        return $this->query($accountId, $level)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Get the latest log ID for stream cursor initialization.
     */
    public function latestId(): int
    {
        $max = BotLog::max('id');

        return $max !== null ? (int) $max : 0;
    }

    /**
     * Get accounts list for log filters.
     *
     * @return Collection<int, Account>
     */
    public function getFilterAccounts(): Collection
    {
        return Account::select('id', 'username', 'nickname')
            ->withExists('marketServerConnections')
            ->orderBy('username')
            ->get();
    }

    /**
     * Base query with eager-loaded relations and applied filters.
     *
     * @return Builder<BotLog>
     */
    private function query(?int $accountId = null, ?string $level = null): Builder
    {
        $query = BotLog::with([
            'account' => static function ($query): void {
                $query->select('id', 'username', 'nickname')->withExists('marketServerConnections');
            },
        ]);

        if ($accountId !== null) {
            $query->where('account_id', $accountId);
        }

        if ($level !== null && $level !== '') {
            $query->where('level', $level);
        }

        return $query;
    }
}
