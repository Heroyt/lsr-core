<?php

namespace Lsr\Core\Models\Interfaces;

use Dibi\Row;

interface InsertExtendInterface
{

    /**
     * Parse data from DB into the object
     *
     * @param  Row  $row  Row from DB
     *
     * @return static|null
     */
    public static function parseRow(Row $row) : ?static;

    /**
     * Add data from the object into the data array for DB INSERT/UPDATE
     *
     * @param  array<string, mixed>  $data
     */
    public function addQueryData(array &$data) : void;

}