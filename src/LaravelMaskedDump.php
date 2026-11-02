<?php

namespace BeyondCode\LaravelMaskedDumper;

use Generator;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\LazyCollection;
use Illuminate\Database\Query\Expression;
use BeyondCode\LaravelMaskedDumper\TableDefinitions\TableDefinition;

class LaravelMaskedDump
{
    protected DumpSchema $definition;

    protected OutputStyle $output;

    public function __construct(DumpSchema $definition, OutputStyle $output)
    {
        $this->definition = $definition;
        $this->output = $output;
    }

    public function dump(): string
    {
        return implode('', iterator_to_array($this->generate()));
    }

    public function generate(): Generator
    {
        $tables = $this->definition->getDumpTables();

        $overallTableProgress = $this->output->createProgressBar(count($tables));

        foreach ($tables as $table) {
            if ($table->shouldDumpData()) {
                foreach ($this->streamTableData($table) as $statement) {
                    yield $statement;
                }
            }

            $overallTableProgress->advance();
        }
    }

    protected function transformResultForInsert($row, TableDefinition $table, Builder $query): array
    {
        return collect($row)->map(function ($value, $column) use ($table, $query) {
            if ($columnDefinition = $table->findColumn($column)) {
                $value = $columnDefinition->modifyValue($value);
            }

            return new Expression($query->grammar->escape($value, binary: str_contains($value, "\00")));

        })->toArray();
    }

    protected function streamTableData(TableDefinition $table): Generator
    {
        $queryBuilder = $this->definition->getConnection()->table($table->getDoctrineTable()->getName());

        $table->modifyQuery($queryBuilder);

        $iterator = $this->rowsOf($queryBuilder, $table);

        $rows = [];
        $rowsSize = 0;
        foreach ($iterator as $row) {
            $insertRow = $this->transformResultForInsert((array)$row, $table, $queryBuilder);
            $rowSize = $this->sizeOfRow($insertRow, $queryBuilder);

            if ($rows !== [] && $this->exceedsMaxStatementSize($table, $rowsSize + $rowSize)) {
                yield $this->compileRawInsert($rows, $queryBuilder);
                $rows = [];
                $rowsSize = 0;
            }

            $rows[] = $insertRow;
            $rowsSize += $rowSize;

            if (count($rows) >= $table->getChunkSize()) {
                yield $this->compileRawInsert($rows, $queryBuilder);
                $rows = [];
                $rowsSize = 0;
            }
        }

        if (empty($rows)) {
            return;
        }

        yield $this->compileRawInsert($rows, $queryBuilder);
    }

    /**
     * The number of bytes the row adds to the values of an insert statement: its escaped values,
     * the ", " between them and the "(" and "), " around them.
     */
    private function sizeOfRow(array $insertRow, Builder $queryBuilder): int
    {
        $valuesSize = array_sum(array_map(
            fn (Expression $value): int => strlen((string) $value->getValue($queryBuilder->grammar)),
            $insertRow
        ));

        return $valuesSize + 2 * count($insertRow) + 2;
    }

    private function exceedsMaxStatementSize(TableDefinition $table, int $rowsSize): bool
    {
        return $table->getMaxStatementSize() !== null
            && $rowsSize > $table->getMaxStatementSize();
    }

    private function compileRawInsert(array $rows, Builder $queryBuilder): string
    {
        $rawInsert = $queryBuilder->grammar->compileInsert(
            $queryBuilder, $rows
        );

        return $rawInsert . ';' . PHP_EOL;
    }

    private function rowsOf(Builder $queryBuilder, TableDefinition $table): LazyCollection
    {
        return $this->canPaginateByKey($table, $queryBuilder)
            ? $queryBuilder->lazyById(chunkSize: $table->getQueryChunkSize(), column: $table->getKey())
            : $queryBuilder->cursor();
    }

    private function canPaginateByKey(TableDefinition $table, Builder $queryBuilder): bool
    {
        return $table->getKey() !== null
            && $queryBuilder->getLimit() === null
            && empty($queryBuilder->orders);
    }
}
