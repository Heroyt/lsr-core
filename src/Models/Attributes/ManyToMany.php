<?php

namespace Lsr\Core\Models\Attributes;

use Attribute;
use Lsr\Core\DB;
use Lsr\Core\Dibi\Fluent;
use Lsr\Core\Models\LoadingType;
use Lsr\Core\Models\Model;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class ManyToMany extends ModelRelation
{
    use WithType;

    public function __construct(
      public string      $through = '',
      public string      $foreignKey = '',
      public string      $localKey = '',
      public ?string     $class = null,
      public LoadingType $loadingType = LoadingType::EAGER,
    ) {}

    /**
     * Get a query that returns model ids
     *
     * @param  int  $id
     * @param  string|Model  $targetClass
     * @param  string|Model  $class
     *
     * @return Fluent
     */
    public function getConnectionQuery(int $id, string | Model $targetClass, string | Model $class) : Fluent {
        return DB::select($this->getThroughTableName($targetClass, $class), $this->getForeignKey($targetClass, $class))
                 ->where('%n = %i', $this->getLocalKey($targetClass, $class), $id);
    }

    public function getThroughTableName(string | Model $targetClass, string | Model $class) : string {
        if (empty($this->through)) {
            $this->through = '::'.$class::TABLE.'_'.$targetClass::TABLE;
        }
        return $this->through;
    }

    /**
     * @param  string|Model  $targetClass
     * @param  string|Model  $class
     *
     * @return string
     */
    public function getForeignKey(string | Model $targetClass, string | Model $class) : string {
        if (empty($this->foreignKey)) {
            $this->foreignKey = $targetClass::getPrimaryKey();
        }
        return $this->foreignKey;
    }

    /**
     * @param  string|Model  $targetClass
     * @param  string|Model  $class
     *
     * @return string
     */
    public function getLocalKey(string | Model $targetClass, string | Model $class) : string {
        if (empty($this->localKey)) {
            $this->localKey = $class::getPrimaryKey();
        }
        return $this->localKey;
    }

    /**
     * @param  Model[]  $targetClasses
     * @param  Model  $class
     *
     * @return Fluent
     */
    public function getInsertQuery(array $targetClasses, Model $class) : Fluent {
        $data = [];
        foreach ($targetClasses as $targetClass) {
            $data[] = [
              $this->getLocalKey($targetClass, $class)   => $class->id,
              $this->getForeignKey($targetClass, $class) => $targetClass->id,
            ];
        }
        return DB::insertGet(
             $this->through,
          ...$data
        );
    }

}