<?php
declare(strict_types=1);

namespace Lsr\Core\Links;

use Lsr\Core\Exceptions\InvalidLanguageException;
use Lsr\Core\Translations;

readonly class LanguagePrefixer implements LinkModifier
{

    public function __construct(
      protected Translations $translations,
    ) {}

    /**
     * @inheritDoc
     */
    public function modifyLinkPath(array $link) : array {
        if (isset($link['lang'])) {
            $lang = $link['lang'];
            unset($link['lang']);
        }
        else {
            $lang = $this->translations->getLangId();
        }

        // If the link contains a "[lang]", remove it
        if (isset($link[0]) && preg_match('/^\[lang(?:=[^\[]*)?]$/', (string) $link[0])) {
            array_shift($link);
        }

        // Remove an existing language prefix before applying the configured policy.
        if (
          isset($link[0])
          && preg_match('/^[a-z]{2,3}$/', (string) $link[0])
          && $this->translations->supportsLanguage((string) $link[0])
        ) {
            array_shift($link);
        }

        // Default language does not need a prefix
        try {
            if ($lang === $this->translations->getDefaultLangId()) {
                return $link;
            }
        } catch (InvalidLanguageException) {
            // Ignore
        }

        // Add language prefix
        array_unshift($link, $lang);
        return $link;
    }
}