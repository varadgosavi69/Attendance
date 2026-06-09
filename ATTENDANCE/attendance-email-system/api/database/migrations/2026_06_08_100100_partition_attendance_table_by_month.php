<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phase 5 — SCALABLE_ARCHITECTURE.md Section 3: partition `attendance` by
 * month (RANGE on TO_DAYS(attendance_date)) so monthly/dashboard queries only
 * scan the relevant partitions as the table grows.
 *
 * The architecture doc's illustrative `ALTER TABLE ... PARTITION BY` glosses
 * over two MySQL/InnoDB constraints this migration has to satisfy first:
 *   - A partitioned InnoDB table cannot carry FOREIGN KEY constraints (and
 *     cannot be referenced by one), so the three FKs on `attendance` are
 *     dropped before partitioning and restored on rollback.
 *   - Every unique key — including the PRIMARY KEY — must contain every
 *     column used in the partitioning expression, so the PK is widened from
 *     (attendance_id) to (attendance_id, attendance_date).
 */
return new class extends Migration
{
    private const TABLE = 'attendance';

    private const FOREIGN_KEYS = [
        'student_id' => ['table' => 'students', 'column' => 'student_id'],
        'subject_id' => ['table' => 'subjects', 'column' => 'subject_id'],
        'faculty_id' => ['table' => 'faculty', 'column' => 'faculty_id'],
    ];

    public function up(): void
    {
        if ($this->isPartitioned()) {
            return;
        }

        foreach ($this->existingForeignKeys() as $name) {
            DB::statement('ALTER TABLE `'.self::TABLE."` DROP FOREIGN KEY `{$name}`");
        }

        DB::statement(
            'ALTER TABLE `'.self::TABLE.'` DROP PRIMARY KEY, ADD PRIMARY KEY (attendance_id, attendance_date)'
        );

        DB::statement(
            'ALTER TABLE `'.self::TABLE.'` PARTITION BY RANGE (TO_DAYS(attendance_date)) ('.$this->partitionDefinitions().')'
        );
    }

    public function down(): void
    {
        if (! $this->isPartitioned()) {
            return;
        }

        DB::statement('ALTER TABLE `'.self::TABLE.'` REMOVE PARTITIONING');

        DB::statement(
            'ALTER TABLE `'.self::TABLE.'` DROP PRIMARY KEY, ADD PRIMARY KEY (attendance_id)'
        );

        foreach (self::FOREIGN_KEYS as $column => $reference) {
            DB::statement(sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s`(`%s`)',
                self::TABLE,
                "attendance_{$column}_foreign",
                $column,
                $reference['table'],
                $reference['column'],
            ));
        }
    }

    private function isPartitioned(): bool
    {
        return (bool) DB::table('information_schema.partitions')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', self::TABLE)
            ->whereNotNull('partition_name')
            ->exists();
    }

    /** @return list<string> constraint names of FKs currently defined on `attendance` */
    private function existingForeignKeys(): array
    {
        return DB::table('information_schema.table_constraints')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', self::TABLE)
            ->where('constraint_type', 'FOREIGN KEY')
            ->pluck('constraint_name')
            ->all();
    }

    /**
     * One partition per calendar month, spanning from the month of the
     * earliest attendance row (or the current month, for an empty table)
     * through twelve months past the latest row, plus a MAXVALUE catch-all
     * so inserts beyond the planned range never fail.
     */
    private function partitionDefinitions(): string
    {
        $bounds = DB::table(self::TABLE)
            ->selectRaw('MIN(attendance_date) as min_date, MAX(attendance_date) as max_date')
            ->first();

        $start = $bounds?->min_date ? Carbon::parse($bounds->min_date)->startOfMonth() : Carbon::now()->startOfMonth();
        $end = $bounds?->max_date ? Carbon::parse($bounds->max_date)->startOfMonth() : $start->copy();
        $end = $end->copy()->addMonths(12);

        $definitions = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $boundary = $cursor->copy()->addMonthNoOverflow()->startOfMonth();

            $definitions[] = sprintf(
                "PARTITION p_%s VALUES LESS THAN (TO_DAYS('%s'))",
                $cursor->format('Y_m'),
                $boundary->format('Y-m-d'),
            );

            $cursor = $boundary;
        }

        $definitions[] = 'PARTITION p_future VALUES LESS THAN MAXVALUE';

        return implode(', ', $definitions);
    }
};
