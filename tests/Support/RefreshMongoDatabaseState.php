<?php

namespace Tests\Support;

/**
 * Process-wide state of RefreshMongoDatabase; a static property on the trait would exist once per test class.
 */
final class RefreshMongoDatabaseState
{
    /** @var array<string, list<object>>|null collection name => documents right after migrating; null until the first test migrated */
    public static ?array $documents = null;
}
