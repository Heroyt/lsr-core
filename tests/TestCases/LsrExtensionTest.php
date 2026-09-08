<?php

declare(strict_types=1);

namespace TestCases;

use Lsr\Core\DI\LsrExtension;
use Lsr\Core\Http\TracyExceptionHandler;
use Nette\DI\Compiler;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Processor;
use PHPUnit\Framework\TestCase;

final class LsrExtensionTest extends TestCase
{
    public function test_translation_service_is_discoverable_by_tag(): void {
        $compiler = new Compiler();
        $extension = new LsrExtension();
        $compiler->addExtension('lsr', $extension);
        $config = (new Processor())->process($extension->getConfigSchema(), [
            'appDir' => ROOT,
            'tempDir' => TMP_DIR,
            'http' => [
                'exceptionHandlers' => [new Statement(TracyExceptionHandler::class)],
            ],
        ]);
        self::assertIsObject($config);
        $extension->setConfig($config);
        $extension->loadConfiguration();

        $builder = $compiler->getContainerBuilder();
        self::assertArrayHasKey(
            $builder->getAliases()['translations'],
            $builder->findByTag('translations'),
        );
    }
}
