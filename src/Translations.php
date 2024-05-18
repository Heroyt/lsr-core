<?php

namespace Lsr\Core;

use Gettext\Languages\Language;
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

    public function translate(string | Stringable $message, mixed ...$params) : string {
        if (empty($message)) {
            return '';
        }

        Timer::startIncrementing('translation');

        $msgTmp = $message;
        // Add context
        if (!empty($params['context'])) {
            $message = $params['context']."\004".$message;
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

}