<?php
declare(strict_types=1);

namespace Lsr\Core\Migrations;

/**
 * @phpstan-type IndexData array{name: string, columns: string[]|string, unique?:bool, pk?:bool}
 */
readonly final class Index
{

    /**
     * @param string[] $columns
     */
    public function __construct(
      public string $name,
      public array $columns,
      public bool $unique = false,
      public bool $pk = false,
    ){}

    /**
     * @param  IndexData  $data
     * @return self
     */
    public static function fromArray(array $data) : self {
        if (is_string($data['columns'])) {
            $data['columns'] = [$data['columns']];
        }
        return new self(
          $data['name'],
          $data['columns'],
          $data['unique'] ?? false,
          $data['pk'] ?? false,
        );
    }

}