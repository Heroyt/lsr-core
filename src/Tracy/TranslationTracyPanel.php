<?php
/**
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

namespace Lsr\Core\Tracy;

use Lsr\Core\App;
use Lsr\Core\Tracy\Events\TranslationEvent;
use Tracy\Dumper;
use Tracy\IBarPanel;

class TranslationTracyPanel implements IBarPanel
{

    /** @var TranslationEvent[] */
    static public array $events = [];
    static public int $translations = 0;

    public static function logEvent(TranslationEvent $event) : void {
        self::$events[] = $event;
    }

    public static function incrementTranslations() : void {
        self::$translations++;
    }

    /**
     * @inheritDoc
     */
    public function getTab() : string {
        $title = lang('Translations', context: 'debugPanel');
        return <<<HTML
        <span title="Překlady">
            <svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="512" height="512" x="0" y="0" viewBox="0 0 512 512"
                 style="enable-background:new 0 0 512 512" xml:space="preserve" class=""><g><path
                            xmlns="http://www.w3.org/2000/svg"
                            d="m353.082031 83.128906-50.554687-36.109375c-3.949219-26.570312-26.914063-47.019531-54.5625-47.019531h-192.796875c-30.421875 0-55.167969 24.746094-55.167969 55.164062v192.804688c0 30.417969 24.746094 55.164062 55.167969 55.164062h168.699219c8.285156 0 15-6.714843 15-15 0-4.144531-1.675782-7.894531-4.390626-10.605468l43.046876-43.050782c2.714843 2.714844 6.464843 4.394532 10.605468 4.394532 8.285156 0 15-6.71875 15-15v-80.652344l49.953125-35.679688c3.941407-2.8125 6.28125-7.359374 6.28125-12.203124 0-4.847657-2.339843-9.394532-6.28125-12.207032zm0 0"
                            fill="#00d8e0" data-original="#00d8e0" style="" class=""></path><path
                            xmlns="http://www.w3.org/2000/svg"
                            d="m353.082031 83.128906-50.554687-36.109375c-3.949219-26.570312-26.914063-47.019531-54.5625-47.019531h-95.328125v303.132812h71.230469c8.285156 0 15-6.714843 15-15 0-4.144531-1.675782-7.894531-4.390626-10.605468l43.046876-43.050782c2.714843 2.714844 6.464843 4.394532 10.605468 4.394532 8.285156 0 15-6.71875 15-15v-80.652344l49.953125-35.679688c3.941407-2.8125 6.28125-7.359374 6.28125-12.203124 0-4.847657-2.339843-9.394532-6.28125-12.207032zm0 0"
                            fill="#00acb3" data-original="#00acb3" style="" class=""></path><path
                            xmlns="http://www.w3.org/2000/svg"
                            d="m197.652344 193.859375c-11.988282 0-23.207032-3.3125-32.8125-9.070313 11.585937-12.503906 19.289062-28.648437 21.152344-46.515624h11.660156c4.667968 0 8.457031-3.785157 8.457031-8.457032s-3.789063-8.460937-8.457031-8.460937h-37.628906v-20.539063c0-4.675781-3.789063-8.460937-8.460938-8.460937-4.667969 0-8.457031 3.785156-8.457031 8.460937v20.539063h-37.628907c-4.667968 0-8.457031 3.789062-8.457031 8.460937s3.789063 8.457032 8.457031 8.457032h11.660157c1.859375 17.867187 9.5625 34.011718 21.148437 46.515624-9.601562 5.75-20.820312 9.070313-32.808594 9.070313-4.671874 0-8.460937 3.785156-8.460937 8.457031s3.789063 8.460938 8.460937 8.460938c17.113282 0 32.996094-5.355469 46.085938-14.453125 13.089844 9.101562 28.972656 14.453125 46.085938 14.453125 4.671874 0 8.460937-3.789063 8.460937-8.460938s-3.789063-8.457031-8.457031-8.457031zm-28.707032-55.585937c-1.839843 13.867187-8.125 26.339843-17.382812 35.941406-9.253906-9.601563-15.539062-22.074219-17.378906-35.941406zm0 0"
                            fill="#fef4f5" data-original="#fef4f5" style=""></path><path xmlns="http://www.w3.org/2000/svg"
                                                                                         d="m456.835938 208.867188h-192.800782c-30.417968 0-55.164062 24.746093-55.164062 55.164062v104.75l-49.953125 35.679688c-3.941407 2.8125-6.28125 7.359374-6.28125 12.203124 0 4.847657 2.339843 9.394532 6.28125 12.207032l50.554687 36.109375c3.949219 26.570312 26.914063 47.019531 54.5625 47.019531h192.800782c30.417968 0 55.164062-24.746094 55.164062-55.167969v-192.800781c0-30.417969-24.746094-55.164062-55.164062-55.164062zm0 0"
                                                                                         fill="#54e360" data-original="#54e360"
                                                                                         style="" class=""></path><path
                            xmlns="http://www.w3.org/2000/svg"
                            d="m456.835938 208.867188h-96.402344v303.132812h96.402344c30.417968 0 55.164062-24.746094 55.164062-55.167969v-192.800781c0-30.417969-24.746094-55.164062-55.164062-55.164062zm0 0"
                            fill="#00ab5e" data-original="#00ab5e" style=""></path><path xmlns="http://www.w3.org/2000/svg"
                                                                                         d="m315.683594 410.96875c0-.324219.164062-.964844.324218-1.609375l31.011719-101.0625c1.445313-4.820313 7.386719-7.074219 13.332031-7.074219 6.109376 0 12.054688 2.253906 13.5 7.074219l31.007813 101.0625c.160156.644531.324219 1.125.324219 1.609375 0 4.976562-7.550782 8.671875-13.175782 8.671875-3.535156 0-6.265624-1.121094-7.070312-4.175781l-6.105469-21.371094h-36.796875l-6.105468 21.371094c-.800782 3.054687-3.53125 4.175781-7.066407 4.175781-5.625 0-13.179687-3.535156-13.179687-8.671875zm58.652344-33.265625-13.984376-49.324219-13.976562 49.324219zm0 0"
                                                                                         fill="#fef4f5" data-original="#fef4f5"
                                                                                         style=""></path><g
                            xmlns="http://www.w3.org/2000/svg" fill="#d5eded"><path
                                d="m197.652344 138.273438c4.667968 0 8.457031-3.785157 8.457031-8.457032s-3.789063-8.460937-8.457031-8.460937h-37.628906v-20.539063c0-4.308594-3.222657-7.855468-7.386719-8.382812v45.839844h16.308593c-1.769531 13.320312-7.644531 25.347656-16.308593 34.785156v23.980468c12.882812 8.65625 28.359375 13.738282 45.015625 13.738282 4.667968 0 8.457031-3.789063 8.457031-8.460938s-3.789063-8.457031-8.457031-8.457031c-11.988282 0-23.207032-3.316406-32.8125-9.070313 11.585937-12.503906 19.289062-28.648437 21.152344-46.515624zm0 0"
                                fill="#d5eded" data-original="#d5eded" style=""></path><path
                                d="m404.859375 409.359375-31.007813-101.0625c-1.441406-4.800781-7.339843-7.054687-13.417968-7.070313v27.4375l13.898437 49.039063h-13.898437v16.390625h18.398437l6.105469 21.371094c.804688 3.054687 3.535156 4.175781 7.070312 4.175781 5.625 0 13.175782-3.691406 13.175782-8.671875 0-.484375-.160156-.964844-.324219-1.609375zm0 0"
                                fill="#d5eded" data-original="#d5eded" style=""></path></g></g></svg>
            <span class="tracy-label">$title</span>
        </span>
        HTML;

    }

    /**
     * @inheritDoc
     */
    public function getPanel() : string {
        $title = lang('Translations', context: 'debugPanel');
        $languageTitle = lang('Language', context: 'debugPanel');
        $language = App::getInstance()->getLanguage();
        $translations = App::getInstance()->translations;
        $activeDump = Dumper::toHtml($translations->getLang());
        $languageDump = Dumper::toHtml($language);
        $supportedDump = Dumper::toHtml($translations->supportedLanguages);
        $accept = '';
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && is_string($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $accept = '<p><strong>HTTP header:</strong> '.$_SERVER['HTTP_ACCEPT_LANGUAGE'].'</p>';
        }
        $panel = <<<HTML
        <h1>{$title}</h1>
        <div class="tracy-inner">
            <div class="tracy-inner-container">
                <p><strong>{$languageTitle}:</strong></p>
                <div class="p-3 my-2 border rounded">
                    <h5>{$language->name}</h5>
                    {$accept}
                    {$activeDump}
                    {$languageDump}
                    {$supportedDump}
                </div>
        HTML;
        $panel .= '<p><strong>Translated strings:</strong> '.self::$translations.'</p>';
        foreach (self::$events as $event) {
            $panel .= '<div class="p-3 my-2 rounded border"><h5 class="my-1 fs-5">Added a new string:</h5><p>String: '.$event->message.'</p>';
            if (!empty($event->plural)) {
                $panel .= '<p>Plural: '.$event->plural.'</p>';
            }
            if (!empty($event->domain)) {
                $panel .= '<p>Domain: '.$event->domain.'</p>';
            }
            if (!empty($event->context)) {
                $panel .= '<p>Context: '.$event->context.'</p>';
            }
            $panel .= '<div class="p-1 rounded bg-secondary text-light w-100">'.$event->source.'</div></div>';
        }
        $panel .= '</div></div>';
        return $panel;
    }
}