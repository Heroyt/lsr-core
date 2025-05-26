<?php

namespace Lsr\Core;

use Gettext\Generator\MoGenerator;
use Gettext\Generator\PoGenerator;
use Gettext\Languages\Language;
use Gettext\Loader\PoLoader;
use Gettext\Translation;
use Lsr\Core\Exceptions\InvalidLanguageException;
use Lsr\Core\Tracy\TranslationTracyPanel;
use Lsr\Helpers\Tools\Timer;
use Nette\Localization\Translator;
use Stringable;
use Tracy\Debugger;

class Translations implements Translator
{

    /** @var array<string,string> */
    public readonly array $supportedLanguages;
    /** @var array<string, string> */
    public readonly array $supportedCountries;

    private string $lang;
    private Language $language;
    private Language $defaultLanguage;

    private string $langId = '';
    private string $country = '';

    private PoLoader $poLoader;

    /** @var array<string,array<string,\Gettext\Translations>> */
    private array $translations = [];
    private bool $loadedAllTranslations = false;
    private bool $translationsChanged = false;

    /**
     * @param  Config  $config
     * @param  string  $defaultLang
     * @param  array<string,string>  $supportedLanguages
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
        if (empty($supportedLanguages)) {
            /** @var string[] $languages */
            $languages = $this->config->getConfig('languages');
            if (empty($languages)) {
                // By default, load all languages in language directory
                $files = glob(LANGUAGE_DIR.'*');
                assert(is_array($files));
                $languages = array_map(
                  static function (string $dir) {
                      return str_replace(LANGUAGE_DIR, '', $dir);
                  },
                  $files
                );
            }

            foreach ($languages as $language) {
                $explode = explode('_', $language);
                if (count($explode) !== 2) {
                    continue;
                }
                [$lang, $country] = $explode;
                $supportedLanguages[$lang] = $country;
            }
        }
        else {
            $modified = [];
            foreach ($supportedLanguages as $lang => $country) {
                $split = explode('_', $country);
                if (count($split) === 2) {
                    [$lang, $country] = $split;
                }
                $modified[$lang] = $country;
            }
            $supportedLanguages = $modified;
        }
        $this->supportedLanguages = $supportedLanguages;

        $supportedCountries = [];
        foreach ($this->supportedLanguages as $country) {
            if (isset(Constants::COUNTRIES[$country])) {
                $supportedCountries[$country] = Constants::COUNTRIES[$country];
            }
        }
        $this->supportedCountries = $supportedCountries;
        $this->setDefaultLanguage($defaultLang);
        $this->setLang($defaultLang);
    }

    /**
     * Get the default language ID (ex. `cs`)
     *
     * @return string
     * @throws InvalidLanguageException
     */
    public function getDefaultLangId() : string {
        $id = $this->getDefaultLanguage()->id;
        $split = explode('_', $id);
        if (count($split) === 2) {
            $id = $split[0];
        }
        return $id;
    }

    /**
     * @return Language
     * @throws InvalidLanguageException
     */
    public function getDefaultLanguage() : Language {
        if (!isset($this->defaultLanguage)) {
            throw new InvalidLanguageException('Default language "'.DEFAULT_LANGUAGE.'" not found');
        }
        return $this->defaultLanguage;
    }

    /**
     * @param  string  $lang
     * @return $this
     * @throws InvalidLanguageException
     */
    public function setDefaultLanguage(string $lang) : Translations {
        $language = $this->findLanguage($lang);
        if (!isset($language)) {
            throw new InvalidLanguageException('Invalid language "'.$lang.'"');
        }
        $this->defaultLanguage = $language;
        return $this;
    }

    public function findLanguage(string $lang) : ?Language {
        return Language::getById($lang);
    }

    /**
     * Get the whole language code (ex. `cs_CZ`)
     *
     * @return string
     */
    public function getLang() : string {
        return $this->lang;
    }

    /**
     * @param  string  $lang
     *
     * @return $this
     * @throws InvalidLanguageException
     */
    public function setLang(string $lang) : Translations {
        if (!isset($this->lang) || $this->lang !== $lang) {
            $language = $this->findLanguage($lang);
            if (!isset($language)) {
                throw new InvalidLanguageException('Invalid language "'.$lang.'"');
            }
            $id = $language->id;
            $split = explode('_', $id);
            if (count($split) === 2) {
                $id = $split[0];
            }
            if (!isset($this->supportedLanguages[$id])) {
                throw new InvalidLanguageException(
                  'Unsupported language '.$lang.' ('.$id.'). Supported languages are: '.implode(
                    ',',
                    array_keys($this->supportedLanguages)
                  )
                );
            }
            $this->language = $language;
            $this->lang = $id.'_'.$this->supportedLanguages[$id];
            $this->langId = $id;
            $this->country = $this->supportedLanguages[$id];
            $this->initLanguage();
        }
        return $this;
    }

    public function supportsLanguage(string $lang) : bool {
        $language = $this->findLanguage($lang);
        if (!isset($language)) {
            return false;
        }
        $id = $language->id;
        $split = explode('_', $id);
        if (count($split) === 2) {
            $id = $split[0];
        }
        return isset($this->supportedLanguages[$id]);
    }

    public function getLanguage() : Language {
        return $this->language;
    }

    public function updateTranslations() : void {
        if (!$this->translationsChanged) {
            return;
        }
        Timer::startIncrementing('translation.update');
        $poGenerator = new PoGenerator();
        $moGenerator = new MoGenerator();
        $templates = [];
        foreach ($this->translations as $lang => $langTranslations) {
            foreach ($langTranslations as $domain => $translation) {
                if (!isset($templates[$domain])) {
                    $templates[$domain] = clone $translation;
                }
                $poGenerator->generateFile($translation, LANGUAGE_DIR.$lang.'/LC_MESSAGES/'.$domain.'.po');
                $moGenerator->generateFile($translation, LANGUAGE_DIR.$lang.'/LC_MESSAGES/'.$domain.'.mo');
            }
        }
        foreach ($templates as $domain => $template) {
            /** @var Translation $string */
            foreach ($template->getTranslations() as $string) {
                $string->translate('');
                $pluralCount = count($string->getPluralTranslations());
                if ($pluralCount > 0) {
                    $plural = [];
                    for ($i = 0; $i < $pluralCount; $i++) {
                        $plural[] = '';
                    }
                    $string->translatePlural(...$plural);
                }
            }
            $poGenerator->generateFile($template, LANGUAGE_DIR.$domain.'.pot');
        }
        Timer::stop('translation.update');
        $this->translationsChanged = false;
    }

    private function getTranslations(string $lang, string $domain = LANGUAGE_FILE_NAME) : \Gettext\Translations {
        $this->translations[$lang] ??= [];
        if (!isset($this->translations[$lang][$domain])) {
            if (!isset($this->poLoader)) {
                $this->poLoader = new PoLoader();
            }

            $file = LANGUAGE_DIR.$lang.'/LC_MESSAGES/'.$domain.'.po';
            if (file_exists($file)) {
                $this->translations[$lang][$domain] = $this->poLoader->loadFile($file);
            }
            else {
                $this->translations[$lang][$domain] = \Gettext\Translations::create($domain, $lang);
            }
        }
        return $this->translations[$lang][$domain];
    }

    public function translate(string | Stringable $message, mixed ...$params) : string {
        if (empty($message)) {
            return '';
        }

        Timer::startIncrementing('translation');

        $context = $params['context'] ?? '';
        // Add context
        if (is_string($context) && !empty($context)) {
            $message = $context."\004".$message;
        }

        assert($params['plural'] === null || is_string($params['plural']));
        $plural = $params['plural'] ?? '';
        assert($params['num'] === null || is_numeric($params['num']));
        $num = (int) ($params['num'] ?? 1);
        assert($params['domain'] === null || is_string($params['domain']));
        $domain = $params['domain'] ?? LANGUAGE_FILE_NAME;

        $translated = $this->translateModular($message, $plural, $num, $domain);

        $split = explode("\004", $translated);
        if (count($split) === 2) {
            $translated = $split[1];
        }

        if (!empty($params['format']) && is_array($params['format'])) {
            assert(
              array_all(
                $params['format'],
                static fn($val) => $val === null || is_string($val) || is_int($val) || is_float($val) || is_bool($val)
              )
            );
            /** @phpstan-ignore argument.type */
            $translated = sprintf($translated, ...$params['format']);
        }

        TranslationTracyPanel::incrementTranslations();
        Timer::stop('translation');

        return $translated;
    }

    private function translateModular(string $message, string $plural, int $num, string $domain) : string {
        /** @phpstan-ignore-next-line */
        if (CHECK_TRANSLATIONS && Debugger::isEnabled()) {
            $split = explode("\004", $message);
            if (count($split) === 2) {
                [$context, $msgTmp] = $split;
            }
            else {
                $msgTmp = $message;
                $context = null;
            }
            foreach ($this->getAllTranslations() as $lang => $langTranslations) {
                if (!isset($langTranslations[$domain])) {
                    $langTranslations[$domain] = \Gettext\Translations::create($domain, $lang);
                }

                $translations = $langTranslations[$domain];
                if (!($translations->find($context, $msgTmp))) {
                    $translation = Translation::create($context, $msgTmp);
                    if ($plural !== '') {
                        $translation->setPlural($plural);
                    }
                    $translations->add($translation);
                    $this->translationsChanged = true;
                }
            }
        }

        if ($domain === LANGUAGE_FILE_NAME) {
            if ($num === 1) {
                return gettext($message);
            }

            return ngettext($message, $plural, $num);
        }

        if ($num === 1) {
            return dgettext($domain, $message);
        }

        return dngettext($domain, $message, $plural, $num);
    }

    /**
     * @return \Gettext\Translations[][]
     */
    private function getAllTranslations() : array {
        if (!$this->loadedAllTranslations) {
            foreach ($this->supportedLanguages as $lang => $country) {
                $langConcat = $lang.'_'.$country;
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
    public function getLangId() : string {
        return $this->langId;
    }

    /**
     * Get the country code (ex. `CZ`)
     *
     * @return string
     */
    public function getCountry() : string {
        return $this->country;
    }

    private function initLanguage() : void {
        // Set target language
        putenv('LANG='.$this->lang);
        putenv('LC_ALL='.$this->lang);
        setlocale(LC_ALL, '0');
        setlocale(
          LC_ALL,
          $this->lang,
          $this->lang.'.UTF8',
          $this->lang.'.UTF-8',
          $this->lang.'.utf-8',
          $this->language->name
        );
        setlocale(
          LC_MESSAGES,
          $this->lang,
          $this->lang.'.UTF8',
          $this->lang.'.UTF-8',
          $this->lang.'.utf-8',
          $this->language->name
        );
        bindtextdomain(LANGUAGE_FILE_NAME, substr(LANGUAGE_DIR, 0, -1));
        bind_textdomain_codeset(LANGUAGE_FILE_NAME, "UTF-8");

        foreach ($this->textDomains as $textdomain) {
            bindtextdomain($textdomain, substr(LANGUAGE_DIR, 0, -1));
            bind_textdomain_codeset($textdomain, "UTF-8");
        }

        textdomain(LANGUAGE_FILE_NAME);
    }


}