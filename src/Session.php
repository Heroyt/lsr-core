<?php

namespace Lsr\Core;

use InvalidArgumentException;
use Lsr\Interfaces\SessionInterface;
use Random\Randomizer;
use Tracy\SessionStorage;

class Session implements SessionInterface, SessionStorage
{

    private const string SESSION_KEY_PREFIX = 'session_';
    private const string SESSION_COOKIE_NAME = 'SESSID';
    private const string SESSION_FLASH_KEY = 'session_flash';

    private static Session $instance;

    private int $status = PHP_SESSION_NONE;
    private ?string $sessionId = null;
    /** @var array<string,mixed>|null */
    private ?array $data = null;
    private int $ttl = 86400; // 1 day
    private string $path = '/';
    private string $domain = '';
    private bool $secure = false;
    private bool $httponly = false;

    private string $filePrefix;

    private string $serializer = 'igbinary';

    public function __construct(
      string $directory = TMP_DIR.'sessions',
    ) {
        if (!file_exists($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException(
              sprintf('Session directory "%s" was not created', $directory)
            );
        }
        $this->filePrefix = trailingSlashIt($directory).self::SESSION_KEY_PREFIX;

        if (!extension_loaded('igbinary')) {
            $this->serializer = 'php';
        }
    }

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

    public function __wakeup() : void {
        $this->init();
    }

    /**
     * @inheritDoc
     */
    public function init() : void {
        // Get session cookie from request
        $cookies = App::cookieJar();

        // Check if session file exists
        if ((($id = $cookies->get(self::SESSION_COOKIE_NAME)) !== null) && file_exists($this->filePrefix.$id)) {
            $this->sessionId = $id;
            $this->status = PHP_SESSION_ACTIVE;
            $this->data = null;
            $this->setCookie();
            return;
        }

        // Generate new session
        $this->sessionId = $this->generateSessionId();
        $this->data = null;
        $this->status = PHP_SESSION_ACTIVE;
        $this->setCookie();
    }

    /**
     * @inheritDoc
     * @template T
     * @param  T  $default
     * @return mixed|T
     */
    public function &get(string $key, mixed $default = null) : mixed {
        if (!$this->isInitialized()) {
            $this->init();
        }
        if ($this->data === null) {
            $this->loadSessionData();
        }
        if (!isset($this->data[$key])) {
            $this->data[$key] = $default;
        }
        return $this->data[$key];
    }

    private function loadSessionData() : void {
        assert($this->sessionId !== null);
        $file = $this->filePrefix.$this->sessionId;
        if (!file_exists($file)) {
            $this->data = [];
            return;
        }
        $contents = file_get_contents($file);
        $decoded = $this->getUnserializer()($contents);
        if (!is_array($decoded) || !isset($decoded['expire']) || $decoded['expire'] < time()) {
            $this->data = [];
            return;
        }
        if (!isset($decoded['data']) || !is_array($decoded['data'])) {
            $this->data = [];
            return;
        }
        $this->data = $decoded['data'];
        $_SESSION = $this->data;
    }

    /**
     * @return callable(string): mixed
     */
    private function getUnserializer() : callable {
        if ($this->serializer === 'igbinary') {
            return 'igbinary_unserialize';
        }
        return 'unserialize';
    }

    private function setCookie() : void {
        App::cookieJar()
           ->set(
             self::SESSION_COOKIE_NAME,
             $this->sessionId,
             time() + $this->ttl,
             $this->path,
             $this->domain,
             $this->secure,
             $this->httponly
           );
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value) : void {
        if (!$this->isInitialized()) {
            $this->init();
        }
        if ($this->data === null) {
            $this->loadSessionData();
        }
        if ($key === self::SESSION_FLASH_KEY) {
            throw new InvalidArgumentException('Key is reserved');
        }
        $this->data[$key] = $value;
    }

    private function generateSessionId() : string {
        $random = new Randomizer();
        do {
            $id = bin2hex($random->getBytes(32));
        } while (file_exists($this->filePrefix.$id));
        return $id;
    }

    public function close() : void {
        if ($this->sessionId === null) {
            $this->status = PHP_SESSION_NONE;
            return;
        }
        $this->saveSessionData();
        $this->sessionId = null;
        $this->data = null;
        $this->status = PHP_SESSION_NONE;
    }

    private function saveSessionData() : void {
        if ($this->sessionId === null) {
            throw new \LogicException('Session not initialized');
        }
        $file = $this->filePrefix.$this->sessionId;
        $data = ($this->getSerializer())(
          [
            'expire' => time() + $this->ttl,
            'data'   => $this->data,
          ]
        );
        file_put_contents($file, $data);
    }

    /**
     * @return callable(mixed):string
     */
    private function getSerializer() : callable {
        if ($this->serializer === 'igbinary') {
            return 'igbinary_serialize';
        }
        return 'serialize';
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

        $this->ttl = $lifetime;
        $this->path = $path;
        $this->domain = $domain;
        $this->secure = $secure;
        $this->httponly = $httponly;
        return true;
    }

    /**
     * Get session Cookie parameters
     *
     * @return array{lifetime:int,path:string,domain:string,secure:bool,httponly:bool}
     */
    public function getParams() : array {
        return [
          'lifetime' => $this->ttl,
          'path'     => $this->path,
          'domain'   => $this->domain,
          'secure'   => $this->secure,
          'httponly' => $this->httponly,
        ];
    }

    public function isInitialized() : bool {
        return $this->getStatus() === PHP_SESSION_ACTIVE;
    }

    public function getStatus() : int {
        return $this->status;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key) : void {
        if (!$this->isInitialized()) {
            $this->init();
        }
        if ($this->data === null) {
            $this->loadSessionData();
        }
        if (isset($this->data[$key])) {
            unset($this->data[$key]);
        }
    }

    /**
     * @inheritDoc
     */
    public function clear() : void {
        $this->data = null;
    }

    /**
     * @inheritDoc
     */
    public function getFlash(string $key, mixed $default = null) : mixed {
        if ($this->data === null) {
            $this->loadSessionData();
        }
        if (!isset($this->data[self::SESSION_FLASH_KEY]) || !is_array($this->data[self::SESSION_FLASH_KEY])) {
            $this->data[self::SESSION_FLASH_KEY] = [];
        }
        return $this->data[self::SESSION_FLASH_KEY][$key] ?? $default;
    }

    /**
     * @inheritDoc
     */
    public function flash(string $key, mixed $value) : void {
        if (!$this->isInitialized()) {
            $this->init();
        }
        if ($this->data === null) {
            $this->loadSessionData();
        }
        if (!isset($this->data[self::SESSION_FLASH_KEY]) || !is_array($this->data[self::SESSION_FLASH_KEY])) {
            $this->data[self::SESSION_FLASH_KEY] = [];
        }
        $this->data[self::SESSION_FLASH_KEY][$key] = $value;
    }

    public function isAvailable() : bool {
        return $this->getStatus() === PHP_SESSION_ACTIVE;
    }

    /**
     * @return array<string,mixed>
     */
    public function &getData() : array {
        if ($this->get('_tracy') === null) {
            $this->data['_tracy'] = [];
        }
        /** @phpstan-ignore return.type */
        return $this->data['_tracy'];
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

    public function clearSessionFiles() : void {
        $files = glob($this->filePrefix.'*');
        if ($files === false) {
            throw new \RuntimeException('Failed to read session files');
        }
        foreach ($files as $file) {
            // Check file ttl
            $contents = file_get_contents($file);
            if ($contents === false) {
                unlink($file);
                continue;
            }

            $decoded = ($this->getUnserializer())($contents);
            if (!is_array($decoded) || !isset($decoded['expire']) || $decoded['expire'] < time()) {
                unlink($file);
            }
        }
    }

}