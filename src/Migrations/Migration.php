<?php
declare(strict_types=1);

namespace Lsr\Core\Migrations;

/**
 * @phpstan-import-type IndexData from Index
 * @phpstan-import-type ForeignKeyData from ForeignKey
 * @phpstan-type MigrationData array{
 *     order?:numeric,
 *     definition:string,
 *     modifications?:array<string,string[]>,
 *     indexes: IndexData[],
 *     foreignKeys: ForeignKeyData[],
 * }
 */
readonly final class Migration
{

    /**
     * @param  array<string,string[]>  $modifications
     * @param  Index[]  $indexes
     * @param  ForeignKey[]  $foreignKeys
     */
    public function __construct(
      public string $table,
      public string $definition,
      public ?int $order = null,
      public array $modifications = [],
      public array $indexes = [],
      public array $foreignKeys = [],
    ){}


    /**
     * @param  string  $table
     * @param  MigrationData  $data
     * @return self
     */
    public static function fromArray(string $table, array $data) : self {
        $indexes = [];
        $foreignKeys = [];

        foreach ($data['indexes'] ?? [] as $indexData) {
            $indexes[] = Index::fromArray($indexData);
        }
        foreach ($data['foreignKeys'] ?? [] as $foreignKeyData) {
            $foreignKeys[] = ForeignKey::fromArray($foreignKeyData);
        }

        return new self(
          $table,
          $data['definition'],
          $data['order'] ?? null,
          $data['modifications'] ?? [],
          $indexes,
          $foreignKeys,
        );
    }
}