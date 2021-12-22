<?php
/**
 * @class Dotclear\Utils\Files
 * @brief Basic files handling tool
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @package Dotclear
 * @subpackage Utils
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Utils;

use Dotclear\Exception\UtilsException;

class Files
{
    public static $dir_mode = null; ///< Default directories mode

    public static $mimeType = ///< MIME types
    [
        'odt' => 'application/vnd.oasis.opendocument.text',
        'odp' => 'application/vnd.oasis.opendocument.presentation',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',

        'sxw' => 'application/vnd.sun.xml.writer',
        'sxc' => 'application/vnd.sun.xml.calc',
        'sxi' => 'application/vnd.sun.xml.impress',

        'ppt' => 'application/mspowerpoint',
        'doc' => 'application/msword',
        'xls' => 'application/msexcel',

        'pdf'  => 'application/pdf',
        'ps'   => 'application/postscript',
        'ai'   => 'application/postscript',
        'eps'  => 'application/postscript',
        'json' => 'application/json',
        'xml'  => 'application/xml',

        'bin' => 'application/octet-stream',
        'exe' => 'application/octet-stream',

        'bz2' => 'application/x-bzip',
        'deb' => 'application/x-debian-package',
        'gz'  => 'application/x-gzip',
        'jar' => 'application/x-java-archive',
        'rar' => 'application/rar',
        'rpm' => 'application/x-redhat-package-manager',
        'tar' => 'application/x-tar',
        'tgz' => 'application/x-gtar',
        'zip' => 'application/zip',

        'aiff' => 'audio/x-aiff',
        'ua'   => 'audio/basic',
        'mp3'  => 'audio/mpeg3',
        'mid'  => 'audio/x-midi',
        'midi' => 'audio/x-midi',
        'ogg'  => 'application/ogg',
        'ra'   => 'audio/x-pn-realaudio',
        'ram'  => 'audio/x-pn-realaudio',
        'wav'  => 'audio/x-wav',
        'wma'  => 'audio/x-ms-wma',

        'swf'  => 'application/x-shockwave-flash',
        'swfl' => 'application/x-shockwave-flash',
        'js'   => 'application/javascript',

        'bmp'  => 'image/bmp',
        'gif'  => 'image/gif',
        'ico'  => 'image/vnd.microsoft.icon',
        'jpeg' => 'image/jpeg',
        'jpg'  => 'image/jpeg',
        'jpe'  => 'image/jpeg',
        'png'  => 'image/png',
        'svg'  => 'image/svg+xml',
        'tiff' => 'image/tiff',
        'tif'  => 'image/tiff',
        'webp' => 'image/webp',
        'xbm'  => 'image/x-xbitmap',

        'css'  => 'text/css',
        'csv'  => 'text/csv',
        'html' => 'text/html',
        'htm'  => 'text/html',
        'txt'  => 'text/plain',
        'rtf'  => 'text/richtext',
        'rtx'  => 'text/richtext',

        'mpg'  => 'video/mpeg',
        'mpeg' => 'video/mpeg',
        'mpe'  => 'video/mpeg',
        'ogv'  => 'video/ogg',
        'viv'  => 'video/vnd.vivo',
        'vivo' => 'video/vnd.vivo',
        'qt'   => 'video/quicktime',
        'mov'  => 'video/quicktime',
        'mp4'  => 'video/mp4',
        'm4v'  => 'video/x-m4v',
        'flv'  => 'video/x-flv',
        'avi'  => 'video/x-msvideo',
        'wmv'  => 'video/x-ms-wmv',
    ];

    /**
     * Directory scanning
     *
     * Returns a directory child files and directories.
     *
     * @param string    $d        Path to scan
     * @param boolean    $order    Order results
     * @return array
     */
    public static function scandir(string $d, bool $order = true): array
    {
        $res = [];
        $dh  = @opendir($d);

        if ($dh === false) {
            throw new UtilsException(__('Unable to open directory.'));
        }

        while (($f = readdir($dh)) !== false) {
            $res[] = $f;
        }
        closedir($dh);

        if ($order) {
            sort($res);
        }

        return $res;
    }

    /**
     * File extension
     *
     * Returns a file extension.
     *
     * @param string    $f    File name
     * @return string
     */
    public static function getExtension(string $f): string
    {
        if (function_exists('pathinfo')) {
            return strtolower(pathinfo($f, PATHINFO_EXTENSION));
        }
        $f = explode('.', basename($f));
        if (count($f) <= 1) {
            return '';
        }

        return strtolower($f[count($f) - 1]);
    }

    /**
     * MIME type
     *
     * Returns a file MIME type, based on static var {@link $mimeType}
     *
     * @param string    $f    File name
     * @return string
     */
    public static function getMimeType(string $f): string
    {
        $ext   = self::getExtension($f);
        $types = self::mimeTypes();

        if (isset($types[$ext])) {
            return $types[$ext];
        }

        return 'application/octet-stream';
    }

    /**
     * MIME types
     *
     * Returns all defined MIME types.
     *
     * @return array
     */
    public static function mimeTypes(): array
    {
        return self::$mimeType;
    }

    /**
     * New MIME types
     *
     * Append new MIME types to defined MIME types.
     *
     * @param array        $tab        New MIME types.
     */
    public static function registerMimeTypes(array $tab): void
    {
        self::$mimeType = array_merge(self::$mimeType, $tab);
    }

    /**
     * Is a file or directory deletable.
     *
     * Returns true if $f is a file or directory and is deletable.
     *
     * @param string    $f    File or directory
     * @return boolean
     */
    public static function isDeletable(string $f): bool
    {
        if (is_file($f)) {
            return is_writable(dirname($f));
        } elseif (is_dir($f)) {
            return (is_writable(dirname($f)) && count(Files::scandir($f)) <= 2);
        }

        return false;
    }

    /**
     * Recursive removal
     *
     * Remove recursively a directory.
     *
     * @param string    $dir        Directory patch
     * @return boolean
     */
    public static function deltree(string $dir): bool
    {
        $current_dir = opendir($dir);
        while ($entryname = readdir($current_dir)) {
            if (is_dir($dir . '/' . $entryname) and ($entryname != '.' and $entryname != '..')) {
                if (!Files::deltree($dir . '/' . $entryname)) {
                    return false;
                }
            } elseif ($entryname != '.' and $entryname != '..') {
                if (!@unlink($dir . '/' . $entryname)) {
                    return false;
                }
            }
        }
        closedir($current_dir);

        return @rmdir($dir);
    }

    /**
     * Touch file
     *
     * Set file modification time to now.
     *
     * @param string    $f        File to change
     */
    public static function touch(string $f): void
    {
        if (is_writable($f)) {
            if (function_exists('touch')) {
                @touch($f);
            } else {
                # Very bad hack
                @file_put_contents($f, file_get_contents($f));
            }
        }
    }

    /**
     * Directory creation.
     *
     * Creates directory $f. If $r is true, attempts to create needed parents
     * directories.
     *
     * @param string    $f        Directory to create
     * @param boolean    $r        Create parent directories
     */
    public static function makeDir(string $f, bool $r = false): void
    {
        if (empty($f)) {
            return;
        }

        if (DIRECTORY_SEPARATOR == '\\') {
            $f = str_replace('/', '\\', $f);
        }

        if (is_dir($f)) {
            return;
        }

        if ($r) {
            $dir  = Path::real($f, false);
            $dirs = [];

            while (!is_dir($dir)) {
                array_unshift($dirs, basename($dir));
                $dir = dirname($dir);
            }

            foreach ($dirs as $d) {
                $dir .= DIRECTORY_SEPARATOR . $d;
                if ($d != '' && !is_dir($dir)) {
                    self::makeDir($dir);
                }
            }
        } else {
            if (@mkdir($f) === false) {
                throw new UtilsException(__('Unable to create directory.'));
            }
            self::inheritChmod($f);
        }
    }

    /**
     * Mode inheritage
     *
     * Sets file or directory mode according to its parent.
     *
     * @param string    $file        File to change
     */
    public static function inheritChmod(string $file): bool
    {
        if (!function_exists('fileperms') || !function_exists('chmod')) {
            return false;
        }

        if (self::$dir_mode != null) {
            return @chmod($file, self::$dir_mode);
        }

        return @chmod($file, fileperms(dirname($file)));
    }

    /**
     * Changes file content.
     *
     * Writes $f_content into $f file.
     *
     * @param string    $f            File to edit
     * @param string    $f_content    Content to write
     */
    public static function putContent(string $f, string $f_content): bool
    {
        if (file_exists($f) && !is_writable($f)) {
            throw new UtilsException(__('File is not writable.'));
        }

        $fp = @fopen($f, 'w');

        if ($fp === false) {
            throw new UtilsException(__('Unable to open file.'));
        }

        fwrite($fp, $f_content, strlen($f_content));
        fclose($fp);

        return true;
    }

    /**
     * Human readable file size.
     *
     * @param integer    $size        Bytes
     * @return string
     */
    public static function size(int $size): string
    {
        $kb = 1024;
        $mb = 1024 * $kb;
        $gb = 1024 * $mb;
        $tb = 1024 * $gb;

        if ($size < $kb) {
            return $size . ' B';
        } elseif ($size < $mb) {
            return round($size / $kb, 2) . ' KB';
        } elseif ($size < $gb) {
            return round($size / $mb, 2) . ' MB';
        } elseif ($size < $tb) {
            return round($size / $gb, 2) . ' GB';
        }

        return round($size / $tb, 2) . ' TB';
    }

    /**
     * Converts a human readable file size to bytes.
     *
     * @param string    $v            Size
     * @return float
     */
    public static function str2bytes(string $v): float
    {
        $v    = trim($v);
        $last = strtolower(substr($v, -1, 1));
        $v    = (float) substr($v, 0, -1);
        switch ($last) {
            case 'g':
                $v *= 1024;
            case 'm':
                $v *= 1024;
            case 'k':
                $v *= 1024;
        }

        return $v;
    }

    /**
     * Upload status
     *
     * Returns true if upload status is ok, throws an UtilsException instead.
     *
     * @param array        $file        File array as found in $_FILES
     * @return boolean
     */
    public static function uploadStatus(array $file): bool
    {
        if (!isset($file['error'])) {
            throw new UtilsException(__('Not an uploaded file.'));
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                return true;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new UtilsException(__('The uploaded file exceeds the maximum file size allowed.'));
            case UPLOAD_ERR_PARTIAL:
                throw new UtilsException(__('The uploaded file was only partially uploaded.'));
            case UPLOAD_ERR_NO_FILE:
                throw new UtilsException(__('No file was uploaded.'));
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new UtilsException(__('Missing a temporary folder.'));
            case UPLOAD_ERR_CANT_WRITE:
                throw new UtilsException(__('Failed to write file to disk.'));
            case UPLOAD_ERR_EXTENSION:
                throw new UtilsException(__('A PHP extension stopped the file upload.'));
            default:
                return true;
        }
    }

    # Packages generation methods
    #
    /**
     * Recursive directory scanning
     *
     * Returns an array of a given directory's content. The array contains
     * two arrays: dirs and files. Directory's content is fetched recursively.
     *
     * @param string        $dirName        Directory name
     * @param array        $contents        Contents array. Leave it empty
     * @return array
     */
    public static function getDirList(string $dirName, array &$contents = null): array
    {
        if (!$contents) {
            $contents = ['dirs' => [], 'files' => []];
        }

        $exclude_list = ['.', '..', '.svn'];
        $dirName      = preg_replace('|/$|', '', $dirName);

        if (!is_dir($dirName)) {
            throw new UtilsException(sprintf(__('%s is not a directory.'), $dirName));
        }

        $contents['dirs'][] = $dirName;

        $d = @dir($dirName);
        if ($d === false) {
            throw new UtilsException(__('Unable to open directory.'));
        }

        while ($entry = $d->read()) {
            if (!in_array($entry, $exclude_list)) {
                if (is_dir($dirName . '/' . $entry)) {
                    Files::getDirList($dirName . '/' . $entry, $contents);
                } else {
                    $contents['files'][] = $dirName . '/' . $entry;
                }
            }
        }
        $d->close();

        return $contents;
    }

    /**
     * Filename cleanup
     *
     * Removes unwanted characters in a filename.
     *
     * @param string    $n        Filename
     * @return string
     */
    public static function tidyFileName(string $n): string
    {
        $n = Text::deaccent($n);
        $n = preg_replace('/^[.]/u', '', $n);

        return preg_replace('/[^A-Za-z0-9._-]/u', '_', $n);
    }
}
