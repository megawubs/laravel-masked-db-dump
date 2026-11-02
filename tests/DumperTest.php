<?php

namespace BeyondCode\LaravelMaskedDumper\Tests;

use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use BeyondCode\LaravelMaskedDumper\DumpSchema;
use BeyondCode\LaravelMaskedDumper\LaravelMaskedDumpServiceProvider;
use BeyondCode\LaravelMaskedDumper\TableDefinitions\TableDefinition;
use Faker\Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

class DumperTest extends TestCase
{
    use MatchesSnapshots;

    protected function getPackageProviders($app)
    {
        return [LaravelMaskedDumpServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    #[Test]
    public function it_can_dump_all_tables_without_modifications()
    {
        $this->loadLaravelMigrations();

        DB::table('users')
            ->insert([
                'name' => 'Marcel',
                'email' => 'marcel@beyondco.de',
                'password' => 'test',
                'created_at' => '2021-01-01 00:00:00',
                'updated_at' => '2021-01-01 00:00:00',
            ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()->allTables();

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    #[Test]
    public function it_can_mask_user_names()
    {
        $this->loadLaravelMigrations();

        DB::table('users')
            ->insert([
                'name' => 'Marcel',
                'email' => 'marcel@beyondco.de',
                'password' => 'test',
                'created_at' => '2021-01-01 00:00:00',
                'updated_at' => '2021-01-01 00:00:00',
            ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->allTables()
            ->table('users', function (TableDefinition $table) {
                $table->mask('name');
            });

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    #[Test]
    public function it_can_replace_columns_with_static_values()
    {
        $this->loadLaravelMigrations();

        DB::table('users')
            ->insert([
                'name' => 'Marcel',
                'email' => 'marcel@beyondco.de',
                'password' => 'test',
                'created_at' => '2021-01-01 00:00:00',
                'updated_at' => '2021-01-01 00:00:00',
            ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->allTables()
            ->table('users', function (TableDefinition $table) {
                $table->replace('password', 'test');
            });

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    #[Test]
    public function it_can_replace_columns_with_faker_values()
    {
        $this->loadLaravelMigrations();

        DB::table('users')
            ->insert([
                'name' => 'Marcel',
                'email' => 'marcel@beyondco.de',
                'password' => 'test',
                'created_at' => '2021-01-01 00:00:00',
                'updated_at' => '2021-01-01 00:00:00',
            ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->allTables()
            ->table('users', function (TableDefinition $table, Generator $faker) {
                $faker->seed(1);
                $table->replace('email', $faker->safeEmail());
            });

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    #[Test]
    public function it_can_dump_certain_tables_as_schema_only()
    {
        $this->loadLaravelMigrations();

        DB::table('users')
            ->insert([
                'name' => 'Marcel',
                'email' => 'marcel@beyondco.de',
                'password' => 'test',
                'created_at' => '2021-01-01 00:00:00',
                'updated_at' => '2021-01-01 00:00:00',
            ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->allTables();

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    #[Test]
    public function it_does_remove_excluded_tables_from_allTables()
    {
        $this->loadLaravelMigrations();

        DB::table('users')
            ->insert([
                'name' => 'Marcel',
                'email' => 'marcel@beyondco.de',
                'password' => 'test',
                'created_at' => '2021-01-01 00:00:00',
                'updated_at' => '2021-01-01 00:00:00',
            ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->allTables()
            ->exclude('users');

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    #[Test]
    public function it_does_remove_excluded_array_of_tables_from_allTables()
    {
        $this->loadLaravelMigrations();

        DB::table('users')
            ->insert([
                'name' => 'Marcel',
                'email' => 'marcel@beyondco.de',
                'password' => 'test',
                'created_at' => '2021-01-01 00:00:00',
                'updated_at' => '2021-01-01 00:00:00',
            ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->allTables()
            ->exclude(['users']);

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    #[Test]
    public function it_creates_chunked_insert_statements_for_a_table()
    {
        $this->loadLaravelMigrations();

        DB::table('users')
            ->insert(['name' => 'Marcel1', 'email' => 'marcel1@beyondco.de', 'password' => 'test',
                'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00',
            ]);
        DB::table('users')
            ->insert(['name' => 'Marcel2', 'email' => 'marcel2@beyondco.de', 'password' => 'test',
                'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00',
            ]);
        DB::table('users')
            ->insert(['name' => 'Marcel3', 'email' => 'marcel3@beyondco.de', 'password' => 'test',
                'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00',
            ]);
        DB::table('users')
            ->insert(['name' => 'Marcel4', 'email' => 'marcel4@beyondco.de', 'password' => 'test',
                'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00',
            ]);
        DB::table('users')
            ->insert(['name' => 'Marcel5', 'email' => 'marcel5@beyondco.de', 'password' => 'test',
                'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00',
            ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->allTables()
            ->table('users', function($table) {
                return $table->outputInChunksOf(3);
            });

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    #[Test]
    public function it_starts_a_new_insert_statement_before_the_statement_size_limit_is_exceeded()
    {
        $this->loadLaravelMigrations();

        DB::table('users')
            ->insert(['name' => 'Marcel1', 'email' => 'marcel1@beyondco.de', 'password' => 'test',
                'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00',
            ]);
        DB::table('users')
            ->insert(['name' => 'Marcel2', 'email' => 'marcel2@beyondco.de', 'password' => 'test',
                'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00',
            ]);
        DB::table('users')
            ->insert(['name' => 'Marcel3', 'email' => 'marcel3@beyondco.de', 'password' => 'test',
                'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00',
            ]);

        $outputFile = base_path('test.sql');

        // The values of one of these rows take 105 bytes, so two rows fit in 250 bytes and three do not
        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->allTables()
            ->table('users', function($table) {
                return $table->outputInChunksOf(2000)->limitStatementSizeTo(250);
            });

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    #[Test]
    public function it_can_include_individual_unmodified_tables()
    {
        $this->loadLaravelMigrations();

        DB::table('users')
            ->insert([
                'name' => 'Marcel',
                'email' => 'marcel@beyondco.de',
                'password' => 'test',
                'created_at' => '2021-01-01 00:00:00',
                'updated_at' => '2021-01-01 00:00:00',
            ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->include('users');

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    #[Test]
    public function it_can_include_an_array_of_unmodified_tables()
    {
        $this->loadLaravelMigrations();

        DB::table('users')
            ->insert([
                'name' => 'Marcel',
                'email' => 'marcel@beyondco.de',
                'password' => 'test',
                'created_at' => '2021-01-01 00:00:00',
                'updated_at' => '2021-01-01 00:00:00',
            ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->include(['users', 'migrations']);

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    #[Test]
    public function it_still_includes_tables_with_repeated_use_of_include()
    {
        $this->loadLaravelMigrations();

        DB::table('users')
            ->insert([
                'name' => 'Marcel',
                'email' => 'marcel@beyondco.de',
                'password' => 'test',
                'created_at' => '2021-01-01 00:00:00',
                'updated_at' => '2021-01-01 00:00:00',
            ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->include('users')
            ->include('migrations');

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    #[Test]
    public function it_can_specify_the_schema(): void
    {
        $mock = $this->partialMock(DumpSchema::class, function (MockInterface $mock) {
            $mock->shouldReceive('load')->with('my_schema')->once();
        });

        $this->app['config']['masked-dump.default'] = $mock;

        $outputFile = base_path('test.sql');

        $this->artisan('db:masked-dump', [
            'output' => $outputFile,
            '--schema' => 'my_schema',
        ]);
    }

    #[Test]
    public function it_writes_the_same_dump_when_streaming_to_the_output_file(): void
    {
        $this->loadLaravelMigrations();

        DB::table('users')->insert([
            ['name' => 'Marcel1', 'email' => 'marcel1@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
            ['name' => 'Marcel2', 'email' => 'marcel2@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
        ]);

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->allTables();

        $this->artisan('db:masked-dump', [
            'output' => base_path('test.sql'),
        ]);

        $this->artisan('db:masked-dump', [
            'output' => base_path('test-streamed.sql'),
            '--stream' => true,
        ]);

        $this->assertSame(file_get_contents(base_path('test.sql')), file_get_contents(base_path('test-streamed.sql')));
    }

    #[Test]
    public function it_writes_the_same_dump_when_streaming_to_a_gzipped_output_file(): void
    {
        $this->loadLaravelMigrations();

        DB::table('users')->insert([
            ['name' => 'Marcel1', 'email' => 'marcel1@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
            ['name' => 'Marcel2', 'email' => 'marcel2@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
        ]);

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->allTables();

        $this->artisan('db:masked-dump', [
            'output' => base_path('test.sql'),
        ]);

        $this->artisan('db:masked-dump', [
            'output' => base_path('test-streamed.sql.gz'),
            '--stream' => true,
            '--gzip' => true,
        ]);

        $this->assertSame(file_get_contents(base_path('test.sql')), gzdecode(file_get_contents(base_path('test-streamed.sql.gz'))));
    }

    #[Test]
    public function it_dumps_values_with_characters_that_need_escaping_so_they_import_unchanged(): void
    {
        $this->loadLaravelMigrations();

        $names = [
            'quote' => "O'Brien",
            'backslash' => 'back\\slash',
            'null byte' => "null\0byte",
            'empty string' => '',
        ];

        DB::table('users')->insert([
            ['name' => $names['quote'], 'email' => 'quote@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
            ['name' => $names['backslash'], 'email' => 'backslash@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
            ['name' => $names['null byte'], 'email' => 'null-byte@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
            ['name' => $names['empty string'], 'email' => 'empty-string@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
        ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->include('users');

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        DB::table('users')->delete();
        DB::unprepared(file_get_contents($outputFile));

        $this->assertSame(
            array_values($names),
            DB::table('users')->orderBy('id')->pluck('name')->all()
        );
    }

    #[Test]
    public function it_dumps_no_rows_of_a_table_whose_query_is_limited_to_zero_rows(): void
    {
        $this->loadLaravelMigrations();

        DB::table('users')->insert([
            ['name' => 'Marcel1', 'email' => 'marcel1@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
            ['name' => 'Marcel2', 'email' => 'marcel2@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
        ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->include('users')
            ->table('users', function (TableDefinition $table) {
                $table->query(fn (Builder $query) => $query->limit(0));
            });

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertStringNotContainsString('marcel1@beyondco.de', file_get_contents($outputFile));
        $this->assertStringNotContainsString('marcel2@beyondco.de', file_get_contents($outputFile));
    }

    #[Test]
    public function it_dumps_only_the_limited_number_of_rows_when_the_table_is_read_in_chunks_smaller_than_the_limit(): void
    {
        $this->loadLaravelMigrations();

        DB::table('users')->insert([
            ['name' => 'Marcel1', 'email' => 'marcel1@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
            ['name' => 'Marcel2', 'email' => 'marcel2@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
            ['name' => 'Marcel3', 'email' => 'marcel3@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
        ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->include('users')
            ->table('users', function (TableDefinition $table) {
                $table->queryInChunksOf(1)
                    ->query(fn (Builder $query) => $query->limit(2));
            });

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $dump = file_get_contents($outputFile);

        $this->assertStringContainsString('marcel1@beyondco.de', $dump);
        $this->assertStringContainsString('marcel2@beyondco.de', $dump);
        $this->assertStringNotContainsString('marcel3@beyondco.de', $dump);
    }

    #[Test]
    public function it_skips_the_offset_rows_when_the_table_is_read_in_chunks_smaller_than_the_offset(): void
    {
        $this->loadLaravelMigrations();

        DB::table('users')->insert([
            ['name' => 'Marcel1', 'email' => 'marcel1@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
            ['name' => 'Marcel2', 'email' => 'marcel2@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
            ['name' => 'Marcel3', 'email' => 'marcel3@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
        ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->include('users')
            ->table('users', function (TableDefinition $table) {
                $table->queryInChunksOf(1)
                    ->query(fn (Builder $query) => $query->offset(1)->limit(10));
            });

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $dump = file_get_contents($outputFile);

        $this->assertStringNotContainsString('marcel1@beyondco.de', $dump);
        $this->assertStringContainsString('marcel2@beyondco.de', $dump);
        $this->assertStringContainsString('marcel3@beyondco.de', $dump);
    }

    #[Test]
    public function it_dumps_every_row_once_when_the_query_orders_by_another_column_than_the_key(): void
    {
        $this->loadLaravelMigrations();

        // The names sort in the reverse order of the ids, so an order by name conflicts with paginating by id.
        DB::table('users')->insert([
            ['name' => 'Marcel3', 'email' => 'marcel3@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
            ['name' => 'Marcel2', 'email' => 'marcel2@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
            ['name' => 'Marcel1', 'email' => 'marcel1@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
        ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->include('users')
            ->table('users', function (TableDefinition $table) {
                $table->queryInChunksOf(1)
                    ->query(fn (Builder $query) => $query->orderBy('name'));
            });

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $dump = file_get_contents($outputFile);

        $this->assertSame(1, substr_count($dump, 'marcel1@beyondco.de'));
        $this->assertSame(1, substr_count($dump, 'marcel2@beyondco.de'));
        $this->assertSame(1, substr_count($dump, 'marcel3@beyondco.de'));
    }

    #[Test]
    public function it_dumps_every_matching_row_once_when_the_query_filters_and_the_table_is_read_in_chunks(): void
    {
        $this->loadLaravelMigrations();

        DB::table('users')->insert([
            ['name' => 'Marcel1', 'email' => 'marcel1@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
            ['name' => 'Marcel2', 'email' => 'marcel2@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
            ['name' => 'Marcel3', 'email' => 'marcel3@beyondco.de', 'password' => 'test', 'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00'],
        ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->include('users')
            ->table('users', function (TableDefinition $table) {
                $table->queryInChunksOf(1)
                    ->query(fn (Builder $query) => $query->where('name', '!=', 'Marcel2'));
            });

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $dump = file_get_contents($outputFile);

        $this->assertSame(1, substr_count($dump, 'marcel1@beyondco.de'));
        $this->assertSame(0, substr_count($dump, 'marcel2@beyondco.de'));
        $this->assertSame(1, substr_count($dump, 'marcel3@beyondco.de'));
    }

    #[Test]
    public function it_dumps_every_row_once_of_a_table_with_a_composite_primary_key_read_in_chunks(): void
    {
        Schema::create('role_user', function (Blueprint $table) {
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('user_id');
            $table->primary(['role_id', 'user_id']);
        });

        DB::table('role_user')->insert([
            ['role_id' => 1, 'user_id' => 1],
            ['role_id' => 1, 'user_id' => 2],
            ['role_id' => 2, 'user_id' => 1],
        ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->include('role_user')
            ->table('role_user', function (TableDefinition $table) {
                $table->queryInChunksOf(1);
            });

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        DB::table('role_user')->delete();
        DB::unprepared(file_get_contents($outputFile));

        $this->assertSame(
            [['role_id' => 1, 'user_id' => 1], ['role_id' => 1, 'user_id' => 2], ['role_id' => 2, 'user_id' => 1]],
            DB::table('role_user')->orderBy('role_id')->orderBy('user_id')->get()->map(fn ($row) => (array) $row)->all()
        );
    }

    #[Test]
    public function it_replaces_the_value_of_a_redacted_column_with_a_redacted_marker(): void
    {
        $this->loadLaravelMigrations();

        DB::table('users')->insert([
            'name' => 'Marcel',
            'email' => 'marcel@beyondco.de',
            'password' => 'test',
            'created_at' => '2021-01-01 00:00:00',
            'updated_at' => '2021-01-01 00:00:00',
        ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->include('users')
            ->table('users', function (TableDefinition $table) {
                $table->redact('name');
            });

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        DB::table('users')->delete();
        DB::unprepared(file_get_contents($outputFile));

        $this->assertSame('[redacted]', DB::table('users')->value('name'));
    }
}
