<?php

namespace Lsr\Core;

use Lsr\Interfaces\SessionInterface;

class Session implements SessionInterface
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
		session_start();
		if (!isset($_SESSION['flash'])) {
			$_SESSION['flash'] = [];
		}
		session_write_close();
	}

	/**
	 * Get session Cookie parameters
	 *
	 * @return array{lifetime:int,path:string,domain:string,secure:bool,httponly:bool}
	 */
	public function getParams() : array {
		// @phpstan-ignore-next-line
		return session_get_cookie_params();
	}

	public function setParams(int $lifetime, ?string $path = null, ?string $domain = null, ?bool $secure = null, ?bool $httponly = null) : bool {
		$defaults = $this->getParams();
		$path = $path ?? $defaults['path'];
		$domain = $domain ?? $defaults['domain'];
		$secure = $secure ?? $defaults['secure'];
		$httponly = $httponly ?? $defaults['httponly'];

		if ($this->getStatus() === PHP_SESSION_ACTIVE) {
			return setcookie(
				session_name(),
				session_id(),
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

	public function getStatus() : int {
		return session_status();
	}

	/**
	 * @inheritDoc
	 */
	public function get(string $key, mixed $default = null) : mixed {
		return $_SESSION[$key] ?? $default;
	}

	/**
	 * @inheritDoc
	 */
	public function set(string $key, mixed $value) : void {
		if ($this->getStatus() !== PHP_SESSION_ACTIVE) {
			session_start();
		}
		$_SESSION[$key] = $value;
	}

	/**
	 * @inheritDoc
	 */
	public function delete(string $key) : void {
		if ($this->getStatus() !== PHP_SESSION_ACTIVE) {
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
		if (isset($_SESSION['flash'][$key])) {
			$value = $_SESSION['flash'][$key];
			unset($_SESSION['flash'][$key]);
		}
		return $value;
	}

	/**
	 * @inheritDoc
	 */
	public function flash(string $key, mixed $value) : void {
		if ($this->getStatus() !== PHP_SESSION_ACTIVE) {
			session_start();
		}
		$_SESSION['flash'][$key] = $value;
	}
}