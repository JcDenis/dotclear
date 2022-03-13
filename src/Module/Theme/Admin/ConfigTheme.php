<?php
/**
 * @class Dotclear\Module\Theme\Admin\ConfigTheme
 * @brief Helper for theme configurators.
 * @since 2.7
 *
 * Provides helper tools for theme configurators.
 *
 * @package Dotclear
 * @subpackage Theme
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Module\Theme\Admin;

use Dotclear\Exception\ModuleException;

use Dotclear\Core\Media;

use Dotclear\Helper\File\Path;
use Dotclear\Helper\File\Files;

class ConfigTheme
{
    /**
     * Compute contrast ratio between two colors
     *
     * @param  string $color      text color
     * @param  string $background background color
     *
     * @return float             computed ratio
     */
    public function computeContrastRatio($color, $background)
    {
        // Compute contrast ratio between two colors

        $color = $this->adjustColor($color);
        if (($color == '') || (strlen($color) != 7)) {
            return 0;
        }

        $background = $this->adjustColor($background);
        if (($background == '') || (strlen($background) != 7)) {
            return 0;
        }

        $l1 = (0.2126 * pow(hexdec(substr($color, 1, 2)) / 255, 2.2)) + (0.7152 * pow(hexdec(substr($color, 3, 2)) / 255, 2.2)) + (0.0722 * pow(hexdec(substr($color, 5, 2)) / 255, 2.2));

        $l2 = (0.2126 * pow(hexdec(substr($background, 1, 2)) / 255, 2.2)) + (0.7152 * pow(hexdec(substr($background, 3, 2)) / 255, 2.2)) + (0.0722 * pow(hexdec(substr($background, 5, 2)) / 255, 2.2));

        if ($l1 > $l2) {
            $ratio = ($l1 + 0.05) / ($l2 + 0.05);
        } else {
            $ratio = ($l2 + 0.05) / ($l1 + 0.05);
        }

        return $ratio;
    }

    /**
     * Compute WCAG contrast ration level
     *
     * @param  float  $ratio computed ratio between foreground and backround color
     * @param  string  $size  font size as defined in CSS
     * @param  boolean $bold  true if bold font
     *
     * @return string         WCAG contrast ratio level (AAA, AA or <nothing>)
     */
    public function contrastRatioLevel($ratio, $size, $bold = false)
    {
        if ($size == '') {
            return '';
        }

        // Eval font size in em (assume base font size in pixels equal to 16)
        if (preg_match('/^([0-9.]+)\s*(%|pt|px|em|ex|rem)?$/', $size, $m)) {
            if (empty($m[2])) {
                $m[2] = 'em';
            }
        } else {
            return '';
        }
        switch ($m[2]) {
            case '%':
                $s = (float) $m[1] / 100;

                break;
            case 'pt':
                $s = (float) $m[1] / 12;

                break;
            case 'px':
                $s = (float) $m[1] / 16;

                break;
            case 'em':
                $s = (float) $m[1];

                break;
            case 'ex':
                $s = (float) $m[1] / 2;

                break;
            default:
                return '';
        }

        $large = ((($s > 1.5) && ($bold == false)) || (($s > 1.2) && ($bold == true)));

        // Check ratio
        if ($ratio > 7) {
            return 'AAA';
        } elseif (($ratio > 4.5) && $large) {
            return 'AAA';
        } elseif ($ratio > 4.5) {
            return 'AA';
        } elseif (($ratio > 3) && $large) {
            return 'AA';
        }

        return '';
    }

    /**
     * Return full information about constrat ratio
     *
     * @param  string  $color      text color
     * @param  string  $background background color
     * @param  string  $size       font size
     * @param  boolean $bold       bold font
     *
     * @return string              contrast ratio including WCAG level
     */
    public function contrastRatio($color, $background, $size = '', $bold = false)
    {
        if (($color != '') && ($background != '')) {
            $ratio = $this->computeContrastRatio($color, $background);
            $level = $this->contrastRatioLevel($ratio, $size, $bold);

            return
            sprintf(__('ratio %.1f'), $ratio) .
                ($level != '' ? ' ' . sprintf(__('(%s)'), $level) : '');
        }

        return '';
    }

    /**
     * Check font size
     *
     * @param  string $s font size
     *
     * @return mixed    checked font size
     */
    public function adjustFontSize($s)
    {
        if ($s) {
            if (preg_match('/^([0-9.]+)\s*(%|pt|px|em|ex|rem|ch)?$/', $s, $m)) {
                if (empty($m[2])) {
                    $m[2] = 'em';
                }

                return $m[1] . $m[2];
            }
        }

        return $s;
    }

    /**
     * Check object position, should be x:y
     *
     * @param  string $p position
     *
     * @return mixed    checked position
     */
    public function adjustPosition($p)
    {
        if (!$p) {
            return '';
        }

        if (!preg_match('/^[0-9]+(:[0-9]+)?$/', $p)) {
            return '';
        }
        $p = explode(':', $p);

        return $p[0] . (count($p) == 1 ? ':0' : ':' . $p[1]);
    }

    /**
     * Check a CSS color
     *
     * @param  string $c CSS color
     *
     * @return string    checked CSS color
     */
    public function adjustColor($c)
    {
        if (!$c) {
            return '';
        }

        $c = strtoupper($c);

        if (preg_match('/^[A-F0-9]{3,6}$/', $c)) {
            $c = '#' . $c;
        }
        if (preg_match('/^#[A-F0-9]{6}$/', $c)) {
            return $c;
        }
        if (preg_match('/^#[A-F0-9]{3,}$/', $c)) {
            return '#' . substr($c, 1, 1) . substr($c, 1, 1) . substr($c, 2, 1) . substr($c, 2, 1) . substr($c, 3, 1) . substr($c, 3, 1);
        }

        return '';
    }

    /**
     * Check and clean CSS
     *
     * @param  string $css CSS to be checked
     *
     * @return string      checked CSS
     */
    public function cleanCSS($css)
    {
        // TODO ?
        return $css;
    }

    /**
     * Return real path of a user defined CSS
     *
     * @param  string $folder CSS folder
     *
     * @return string         real path of CSS
     */
    public function cssPath($folder)
    {
        return Path::real(dotclear()->blog()->public_path, false) . '/' . $folder;
    }

    /**
     * Retirn URL of a user defined CSS
     *
     * @param  string $folder CSS folder
     *
     * @return string         CSS URL
     */
    public function cssURL($folder)
    {
        return dotclear()->blog()->public_url . '/' . $folder;
    }

    /**
     * Check if user defined CSS may be written
     *
     * @param  string  $folder CSS folder
     * @param  boolean $create create CSS folder if necessary
     *
     * @return boolean          true if CSS folder exists and may be written, else false
     */
    public function canWriteCss($folder, $create = false)
    {
        $public = Path::real(dotclear()->blog()->public_path, false);
        $css    = $this->cssPath($folder);

        if (!is_dir($public)) {
            dotclear()->error()->add(__('The \'public\' directory does not exist.'));

            return false;
        }

        if (!is_dir($css)) {
            if (!is_writable($public)) {
                dotclear()->error(sprintf(__('The \'%s\' directory cannot be modified.'), 'public'));

                return false;
            }
            if ($create) {
                Files::makeDir($css);
            }

            return true;
        }

        if (!is_writable($css)) {
            dotclear()->error(sprintf(__('The \'%s\' directory cannot be modified.'), 'public/' . $folder));

            return false;
        }

        return true;
    }

    /**
     * Store CSS property value in associated array
     *
     * @param  array $css       CSS associated array
     * @param  string $selector selector
     * @param  string $prop     property
     * @param  string $value    value
     */
    public function prop(&$css, $selector, $prop, $value)
    {
        if ($value) {
            $css[$selector][$prop] = $value;
        }
    }

    /**
     * Store background image property in CSS associated array
     *
     * @param  string $folder   image folder
     * @param  array $css       CSS associated array
     * @param  string $selector selector
     * @param  boolean $value   false for default, true if image should be set
     * @param  string $image    image filename
     */
    public function backgroundImg($folder, &$css, $selector, $value, $image)
    {
        $file = $this->imagesPath($folder) . '/' . $image;
        if ($value && file_exists($file)) {
            $css[$selector]['background-image'] = 'url(' . $this->imagesURL($folder) . '/' . $image . ')';
        }
    }

    /**
     * Write CSS file
     *
     * @param  string $folder CSS folder
     * @param  string $theme  CSS filename
     * @param  string $css    CSS file content
     */
    public function writeCss($folder, $theme, $css)
    {
        file_put_contents($this->cssPath($folder) . '/' . $theme . '.css', $css);
    }

    /**
     * Delete CSS file
     *
     * @param  string $folder CSS folder
     * @param  string $theme  CSS filename to be removed
     */
    public function dropCss($folder, $theme)
    {
        $file = Path::real($this->cssPath($folder) . '/' . $theme . '.css');
        if (!$file) {
            return;
        }
        if (is_writable(dirname($file))) {
            @unlink($file);
        }
    }

    /**
     * Return public URL of user defined CSS
     *
     * @param  string $folder CSS folder
     *
     * @return mixed         CSS file URL
     */
    public function publicCssUrlHelper($folder)
    {
        $theme = dotclear()->blog()->settings()->system->theme;
        $url   = $this->cssURL($folder);
        $path  = $this->cssPath($folder);

        if (file_exists($path . '/' . $theme . '.css')) {
            return $url . '/' . $theme . '.css';
        }
    }

    /**
     * Return real path of folder images
     *
     * @param  string $folder images folder
     *
     * @return string         real path of folder
     */
    public function imagesPath($folder)
    {
        return Path::real(dotclear()->blog()->public_path, false) . '/' . $folder;
    }

    /**
     * Return URL of images folder
     *
     * @param  string $folder images folder
     *
     * @return string         URL of images folder
     */
    public function imagesURL($folder)
    {
        return dotclear()->blog()->public_url . '/' . $folder;
    }

    /**
     * Check if images folder exists and may be written
     *
     * @param  string  $folder images folder
     * @param  boolean $create create the folder if not exists
     *
     * @return boolean          true if folder exists and may be written
     */
    public function canWriteImages($folder, $create = false)
    {
        $public = Path::real(dotclear()->blog()->public_path);
        $imgs   = $this->imagesPath($folder);

        if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng') || !function_exists('imagecreatefrompng')) {
            dotclear()->error()->add(__('At least one of the following functions is not available: ' .
                'imagecreatetruecolor, imagepng & imagecreatefrompng.'));

            return false;
        }

        if (!is_dir($public)) {
            dotclear()->error()->add(__('The \'public\' directory does not exist.'));

            return false;
        }

        if (!is_dir($imgs)) {
            if (!is_writable($public)) {
                dotclear()->error(sprintf(__('The \'%s\' directory cannot be modified.'), 'public'));

                return false;
            }
            if ($create) {
                Files::makeDir($imgs);
            }

            return true;
        }

        if (!is_writable($imgs)) {
            dotclear()->error(sprintf(__('The \'%s\' directory cannot be modified.'), 'public/' . $folder));

            return false;
        }

        return true;
    }

    /**
     * Upload an image in images folder
     *
     * @param  string $folder images folder
     * @param  array  $f      selected image file (as $_FILES[<file input fieldname>])
     * @param  int    $width  check accurate width of uploaded image if <> 0
     *
     * @return string         full pathname of uploaded image
     */
    public function uploadImage($folder, $f, $width = 0)
    {
        if (!$this->canWriteImages($folder, true)) {
            throw new ModuleException(__('Unable to create images.'));
        }

        $name = $f['name'];
        $type = Files::getMimeType($name);

        if ($type != 'image/jpeg' && $type != 'image/png') {
            throw new ModuleException(__('Invalid file type.'));
        }

        $dest = $this->imagesPath($folder) . '/uploaded' . ($type == 'image/png' ? '.png' : '.jpg');

        if (@move_uploaded_file($f['tmp_name'], $dest) === false) {
            throw new ModuleException(__('An error occurred while writing the file.'));
        }

        if ($width) {
            $s = getimagesize($dest);
            if ($s[0] != $width) {
                throw new ModuleException(sprintf(__('Uploaded image is not %s pixels wide.'), $width));
            }
        }

        return $dest;
    }

    /**
     * Delete an image from images folder (with its thumbnails if any)
     *
     * @param  string $folder images folder
     * @param  string $img    image filename
     */
    public function dropImage($folder, $img)
    {
        if ($folder) {
            $img = Path::real($this->imagesPath($folder) . '/' . $img);
        }
        if (!$img) {
            return;
        }

        if (is_writable(dirname($img))) {
            if (dotclear()->media()) {
                // Delete thumbnails if any
                try {
                    dotclear()->media()->imageThumbRemove($img);
                } catch (\Exception $e) {
                    dotclear()->error()->add($e->getMessage());
                }
            }
            // Delete image
            @unlink($img);
        }
    }
}
