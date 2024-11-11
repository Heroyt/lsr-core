<?php
/**
 * @noinspection PhpUnreachableStatementInspection
 * @noinspection PhpInconsistentReturnPointsInspection
 */

declare(strict_types=1);

namespace Lsr\Core\Templating\Nodes;

use Generator;

trait NoIteratorNode
{

    public function &getIterator() : Generator {
        return;
        /** @phpstan-ignore deadCode.unreachable */
        yield;
    }
}