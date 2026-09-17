<?php

namespace App\Database;

use Illuminate\Database\Migrations\DatabaseMigrationRepository;

class CentralMigrationRepository extends DatabaseMigrationRepository
{
    public const LEDGER_CONNECTION = 'dbsincro';

    public function setSource($name)
    {
        parent::setSource(self::LEDGER_CONNECTION);
    }

    public function getConnection()
    {
        return $this->resolver->connection(self::LEDGER_CONNECTION);
    }
}
