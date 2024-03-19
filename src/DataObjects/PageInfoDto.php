<?php

namespace Lsr\Core\DataObjects;

use Lsr\Enums\RequestMethod;

readonly class PageInfoDto implements \JsonSerializable
{

	public function __construct(
		public RequestMethod $type,
		public ?string       $routeName = null,
		public array         $path = []
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize(): array {
		return get_object_vars($this);
	}
}