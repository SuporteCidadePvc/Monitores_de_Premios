<?php

namespace App\Domain\Operations;

interface OdbcConnectionProbe
{
    /**
     * @return array{production: 'ok'|'unavailable', dashboard: 'ok'|'unavailable'}
     */
    public function check(): array;
}
