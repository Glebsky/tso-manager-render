<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixPostgresSequencesCommand extends Command
{
    protected $signature = 'tso:fix-sequences';

    protected $description = 'Resynchronize PostgreSQL auto-increment sequences with current table MAX(id).';

    public function handle(): int
    {
        $driver = DB::getDriverName();

        if ($driver !== 'pgsql') {
            $this->info("Current database driver is '{$driver}'. Sequence resynchronization is only applicable to PostgreSQL.");

            return self::SUCCESS;
        }

        $this->info('Resynchronizing PostgreSQL sequences...');

        /** @var list<object{table_name: string, column_name: string}> $columns */
        $columns = DB::select("
            SELECT table_name, column_name
            FROM information_schema.columns
            WHERE table_schema = 'public' AND column_name = 'id'
            ORDER BY table_name
        ");

        $fixedCount = 0;

        foreach ($columns as $column) {
            $tableName = (string) $column->table_name;
            $columnName = (string) $column->column_name;

            /** @var list<object{seq: string|null}> $seqResult */
            $seqResult = DB::select('SELECT pg_get_serial_sequence(?, ?) AS seq', [$tableName, $columnName]);
            $seqName = $seqResult[0]->seq ?? null;

            if ($seqName === null) {
                continue;
            }

            /** @var list<object{max_id: int|null}> $maxResult */
            $maxResult = DB::select(sprintf('SELECT COALESCE(MAX("%s"), 0) AS max_id FROM "%s"', $columnName, $tableName));
            $maxId = (int) ($maxResult[0]->max_id ?? 0);

            if ($maxId > 0) {
                DB::statement('SELECT setval(?, ?, true)', [$seqName, $maxId]);
            } else {
                DB::statement('SELECT setval(?, 1, false)', [$seqName]);
            }

            $this->line("  ✓ Table <comment>{$tableName}</comment>: sequence <info>{$seqName}</info> set to <info>{$maxId}</info>");
            $fixedCount++;
        }

        $this->info("Completed. Resynchronized {$fixedCount} sequence(s).");

        return self::SUCCESS;
    }
}
