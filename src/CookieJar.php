<?php
declare(strict_types=1);

namespace Lsr\Core;

use Lsr\Interfaces\CookieJarInterface;
use Psr\Http\Message\ServerRequestInterface;

class CookieJar implements CookieJarInterface
{

    /** @var array<string, array{value:string,expire:int,path:string,domain:string,secure:bool,httponly:bool}> */
    protected array $cookiesToSet = [];
    /** @var string[] */
    protected array $cookiesToDelete = [];

    /**
     * @param  array<string,string>  $cookies
     */
    public function __construct(
      protected array $cookies = []
    ) {}

    public static function fromRequest(ServerRequestInterface $request) : CookieJar {
        /** @phpstan-ignore argument.type */
        return new self($request->getCookieParams());
    }

    public function get(string $name, ?string $default = null) : ?string {
        return $this->cookies[$name] ?? $default;
    }

    /**
     * @return array<string,string>
     */
    public function all() : array {
        return $this->cookies;
    }

    public function set(
      string $name,
      string $value,
      int    $expire = 0,
      string $path = '/',
      string $domain = '',
      bool   $secure = false,
      bool   $httponly = false
    ) : void {
        $this->cookiesToSet[$name] = [
          'value'    => $value,
          'expire'   => $expire,
          'path'     => $path,
          'domain'   => $domain,
          'secure'   => $secure,
          'httponly' => $httponly,
        ];
        $this->cookies[$name] = $value;
    }

    public function delete(string $name) : void {
        $this->cookiesToDelete[] = $name;
        if (isset($this->cookies[$name])) {
            unset($this->cookies[$name]);
        }
    }

    /**
     * @return non-empty-string[]
     */
    public function getHeaders() : array {
        $headers = [];
        foreach ($this->cookiesToSet as $name => $cookies) {
            $header = sprintf('%s=%s', $name, $cookies['value']);
            if ($cookies['expire'] > 0) {
                $header .= '; expires='.gmdate('D, d M Y H:i:s T', $cookies['expire']);
            }
            $header .= '; path='.$cookies['path'];
            if ($cookies['domain'] !== '') {
                $header .= '; domain='.$cookies['domain'];
            }
            if ($cookies['secure']) {
                $header .= '; secure';
            }
            if ($cookies['httponly']) {
                $header .= '; httponly';
            }
            $headers[] = $header;
        }
        foreach ($this->cookiesToDelete as $name) {
            $headers[] = sprintf('%s=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/', $name);
        }
        return $headers;
    }

}