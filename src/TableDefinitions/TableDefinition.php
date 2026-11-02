<?php

namespace BeyondCode\LaravelMaskedDumper\TableDefinitions;

use BeyondCode\LaravelMaskedDumper\Contracts\Column;
use BeyondCode\LaravelMaskedDumper\ColumnDefinitions\ColumnDefinition;
use Doctrine\DBAL\Schema\Table;

class TableDefinition
{
    const DUMP_FULL = 'full';
    const DUMP_SCHEMA = 'schema';

    protected $table;
    protected $dumpType;
    protected $query;
    protected $columns = [];
    protected $chunkSize = 0;
    protected $queryChunkSize = 2000;
    protected ?int $maxStatementSize = null;

    protected ?array $primary = null;

    public function __construct(Table $table, ?array $index = null)
    {
        $this->table = $table;
        $this->dumpType = static::DUMP_FULL;
        $this->primary = $index;
    }

    public function fullDump()
    {
        $this->dumpType = static::DUMP_FULL;

        return $this;
    }

    public function outputInChunksOf(int $chunkSize)
    {
        $this->chunkSize = $chunkSize;

        return $this;
    }

    /**
     * Start a new insert statement before its values exceed the given number of bytes,
     * so every statement fits within the max_allowed_packet of the server that imports the dump.
     * A single row larger than the limit still gets a statement of its own.
     */
    public function limitStatementSizeTo(int $bytes)
    {
        $this->maxStatementSize = $bytes;

        return $this;
    }

    public function queryInChunksOf(int $chunkSize)
    {
        $this->queryChunkSize = $chunkSize;

        return $this;
    }

    public function query(callable $callable)
    {
        $this->query = $callable;
    }

    public function mask(string $column, string $maskCharacter = 'x')
    {
        $this->columns[$column] = ColumnDefinition::mask($column, $maskCharacter);

        return $this;
    }

    public function replace(string $column, $replacer)
    {
        $this->columns[$column] = ColumnDefinition::replace($column, $replacer);

        return $this;
    }

    public function redact(string $column)
    {
        $this->columns[$column] = ColumnDefinition::replace($column, '[redacted]');

        return $this;
    }

    /**
     * @param string $column
     * @return Column|null
     */
    public function findColumn(string $column)
    {
        if (array_key_exists($column, $this->columns)) {
            return $this->columns[$column];
        }

        return false;
    }

    public function getChunkSize()
    {
        return $this->chunkSize;
    }

    public function getMaxStatementSize(): ?int
    {
        return $this->maxStatementSize;
    }

    public function getQueryChunkSize()
    {
        return $this->queryChunkSize;
    }

    public function getDoctrineTable()
    {
        return $this->table;
    }

    public function shouldDumpData()
    {
        return $this->dumpType === static::DUMP_FULL;
    }

    public function modifyQuery($query)
    {
        if (is_null($this->query)) {
            return;
        }
        call_user_func($this->query, $query);
    }

    public function getKey(): ?string
    {
        $columns = $this->primary['columns'] ?? null;

        if ($columns === null) {
            return null;
        }

        if (count($columns) === 1) {
            return $columns[0];
        }

        return null;
    }
}
