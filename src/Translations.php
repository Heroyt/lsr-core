<?php

declare(strict_types=1);

namespace Lsr\Core;

use Gettext\Generator\MoGenerator;
use Gettext\Generator\PoGenerator;
use Gettext\Languages\Language;
use Gettext\Loader\PoLoader;
use Gettext\Translation;
use Lsr\Core\Exceptions\InvalidLanguageException;
use Lsr\Core\Tracy\TranslationTracyPanel;
use Lsr\Helpers\Tools\Timer;
use Lsr\Translations\GettextEnvironment;
use Lsr\Translations\GettextTranslator;
use Lsr\Translations\InvalidLanguageException as LanguageException;
use Lsr\Translations\Localization;
use Lsr\Translations\MessageFormatter;
use Lsr\Translations\SupportedLanguages;
use Nette\Localization\Translator;
use Stringable;
use Tracy\Debugger;

/**
 * LSR application translations: `lsr/translations` native gettext runtime bound to `LANGUAGE_DIR` and
 * `LANGUAGE_FILE_NAME`, plus development collection of missing messages (`CHECK_TRANSLATIONS`).
 */
class Translations implements Translator
{
    /** @var array<string,string> */
    public readonly array $supportedLanguages;
    /** @var array<string, string> */
    public readonly array $supportedCountries;

    private readonly Localization $localization;
    private readonly GettextTranslator $translator;

    private PoLoader $poLoader;

    /** @var array<string,array<string,\Gettext\Translations>> */
    private array $translations = [];
    private bool $loadedAllTranslations = false;
    /** @var array<string, true> Domains with collected messages not yet written by updateTranslations(). */
    private array $changedDomains = [];

    /**
     * @param  Config  $config
     * @param  string  $defaultLang
     * @param  array<string|int,string>  $supportedLanguages
     * @param  string[]  $textDomains
     *
     * @throws InvalidLanguageException
     */
    public function __construct(
        private readonly Config $config,
        string                  $defaultLang = 'cs_CZ',
        array                   $supportedLanguages = [],
        public readonly array   $textDomains = [],
    ) {
        $languages = new SupportedLanguages(
            empty($supportedLanguages) ? $this->discoverLanguages() : $supportedLanguages,
        );
        $this->supportedLanguages = $languages->languages;

        $supportedCountries = [];
        foreach ($this->supportedLanguages as $country) {
            if (isset(Constants::COUNTRIES[$country])) {
                $supportedCountries[$country] = Constants::COUNTRIES[$country];
            }
        }
        $this->supportedCountries = $supportedCountries;

        $environment = new GettextEnvironment(
            LANGUAGE_FILE_NAME,
            LANGUAGE_DIR,
            array_fill_keys($this->textDomains, LANGUAGE_DIR),
        );
        $this->localization = self::guard(
            static fn (): Localization => new Localization($languages, $environment, $defaultLang),
        );
        $this->translator = new GettextTranslator(LANGUAGE_FILE_NAME);
    }

    /**
     * Get the default language ID (ex. `cs`)
     *
     * @return string
     */
    public function getDefaultLangId(): string {
        return $this->localization->defaultLanguageId();
    }

    public function getDefaultLanguage(): Language {
        return $this->localization->defaultLanguage();
    }

    /**
     * @param  string  $lang
     * @return $this
     * @throws InvalidLanguageException
     */
    public function setDefaultLanguage(string $lang): Translations {
        self::guard(fn () => $this->localization->setDefaultLanguage($lang));
        return $this;
    }

    public function findLanguage(string $lang): ?Language {
        return Language::getById($lang);
    }

    /**
     * Get the whole language code (ex. `cs_CZ`)
     *
     * @return string
     */
    public function getLang(): string {
        return $this->localization->locale()->code;
    }

    /**
     * @param  string  $lang
     *
     * @return $this
     * @throws InvalidLanguageException
     */
    public function setLang(string $lang): Translations {
        self::guard(fn () => $this->localization->setLocale($lang));
        return $this;
    }

    public function supportsLanguage(string $lang): bool {
        return $this->localization->supports($lang);
    }

    public function getLanguage(): Language {
        return $this->localization->locale()->language;
    }

    /**
     * Write messages collected with `CHECK_TRANSLATIONS` to the PO, MO and POT files of their domains.
     */
    public function updateTranslations(): void {
        if ($this->changedDomains === []) {
            return;
        }
        Timer::startIncrementing('translation.update');
        $poGenerator = new PoGenerator();
        $moGenerator = new MoGenerator();
        $templates = [];
        foreach ($this->translations as $lang => $langTranslations) {
            foreach ($langTranslations as $domain => $translation) {
                if ( ! isset($this->changedDomains[$domain])) {
                    continue;
                }
                if ( ! isset($templates[$domain])) {
                    $templates[$domain] = clone $translation;
                }
                $poGenerator->generateFile($translation, LANGUAGE_DIR . $lang . '/LC_MESSAGES/' . $domain . '.po');
                $moGenerator->generateFile($translation, LANGUAGE_DIR . $lang . '/LC_MESSAGES/' . $domain . '.mo');
            }
        }
        foreach ($templates as $domain => $template) {
            /** @var Translation $string */
            foreach ($template->getTranslations() as $string) {
                $string->translate('');
                $pluralCount = count($string->getPluralTranslations());
                if ($pluralCount > 0) {
                    $string->translatePlural(...array_fill(0, $pluralCount, ''));
                }
            }
            $poGenerator->generateFile($template, LANGUAGE_DIR . $domain . '.pot');
        }
        Timer::stop('translation.update');
        $this->changedDomains = [];
    }

    private function getTranslations(string $lang, string $domain = LANGUAGE_FILE_NAME): \Gettext\Translations {
        $this->translations[$lang] ??= [];
        if ( ! isset($this->translations[$lang][$domain])) {
            if ( ! isset($this->poLoader)) {
                $this->poLoader = new PoLoader();
            }

            $file = LANGUAGE_DIR . $lang . '/LC_MESSAGES/' . $domain . '.po';
            if (file_exists($file)) {
                $this->translations[$lang][$domain] = $this->poLoader->loadFile($file);
            } else {
                $this->translations[$lang][$domain] = \Gettext\Translations::create($domain, $lang);
            }
        }
        return $this->translations[$lang][$domain];
    }

    public function translate(string | Stringable $message, mixed ...$params): string {
        if (empty($message)) {
            return '';
        }

        Timer::startIncrementing('translation');

        $context = $params['context'] ?? null;
        $context = is_string($context) && $context !== '' ? $context : null;
        $plural = $params['plural'] ?? null;
        assert($plural === null || is_string($plural));
        $num = $params['num'] ?? 1;
        assert(is_numeric($num));
        $domain = $params['domain'] ?? LANGUAGE_FILE_NAME;
        assert(is_string($domain));
        $format = $params['format'] ?? [];
        $format = is_array($format) ? $format : [];
        MessageFormatter::assertParameters($format);

        /** @phpstan-ignore-next-line */
        if (CHECK_TRANSLATIONS && Debugger::isEnabled()) {
            $this->collect((string) $message, $plural, $context, $domain);
        }

        $translated = $this->translator->translate($message, $plural, (int) $num, $context, $domain, $format);

        TranslationTracyPanel::incrementTranslations();
        Timer::stop('translation');

        return $translated;
    }

    /**
     * Add a message missing from the loaded PO files of a configured domain.
     */
    private function collect(string $message, ?string $plural, ?string $context, string $domain): void {
        foreach ($this->getAllTranslations() as $langTranslations) {
            $translations = $langTranslations[$domain] ?? null;
            if ($translations === null || $translations->find($context, $message) !== null) {
                continue;
            }
            $translation = Translation::create($context, $message);
            if ($plural !== null && $plural !== '') {
                $translation->setPlural($plural);
            }
            $translations->add($translation);
            $this->changedDomains[$domain] = true;
        }
    }

    /**
     * @return \Gettext\Translations[][]
     */
    private function getAllTranslations(): array {
        if ( ! $this->loadedAllTranslations) {
            foreach ($this->supportedLanguages as $lang => $country) {
                $langConcat = $lang . '_' . $country;
                $this->translations[$langConcat][LANGUAGE_FILE_NAME] = $this->getTranslations($langConcat);
                foreach ($this->textDomains as $textdomain) {
                    $this->translations[$langConcat][$textdomain] = $this->getTranslations($langConcat, $textdomain);
                }
            }
            $this->loadedAllTranslations = true;
        }
        return $this->translations;
    }

    /**
     * Get the short language code (ex. `cs`)
     *
     * @return string
     */
    public function getLangId(): string {
        return $this->localization->locale()->languageId;
    }

    /**
     * Get the country code (ex. `CZ`)
     *
     * @return string
     */
    public function getCountry(): string {
        return $this->localization->locale()->country;
    }

    /**
     * Languages from the `languages` config, or every `language_COUNTRY` directory in `LANGUAGE_DIR`.
     *
     * @return list<string>
     */
    private function discoverLanguages(): array {
        /** @var string[] $languages */
        $languages = $this->config->getConfig('languages');
        if (empty($languages)) {
            $files = glob(LANGUAGE_DIR . '*');
            assert(is_array($files));
            $languages = array_map(
                static fn (string $dir): string => str_replace(LANGUAGE_DIR, '', $dir),
                $files,
            );
        }

        return array_values(
            array_filter($languages, static fn (string $language): bool => count(explode('_', $language)) === 2),
        );
    }

    /**
     * @template T
     * @param  callable(): T  $operation
     * @return T
     * @throws InvalidLanguageException
     */
    private static function guard(callable $operation): mixed {
        try {
            return $operation();
        } catch (LanguageException $exception) {
            throw new InvalidLanguageException($exception->getMessage(), previous: $exception);
        }
    }
}
