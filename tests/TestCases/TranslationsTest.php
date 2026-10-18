<?php

declare(strict_types=1);

namespace TestCases;

use InvalidArgumentException;
use Lsr\Core\Config;
use Lsr\Core\Exceptions\InvalidLanguageException;
use Lsr\Core\Translations;
use PHPUnit\Framework\TestCase;
use Stringable;

defined('CHECK_TRANSLATIONS') || define('CHECK_TRANSLATIONS', false);
defined('LANGUAGE_DIR') || define('LANGUAGE_DIR', __DIR__ . '/../languages/');
defined('LANGUAGE_FILE_NAME') || define('LANGUAGE_FILE_NAME', 'translations');
defined('PRODUCTION') || define('PRODUCTION', true);

class TranslationsTest extends TestCase
{
    private Translations $translations;

    protected function setUp(): void {
        $this->translations = new Translations(
            new Config(sys_get_temp_dir()),
            supportedLanguages: ['cs' => 'CZ'],
        );
    }

    public function test_stringable_message_supports_translation_and_formatting(): void {
        $message = new class implements Stringable {
            public function __toString(): string {
                return 'Player %s';
            }
        };

        self::assertSame(
            'Player Ada',
            $this->translations->translate(
                $message,
                format: ['Ada'],
                plural: null,
                num: null,
                domain: null,
            ),
        );
    }

    public function test_numeric_format_keys_use_sprintf(): void {
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

    public function test_string_format_keys_use_vue_gettext_placeholders(): void {
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

    public function test_mixed_format_keys_are_rejected(): void {
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

    public function test_invalid_format_values_are_rejected(): void {
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

    public function test_language_codes_resolve_to_the_configured_country(): void {
        $translations = new Translations(
            new Config(sys_get_temp_dir()),
            defaultLang: 'cs_CZ',
            supportedLanguages: ['cs_CZ', 'en_GB'],
        );

        self::assertSame(['cs' => 'CZ', 'en' => 'GB'], $translations->supportedLanguages);
        self::assertSame('cs_CZ', $translations->getLang());
        $translations->setLang('en_US');
        self::assertSame(['en_GB', 'en', 'GB'], [$translations->getLang(), $translations->getLangId(), $translations->getCountry()]);
        self::assertSame('cs', $translations->getDefaultLangId());
        self::assertTrue($translations->supportsLanguage('en'));
        self::assertFalse($translations->supportsLanguage('de'));
    }

    public function test_unsupported_language_keeps_the_core_exception_type_and_active_language(): void {
        try {
            $this->translations->setLang('de');
            self::fail('Unsupported language was accepted.');
        } catch (InvalidLanguageException $exception) {
            self::assertStringStartsWith('Unsupported language de (de).', $exception->getMessage());
        }
        self::assertSame('cs_CZ', $this->translations->getLang());
    }
}
