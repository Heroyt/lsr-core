<?php

namespace Lsr\Core\Controllers;

use JsonSerializable;
use Lsr\Core\App;
use Lsr\Core\Exceptions\UnsupportedOperationException;
use Lsr\Core\Requests\Request;
use Nette\SmartObject;

class TemplateParameters implements \ArrayAccess, JsonSerializable
{
    use SmartObject;

    public Controller $page;
    public App $app;
    public Request $request;
    /** @var array<string|int, string> */
    public array $errors = [];
    /** @var array<string|array{title?:string,content:string,type?:string}> */
    public array $notices = [];
    /** @var string[] */
    public array $addCss = [];
    /** @var string[] */
    public array $addJs = [];

    /** @var array<string,mixed> */
    protected array $additionalData = [];

    /**
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset) : bool {
        return isset($this->{$offset}) || isset($this->additionalData[$offset]);
    }

    /**
     * @param string $offset
     * @return mixed
     */
    public function offsetGet($offset) : mixed {
        return $this->{$offset} ?? $this->additionalData[$offset] ?? null;
    }

    /**
     * @param string $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet($offset, $value) : void {
        if (property_exists($this, $offset)) {
            $this->{$offset} = $value;
            return;
        }
        $this->additionalData[$offset] = $value;
    }

    /**
     * @warning unset() should not be called on template parameters
     * @interal
     * @param string $offset
     * @return void
     */
    public function offsetUnset($offset) : void {
        throw new UnsupportedOperationException('Cannot call unset() on a template parameter');
    }

    public function jsonSerialize() : array {
        return array_merge(get_object_vars($this), $this->additionalData);
    }
}