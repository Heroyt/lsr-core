<?php
/** @noinspection AutoloadingIssuesInspection */
/** @noinspection PhpIllegalPsrClassPathInspection */

define('ROOT', dirname(__DIR__).'/');
const PRIVATE_DIR = ROOT.'tests/private/';
const TMP_DIR = ROOT.'tests/tmp/';
const LOG_DIR = ROOT.'tests/logs/';
const LANGUAGE_DIR = ROOT.'languages/';
const TEMPLATE_DIR = ROOT.'templates/';
const LANGUAGE_FILE_NAME = 'translations';
const DEFAULT_LANGUAGE = 'cs_CZ';
const CHECK_TRANSLATIONS = true;
const PRODUCTION = true;
const ASSETS_DIR = ROOT.'assets/';

require_once ROOT.'vendor/autoload.php';

/**
 * @property string $value
 */
enum TestEnum: string
{
	case A = 'A';
	case B = 'B';
	case C = 'C';
	case D = 'D';
}