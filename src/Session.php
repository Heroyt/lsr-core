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
		$_SESSION[$key] = $value;
	}

	/**
	 * @inheritDoc
	 */
	public function delete(string $key) : void {
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
		$_SESSION['flash'][$key] = $value;
	}
}