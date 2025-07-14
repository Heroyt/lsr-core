<?php
declare(strict_types=1);

namespace Lsr\Core\Http;

interface AsyncHandlerInterface
{

    public function run() : void;

}