<?php

namespace Illuminate\Tests\Database;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

class DatabaseSchemaBuilderIntegrationTest extends TestCase
{
    protected $db;

    /**
     * Bootstrap database.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->db = $db = new DB;

        $db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $db->setAsGlobal();

        $container = new Container;
        $container->instance('db', $db->getDatabaseManager());
        Facade::setFacadeApplication($container);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
    }

    public function testDropAllTablesWorksWithForeignKeys()
    {
        $this->db->connection()->getSchemaBuilder()->create('table1', function (Blueprint $table) {
            $table->integer('id');
            $table->string('name');
        });

        $this->db->connection()->getSchemaBuilder()->create('table2', function (Blueprint $table) {
            $table->integer('id');
            $table->string('user_id');
            $table->foreign('user_id')->references('id')->on('table1');
        });

        $this->assertTrue($this->db->connection()->getSchemaBuilder()->hasTable('table1'));
        $this->assertTrue($this->db->connection()->getSchemaBuilder()->hasTable('table2'));

        $this->db->connection()->getSchemaBuilder()->dropAllTables();

        $this->assertFalse($this->db->connection()->getSchemaBuilder()->hasTable('table1'));
        $this->assertFalse($this->db->connection()->getSchemaBuilder()->hasTable('table2'));
    }

    public function testHasColumnWithTablePrefix()
    {
        $this->db->connection()->setTablePrefix('test_');

        $this->db->connection()->getSchemaBuilder()->create('table1', function (Blueprint $table) {
            $table->integer('id');
            $table->string('name');
        });

        $this->assertTrue($this->db->connection()->getSchemaBuilder()->hasColumn('table1', 'name'));
    }

    public function testTableHasIndexTablePrefix()
    {
        $this->db->connection()->setTablePrefix('test_');

        $this->schemaBuilder()->create('pandemic_table', function (Blueprint $table) {
            $table->id();
            $table->string('stay_home')->index();
            $table->string('covid19');
            $table->string('wear_mask');
            $table->unique(['wear_mask', 'covid19']);
        });
        $this->assertTrue($this->schemaBuilder()->hasIndex('pandemic_table', 'id', 'primary'));
        $this->assertTrue($this->schemaBuilder()->hasIndex('pandemic_table', 'stay_home'));
        $this->assertTrue(
            $this->schemaBuilder()->hasIndex(
                'pandemic_table',
                ['wear_mask', 'covid19'],
                'pandemic_table_wear_mask_covid19_unique'
            )
        );
        $this->assertFalse($this->schemaBuilder()->hasIndex('pandemic_table', ['wear_mask']));
        $this->assertFalse($this->schemaBuilder()->hasIndex('pandemic_table', ['wear_mask'], 'primary'));
    }

    public function testTableHasForeignKeyTablePrefix()
    {
        $this->db->connection()->setTablePrefix('test_');
        // Images table
        $this->schemaBuilder()->create('images_table', function (Blueprint $table) {
            $table->id();
            $table->string('image_name')->index();
        });
        // Countries table
        $this->schemaBuilder()->create('countries_table', function (Blueprint $table) {
            $table->id();
            $table->string('country_name')->index();
            $table->bigInteger('image_id');
        });
        // users table
        $this->schemaBuilder()->create('users_table', function (Blueprint $table) {
            $table->id();
            $table->string('username')->index();
            $table->foreignId('country_id')->references('id')->on('countries_table')->cascadeOnDelete();
            $table->foreignId('image_id')->references('image_id')->on('countries_table')->cascadeOnDelete();
        });
        $this->assertTrue($this->schemaBuilder()->hasForeignKey('users_table','country_id','countries_table','id'));
        $this->assertTrue($this->schemaBuilder()->hasForeignKey('users_table','image_id','countries_table','image_id'));
        $this->assertFalse($this->schemaBuilder()->hasForeignKey('users_table','id','countries_table','image_id'));
        $this->assertFalse($this->schemaBuilder()->hasForeignKey('users_table','image_id','images_table','id'));
    }

    public function testTableHasForeignKeyWithoutTablePrefix()
    {
        // Images table
        $this->schemaBuilder()->create('images_table', function (Blueprint $table) {
            $table->id();
            $table->string('image_name')->index();
        });
        // Countries table
        $this->schemaBuilder()->create('countries_table', function (Blueprint $table) {
            $table->id();
            $table->string('country_name')->index();
            $table->bigInteger('image_id');
        });
        // users table
        $this->schemaBuilder()->create('users_table', function (Blueprint $table) {
            $table->id();
            $table->string('username')->index();
            $table->foreignId('country_id')->references('id')->on('countries_table')->cascadeOnDelete();
            $table->foreignId('image_id')->references('image_id')->on('countries_table')->cascadeOnDelete();
        });
        $this->assertTrue($this->schemaBuilder()->hasForeignKey('users_table','country_id','countries_table','id'));
        $this->assertTrue($this->schemaBuilder()->hasForeignKey('users_table','image_id','countries_table','image_id'));
        $this->assertFalse($this->schemaBuilder()->hasForeignKey('users_table','id','countries_table','image_id'));
        $this->assertFalse($this->schemaBuilder()->hasForeignKey('users_table','image_id','images_table','id'));
    }

    public function testHasColumnAndIndexWithPrefixIndexDisabled()
    {
        $this->db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => 'example_',
            'prefix_indexes' => false,
        ]);

        $this->db->connection()->getSchemaBuilder()->create('table1', function (Blueprint $table) {
            $table->integer('id');
            $table->string('name')->index();
        });

        $this->assertArrayHasKey('table1_name_index', $this->db->connection()->getDoctrineSchemaManager()->listTableIndexes('example_table1'));
    }

    public function testHasColumnAndIndexWithPrefixIndexEnabled()
    {
        $this->db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => 'example_',
            'prefix_indexes' => true,
        ]);

        $this->db->connection()->getSchemaBuilder()->create('table1', function (Blueprint $table) {
            $table->integer('id');
            $table->string('name')->index();
        });

        $this->assertArrayHasKey('example_table1_name_index', $this->db->connection()->getDoctrineSchemaManager()->listTableIndexes('example_table1'));
    }

    public function testDropColumnWithTablePrefix()
    {
        $this->db->connection()->setTablePrefix('test_');

        $this->schemaBuilder()->create('pandemic_table', function (Blueprint $table) {
            $table->integer('id');
            $table->string('stay_home');
            $table->string('covid19');
            $table->string('wear_mask');
        });

        // drop single columns
        $this->assertTrue($this->schemaBuilder()->hasColumn('pandemic_table', 'stay_home'));
        $this->schemaBuilder()->dropColumns('pandemic_table', 'stay_home');
        $this->assertFalse($this->schemaBuilder()->hasColumn('pandemic_table', 'stay_home'));

        // drop multiple columns
        $this->assertTrue($this->schemaBuilder()->hasColumn('pandemic_table', 'covid19'));
        $this->schemaBuilder()->dropColumns('pandemic_table', ['covid19', 'wear_mask']);
        $this->assertFalse($this->schemaBuilder()->hasColumn('pandemic_table', 'wear_mask'));
        $this->assertFalse($this->schemaBuilder()->hasColumn('pandemic_table', 'covid19'));
    }

    private function schemaBuilder()
    {
        return $this->db->connection()->getSchemaBuilder();
    }
}
