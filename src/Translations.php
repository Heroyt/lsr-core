<?php

namespace Lsr\Core;

use Gettext\Generator\MoGenerator;
use Gettext\Generator\PoGenerator;
use Gettext\Languages\Language;
use Gettext\Loader\PoLoader;
use Gettext\Translation;
use Lsr\Core\Exceptions\InvalidLanguageException;
use Lsr\Helpers\Tools\Timer;
use Lsr\Helpers\Tracy\TranslationTracyPanel;
use Nette\Localization\Translator;
use Stringable;

class Translations implements Translator
{

    /** @var array<string,string> */
    public readonly array $supportedLanguages;
    /** @var array<string, string> */
    public readonly array $supportedCountries;
    private string $lang;
    private Language $language;

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
      private readonly array  $textDomains = [],
    ) {
        if (empty($supportedLanguages)) {
            /** @var string[] $languages */
            $languages = $this->config->getConfig('languages');
            if (empty($languages)) {
                // By default, load all languages in language directory
                /** @var string[] $files */
                $files = glob(LANGUAGE_DIR.'*');
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
        $this->supportedLanguages = $supportedLanguages;
        $supportedCountries = [];
        foreach ($this->supportedLanguages as $country) {
            if (isset(Constants::COUNTRIES[$country])) {
                $supportedCountries[$country] = Constants::COUNTRIES[$country];
            }
        }
        $this->supportedCountries = $supportedCountries;
        $this->setLang($defaultLang);
    }

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
        if ($this->lang !== $lang) {
            $language = $this->findLanguage($lang);
            if (!isset($language)) {
                throw new InvalidLanguageException('Invalid language "'.$lang.'"');
            }
            if (!isset($this->supportedLanguages[$language->id])) {
                {
                    throw new InvalidLanguageException('Unsupported language');
                }
            }
            $this->language = $language;
            $this->lang = $language->id.'_'.$this->supportedLanguages[$language->id];
            $this->initLanguage();
        }
        return $this;
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
                $poGenerator->generateFile($template, LANGUAGE_DIR.$domain.'.pot');
            }
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
            $this->translations[$lang][$domain] = $this->poLoader->loadFile($file);
        }
        return $this->translations[$lang][$domain];
    }

    public function translate(string | Stringable $message, mixed ...$params) : string {
        if (empty($message)) {
            return '';
        }

        Timer::startIncrementing('translation');

        $context = $params['context'] ?? '';
        $msgTmp = $message;
        // Add context
        if (!empty($context)) {
            $message = $context."\004".$message;
        }

        $plural = (string) ($params['plural'] ?? $message);
        $num = (int) ($params['num'] ?? 1);
        $domain = (string) ($params['domain'] ?? LANGUAGE_FILE_NAME);

        $translated = $this->translateModular($message, $plural, $num, $domain);

        // If the translation with the context does not exist, try to translate it without it
        $split = explode("\004", $translated);
        if (count($split) === 2) {
            $translated = $this->translateModular($split[1], $plural, $num, $domain);
        }

        TranslationTracyPanel::incrementTranslations();
        Timer::stop('translation');

        return $translated;
    }

    private function translateModular(string $message, string $plural, int $num, string $domain) : string {
        /** @phpstan-ignore-next-line */
        if (!PRODUCTION && CHECK_TRANSLATIONS) {
            $split = explode("\004", $message);
            if (count($split) === 2) {
                [$context, $msgTmp] = $split;
            }
            else {
                $msgTmp = $message;
                $context = null;
            }
            foreach ($this->getAllTranslations() as $langTranslations) {
                foreach ($langTranslations as $translations) {
                    if (!($translation = $translations->find($context, $msgTmp))) {
                        $translation = Translation::create($context, $msgTmp);
                        if ($plural !== null) {
                            $translation->setPlural($plural);
                        }
                        $translations->add($translation);
                        $this->translationsChanged = true;
                    }
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

    private function findLanguage(string $lang) : ?Language {
        return Language::getById($lang);
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
}