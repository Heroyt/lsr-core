<?php
declare(strict_types=1);

namespace Lsr\Core\Migrations;

/**
 * @phpstan-type ForeignKeyData array{column:string,refTable:string,refColumn:string,onDelete?:string,onUpdate?:string}
 */
readonly final class ForeignKey
{

    public function __construct(
      public string $column,
      public string $refTable,
      public string $refColumn,
      public string $onDelete = 'CASCADE',
      public string $onUpdate = 'CASCADE',
    ){}

    /**
     * @param  ForeignKeyData  $data
     * @return self
     */
    public static function fromArray(array $data) : self {
        return new self(
          $data['column'],
          $data['refTable'],
          $data['refColumn'],
          $data['onDelete'] ?? 'CASCADE',
          $data['onUpdate'] ?? 'CASCADE',
        );
    }

}