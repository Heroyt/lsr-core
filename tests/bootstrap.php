<?php
/** @noinspection AutoloadingIssuesInspection */

/** @noinspection PhpIllegalPsrClassPathInspection */

use Lsr\Caching\Cache;
use Lsr\Core\App;

define('ROOT', dirname(__DIR__).'/');
const PRIVATE_DIR = ROOT.'tests/private/';
const TMP_DIR = ROOT.'tests/tmp/';
const UPLOAD_DIR = ROOT.'tests/upload/';
const LOG_DIR = ROOT.'tests/logs/';
const LANGUAGE_DIR = ROOT.'languages/';
const TEMPLATE_DIR = ROOT.'templates/';
const LANGUAGE_FILE_NAME = 'translations';
const DEFAULT_LANGUAGE = 'cs_CZ';
const CHECK_TRANSLATIONS = true;
const PRODUCTION = true;
const ASSETS_DIR = ROOT.'assets/';

ini_set('open_basedir', ROOT);

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

if (!file_exists(ROOT . "tests/tmp/db.db")) {
	touch(ROOT . "tests/tmp/db.db");
}
if (!file_exists(ROOT . "tests/tmp/dbc.db")) {
	touch(ROOT . "tests/tmp/dbc.db");
}

App::setupDi();

$cache = App::getService('cache');
assert($cache instanceof Cache);
$cache->clean([Cache::All => true]);

// Clear model cache
$files = glob(TMP_DIR.'models/*');
assert(is_array($files));
foreach ($files as $file) {
	unlink($file);
}