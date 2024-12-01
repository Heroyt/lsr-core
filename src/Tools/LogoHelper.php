<?php

namespace Lsr\Core\Tools;

use Lsr\Core\App;

class LogoHelper
{

    /**
     * Checks there exists an image of the arena
     *
     * The image must be either SVG or PNG. If no logo image exists, returns empty string;
     *
     * @return string URL of the image
     */
    public static function getLogoUrl() : string {
        $image = self::getLogoFileName();
        if (empty($image)) {
            return '';
        }
        return str_replace(ROOT, App::getInstance()->getBaseUrl(), $image);
    }

    /**
     * Checks there exists an image of the arena
     *
     * The image must be either SVG or PNG. If no logo image exists, returns empty string;
     *
     * @return string Full path to image
     */
    public static function getLogoFileName() : string {
        $imageBase = UPLOAD_DIR.'logo';
        if (file_exists($imageBase.'.svg')) {
            return $imageBase.'.svg';
        }
        if (file_exists($imageBase.'.png')) {
            return $imageBase.'.png';
        }
        $imageBase = ASSETS_DIR.'images/logo';
        if (file_exists($imageBase.'.svg')) {
            return $imageBase.'.svg';
        }
        if (file_exists($imageBase.'.png')) {
            return $imageBase.'.png';
        }
        return '';
    }

    /**
     * Gets HTML for displaying the arena image
     *
     * For SVG images, it returns the SVG XML, for other formats, it returns the <img> tag.
     *
     * @return string HTML or empty string if no logo exists
     */
    public static function getLogoHtml() : string {
        $image = self::getLogoFileName();
        if (empty($image)) {
            return '';
        }
        $type = pathinfo($image, PATHINFO_EXTENSION);
        if ($type === 'svg') {
            $contents = file_get_contents($image);
            assert(is_string($contents));
            return $contents;
        }
        return '<img src="'.str_replace(
            ROOT,
            App::getInstance()->getBaseUrl(),
            $image
          ).'" class="img-fluid arena-logo" alt="Arena - Logo" id="arena-logo-image" />';
    }
}