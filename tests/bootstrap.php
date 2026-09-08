<?php

declare(strict_types=1);
/** @noinspection AutoloadingIssuesInspection */

/** @noinspection PhpIllegalPsrClassPathInspection */


define('ROOT', dirname(__DIR__) . '/');
const PRIVATE_DIR = ROOT . 'tests/private/';
const TMP_DIR = ROOT . 'tests/tmp/';
const UPLOAD_DIR = ROOT . 'tests/upload/';
const LOG_DIR = ROOT . 'tests/logs/';
const LANGUAGE_DIR = ROOT . 'languages/';
const TEMPLATE_DIR = ROOT . 'templates/';
const LANGUAGE_FILE_NAME = 'translations';
const DEFAULT_LANGUAGE = 'cs_CZ';
const CHECK_TRANSLATIONS = true;
const PRODUCTION = true;
const ASSETS_DIR = ROOT . 'assets/';

// PHPUnit's isolated runner needs temporary files and the active PHP configuration.
$phpConfigFiles = array_filter(array_map('trim', explode(',', (php_ini_loaded_file() ?: '') . ',' . (php_ini_scanned_files() ?: ''))));
ini_set('open_basedir', implode(PATH_SEPARATOR, [ROOT, sys_get_temp_dir(), ...$phpConfigFiles]));

if ( ! is_dir(TMP_DIR) && ! mkdir(TMP_DIR, 0777, true) && ! is_dir(TMP_DIR)) {
    throw new \RuntimeException('Cannot create temporary directory: ' . TMP_DIR);
}

require_once ROOT . 'vendor/autoload.php';

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
