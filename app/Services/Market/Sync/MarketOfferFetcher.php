<?php

declare(strict_types=1);

namespace App\Services\Market\Sync;

use App\Models\Account;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use Exception;
use RuntimeException;

/**
 * Service responsible for fetching raw AMF market data and executing Python parser.
 */
readonly class MarketOfferFetcher
{
    public function __construct(
        private TsoAuthService $authService,
        private TsoAmfService $amfService,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function fetch(Account $account, callable $logCallback): array
    {
        $this->authService->ensureAuthenticated($account);

        $maxRetries = 3;
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
                    throw new RuntimeException('parse_market.py not found in storage/app/');
                }

                $tmpFile = storage_path('app/temp_market_'.uniqid('', true).'.amf');
                file_put_contents($tmpFile, $rawAmf);

                try {
                    $pythonBin = $this->findPython();
                    $command = escapeshellarg($pythonBin).' -W ignore '.escapeshellarg($scriptPath).' '.escapeshellarg($tmpFile);

                    $output = [];
                    $exitCode = 0;

                    exec($command.' 2>&1', $output, $exitCode);
                    $outputStr = trim(implode("\n", $output));

                    if ($exitCode !== 0) {
                        throw new RuntimeException("parse_market.py failed: {$outputStr}");
                    }

                    $parsed = json_decode($outputStr, true, 512, JSON_THROW_ON_ERROR);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $firstBrace = strpos($outputStr, '{');
                        $firstBracket = strpos($outputStr, '[');
                        $start = false;
                        if ($firstBrace !== false && $firstBracket !== false) {
                            $start = min($firstBrace, $firstBracket);
                        } elseif ($firstBrace !== false) {
                            $start = $firstBrace;
                        } elseif ($firstBracket !== false) {
                            $start = $firstBracket;
                        }

                        if ($start !== false) {
                            $lastBrace = strrpos($outputStr, '}');
                            $lastBracket = strrpos($outputStr, ']');
                            $end = max($lastBrace !== false ? $lastBrace : -1, $lastBracket !== false ? $lastBracket : -1);

                            if ($end > $start) {
                                $jsonSub = substr($outputStr, $start, $end - $start + 1);
                                $parsed = json_decode($jsonSub, true, 512, JSON_THROW_ON_ERROR);
                            }
                        }
                    }

                    if (! is_array($parsed)) {
                        throw new RuntimeException('Failed to decode JSON from parser: '.json_last_error_msg().'. Raw output: '.substr($outputStr, 0, 500));
                    }
                } finally {
                    @unlink($tmpFile);
                }

                $errorCode = (int) ($parsed['errorCode'] ?? 0);

                if ($errorCode === 1012) {
                    if ($hasResetSession) {
                        throw new RuntimeException(__('ui.sync.session_intercepted_market', ['code' => $errorCode]));
                    }

                    if ($attempt === 1) {
                        $logCallback('WARNING', __('logs.market.zone_loading_retry', ['delay' => $retryDelay]));
                        sleep($retryDelay);

                        continue;
                    }

                    $logCallback('WARNING', __('logs.market.session_expired_retry', ['code' => $errorCode]));
                    $this->authService->resetSession($account);
                    $this->authService->login($account);
                    $this->amfService->invalidateSession($account->id);
                    $this->amfService->resetClient($account->id);
                    $account->refresh();
                    $hasResetSession = true;
                    sleep(2);

                    continue;
                }

                if ($errorCode === 1005) {
                    if ($hasResetSession) {
                        throw new RuntimeException(__('ui.sync.session_intercepted_market', ['code' => $errorCode]));
                    }
                    $logCallback('WARNING', __('logs.market.session_expired_retry', ['code' => $errorCode]));
                    $this->authService->resetSession($account);
                    $this->authService->login($account);
                    $this->amfService->invalidateSession($account->id);
                    $this->amfService->resetClient($account->id);
                    $account->refresh();
                    $hasResetSession = true;
                    sleep(2);

                    continue;
                }

                break;
            } catch (Exception $attemptEx) {
                $logCallback('WARNING', __('logs.market.attempt_failed', ['attempt' => $attempt, 'max' => $maxRetries, 'error' => $attemptEx->getMessage()]));
                if ($attempt === $maxRetries || $this->authService->isCaptchaOr2faError($attemptEx->getMessage())) {
                    throw $attemptEx;
                }
                sleep($retryDelay);
            }
        }

        if ($errorCode !== 0) {
            throw new RuntimeException("Server returned error code {$errorCode} during market sync.");
        }

        return is_array($parsed) ? $parsed : [];
    }

    /**
     * @throws Exception
     */
    private function findPython(): string
    {
        foreach (['python', 'python3', 'py'] as $bin) {
            $out = [];
            $code = 0;
            exec(escapeshellarg($bin).' --version 2>&1', $out, $code);
            if ($code === 0) {
                return $bin;
            }
        }
        throw new RuntimeException('Python not found in system PATH.');
    }
}
