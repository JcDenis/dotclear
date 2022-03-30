<?php
/**
 * @class Dotclear\Core\Media\Image\ImageTools
 * @brief Basic image handling tool
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 * Some methods are based on https://dev.media-box.net/big/
 *
 * @package Dotclear
 * @subpackage Utils
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Media\Image;

use \GdImage;
use Dotclear\Exception\UtilsException;
use Dotclear\Helper\File\Files;

class ImageTools
{
    /** @var    GdImage|false   $res    Image resource */
    public $res = false;

    /** @ var   string  $memory_limit   Memory limit */
    public $memory_limit = null;

    /**
     * Constructor.
     * 
     * @throws  UtilsException
     */
    public function __construct()
    {
        if (!function_exists('imagegd2')) {
            throw new UtilsException('GD is not installed');
        }
    }

    /**
     * Close
     *
     * Destroy image resource
     */
    public function close(): void
    {
        if (!empty($this->res)) {
            imagedestroy($this->res);
        }

        if ($this->memory_limit) {
            ini_set('memory_limit', $this->memory_limit);
        }
    }

    /**
     * Load image
     *
     * Loads an image content in memory and set {@link $res} property.
     * 
     * @throws  UtilsException
     *
     * @param   string $f   Image file path
     */
    public function loadImage(string $f): void
    {
        if (!file_exists($f)) {
            throw new UtilsException('Image doest not exists');
        }

        if (false !== ($info = @getimagesize($f))) {
            $this->memoryAllocate(
                $info[0],
                $info[1],
                $info['channels'] ?? 4
            );

            switch ($info[2]) {
                case 3: // PNG
                    $this->res = @imagecreatefrompng($f);
                    if (!empty($this->res)) {
                        @imagealphablending($this->res, false);
                        @imagesavealpha($this->res, true);
                    }

                    break;
                case 2: // JPEG
                    $this->res = @imagecreatefromjpeg($f);

                    break;
                case 1: // GIF
                    $this->res = @imagecreatefromgif($f);

                    break;
                case 18: // WEBP
                    if (function_exists('imagecreatefromwebp')) {
                        $this->res = @imagecreatefromwebp($f);
                        if (!empty($this->res)) {
                            @imagealphablending($this->res, false);
                            @imagesavealpha($this->res, true);
                        }
                    } else {
                        throw new UtilsException('WebP image format not supported');
                    }

                    break;
            }
        }

        if (empty($this->res)) {
            throw new UtilsException('Unable to load image');
        }
    }

    /**
     * Image width
     *
     * @return  int     Image width
     */
    public function getW(): int
    {
        return $this->res ? imagesx($this->res) : 0;
    }

    /**
     * Image height
     *
     * @return  int     Image height
     */
    public function getH(): int
    {
        return $this->res ? imagesy($this->res) : 0;
    }

    /**
     * Allocate memory
     * 
     * @throws  UtilsException
     * 
     * @param   int     $w  Image with
     * @param   int     $h  Image height
     * @param   int     $bpp
     */
    public function memoryAllocate(int $w, int $h, int $bpp = 4): void
    {
        $mem_used  = function_exists('memory_get_usage') ? @memory_get_usage() : 4000000;
        $mem_limit = @ini_get('memory_limit');
        if ($mem_limit && trim((string) $mem_limit) === '-1' || !Files::str2bytes($mem_limit)) {
            // Cope with memory_limit set to -1 in PHP.ini
            return;
        }
        if ($mem_used && $mem_limit) {
            $mem_limit  = Files::str2bytes($mem_limit);
            $mem_avail  = $mem_limit - $mem_used - (512 * 1024);
            $mem_needed = $w * $h * $bpp;

            if ($mem_needed > $mem_avail) {
                if (@ini_set('memory_limit', (string) ($mem_limit + $mem_needed + $mem_used)) === false) {
                    throw new UtilsException(__('Not enough memory to open image.'));
                }

                if (!$this->memory_limit) {
                    $this->memory_limit = $mem_limit;
                }
            }
        }
    }

    /**
     * Image output
     *
     * Returns image content in a file or as HTML output (with headers)
     *
     * @param   string          $type   Image type (png or jpg)
     * @param   string|null     $file   Output file. If null, output will be echoed in STDOUT
     * @param   int             $qual   JPEG image quality
     * 
     * @return  bool
     */
    public function output(string $type = 'png', ?string $file = null, int $qual = 90): bool
    {
        if (!$file) {
            header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
            header('Pragma: no-cache');
            switch (strtolower($type)) {
                case 'png':
                    header('Content-type: image/png');
                    imagepng($this->res);

                    return true;
                case 'jpeg':
                case 'jpg':
                    header('Content-type: image/jpeg');
                    imagejpeg($this->res, null, $qual);

                    return true;
                case 'wepb':
                    if (function_exists('imagewebp')) {
                        header('Content-type: image/webp');
                        imagewebp($this->res, null, $qual);

                        return true;
                    }

                    return false;
                default:
                    return false;
            }
        } elseif (is_writable(dirname($file))) {
            switch (strtolower($type)) {
                case 'png':
                    return imagepng($this->res, $file);
                case 'jpeg':
                case 'jpg':
                    return imagejpeg($this->res, $file, $qual);
                case 'webp':
                    if (function_exists('imagewebp')) {
                        return imagewebp($this->res, $file, $qual);
                    }

                    return false;
                default:
                    return false;
            }
        }

        return false;
    }

    /**
     * Resize image
     *
     * @param   string|int  $WIDTH      Image width (px or percent)
     * @param   string|int  $HEIGHT     Image height (px or percent)
     * @param   string      $MODE       Crop mode (force, crop, ratio)
     * @param   bool        $EXPAND     Allow resize of image
     * 
     * @return bool
     */
    public function resize($WIDTH, $HEIGHT, string $MODE = 'ratio', bool $EXPAND = false): bool
    {
        $_h = 0;
        $_w = 0;

        $imgWidth  = $this->getW();
        $imgHeight = $this->getH();

        if (strpos((string) $WIDTH, '%', 0)) {
            $WIDTH = $imgWidth * $WIDTH / 100;
        }

        if (strpos((string) $HEIGHT, '%', 0)) {
            $HEIGHT = $imgHeight * $HEIGHT / 100;
        }

        $ratio = $imgWidth / $imgHeight;

        // guess resize ($_w et $_h)
        if ($MODE == 'ratio') {
            $_w = 99999;
            if ($HEIGHT > 0) {
                $_h = $HEIGHT;
                $_w = $_h * $ratio;
            }
            if ($WIDTH > 0 && $_w > $WIDTH) {
                $_w = $WIDTH;
                $_h = $_w / $ratio;
            }

            if (!$EXPAND && $_w > $imgWidth) {
                $_w = $imgWidth;
                $_h = $imgHeight;
            }
        } else {
            // crop source image
            $_w = $WIDTH;
            $_h = $HEIGHT;
        }

        if ($MODE == 'force') {
            if ($WIDTH > 0) {
                $_w = $WIDTH;
            } else {
                $_w = $HEIGHT * $ratio;
            }

            if ($HEIGHT > 0) {
                $_h = $HEIGHT;
            } else {
                $_h = $WIDTH / $ratio;
            }

            if (!$EXPAND && $_w > $imgWidth) {
                $_w = $imgWidth;
                $_h = $imgHeight;
            }

            $cropW  = $imgWidth;
            $cropH  = $imgHeight;
            $decalW = 0;
            $decalH = 0;
        } else {
            // guess real viewport of image
            $innerRatio = $_w / $_h;
            if ($ratio >= $innerRatio) {
                $cropH  = $imgHeight;
                $cropW  = $imgHeight * $innerRatio;
                $decalH = 0;
                $decalW = ($imgWidth - $cropW) / 2;
            } else {
                $cropW  = $imgWidth;
                $cropH  = $imgWidth / $innerRatio;
                $decalW = 0;
                $decalH = ($imgHeight - $cropH) / 2;
            }
        }

        if ($_w < 1) {
            $_w = 1;
        }
        if ($_h < 1) {
            $_h = 1;
        }

        // convert float to int
        settype($decalW, 'int');
        settype($decalH, 'int');
        settype($_w, 'int');
        settype($_h, 'int');
        settype($cropW, 'int');
        settype($cropH, 'int');

        # truecolor is 24 bit RGB, ie. 3 bytes per pixel.
        $this->memoryAllocate($_w, $_h, 3);
        $dest = imagecreatetruecolor($_w, $_h);
        $fill = imagecolorallocate($dest, 128, 128, 128);
        imagefill($dest, 0, 0, $fill);
        @imagealphablending($dest, false);
        @imagesavealpha($dest, true);
        imagecopyresampled($dest, $this->res, 0, 0, $decalW, $decalH, $_w, $_h, $cropW, $cropH);
        imagedestroy($this->res);
        $this->res = $dest;

        return true;
    }
}
