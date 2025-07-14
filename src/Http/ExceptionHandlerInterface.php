<?php
declare(strict_types=1);

namespace Lsr\Core\Http;

use Lsr\Core\Requests\Request;
use Psr\Http\Message\ResponseInterface;

interface ExceptionHandlerInterface
{

    /**
     * Checks if the handler can handle the given exception.
     */
    public function handles(\Throwable $exception) : bool;

    /**
     * Handles the given exception and returns a response.
     */
    public function handle(\Throwable $exception, Request $request) : ResponseInterface;

}