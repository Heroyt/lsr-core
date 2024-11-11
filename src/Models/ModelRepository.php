<?php

declare(strict_types=1);

namespace Lsr\Core\Models;

use Lsr\Logging\Logger;

final class ModelRepository
{

    /**
     * @var array<class-string<Model>, array<int, Model>>
     */
    private static array $instances = [];

    /** @var array<class-string<Model>, Logger> */
    private static array $loggers = [];

    /**
     * @template T of Model
     * @param  class-string<T>  $class
     * @param  int  $id
     * @return T|null
     */
    public static function getInstance(string $class, int $id) : ?Model {
        self::$instances[$class] ??= [];
        /** @var array<int,T> $instances */
        $instances = self::$instances[$class];
        return $instances[$id] ?? null;
    }

    public static function setInstance(Model $model) : void {
        if (!isset($model->id)) {
            return;
        }
        self::$instances[$model::class] ??= [];
        self::$instances[$model::class][$model->id] = $model;
    }

    public static function removeInstance(Model $model) : void {
        if (!isset($model->id)) {
            return;
        }
        if (isset(self::$instances[$model::class][$model->id])) {
            unset(self::$instances[$model::class][$model->id]);
        }
    }

    /**
     * @param  class-string<Model>  $class
     * @return Logger
     */
    public static function getLogger(string $class) : Logger {
        self::$loggers[$class] ??= new Logger(LOG_DIR.'models/', $class::TABLE);
        return self::$loggers[$class];
    }

    /**
     * @param  class-string<Model>|null  $class
     * @return void
     */
    public static function clearInstances(?string $class = null) : void {
        if (isset($class)) {
            foreach (self::$instances[$class] as $id => $model) {
                unset(self::$instances[$class][$id]);
            }
            self::$instances[$class] = [];
            return;
        }

        // Clear all instances
        foreach (self::$instances as $classKey => $models) {
            foreach ($models as $id => $model) {
                unset(self::$instances[$classKey][$id]);
            }
            self::$instances[$classKey] = [];
        }
    }

    public static function clearLoggers() : void {
        foreach (self::$loggers as $class => $loggers) {
            unset(self::$loggers[$class]);
        }
        self::$loggers = [];
    }

}