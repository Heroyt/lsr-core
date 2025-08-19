<?php
declare(strict_types=1);

namespace Lsr\Core\DI;

use Nette;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\Statement;
use Nette\Schema\Expect;
use Tracy;

/**
 * @property object{
 *     debug: bool|Statement|string,
 *     logDir: string,
 *     email: string|string[]|bool,
 *     fromEmail?: string,
 *     emailSnooze?: string,
 *     logSeverity: int|string|array<string|int>,
 *     editor?: string|null,
 *     browser?: string,
 *     strictMode: bool|int|string|array<string|int>,
 *     showBar?: bool,
 *     maxLength?: int,
 *     maxDepth?: int,
 *     maxItems?: int,
 *     keysToHide?: array<string>,
 *     dumpTheme?: string,
 *     showLocation?: bool,
 *     scream: bool|int|string|array<string|int>,
 *     bar: array<string|Statement>,
 *     blueScreen: array<callable>,
 *     editorMapping?: array<string>,
 * } $config
 */
class TracyExtension extends CompilerExtension
{

    private const string ERROR_SEVERITY_PATTERN = 'E_(?:ALL|PARSE|STRICT|RECOVERABLE_ERROR|(?:CORE|COMPILE)_(?:ERROR|WARNING)|(?:USER_)?(?:ERROR|WARNING|NOTICE|DEPRECATED))';

    public function __construct(
      private readonly bool $cliMode = false,
    ) {}

    public function getConfigSchema() : Nette\Schema\Schema {
        $errorSeverity = Expect::string()->pattern(self::ERROR_SEVERITY_PATTERN);
        $errorSeverityExpr = Expect::string()->pattern('('.self::ERROR_SEVERITY_PATTERN.'|[ &|~()])+');

        return Expect::structure(
          [
            'debug'         => Expect::anyOf(
              Expect::bool(),
              Statement::class,
              Expect::string(),
            )->default(false),
            'logDir'        => Expect::string()->default(LOG_DIR.'tracy/'),
            'email'         => Expect::anyOf(
              Expect::email(),
              Expect::listOf('email'),
              Expect::bool(),
            )->default(false),
            'fromEmail'     => Expect::email()->dynamic(),
            'emailSnooze'   => Expect::string()->dynamic(),
            'logSeverity'   => Expect::anyOf(Expect::int(), $errorSeverityExpr, Expect::listOf($errorSeverity)),
            'editor'        => Expect::type('string|null')->dynamic(),
            'browser'       => Expect::string()->dynamic(),
            'strictMode'    => Expect::anyOf(
              Expect::bool(),
              Expect::int(),
              $errorSeverityExpr,
              Expect::listOf($errorSeverity)
            ),
            'showBar'       => Expect::bool()->dynamic(),
            'maxLength'     => Expect::int()->dynamic(),
            'maxDepth'      => Expect::int()->dynamic(),
            'maxItems'      => Expect::int()->dynamic(),
            'keysToHide'    => Expect::array(null)->dynamic(),
            'dumpTheme'     => Expect::string()->dynamic(),
            'showLocation'  => Expect::bool()->dynamic(),
            'scream'        => Expect::anyOf(
              Expect::bool(),
              Expect::int(),
              $errorSeverityExpr,
              Expect::listOf($errorSeverity)
            ),
            'bar'           => Expect::listOf('string|Nette\DI\Definitions\Statement'),
            'blueScreen'    => Expect::listOf('callable'),
            'editorMapping' => Expect::arrayOf('string')->dynamic()->default(null),
          ]
        );
    }


    public function loadConfiguration() : void {
        $builder = $this->getContainerBuilder();

        $builder->addDefinition($this->prefix('logger'))
                ->setType(Tracy\ILogger::class)
                ->setFactory([Tracy\Debugger::class, 'getLogger']);

        $builder->addDefinition($this->prefix('blueScreen'))
                ->setFactory([Tracy\Debugger::class, 'getBlueScreen']);

        $builder->addDefinition($this->prefix('bar'))
                ->setFactory([Tracy\Debugger::class, 'getBar']);
    }

    public function afterCompile(Nette\PhpGenerator\ClassType $class) : void {
        $initialize = $this->initialization ?? new Nette\PhpGenerator\Closure;

        $builder = $this->getContainerBuilder();

        $logger = $builder->getDefinition($this->prefix('logger'));
        $initialize->addBody($builder->formatPhp('$logger = ?;', [$logger]));
        if (
          !$logger instanceof Nette\DI\Definitions\ServiceDefinition
          || $logger->getFactory()->getEntity() !== [Tracy\Debugger::class, 'getLogger']
        ) {
            $initialize->addBody('Tracy\Debugger::setLogger($logger);');
        }

        $options = (array) $this->config;
        unset($options['bar'], $options['blueScreen'], $options['netteMailer'], $options['debug'], $options['email'], $options['logDir']);

        foreach (['logSeverity', 'strictMode', 'scream'] as $key) {
            if (is_string($options[$key]) || is_array($options[$key])) {
                $options[$key] = $this->parseErrorSeverity($options[$key]);
            }
        }

        foreach ($options as $key => $value) {
            if ($value !== null) {
                $tbl = [
                  'keysToHide'  => 'array_push(Tracy\Debugger::getBlueScreen()->keysToHide, ... ?)',
                  'fromEmail'   => 'if ($logger instanceof Tracy\Logger) $logger->fromEmail = ?',
                  'emailSnooze' => 'if ($logger instanceof Tracy\Logger) $logger->emailSnooze = ?',
                ];
                $initialize->addBody(
                  ($tbl[$key] ?? 'Tracy\Debugger::$'.$key.' = ?').';',
                  Nette\DI\Helpers::filterArguments([$value]),
                );
            }
        }

        if ($this->config->email) {
            if ($this->config->email === true) {
                $initialize->addBody(
                  '$email = \Lsr\Core\App::getInstance()->config->getConfig("env")["TRACY_MAIL"] ?? "";'
                );
            }
            elseif (is_string($this->config->email) || is_array($this->config->email)) {
                $initialize->addBody(
                  '$email = ?;',
                  Nette\DI\Helpers::filterArguments([$this->config->email]),
                );
            }
            $initialize->addBody(
              'if (!empty($email)) {
Tracy\Debugger::$email = $email;
if ($logger instanceof Tracy\Logger) {
$logger->mailer = function($message, string $email) use ($logger) {
    $mailSender = new Tracy\Bridges\Nette\MailSender($this->getService("mailer"), $logger->email, \Lsr\Core\App::getInstance()->getBaseUrl());
		$mailSender->send($message, $email);
};
}
}',
            );
        }

        $panels = '';
        foreach ($this->config->bar as $item) {
            if (is_string($item) && str_starts_with($item, '@')) {
                $item = new Statement(['@'.$builder::ThisContainer, 'getService'], [substr($item, 1)]);
            }

            elseif
            (is_string($item)) {
                $item = new Statement($item);
            }

            $panels .= $builder->formatPhp(
              '$this->getService(?)->addPanel(?);',
              Nette\DI\Helpers::filterArguments([$this->prefix('bar'), $item]),
            );
        }

        if ($this->config->debug === true) {
            $initialize->addBody(
              'Tracy\Debugger::enable(Tracy\Debugger::Development, ?);',
              [
                $this->config->logDir,
              ]
            );
            $initialize->addBody($panels);
        }
        elseif (is_string($this->config->debug) && str_starts_with($this->config->debug, '@')) {
            // If the debug is a service, it should exist and implement the \Lsr\Interfaces\RuntimeConfigurationInterface.
            $initialize->addBody(
              '$lsrRuntimeConfig = $this->getService(?);'."\n".
              'if (!($lsrRuntimeConfig instanceof \Lsr\Interfaces\RuntimeConfigurationInterface)) throw new '.Nette\DI\InvalidConfigurationException::class.'("Invalid type of service in TracyExtension.debug. Expected type of \Lsr\Interfaces\RuntimeConfigurationInterface, got " . get_class($lsrRuntimeConfig));'."\n".
              '$lsrDebugEnabled = $lsrRuntimeConfig->isDebugMode();'."\n".
              'Tracy\Debugger::enable($lsrDebugEnabled \? Tracy\Debugger::Development : Tracy\Debugger::Production, ?);'."\n".
              'if ($lsrDebugEnabled) {'.$panels.'}',
              [
                substr($this->config->debug, 1),
                $this->config->logDir,
              ],
            );
        }

        if (!$this->cliMode && ($name = $builder->getByType(Tracy\SessionStorage::class))) {
            $initialize->addBody(
              'Tracy\Debugger::setSessionStorage($this->getService(?));',
              [$name],
            );
        }

        foreach ($this->config->blueScreen as $item) {
            $initialize->addBody(
              '$this->getService(?)->addPanel(?);',
              Nette\DI\Helpers::filterArguments([$this->prefix('blueScreen'), $item]),
            );
        }

        if (empty($this->initialization)) {
            $class->getMethod('initialize')->addBody("($initialize)();");
        }
    }


    /**
     * @param  string|string[]  $value
     */
    private function parseErrorSeverity(string | array $value) : int {
        $value = implode('|', (array) $value);
        $res = (int) @parse_ini_string('e = '.$value)['e']; // @ may fail
        if (!$res) {
            throw new Nette\InvalidStateException("Syntax error in expression '$value'");
        }

        return $res;
    }

}