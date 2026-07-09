<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Exception;

class ZoneParserService
{
    /**
     * Parse raw AMF zone response into structured JSON.
     *
     * Writes the raw AMF to a temp file, shells out to Python (pyamf),
     * and returns the parsed data as an array.
     *
     * @param  string $rawAmf  Raw AMF binary response
     * @return array           ['buildings' => [...], 'specialists' => [...], 'buffs' => [...]]
     * @throws Exception
     */
    public function parse(string $rawAmf): array
    {
        // Ensure the Python script exists
        $scriptPath = storage_path('app/parse_zone.py');
        if (!file_exists($scriptPath)) {
            throw new Exception('parse_zone.py not found in storage/app/. Please deploy the script.');
        }

        // Write raw AMF to a temp file
        $tmpFile = storage_path('app/temp_zone_' . uniqid() . '.amf');
        file_put_contents($tmpFile, $rawAmf);

        try {
            $pythonBin = $this->findPython();
            $command   = escapeshellarg($pythonBin) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($tmpFile);
            $output    = [];
            $exitCode  = 0;

            exec($command . ' 2>&1', $output, $exitCode);

            $outputStr = implode("\n", $output);

            if ($exitCode !== 0) {
                throw new Exception("parse_zone.py failed (exit {$exitCode}): {$outputStr}");
            }

            $result = json_decode($outputStr, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Failed to parse zone JSON: ' . json_last_error_msg() . '. Raw output: ' . substr($outputStr, 0, 500));
            }

            return $result;
        } finally {
            @unlink($tmpFile);
        }
    }

    /**
     * Locate a working Python binary.
     */
    private function findPython(): string
    {
        // Try common locations
        $candidates = ['python', 'python3', 'py'];
        foreach ($candidates as $bin) {
            $out  = [];
            $code = 0;
            exec(escapeshellarg($bin) . ' --version 2>&1', $out, $code);
            if ($code === 0) {
                return $bin;
            }
        }
        throw new Exception('Python not found. Install Python and ensure it is on PATH.');
    }
}
