<?php

declare(strict_types=1);

namespace TestCases;

use InvalidArgumentException;
use Lsr\Core\Config;
use Lsr\Core\Translations;
use PHPUnit\Framework\TestCase;

defined('CHECK_TRANSLATIONS') || define('CHECK_TRANSLATIONS', false);
defined('LANGUAGE_DIR') || define('LANGUAGE_DIR', __DIR__.'/../languages/');
defined('LANGUAGE_FILE_NAME') || define('LANGUAGE_FILE_NAME', 'translations');
defined('PRODUCTION') || define('PRODUCTION', true);

class TranslationsTest extends TestCase
{
    private Translations $translations;

    protected function setUp(): void
    {
        $this->translations = new Translations(
            new Config(sys_get_temp_dir()),
            supportedLanguages: ['cs' => 'CZ'],
        );
    }

    public function testNumericFormatKeysUseSprintf(): void
    {
        self::assertSame(
            'Player Ada has 42 points',
            $this->translations->translate(
                'Player %s has %d points',
                format: [1 => 'Ada', 3 => 42],
                plural: null,
                num: null,
                domain: null,
            ),
        );
    }

    public function testStringFormatKeysUseVueGettextPlaceholders(): void
    {
        self::assertSame(
            'Player Ada has 42 points; Ada is done (100%)',
            $this->translations->translate(
                'Player %{ player } has %{points} points; %{player} is done (100%)',
                format: ['player' => 'Ada', 'points' => 42],
                plural: null,
                num: null,
                domain: null,
            ),
        );
    }

    public function testMixedFormatKeysAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Format parameters must use either only numeric keys or only string keys.',
        );

        $this->translations->translate(
            'Player %s has %{points} points',
            format: ['Ada', 'points' => 42],
            plural: null,
            num: null,
            domain: null,
        );
    }

    public function testInvalidFormatValuesAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Format parameter values must be scalar or null.');

        $this->translations->translate(
            'Player %{player}',
            format: ['player' => []],
            plural: null,
            num: null,
            domain: null,
        );
    }
}
