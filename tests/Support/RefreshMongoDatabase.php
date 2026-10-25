<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Migrates the test database once per process and, before every later test, restores the documents the
 * migrations left (e.g. the default groups). Collections and their indexes stay in place, which is far
 * cheaper than DatabaseMigrations running migrate:fresh around every single test.
 */
trait RefreshMongoDatabase
{
    /** Called by Laravel's setUpTraits(). */
    public function setUpRefreshMongoDatabase(): void
    {
        $database = DB::connection()->getDatabase();

        if (! app()->environment('testing') || $database->getDatabaseName() !== 'testing') {
            throw new RuntimeException("Refusing to reset the '{$database->getDatabaseName()}' database outside of tests.");
        }

        if (RefreshMongoDatabaseState::$documents === null) {
            $this->artisan('migrate:fresh');

            $documents = [];

            foreach ($database->listCollectionNames() as $name) {
                $documents[$name] = iterator_to_array($database->selectCollection($name)->find(), false);
            }

            RefreshMongoDatabaseState::$documents = $documents;

            return;
        }

        foreach ($database->listCollectionNames() as $name) {
            if (! array_key_exists($name, RefreshMongoDatabaseState::$documents)) {
                $database->dropCollection($name);

                continue;
            }

            $collection = $database->selectCollection($name);
            $collection->deleteMany([]);

            if (RefreshMongoDatabaseState::$documents[$name] !== []) {
                $collection->insertMany(RefreshMongoDatabaseState::$documents[$name]);
            }
        }
    }
}
