<?php
/**
 * @class Dotclear\Core\Media\Manager\Item
 * @brief Item for file manager tool
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @package Dotclear
 * @subpackage File
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Media\Manager;

use Dotclear\Helper\File\Path;
use Dotclear\Helper\File\Files;

class Item
{

    /** @var    string  $file   Complete path to file */
    public $file;

    /** @var    string  $basename   File basename */
    public $basename;

    /** @var    string  $dir    File directory name */
    public $dir;

    /** @var    string  $file_url   File URL */
    public $file_url;

    /** @var    string  $dir_url    File directory URL */
    public $dir_url;

    /** @var    string  $extension  File extension */
    public $extension;

    /** @var    string  $relname    File path relative to <var>$root</var> given in constructor */
    public $relname;

    /** @var    bool    $parent     Parent directory (ie. "..") */
    public $parent = false;

    /** @var    string  $type   File MimeType. See {@link Files::getMimeType()}. */
    public $type;

    /** @var    string  $type_prefix  */  
    public $type_prefix;

    /** @var    int     $mtime   File modification timestamp */
    public $mtime;

    /** @var    int     $size  File size */
    public $size;

    /** @var    int     $mode  File permissions mode */
    public $mode;

    /** @var    int     $uid    File owner ID */
    public $uid;

    /** @var    int     $gid    File group ID */
    public $gid;

    /** @var    bool    $w  True if file or directory is writable */
    public $w;

    /** @var    bool    $d  True if file is a directory */
    public $d;

    /** @var    bool    $x  True if file file is executable or directory is traversable */
    public $x;

    /** @var    bool    $f  True if file is a file */
    public $f;

    /** @var    bool    $del    True if file or directory is deletable */
    public $del;

    /** @var    bool    $editable   Is editable */
    public $editable    = true;


    /** @var    string  $media_id   Media id */
    public $media_id    = '';

    /** @var    string  $media_title   Media title */ 
    public $media_title = '';

    /** @var \SimpleXMLElement|null     $media_meta     Media meta */
    public $media_meta; //xml

    /** @var    string  $media_user     Media owner */
    public $media_user  = '';

    /** @var    bool   $media_priv   Media is private */
    public $media_priv  = false;

    /** @var    int     $media_dt   Media date */
    public $media_dt    = 0;

    /** @var    string  $media_dtstr    Media date */
    public $media_dtstr = '';

    /** @var    bool    $media_image    Media is image */
    public $media_image = false;

    /** @var    string  $media_icon     Media icon */
    public $media_icon  = 'blank';

    /** @var    string  $media_type     Media type */
    public $media_type = 'image';

    /** @var    array   $media_thumb    Media avialble thumbnail */
    public $media_thumb = [];

    /**
     * Constructor
     *
     * Creates an instance of fileItem object.
     *
     * @param   string  $file       Absolute file or directory path
     * @param   string  $root       File root path
     * @param   string  $root_url   File root URL
     */
    public function __construct(string $file, string $root, string $root_url = '')
    {
        $file = Path::real($file, false);
        $stat = stat($file);
        $path = Path::info($file);
        $rel  = preg_replace('/^' . preg_quote($root, '/') . '\/?/', '', (string) $file);

        $this->file        = $file;
        $this->basename    = $path['basename'];
        $this->dir         = $path['dirname'];
        $this->relname     = $rel;
        $this->file_url    = $root_url . str_replace('%2F', '/', rawurlencode($rel));
        $this->dir_url     = dirname($this->file_url);
        $this->extension   = $path['extension'];
        $this->mtime       = $stat[9];
        $this->size        = $stat[7];
        $this->mode        = $stat[2];
        $this->uid         = $stat[4];
        $this->gid         = $stat[5];
        $this->w           = is_writable($file);
        $this->d           = is_dir($file);
        $this->f           = is_file($file);
        $this->x           = $this->d ? file_exists($file . '/.') : false;
        $this->del         = Files::isDeletable($file);
        $this->type        = $this->d ? null : Files::getMimeType($file);
        $this->type_prefix = preg_replace('/^(.+?)\/.+$/', '$1', (string) $this->type);
    }
}
