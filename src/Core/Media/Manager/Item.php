<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Media\Manager;

use Dotclear\Helper\File\Path;
use Dotclear\Helper\File\Files;
use SimpleXMLElement;

/**
 * Item for file manager tool.
 *
 * \Dotclear\Core\Media\Manager\Item
 *
 * Source clearbricks https://git.dotclear.org/dev/clearbricks
 *
 * @ingroup  Core Media File
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
class Item
{
    /** @var string Complete path to file */
    public $file;

    /** @var string File basename */
    public $basename;

    /** @var string File directory name */
    public $dir;

    /** @var string File URL */
    public $file_url;

    /** @var string File directory URL */
    public $dir_url;

    /** @var string File extension */
    public $extension;

    /** @var string File path relative to <var>$root</var> given in constructor */
    public $relname;

    /** @var bool Parent directory (ie. "..") */
    public $parent = false;

    /** @var string File MimeType. See {@link Files::getMimeType()}. */
    public $type;

    /** @var string */
    public $type_prefix;

    /** @var int File modification timestamp */
    public $mtime;

    /** @var int File size */
    public $size;

    /** @var int File permissions mode */
    public $mode;

    /** @var int File owner ID */
    public $uid;

    /** @var int File group ID */
    public $gid;

    /** @var bool True if file or directory is writable */
    public $w;

    /** @var bool True if file is a directory */
    public $d;

    /** @var bool True if file file is executable or directory is traversable */
    public $x;

    /** @var bool True if file is a file */
    public $f;

    /** @var bool True if file or directory is deletable */
    public $del;

    /** @var bool Is editable */
    public $editable = true;

    /** @var string Media id */
    public $media_id = '';

    /** @var string Media title */
    public $media_title = '';

    /** @var null|SimpleXMLElement Media meta */
    public $media_meta; // xml

    /** @var string Media owner */
    public $media_user = '';

    /** @var bool Media is private */
    public $media_priv = false;

    /** @var int Media date */
    public $media_dt = 0;

    /** @var string Media date */
    public $media_dtstr = '';

    /** @var bool Media is image */
    public $media_image = false;

    /** @var string Media icon */
    public $media_icon = 'blank';

    /** @var string Media type */
    public $media_type = 'image';

    /** @var array Media avialble thumbnail */
    public $media_thumb = [];

    /**
     * Constructor.
     *
     * Creates an instance of fileItem object.
     *
     * @param string $file     Absolute file or directory path
     * @param string $root     File root path
     * @param string $root_url File root URL
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
