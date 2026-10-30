<?php

declare(strict_types=1);

namespace LSlim\Tests\Integration\Validation;

use Illuminate\Database\Query\Builder;
use LSlim\Tests\Support\MySqlTestCase;
use LSlim\Validation\Rules\ExistsInTable;

final class ExistsInTableTest extends MySqlTestCase
{
    public function testChecksExistenceAndAppliesAdditionalConditions(): void
    {
        $table = 'lslim_exists_' . bin2hex(random_bytes(8));
        $this->connection->statement("CREATE TEMPORARY TABLE `{$table}` (id INT PRIMARY KEY, active BOOLEAN NOT NULL)");
        $this->connection->table($table)->insert([['id' => 1, 'active' => true], ['id' => 2, 'active' => false]]);

        $any = new ExistsInTable($this->connection, $table, 'id');
        $active = new ExistsInTable($this->connection, $table, 'id', static function (Builder $query): void {
            $query->where('active', true);
        });
        self::assertTrue($any->isValid(1));
        self::assertTrue($any->isValid(2));
        self::assertFalse($any->isValid(999));
        self::assertTrue($active->isValid(1));
        self::assertFalse($active->isValid(2));
    }
}
