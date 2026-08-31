<?php
declare(strict_types=1);

namespace TestCases;

use Lsr\Core\Links\LanguagePrefixer;
use Lsr\Core\Translations;
use PHPUnit\Framework\TestCase;

class LanguagePrefixerTest extends TestCase
{
    public function testExistingNonDefaultPrefixIsNotDuplicated(): void
    {
        $translations = $this->createStub(Translations::class);
        $translations->method('getLangId')->willReturn('en');
        $translations->method('getDefaultLangId')->willReturn('cs');
        $translations->method('supportsLanguage')->willReturn(true);

        $prefixer = new LanguagePrefixer($translations);

        self::assertSame(['en', 'privacy'], $prefixer->modifyLinkPath(['en', 'privacy']));
    }

    public function testDefaultLocalePrefixIsRemovedAsAnExactSegment(): void
    {
        $translations = $this->createStub(Translations::class);
        $translations->method('getLangId')->willReturn('cs');
        $translations->method('getDefaultLangId')->willReturn('cs');
        $translations->method('supportsLanguage')->willReturn(true);

        $prefixer = new LanguagePrefixer($translations);

        self::assertSame(['privacy'], $prefixer->modifyLinkPath(['cs', 'privacy']));
    }
}
