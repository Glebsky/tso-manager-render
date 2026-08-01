<?php

declare(strict_types=1);

namespace App\Services\Market\Sync;

use App\Models\Account;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use Exception;

/**
 * Service responsible for fetching raw AMF market data and executing Python parser.
 */
class MarketOfferFetcher
{
    public function __construct(
        private readonly TsoAuthService $authService,
        private readonly TsoAmfService $amfService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function fetch(Account $account, string $serverId, callable $logCallback): array
    {
        $action = 'Market sync';

        if (! $this->authService->isAuthenticated($account)) {
            $this->authService->login($account);
            $account->refresh();
        }

        $maxRetries = 6;
        $retryDelay = 3;
        $hasResetSession = false;
        $parsed = null;
        $errorCode = 0;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $logCallback('INFO', __('logs.market.fetch_attempt', ['attempt' => $attempt, 'max' => $maxRetries]));
                $rawAmf = $this->amfService->getMarketOffers($account);

                $scriptPath = storage_path('app/parse_market.py');
                if (! file_exists($scriptPath)) {
                    throw new Exception('parse_market.py not found in storage/app/');
                }

                $tmpFile = storage_path('app/temp_market_'.uniqid().'.amf');
                file_put_contents($tmpFile, $rawAmf);

                try {
                    $pythonBin = $this->findPython();
                    $command = escapeshellarg($pythonBin).' '.escapeshellarg($scriptPath).' '.escapeshellarg($tmpFile);
                    $output = [];
                    $exitCode = 0;

                    exec($command.' 2>&1', $output, $exitCode);
                    $outputStr = implode("\n", $output);

                    if ($exitCode !== 0) {
                        throw new Exception("parse_market.py failed: {$outputStr}");
                    }

                    $parsed = json_decode($outputStr, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception('Failed to decode JSON from parser: '.json_last_error_msg());
                    }
                } finally {
                    @unlink($tmpFile);
                }

                $errorCode = (int) ($parsed['errorCode'] ?? 0);

                if ($errorCode === 1012) {
                    $logCallback('WARNING', __('logs.market.zone_loading_retry', ['delay' => $retryDelay]));
                    sleep($retryDelay);

                    continue;
                }

                if ($errorCode === 1005) {
                    if ($hasResetSession) {
                        throw new Exception(__('ui.sync.session_intercepted_market', ['code' => $errorCode]));
                    }
                    $logCallback('WARNING', __('logs.market.session_expired_retry', ['code' => $errorCode]));
                    @unlink($this->authService->getCookieFile($account));
                    $this->authService->login($account);
                    $this->amfService->resetClient();
                    $account->refresh();
                    $hasResetSession = true;
                    sleep(2);

                    continue;
                }

                break;
            } catch (Exception $attemptEx) {
                $logCallback('WARNING', __('logs.market.attempt_failed', ['attempt' => $attempt, 'max' => $maxRetries, 'error' => $attemptEx->getMessage()]));
                if ($attempt === $maxRetries) {
                    throw $attemptEx;
                }
                sleep($retryDelay);
            }
        }

        if ($errorCode !== 0) {
            throw new Exception("Server returned error code {$errorCode} during market sync.");
        }

        return is_array($parsed) ? $parsed : [];
    }

    private function findPython(): string
    {
        $candidates = ['python', 'python3', 'py'];
        foreach ($candidates as $bin) {
            $out = [];
            $code = 0;
            exec(escapeshellarg($bin).' --version 2>&1', $out, $code);
            if ($code === 0) {
                return $bin;
            }
        }
        throw new Exception('Python not found in system PATH.');
    }
}
