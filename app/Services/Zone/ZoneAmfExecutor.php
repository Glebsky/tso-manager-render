<?php

declare(strict_types=1);

namespace App\Services\Zone;

use Exception;

/**
 * Service responsible for executing parse_zone.py Python script against raw AMF binary data.
 */
class ZoneAmfExecutor
{
    /**
     * Parse raw AMF binary bytes into a decoded associative array.
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function execute(string $rawAmf): array
    {
        $scriptPath = storage_path('app/parse_zone.py');
        if (! file_exists($scriptPath)) {
            throw new Exception('parse_zone.py not found in storage/app/. Please deploy the script.');
        }

        $tmpFile = storage_path('app/temp_zone_'.uniqid().'.amf');
        file_put_contents($tmpFile, $rawAmf);

        try {
            $pythonBin = $this->findPython();
            $command = escapeshellarg($pythonBin).' '.escapeshellarg($scriptPath).' '.escapeshellarg($tmpFile);
            $output = [];
            $exitCode = 0;

            exec($command.' 2>&1', $output, $exitCode);

            $outputStr = implode("\n", $output);

            if ($exitCode !== 0) {
                throw new Exception("parse_zone.py failed (exit {$exitCode}): {$outputStr}");
            }

            $result = json_decode($outputStr, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Failed to parse zone JSON: '.json_last_error_msg().'. Raw output: '.substr($outputStr, 0, 500));
            }

            return is_array($result) ? $result : [];
        } finally {
            @unlink($tmpFile);
        }
    }

    /**
     * Locate a working Python binary.
     */
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
        throw new Exception('Python not found. Install Python and ensure it is on PATH.');
    }
}
