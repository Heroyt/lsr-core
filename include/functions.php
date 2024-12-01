<?php
declare(strict_types=1);


use Lsr\Core\App;

if (!function_exists('lang')) {
    /**
     * Wrapper for gettext function
     *
     * @param  string|null  $msg  Massage to translate
     * @param  string|null  $plural
     * @param  int  $num
     * @param  string|null  $context
     * @param  string|null  $domain
     * @param  array  $format
     * @return string Translated message
     *
     * @version 1.0
     * @author  Tomáš Vojík <vojik@wboy.cz>
     */
    function lang(
      ?string $msg = null,
      ?string $plural = null,
      int     $num = 1,
      ?string $context = null,
      ?string $domain = null,
      array   $format = []
    ) : string {
        return App::getInstance()->translations->translate(
                   $msg,
          plural : $plural,
          num    : $num,
          domain : $domain,
          context: $context,
          format : $format
        );
    }
}

if (!function_exists('updateTranslations')) {
    /**
     * Regenerate the translation .po files
     */
    function updateTranslations() : void {
        App::getInstance()->translations->updateTranslations();
    }
}