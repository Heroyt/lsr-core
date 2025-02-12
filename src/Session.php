<?php

namespace Lsr\Core;

use Lsr\Interfaces\SessionInterface;
use Tracy\SessionStorage;

class Session implements SessionInterface, SessionStorage
{

    private static Session $instance;

    /**
     * @inheritDoc
     */
    public static function getInstance() : static {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        // @phpstan-ignore-next-line
        return self::$instance;
    }

    /**
     * @inheritDoc
     */
    public function init() : void {
        if ($this->getStatus() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['flash'])) {
            $_SESSION['flash'] = [];
        }
        /** @noinspection PhpUndefinedConstantInspection */
        if (defined('SESSION_AUTO_CLOSE') && SESSION_AUTO_CLOSE) {
            session_write_close();
        }
    }

    public function getStatus() : int {
        return session_status();
    }

    public function close() : void {
        session_write_close();
        $this->clear();
    }

    public function setParams(
      int     $lifetime,
      ?string $path = null,
      ?string $domain = null,
      ?bool   $secure = null,
      ?bool   $httponly = null
    ) : bool {
        $defaults = $this->getParams();
        $path = $path ?? $defaults['path'];
        $domain = $domain ?? $defaults['domain'];
        $secure = $secure ?? $defaults['secure'];
        $httponly = $httponly ?? $defaults['httponly'];

        if ($this->getStatus() === PHP_SESSION_ACTIVE) {
            /**
             * @noinspection PhpArgumentWithoutNamedIdentifierInspection
             */
            return setcookie(
              session_name(), // @phpstan-ignore-line
              session_id(),   // @phpstan-ignore-line
              $lifetime,
              $path,
              $domain,
              $secure,
              $httponly
            );
        }
        /** @noinspection PhpVoidFunctionResultUsedInspection */
        return session_set_cookie_params(
          $lifetime,
          $path,
          $domain,
          $secure,
          $httponly
        );
    }

    /**
     * Get session Cookie parameters
     *
     * @return array{lifetime:int,path:string,domain:string,secure:bool,httponly:bool}
     */
    public function getParams() : array {
        return session_get_cookie_params();
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value) : void {
        if (!$this->isInitialized()) {
            session_start();
        }
        $_SESSION[$key] = $value;
    }

    public function isInitialized() : bool {
        return $this->getStatus() === PHP_SESSION_ACTIVE;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key) : void {
        if (!$this->isInitialized()) {
            session_start();
        }
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * @inheritDoc
     */
    public function clear() : void {
        $_SESSION = [];
    }

    /**
     * @inheritDoc
     */
    public function getFlash(string $key, mixed $default = null) : mixed {
        $value = $default;
        if (isset($_SESSION['flash']) && is_array($_SESSION['flash']) && isset($_SESSION['flash'][$key])) {
            $value = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
        }
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function flash(string $key, mixed $value) : void {
        if (!$this->isInitialized()) {
            session_start();

        }
        if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
            $_SESSION['flash'] = [];
        }
        $_SESSION['flash'][$key] = $value;
    }

    public function isAvailable() : bool {
        return $this->getStatus() === PHP_SESSION_ACTIVE;
    }

    /**
     * @return array<string,mixed>
     */
    public function &getData() : array {
        /** @phpstan-ignore return.type */
        return $this->get('_tracy', []);
    }

    /**
     * @inheritDoc
     * @template T
     * @param  T  $default
     * @return mixed|T
     */
    public function &get(string $key, mixed $default = null) : mixed {
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = $default;
        }
        return $_SESSION[$key];
    }

    public function getCookieHeader() : string {
        $params = $this->getParams();
        $cookie = session_name().'='.session_id();
        if (!empty($params['domain'])) {
            $cookie .= '; Domain='.$params['domain'];
        }
        if (!empty($params['path'])) {
            $cookie .= '; Path='.$params['path'];
        }
        if ($params['secure']) {
            $cookie .= '; Secure';
        }
        if ($params['httponly']) {
            $cookie .= '; HttpOnly';
        }
        if (!empty($params['lifetime'])) {
            $cookie .= '; Expires='.$params['lifetime'];
        }
        return $cookie;
    }
}