<?php
declare(strict_types=1);

namespace Lsr\Core\DI;

use Gettext\Languages\Language;
use Latte\Engine;
use Lsr\Core\App;
use Lsr\Core\Config;
use Lsr\Core\Links\Generator;
use Lsr\Core\Menu\MenuBuilder;
use Lsr\Core\RouteHandler;
use Lsr\Core\Session;
use Lsr\Core\Templating\Latte;
use Lsr\Core\Templating\LatteExtension;
use Lsr\Core\Templating\TranslatorExtension;
use Lsr\Core\Translations;
use Lsr\Helpers\Csrf\TokenHelper;
use Nette;
use Nette\DI\CompilerExtension;
use Nette\Schema\Expect;

/**
 * @property-read object{
 *     appDir: non-empty-string,
 *     tempDir: non-empty-string,
 *     translations: object{
 *     defaultLang: non-empty-string,
 *      supportedLanguages: non-empty-string[],
 *      domains: string[]
 *     },
 *     latte: object{
 *      tempDir?:string
 *     }
 *  } $config
 */
class LsrExtension extends CompilerExtension
{

    public function getConfigSchema() : Nette\Schema\Schema {
        return Expect::structure(
          [
            'appDir'       => Expect::string()
                                    ->required()
                                    ->assert(
                                      static fn(string $value) => file_exists($value) && is_dir($value),
                                      'App directory must be a valid directory'
                                    ),
            'tempDir'      => Expect::string()
                                    ->required()
                                    ->assert(
                                      static fn(string $value) => file_exists($value) && is_dir($value),
                                      'Temp directory must be a valid directory'
                                    ),
            'latte'        => Expect::structure(
              [
                'tempDir' => Expect::string()
                                   ->assert(
                                     static fn(string $value) => file_exists($value) && is_dir($value),
                                     'Temp directory must be a valid directory'
                                   ),
              ]
            ),
            'translations' => Expect::structure(
              [
                'defaultLang'        => Expect::string()
                                              ->assert(
                                                static fn(string $value) => Language::getById($value) !== null,
                                                'Default language must be a valid language code'
                                              )
                                              ->default('cs_CZ'),
                'supportedLanguages' => Expect::listOf('string')
                                              ->assert(
                                                static fn(array $values) => array_all(
                                                  $values,
                                                  static fn($value) => is_string($value)
                                                    && Language::getById($value) !== null
                                                ),
                                                'Supported languages must be valid language codes'
                                              )
                                              ->default(['cs_CZ']),
                'domains'            => Expect::listOf('string')
                                              ->default([]),
              ]
            ),
          ]
        );
    }

    public function loadConfiguration() : void {
        $builder = $this->getContainerBuilder();

        // Core
        $builder->addDefinition($this->prefix('config'))
                ->setFactory(
                  [Config::class, 'getInstance'],
                  [$this->config->tempDir]
                )
                ->addSetup('init')
                ->setTags(['lsr', 'core']);
        $builder->addDefinition($this->prefix('session'))
                ->setFactory([Session::class, 'getInstance'])
                ->addSetup('init')
                ->setTags(['lsr', 'core']);
        $builder->addDefinition($this->prefix('routeHandler'))
                ->setFactory(RouteHandler::class)
                ->setTags(['lsr', 'core']);
        $builder->addDefinition($this->prefix('app'))
                ->setFactory(App::class)
                ->setTags(['lsr', 'core']);
        $builder->addDefinition($this->prefix('links.generator'))
                ->setFactory(Generator::class)
                ->setTags(['lsr', 'core']);
        $builder->addDefinition($this->prefix('menu.builder'))
                ->setFactory(MenuBuilder::class)
                ->setTags(['lsr', 'core']);
        $builder->addDefinition($this->prefix('csrf.helper'))
                ->setFactory([TokenHelper::class, 'getInstance'], ['@'.$this->prefix('session')])
                ->setTags(['lsr', 'core']);

        $builder->addAlias('config', $this->prefix('config'));
        $builder->addAlias('session', $this->prefix('session'));
        $builder->addAlias('routeHandler', $this->prefix('routeHandler'));
        $builder->addAlias('app', $this->prefix('app'));
        $builder->addAlias('links.generator', $this->prefix('links.generator'));
        $builder->addAlias('links', $this->prefix('links.generator'));
        $builder->addAlias('menu.builder', $this->prefix('menu.builder'));

        // Translations
        $builder->addDefinition($this->prefix('translations'))
                ->setFactory(
                  Translations::class,
                  [
                    '@'.$this->prefix('config'),
                    $this->config->translations->defaultLang,
                    $this->config->translations->supportedLanguages,
                    $this->config->translations->domains,
                  ]
                )
                ->setTags(['lsr', 'core', 'translations']);

        $builder->addAlias('translations', $this->prefix('translations'));

        // Latte
        $builder->addDefinition($this->prefix('latte.extension.cache'))
                ->setFactory(
                  Nette\Bridges\CacheLatte\CacheExtension::class,
                  ['@cache.storage'],
                )
                ->setTags(['latte', 'lsr']);
        $builder->addDefinition($this->prefix('latte.extension.lsr'))
                ->setFactory(LatteExtension::class)
                ->setTags(['latte', 'lsr']);
        $builder->addDefinition($this->prefix('latte.extension.translator'))
                ->setFactory(TranslatorExtension::class, ['@'.$this->prefix('translations')])
                ->setTags(['latte', 'lsr']);
        $tempDir = empty($this->config->latte->tempDir) ? $this->config->tempDir : $this->config->latte->tempDir;
        $builder->addDefinition($this->prefix('latte.engine'))
                ->setFactory(Engine::class)
                ->addSetup('setTempDirectory', [$tempDir])
                ->addSetup('addExtension', ['@'.$this->prefix('latte.extension.lsr')])
                ->addSetup('addExtension', ['@'.$this->prefix('latte.extension.cache')])
                ->addSetup('addExtension', ['@'.$this->prefix('latte.extension.translator')])
                ->setTags(['latte', 'lsr']);
        $builder->addDefinition($this->prefix('latte'))
                ->setFactory(Latte::class, ['@'.$this->prefix('latte.engine')])
                ->setTags(['latte', 'lsr']);

        $builder->addAlias('cache.extension.latte', $this->prefix('latte.extension.cache'));
        $builder->addAlias('templating.latte.extension', $this->prefix('latte.extension.translator'));
        $builder->addAlias('templating.latte.translatorExtension', $this->prefix('latte.extension.translator'));
        $builder->addAlias('templating.latte.engine', $this->prefix('latte.engine'));
        $builder->addAlias('templating.latte', $this->prefix('latte'));
    }

}