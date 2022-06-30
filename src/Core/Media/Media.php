<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Media;

// Dotclear\Core\Media\Media
use Dotclear\App;
use Dotclear\Core\Media\Image\ImageMeta;
use Dotclear\Core\Media\Image\ImageTools;
use Dotclear\Database\Cursor;
use Dotclear\Database\Param;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\InsufficientPermissions;
use Dotclear\Exception\InvalidConfiguration;
use Dotclear\Exception\InvalidValueReference;
use Dotclear\Helper\Clock;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\File\Zip\Unzip;
use Dotclear\Helper\Html\XmlTag;
use Dotclear\Helper\Mapper\Integers;
use Dotclear\Helper\Text;
use SimpleXMLElement;
use Exception;

/**
 * Media handling methods.
 *
 * This class handles Dotclear media items.
 *
 * @ingroup  Core Media
 */
final class Media
{
    /**
     * @var string $pwd
     *             Working (current) director
     */
    private $pwd = '';

    /**
     * @var array<int,string> $exclude_list
     *                        Array of regexps defining excluded items
     */
    private $exclude_list = [];

    /**
     * @var string $exclude_pattern
     *             Files exclusion regexp pattern
     */
    private $exclude_pattern = '';

    /**
     * @var array<string,array> $dir
     *                          Current directory content array
     */
    public $dir = ['dirs' => [], 'files' => []];

    /**
     * @var ThumbSize $thumbsize
     *                Thumb sizes definitions instance
     */
    private $thumbsize;

    /**
     * @var string $file_sort
     *             Sort field
     */
    private $file_sort = 'name-asc';

    /**
     * @var string $path
     *             Media path
     */
    private $path = '';

    /**
     * @var string $relpwd
     *             Relative path
     */
    private $relpwd = '';

    /**
     * @var array<string,array> $file_handler
     *                          File handler callback
     */
    private $file_handler = [];

    /**
     * @var string $thumb_tp
     *             Thumbnail file pattern
     */
    public $thumb_tp = '%s/.%s_%s.jpg';

    /**
     * @var string $thumb_tp_alpha
     *             Thumbnail file pattern (with alpha layer)
     */
    public $thumb_tp_alpha = '%s/.%s_%s.png';

    /**
     * @var string $thumb_tp_webp
     *             Thumbnail file pattern (webp)
     * */
    public $thumb_tp_webp = '%s/.%s_%s.webp';

    /**
     * @var string $icon_img
     *             Icon file URL pattern
     */
    public $icon_img = '?df=images/media/%s.png';

    /**
     * @var string $root
     */
    public $root = '';

    /**
     * @var string @root_url
     */
    public $root_url = '';

    /**
     * Constructor.
     *
     * @param string $type The media type filter
     *
     * @throws InvalidConfiguration
     */
    public function __construct(private string $type = '')
    {
        if (!App::core()->blog()) {
            throw new InvalidConfiguration(__('No blog defined.'));
        }

        $this->root = $this->pwd = Path::real(App::core()->blog()->public_path);
        if (!$this->root || !is_dir($this->root)) {
            // Check public directory
            if (App::core()->user()->isSuperAdmin()) {
                throw new InvalidConfiguration(__('There is no writable directory /public/ at the location set in about:config "public_path". You must create this directory with sufficient rights (or change this setting).'));
            }

            throw new InvalidConfiguration(__('There is no writable root directory for the media manager. You should contact your administrator.'));
        }

        $this->root_url = rawurldecode(App::core()->blog()->public_url);
        if (!preg_match('#/$#', (string) $this->root_url)) {
            $this->root_url = $this->root_url . '/';
        }

        $this->changeDirectory('');

        $this->path = (string) App::core()->blog()->settings('system')->getSetting('public_path');
        // !
        $this->addExclusion(Path::implodeSrc());
        $this->addExclusion(__DIR__ . '/../');

        $this->exclude_pattern = App::core()->blog()->settings('system')->getSetting('media_exclusion');

        // Event handlers
        $this->addFileHandler('image/jpeg', 'create', [$this, 'imageThumbCreate']);
        $this->addFileHandler('image/png', 'create', [$this, 'imageThumbCreate']);
        $this->addFileHandler('image/gif', 'create', [$this, 'imageThumbCreate']);
        $this->addFileHandler('image/webp', 'create', [$this, 'imageThumbCreate']);

        $this->addFileHandler('image/png', 'update', [$this, 'imageThumbUpdate']);
        $this->addFileHandler('image/jpeg', 'update', [$this, 'imageThumbUpdate']);
        $this->addFileHandler('image/gif', 'update', [$this, 'imageThumbUpdate']);
        $this->addFileHandler('image/webp', 'update', [$this, 'imageThumbUpdate']);

        $this->addFileHandler('image/png', 'remove', [$this, 'imageThumbRemove']);
        $this->addFileHandler('image/jpeg', 'remove', [$this, 'imageThumbRemove']);
        $this->addFileHandler('image/gif', 'remove', [$this, 'imageThumbRemove']);
        $this->addFileHandler('image/webp', 'remove', [$this, 'imageThumbRemove']);

        $this->addFileHandler('image/jpeg', 'create', [$this, 'imageMetaCreate']);
        $this->addFileHandler('image/webp', 'create', [$this, 'imageMetaCreate']);

        $this->addFileHandler('image/jpeg', 'recreate', [$this, 'imageThumbCreate']);
        $this->addFileHandler('image/png', 'recreate', [$this, 'imageThumbCreate']);
        $this->addFileHandler('image/gif', 'recreate', [$this, 'imageThumbCreate']);
        $this->addFileHandler('image/webp', 'recreate', [$this, 'imageThumbCreate']);

        // Thumbnails sizes
        $this->thumbsize()->setCode('m', abs(App::core()->blog()->settings('system')->getSetting('media_img_m_size')), false, __('medium'));
        $this->thumbsize()->setCode('s', abs(App::core()->blog()->settings('system')->getSetting('media_img_s_size')), false, __('small'));
        $this->thumbsize()->setCode('t', abs(App::core()->blog()->settings('system')->getSetting('media_img_t_size')), false, __('thumbnail'));
        $this->thumbsize()->setCode('sq', 48, true, __('square'));

        // --BEHAVIOR-- coreAfterConstructMedia, Media
        App::core()->behavior('coreAfterConstructMedia')->call(media: $this);
    }

    /**
     * Get thumb sizes definitions instance.
     *
     * ThumbSize methods are accesible from App::core()->media()->thumbsize()
     *
     * @return ThumbSize The thumb sizes definitions stack
     */
    public function thumbsize(): ThumbSize
    {
        if (!($this->thumbsize instanceof ThumbSize)) {
            $this->thumbsize = new ThumbSize();
        }

        return $this->thumbsize;
    }

    /**
     * Change working directory.
     *
     * @param null|string $dir The directory name
     *
     * @throws InvalidValueReference
     */
    public function changeDirectory(?string $dir): void
    {
        $realdir = Path::real($this->root . '/' . Path::clean($dir));
        if (!$realdir || !is_dir($realdir)) {
            throw new InvalidValueReference('Invalid directory.');
        }

        if ($this->isExclude($realdir)) {
            throw new InvalidValueReference('Directory is excluded.');
        }

        $this->pwd    = $realdir;
        $this->relpwd = preg_replace('/^' . preg_quote($this->root, '/') . '\/?/', '', $this->pwd);
    }

    /**
     * Get working directory.
     */
    public function getCurrentDirectory(): string
    {
        return $this->pwd;
    }

    /**
     * Check if current directory is writable.
     *
     * @return bool True if working directory is writable
     */
    public function isWritableDirectory(): bool
    {
        return !$this->pwd ? false : is_writable($this->pwd);
    }

    /**
     * Add exclusion.
     *
     * Appends an exclusion to exclusions list. $f should be a regexp.
     *
     * @see     self::$exclude_list
     *
     * @param array|string $regexp Exclusion regexp
     */
    public function addExclusion(array|string $regexp): void
    {
        if (is_array($regexp)) {
            foreach ($regexp as $v) {
                if (false !== ($V = Path::real($v))) {
                    $this->exclude_list[] = $V;
                }
            }
        } elseif (false !== ($F = Path::real($regexp))) {
            $this->exclude_list[] = $F;
        }
    }

    /**
     * Path is excluded.
     *
     * Returns true if path (file or directory) $path is excluded.
     * $path is relative to self::$root path.
     *
     * @see self::$exclude_list
     *
     * @param string $path Path to match
     */
    private function isExclude(string $path): bool
    {
        foreach ($this->exclude_list as $v) {
            if (str_starts_with($path, $v)) {
                return true;
            }
        }

        return false;
    }

    /**
     * File is excluded.
     *
     * Returns true if $file is excluded.
     * $file path is relative to self::$root path.
     *
     * @see self::$exclude_pattern
     *
     * @param string $file File to match
     */
    private function isFileExclude(string $file): bool
    {
        return !$this->exclude_pattern ? false : (bool) preg_match($this->exclude_pattern, (string) $file);
    }

    /**
     * Item in jail.
     *
     * Returns true if file or directory $path is in jail.
     * ie. not outside the self::$root directory.
     *
     * @param string $path Path to match
     */
    private function isInJail(string $path): bool
    {
        $path = Path::real($path);

        return false !== $path ? (bool) preg_match('|^' . preg_quote($this->root, '|') . '|', (string) $path) : false;
    }

    /**
     * File in files.
     *
     * Returns true if file $file is in files array of self::$dir.
     *
     * @param string $file File to match
     */
    private function isInFiles(string $file): bool
    {
        foreach ($this->dir['files'] as $v) {
            if ($v->relname == $file) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add a new file handler for a given media type and event.
     *
     * Available events are:
     * - create: file creation
     * - update: file update
     * - remove: file deletion
     *
     * @param string   $type     The media type
     * @param string   $event    The event
     * @param callable $function The callback
     */
    public function addFileHandler(string $type, string $event, callable $function): void
    {
        $this->file_handler[$type][$event][] = $function;
    }

    /**
     * Call file handler.
     *
     * @param string $type  The media type
     * @param string $event The event
     * @param mixed  $args  The callback
     */
    private function callFileHandler(string $type, string $event, mixed ...$args): void
    {
        if (!empty($this->file_handler[$type][$event])) {
            foreach ($this->file_handler[$type][$event] as $f) {
                call_user_func_array($f, $args);
            }
        }
    }

    /**
     * Get HTML breadCrumb for media manager navigation.
     *
     * @param string $href The URL pattern
     * @param string $last The last item pattern
     *
     * @return string HTML code
     */
    public function getMediaBreadcrumb(string $href, string $last = ''): string
    {
        $res = '';
        if ($this->relpwd && '.' != $this->relpwd) {
            $pwd   = '';
            $arr   = explode('/', $this->relpwd);
            $count = count($arr);
            foreach ($arr as $v) {
                if ('' != $last && 0 === --$count) {
                    $res .= sprintf($last, $v);
                } else {
                    $pwd .= rawurlencode($v) . '/';
                    $res .= '<a href="' . sprintf($href, $pwd) . '">' . $v . '</a> / ';
                }
            }
        }

        return $res;
    }

    /**
     * Build record from mdeia item.
     *
     * @param Record $rs Media record
     *
     * @return null|MediaItem Media Item
     */
    private function getMediaItemsFromRecord(Record $rs): ?MediaItem
    {
        if (!$rs->isEmpty()
            && !$this->isFileExclude($this->root . '/' . $rs->field('media_file'))
            && is_file($this->root . '/' . $rs->field('media_file'))
        ) {
            $item = new MediaItem($this->root . '/' . $rs->field('media_file'), $this->root, $this->root_url);

            if ($this->type && $item->type_prefix != $this->type) {
                return null;
            }

            $meta = @simplexml_load_string((string) $rs->field('media_meta'));

            $item->editable    = true;
            $item->media_id    = $rs->field('media_id');
            $item->media_title = $rs->field('media_title');
            $item->media_meta  = $meta instanceof SimpleXMLElement ? $meta : simplexml_load_string('<meta></meta>');
            $item->media_user  = $rs->field('user_id');
            $item->media_priv  = (bool) $rs->field('media_private');
            $item->media_dt    = Clock::ts(date: $rs->field('media_dt'));
            $item->media_dtstr = Clock::str(format: '%Y-%m-%d %H:%M', date: $item->media_dt, to: App::core()->getTimezone());

            $item->media_image = false;

            if (!App::core()->user()->check('media_admin', App::core()->blog()->id)
                && App::core()->user()->userID() != $item->media_user
            ) {
                $item->del      = false;
                $item->editable = false;
            }

            $type_prefix = explode('/', $item->type);
            $type_prefix = $type_prefix[0];

            $item->media_icon = match ($type_prefix) {
                'image' => 'image',
                'audio' => 'audio',
                'text'  => 'text',
                'video' => 'video',
                default => 'blank',
            };
            $item->media_image = 'image' == $item->media_icon;
            $item->media_icon  = match ($item->type) {
                'application/msword',
                'application/vnd.oasis.opendocument.text',
                'application/vnd.sun.xml.writer',
                'application/pdf',
                'application/postscript', => 'document',
                'application/msexcel',
                'application/vnd.oasis.opendocument.spreadsheet',
                'application/vnd.sun.xml.calc', => 'spreadsheet',
                'application/mspowerpoint',
                'application/vnd.oasis.opendocument.presentation',
                'application/vnd.sun.xml.impress', => 'presentation',
                'application/x-debian-package',
                'application/x-bzip',
                'application/x-gzip',
                'application/x-java-archive',
                'application/rar',
                'application/x-redhat-package-manager',
                'application/x-tar',
                'application/x-gtar',
                'application/zip', => 'package',
                'application/octet-stream', => 'executable',
                'application/x-shockwave-flash', => 'video',
                'application/ogg', => 'audio',
                'text/html', => 'html',
                default => $item->media_icon,
            };

            $item->media_type = $item->media_icon;
            $item->media_icon = sprintf($this->icon_img, $item->media_icon);

            // Thumbnails
            $item->media_thumb = [];
            $p                 = Path::info($item->relname);

            $alpha = strtolower($p['extension']) === 'png';
            $webp  = strtolower($p['extension']) === 'webp';

            $thumb = sprintf(
                ($alpha ? $this->thumb_tp_alpha :
                    ($webp ? $this->thumb_tp_webp : $this->thumb_tp)),
                $this->root . '/' . $p['dirname'],
                $p['base'],
                '%s'
            );
            $thumb_url = sprintf(
                ($alpha ? $this->thumb_tp_alpha :
                    ($webp ? $this->thumb_tp_webp : $this->thumb_tp)),
                $this->root_url . $p['dirname'],
                $p['base'],
                '%s'
            );

            // Cleaner URLs
            $thumb_url = preg_replace('#\./#', '/', $thumb_url);
            $thumb_url = preg_replace('#(?<!:)/+#', '/', $thumb_url);

            $thumb_alt     = '';
            $thumb_url_alt = '';

            if ($alpha || $webp) {
                $thumb_alt     = sprintf($this->thumb_tp, $this->root . '/' . $p['dirname'], $p['base'], '%s');
                $thumb_url_alt = sprintf($this->thumb_tp, $this->root_url . $p['dirname'], $p['base'], '%s');
                // Cleaner URLs
                $thumb_url_alt = preg_replace('#\./#', '/', $thumb_url_alt);
                $thumb_url_alt = preg_replace('#(?<!:)/+#', '/', $thumb_url_alt);
            }

            foreach ($this->thumbsize()->getCodes() as $code) {
                if (file_exists(sprintf($thumb, $code))) {
                    $item->media_thumb[$code] = sprintf($thumb_url, $code);
                } elseif (($alpha || $webp) && file_exists(sprintf($thumb_alt, $code))) {
                    $item->media_thumb[$code] = sprintf($thumb_url_alt, $code);
                }
            }

            if ('image' === $item->media_type) {
                if (isset($item->media_thumb['sq'])) {
                    $item->media_icon = $item->media_thumb['sq'];
                } elseif ('svg' === strtolower($p['extension'])) {
                    $item->media_icon = $this->root_url . $p['dirname'] . '/' . $p['base'] . '.' . $p['extension'];
                }
            }

            return $item;
        }

        return null;
    }

    /**
     * Set the file sort.
     *
     * @param string $type The type
     */
    public function setFileSort(string $type = 'name'): void
    {
        if (in_array($type, ['size-asc', 'size-desc', 'name-asc', 'name-desc', 'date-asc', 'date-desc'])) {
            $this->file_sort = $type;
        }
    }

    /**
     * Sort file handler.
     *
     * @param null|MediaItem $a First item
     * @param null|MediaItem $b Second item
     *
     * @return int Comparison result
     */
    private function sortFileHandler(?MediaItem $a, ?MediaItem $b): int
    {
        if (is_null($a) || is_null($b)) {
            return is_null($a) ? 1 : -1;
        }

        return match ($this->file_sort) {
            'size-asc'  => $a->size     == $b->size ? 0 : ($a->size < $b->size ? -1 : 1),
            'size-desc' => $a->size     == $b->size ? 0 : ($a->size > $b->size ? -1 : 1),
            'date-asc'  => $a->media_dt == $b->media_dt ? 0 : ($a->media_dt < $b->media_dt ? -1 : 1),
            'date-desc' => $a->media_dt == $b->media_dt ? 0 : ($a->media_dt > $b->media_dt ? -1 : 1),
            'name-desc' => strcasecmp($b->basename, $a->basename),
            default     => strcasecmp($a->basename, $b->basename),
        };
    }

    /**
     * Get current working directory content.
     *
     * @param null|string $type The media type filter
     *
     * @throws InvalidValueReference
     */
    public function getDirectoryContent(?string $type = null): void
    {
        if ($type) {
            $this->type = $type;
        }

        $media_dir = $this->relpwd ?: '.';

        $sql = new SelectStatement();
        $sql->columns([
            'media_file',
            'media_id',
            'media_path',
            'media_title',
            'media_meta',
            'media_dt',
            'media_creadt',
            'media_upddt',
            'media_private',
            'user_id',
        ]);
        $sql->from(App::core()->getPrefix() . 'media');
        $sql->where('media_path = ' . $sql->quote($this->path));
        $sql->and('media_dir = ' . $sql->quote($media_dir, true));

        if (!App::core()->user()->check('media_admin', App::core()->blog()->id)) {
            $list = ['media_private <> 1'];
            if ($user_id = App::core()->user()->userID()) {
                $list[] = 'user_id = ' . $sql->quote($user_id, true);
            }
            $sql->and($sql->orGroup($list));
        }

        $record = $sql->select();

        // Get list of private files in dir
        $sql = new SelectStatement();
        $sql->columns([
            'media_file',
            'media_id',
            'media_path',
            'media_title',
            'media_meta',
            'media_dt',
            'media_creadt',
            'media_upddt',
            'media_private',
            'user_id',
        ]);
        $sql->from(App::core()->getPrefix() . 'media');
        $sql->where('media_path = ' . $sql->quote($this->path));
        $sql->and('media_dir = ' . $sql->quote($media_dir, true));
        $sql->and('media_private = 1');

        $rsp      = $sql->select();
        $privates = [];
        while ($rsp->fetch()) {
            // File in subdirectory, forget about it!
            if ('.' != dirname($rsp->field('media_file')) && dirname($rsp->field('media_file')) != $this->relpwd) {
                continue;
            }
            if ($item = $this->getMediaItemsFromRecord($rsp)) {
                $privates[] = $item->relname;
            }
        }
        $dir = Path::clean($this->pwd);

        $dh = @opendir($dir);

        if (false === $dh) {
            throw new InvalidValueReference('Unable to read directory.');
        }

        $d_res = $f_res = [];

        while (false !== ($file = readdir($dh))) {
            $fname = $dir . '/' . $file;

            if ($this->isInJail($fname) && !$this->isExclude($fname)) {
                if (is_dir($fname) && '.' != $file) {
                    $tmp = new MediaItem($fname, $this->root, $this->root_url);
                    if ('..' == $file) {
                        $tmp->parent = true;
                    }
                    $d_res[] = $tmp;
                }

                if (is_file($fname) && !str_starts_with($file, '.') && !$this->isFileExclude($file)) {
                    $f_res[] = new MediaItem($fname, $this->root, $this->root_url);
                }
            }
        }
        closedir($dh);

        $this->dir = ['dirs' => $d_res, 'files' => $f_res];
        usort($this->dir['dirs'], [$this, 'sortHandler']);
        usort($this->dir['files'], [$this, 'sortHandler']);

        $f_res = [];
        $p_dir = $this->dir;

        // If type is set, remove items from p_dir
        if ($this->type) {
            foreach ($p_dir['files'] as $k => $item) {
                if ($item->type_prefix != $this->type) {
                    unset($p_dir['files'][$k]);
                }
            }
        }

        $f_reg = [];

        while ($record->fetch()) {
            // File in subdirectory, forget about it!
            if ('.' != dirname($record->field('media_file')) && dirname($record->field('media_file')) != $this->relpwd) {
                continue;
            }

            if ($this->isInFiles($record->field('media_file'))) {
                $item = $this->getMediaItemsFromRecord($record);
                if (null !== $item) {
                    if (isset($f_reg[$record->field('media_file')])) {
                        // That media is duplicated in the database,
                        // time to do a bit of house cleaning.
                        $sql = new DeleteStatement();
                        $sql->from(App::core()->getPrefix() . 'media');
                        $sql->where('media_id = ' . $this->getMediaItemsFromRecord($record)->media_id);
                        $sql->delete();
                    } else {
                        $f_res[]                             = $this->getMediaItemsFromRecord($record);
                        $f_reg[$record->field('media_file')] = 1;
                    }
                }
            } elseif (!empty($p_dir['files']) && '' == $this->relpwd) {
                // Physical file does not exist remove it from DB
                // Because we don't want to erase everything on
                // dotclear upgrade, do it only if there are files
                // in directory and directory is root
                $sql = new DeleteStatement();
                $sql->from(App::core()->getPrefix() . 'media');
                $sql->where('media_path = ' . $sql->quote($this->path, true));
                $sql->and('media_file = ' . $sql->quote($record->field('media_file'), true));
                $sql->delete();
                $this->callFileHandler(Files::getMimeType($record->field('media_file')), 'remove', $this->pwd . '/' . $record->field('media_file'));
            }
        }

        $this->dir['files'] = $f_res;
        foreach ($this->dir['dirs'] as $k => $v) {
            $v->media_icon = sprintf($this->icon_img, ($v->parent ? 'folder-up' : 'folder'));
        }

        // Check files that don't exist in database and create them
        if (App::core()->user()->check('media,media_admin', App::core()->blog()->id)) {
            foreach ($p_dir['files'] as $item) {
                // Warning a file may exist in DB but in private mode for the user, so we don't have to recreate it
                if (!isset($f_reg[$item->relname]) && !in_array($item->relname, $privates)) {
                    if (false !== ($id = $this->createFile($item->basename, null, false, null, false))) {
                        $this->dir['files'][] = $this->getFile($id);
                    }
                }
            }
        }

        try {
            usort($this->dir['files'], [$this, 'sortFileHandler']);
        } catch (\Exception) {
        }
    }

    /**
     * Get file by its id.
     *
     * @param int $id The file identifier
     *
     * @return null|MediaItem The file
     */
    public function getFile(int $id): ?MediaItem
    {
        $sql = new SelectStatement();
        $sql->from(App::core()->getPrefix() . 'media');
        $sql->columns([
            'media_id',
            'media_path',
            'media_title',
            'media_file',
            'media_meta',
            'media_dt',
            'media_creadt',
            'media_upddt',
            'media_private',
            'user_id',
        ]);
        $sql->where('media_path = ' . $sql->quote($this->path));
        $sql->and('media_id = ' . (int) $id);

        if (!App::core()->user()->check('media_admin', App::core()->blog()->id)) {
            $list = ['media_private <> 1'];
            if ($user_id = App::core()->user()->userID()) {
                $list[] = 'user_id = ' . $sql->quote($user_id, true);
            }
            $sql->and($sql->orGroup($list));
        }

        $record = $sql->select();

        return $this->getMediaItemsFromRecord($record);
    }

    /**
     * Upload file.
     *
     * Move <var>$tmp</var> file to its final destination <var>$dest</var> and
     * returns the destination file path.
     * <var>$dest</var> should be in jail. This method will throw exception
     * if the file cannot be written.
     *
     * You should first verify upload status, with {@link Files::uploadStatus()}
     * or PHP native functions.
     *
     * @see Files::uploadStatus()
     *
     * @param string $tmp       Temporary uploaded file path
     * @param string $dest      Destination file
     * @param bool   $overwrite Overwrite mode
     *
     * @throws InvalidConfiguration
     * @throws InvalidValueReference
     *
     * @return string Destination real path
     */
    public function uploadFile(string $tmp, string $dest, bool $overwrite = false): string
    {
        $dest = $this->pwd . '/' . Path::clean($dest);

        if ($this->isFileExclude($dest)) {
            throw new InvalidConfiguration(__('Uploading this file is not allowed.'));
        }

        if (!$this->isInJail(dirname($dest))) {
            throw new InvalidConfiguration(__('Destination directory is not in jail.'));
        }

        if (!$overwrite && file_exists($dest)) {
            throw new InvalidValueReference(__('File already exists.'));
        }

        if (!is_writable(dirname($dest))) {
            throw new InvalidConfiguration(__('Cannot write in this directory.'));
        }

        if (false === @move_uploaded_file($tmp, $dest)) {
            throw new InvalidConfiguration(__('An error occurred while writing the file.'));
        }

        Files::inheritChmod($dest);

        return Path::real($dest);
    }

    /**
     * Upload file by bits.
     *
     * Creates a new file <var>$name</var> with contents of <var>$bits</var> and
     * return the destination file path.
     * <var>$name</var> should be in jail. This method will throw exception
     * if file cannot be written.
     *
     * @param string $bits Destination file content
     * @param string $name Destination file
     *
     * @throws InvalidConfiguration
     *
     * @return string Destination real path
     */
    public function uploadBits(string $name, string $bits): string
    {
        $dest = $this->pwd . '/' . Path::clean($name);

        if ($this->isFileExclude($dest)) {
            throw new InvalidConfiguration(__('Uploading this file is not allowed.'));
        }

        if (!$this->isInJail(dirname($dest))) {
            throw new InvalidConfiguration(__('Destination directory is not in jail.'));
        }

        if (!is_writable(dirname($dest))) {
            throw new InvalidConfiguration(__('Cannot write in this directory.'));
        }

        $fp = @fopen($dest, 'wb');
        if (false === $fp) {
            throw new InvalidConfiguration(__('An error occurred while writing the file.'));
        }

        fwrite($fp, $bits);
        fclose($fp);
        Files::inheritChmod($dest);

        return Path::real($dest);
    }

    /**
     * Search into media db (only).
     *
     * @param string $query The search query
     *
     * @return bool True or false if nothing found
     */
    public function searchMedia(string $query): bool
    {
        if ('' == $query) {
            return false;
        }

        $sql = new SelectStatement();
        $sql->from(App::core()->getPrefix() . 'media');
        $sql->columns([
            'media_file',
            'media_id',
            'media_path',
            'media_title',
            'media_meta',
            'media_dt',
            'media_creadt',
            'media_upddt',
            'media_private',
            'user_id',
        ]);
        $sql->where('media_path = ' . $sql->quote($this->path));
        $sql->and($sql->orGroup([
            $sql->like('media_title', '%' . $sql->escape($query) . '%'),
            $sql->like('media_file', '%' . $sql->escape($query) . '%'),
            $sql->like('media_meta', '%<Description>%' . $sql->escape($query) . '%</Description>%'),
        ]));

        if (!App::core()->user()->check('media_admin', App::core()->blog()->id)) {
            $list = ['media_private <> 1'];
            if ($user_id = App::core()->user()->userID()) {
                $list[] = 'user_id = ' . $sql->quote($user_id, true);
            }
            $sql->and($sql->orGroup($list));
        }

        $record = $sql->select();

        $this->dir = ['dirs' => [], 'files' => []];
        $res       = [];
        while ($record->fetch()) {
            $item = $this->getMediaItemsFromRecord($record);
            if ($item) {
                $res[] = $item;
            }
        }
        $this->dir['files'] = $res;

        try {
            usort($this->dir['files'], [$this, 'sortFileHandler']);
        } catch (\Exception) {
        }

        return count($res) > 0 ? true : false;
    }

    /**
     * Return media items attached to a blog post.
     *
     * Result is an array containing MediaItem objects.
     *
     * @param int         $post  The post ID
     * @param null|int    $media The media ID
     * @param null|string $type  The link type
     *
     * @return array Array of MediaItem
     */
    public function getPostMedia(int $post, ?int $media = null, ?string $type = null): array
    {
        $param = new Param();
        $param->set('post_id', $post);
        $param->set('media_path', $this->path);

        if ($media) {
            $param->set('media_id', $media);
        }
        if ($type) {
            $param->set('link_type', $type);
        }
        $record = (new PostMedia())->getPostMedia(param: $param);

        $res = [];
        while ($record->fetch()) {
            $item = $this->getMediaItemsFromRecord($record);
            if (null !== $item) {
                $res[] = $item;
            }
        }

        return $res;
    }

    /**
     * Rebuilds database items collection.
     *
     * Optional <var>$pwd</var> parameter is
     * the path where to start rebuild.
     *
     * @param string $pwd The directory to rebuild
     *
     * @throws InsufficientPermissions
     */
    public function rebuildDBMedia(string $pwd = ''): void
    {
        if (!App::core()->user()->isSuperAdmin()) {
            throw new InsufficientPermissions(__('You are not a super administrator.'));
        }

        $this->changeDirectory($pwd);
        $this->getDirectoryContent();

        $dir = $this->dir;

        foreach ($dir['dirs'] as $d) {
            if (!$d->parent) {
                $this->rebuildDBMedia($d->relname);
            }
        }

        foreach ($dir['files'] as $f) {
            $this->changeDirectory(dirname($f->relname));
            $this->createFile($f->basename);
        }

        $media_dir = $pwd ?: '.';

        $sql = new SelectStatement();
        $sql->from(App::core()->getPrefix() . 'media');
        $sql->columns([
            'media_file',
            'media_id',
        ]);
        $sql->where('media_path = ' . $sql->quote($this->path));
        $sql->and('media_dir = ' . $sql->quote($media_dir, true));
        $record = $sql->select();

        $ids = new Integers();
        while ($record->fetch()) {
            if (!is_file($this->root . '/' . $record->field('media_file'))) {
                $ids->add($record->integer('media_id'));
            }
        }
        if ($ids->count()) {
            $sql = new DeleteStatement();
            $sql->from(App::core());
            $sql->where('media_id' . $sql->in($ids->dump()));
            $sql->delete();
        }
    }

    /**
     * Make a dir.
     *
     * @param string $dir the directory to create
     */
    public function makeDir(string $dir): void
    {
        $dir = Files::tidyFileName($dir);
        Files::makeDir($this->pwd . '/' . Path::clean($dir));
    }

    /**
     * Move file.
     *
     * Moves a file <var>$source</var> to a new destination <var>$dest</var>. Both
     * <var>$source</var> and <var>$dest</var> are relative to $root.
     *
     * @param string $source Source file
     * @param string $dest   Destination file
     *
     * @throws InvalidConfiguration
     * @throws InvalidValueReference
     */
    public function moveFile(string $source, string $dest): void
    {
        $source = $this->root . '/' . Path::clean($source);
        $dest   = $this->root . '/' . Path::clean($dest);

        if (false === ($source = Path::real($source))) {
            throw new InvalidValueReference(__('Source file does not exist.'));
        }

        $dest_dir = Path::real(dirname($dest));

        if (!$this->isInJail($source)) {
            throw new InvalidConfiguration(__('File is not in jail.'));
        }
        if (!$this->isInJail($dest_dir)) {
            throw new InvalidConfiguration(__('File is not in jail.'));
        }

        if (!is_writable($dest_dir)) {
            throw new InvalidConfiguration(__('Destination directory is not writable.'));
        }

        if (false === @rename($source, $dest)) {
            throw new InvalidConfiguration(__('Unable to rename file.'));
        }
    }

    /**
     * Remove item.
     *
     * Removes a file or directory <var>$path</var> which is relative to working
     * directory.
     *
     * @param string $path Path to remove
     */
    public function removeItem(string $path): void
    {
        $file = Path::real($this->pwd . '/' . Path::clean($path));

        if (false === $file) {
            return;
        }
        if (is_file($file)) {
            $this->removeFile($path);
        } elseif (is_dir($file)) {
            $this->removeDir($path);
        }
    }

    /**
     * Create or update a file in database.
     *
     * Returns new media ID or false if file does not exist.
     *
     * @param string      $name    The file name (relative to working directory)
     * @param null|string $title   The file title
     * @param bool        $private File is private
     * @param null|string $date    File date
     * @param bool        $force   The force flag
     *
     * @throws InsufficientPermissions
     *
     * @return false|int New media ID or false
     */
    public function createFile(string $name, ?string $title = null, bool $private = false, ?string $date = null, bool $force = true): int|false
    {
        if (!App::core()->user()->check('media,media_admin', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('Permission denied.'));
        }

        $file = $this->pwd . '/' . $name;
        if (!file_exists($file)) {
            return false;
        }

        $media_file = $this->relpwd ? Path::clean($this->relpwd . '/' . $name) : Path::clean($name);
        $media_type = Files::getMimeType($name);

        $cur = App::core()->con()->openCursor(App::core()->getPrefix() . 'media');

        $sql = new SelectStatement();
        $sql->from(App::core()->getPrefix() . 'media');
        $sql->column('media_id');
        $sql->where('media_path = ' . $sql->quote($this->path, true));
        $sql->and('media_file = ' . $sql->quote($media_file, true));

        $record = $sql->select();

        if ($record->isEmpty()) {
            App::core()->con()->writeLock(App::core()->getPrefix() . 'media');

            try {
                $sql = new SelectStatement();
                $sql->from(App::core()->getPrefix() . 'media');
                $sql->column($sql->max('media_id'));

                $media_id = $sql->select()->integer() + 1;

                $cur->setField('media_id', $media_id);
                $cur->setField('user_id', (string) App::core()->user()->userID());
                $cur->setField('media_path', (string) $this->path);
                $cur->setField('media_file', (string) $media_file);
                $cur->setField('media_dir', (string) dirname($media_file));
                $cur->setField('media_creadt', Clock::database());
                $cur->setField('media_upddt', Clock::database());
                $cur->setField('media_title', !$title ? (string) $name : (string) $title);
                $cur->setField('media_private', (int) (bool) $private);
                $cur->setField('media_dt', ($date ? $date : Clock::str(format: '%Y-%m-%d %H:%M:%S', date: filemtime($file))));

                try {
                    $cur->insert();
                } catch (Exception $e) {
                    @unlink($name);

                    throw $e;
                }
                App::core()->con()->unlock();
            } catch (Exception $e) {
                App::core()->con()->unlock();

                throw $e;
            }
        } else {
            $media_id = $record->integer('media_id');

            $cur->setField('media_upddt', Clock::database());

            $sql = new UpdateStatement();
            $sql->where('media_id = ' . $media_id);

            $sql->update($cur);
        }

        $this->callFileHandler($media_type, 'create', $cur, $name, $media_id, $force);

        return $media_id;
    }

    /**
     * Update a file in database.
     *
     * @param MediaItem $file The file
     * @param MediaItem $new  The new file
     *
     * @throws InvalidConfiguration
     * @throws InsufficientPermissions
     * @throws InvalidValueReference
     */
    public function updateFile(MediaItem $file, MediaItem $new): void
    {
        if (!App::core()->user()->check('media,media_admin', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('Permission denied.'));
        }

        $id = (int) $file->media_id;

        if (!$id) {
            throw new InvalidValueReference('No file ID');
        }

        if (!App::core()->user()->check('media_admin', App::core()->blog()->id)
            && App::core()->user()->userID() != $file->media_user) {
            throw new InsufficientPermissions(__('You are not the file owner.'));
        }

        $cur = App::core()->con()->openCursor(App::core()->getPrefix() . 'media');

        // We need to tidy new basename. If dir isn't empty, concat to basename
        $new->relname = Files::tidyFileName($new->basename);
        if ($new->dir) {
            $new->relname = $new->dir . '/' . $new->relname;
        }

        if ($file->relname != $new->relname) {
            $new->file = $this->root . '/' . $new->relname;

            if ($this->isFileExclude($new->relname)) {
                throw new InvalidConfiguration(__('This file is not allowed.'));
            }

            if (file_exists($new->file)) {
                throw new InvalidValueReference(__('New file already exists.'));
            }

            $this->moveFile($file->relname, $new->relname);

            $cur->setField('media_file', (string) $new->relname);
            $cur->setField('media_dir', (string) dirname($new->relname));
        }

        $cur->setField('media_title', (string) $new->media_title);
        $cur->setField('media_dt', (string) $new->media_dtstr);
        $cur->setField('media_upddt', Clock::database());
        $cur->setField('media_private', (int) $new->media_priv);

        if ($new->media_meta instanceof SimpleXMLElement) {
            $cur->setField('media_meta', $new->media_meta->asXML());
        }

        $sql = new UpdateStatement();
        $sql->where('media_id = ' . $id);

        $sql->update($cur);

        $this->callFileHandler($file->type, 'update', $file, $new);
    }

    /**
     * Upload a file.
     *
     * @param string      $tmp       The full path of temporary uploaded file
     * @param string      $name      The file name (relative to working directory)me
     * @param null|string $title     The file title
     * @param bool        $private   File is private
     * @param bool        $overwrite File should be overwrite
     *
     * @throws InsufficientPermissions
     *
     * @return false|int New media ID or false
     */
    public function uploadMediaFile(string $tmp, string $name, ?string $title = null, bool $private = false, bool $overwrite = false): int|false
    {
        if (!App::core()->user()->check('media,media_admin', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('Permission denied.'));
        }

        $name = Files::tidyFileName($name);

        $this->uploadFile($tmp, $name, $overwrite);

        return $this->createFile($name, $title, $private);
    }

    /**
     * Create a file from binary content.
     *
     * @param string $name The file name (relative to working directory)
     * @param string $bits The binary file contentits
     *
     * @throws InsufficientPermissions
     *
     * @return false|int New media ID or false
     */
    public function uploadMediaBits($name, $bits): int|false
    {
        if (!App::core()->user()->check('media,media_admin', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('Permission denied.'));
        }

        $name = Files::tidyFileName($name);

        $this->uploadBits($name, $bits);

        return $this->createFile($name, null, false);
    }

    /**
     * Remove a file.
     *
     * @param string $file The filename
     *
     * @throws InsufficientPermissions
     * @throws InvalidConfiguration
     * @throws InvalidValueReference
     */
    public function removeFile(string $file): void
    {
        if (!App::core()->user()->check('media,media_admin', App::core()->blog()->id)) {
            throw new InsufficientPermissions(__('Permission denied.'));
        }

        $media_file = $this->relpwd ? Path::clean($this->relpwd . '/' . $file) : Path::clean($file);

        $sql = new DeleteStatement();
        $sql->from(App::core()->getPrefix() . 'media');
        $sql->where('media_path = ' . $sql->quote($this->path, true));
        $sql->and('media_file = ' . $sql->quote($media_file));

        if (!App::core()->user()->check('media_admin', App::core()->blog()->id)) {
            $sql->and('user_id = ' . $sql->quote(App::core()->user()->userID(), true));
        }

        $sql->delete();

        if (App::core()->con()->changes() == 0) {
            throw new InvalidValueReference(__('File does not exist in the database.'));
        }

        $file = Path::real($this->pwd . '/' . Path::clean($file));

        if (false === $file) {
            return;
        }

        if (!$this->isInJail($file)) {
            throw new InvalidConfiguration(__('File is not in jail.'));
        }

        if (!Files::isDeletable($file)) {
            throw new InvalidConfiguration(__('File cannot be removed.'));
        }

        if (false === @unlink($file)) {
            throw new InvalidConfiguration(__('File cannot be removed.'));
        }

        $this->callFileHandler(Files::getMimeType($media_file), 'remove', $file);
    }

    /**
     * Remove item.
     *
     * Removes a directory <var>$dir</var> which is relative to working directory.
     *
     * @param string $dir Directory to remove
     *
     * @throws InvalidConfiguration
     */
    public function removeDir(string $dir): void
    {
        $dir = Path::real($this->pwd . '/' . Path::clean($dir));

        if (false === $dir) {
            return;
        }

        if (!$this->isInJail($dir)) {
            throw new InvalidConfiguration(__('Directory is not in jail.'));
        }

        if (!Files::isDeletable($dir)) {
            throw new InvalidConfiguration(__('Directory cannot be removed.'));
        }

        if (false === @rmdir($dir)) {
            throw new InvalidConfiguration(__('Directory cannot be removed.'));
        }
    }

    /**
     * Root directories.
     *
     * Returns an array of directory under $root directory.
     *
     * @uses    MediaItem
     */
    public function getDBRootDirectories(): array
    {
        $dir       = [];
        $media_dir = $this->relpwd ?: '.';

        $sql = new SelectStatement();
        $sql->from(App::core()->getPrefix() . 'media');
        $sql->column('distinct media_dir');
        $sql->where('media_path = ' . $sql->quote($this->path));

        $record = $sql->select();
        while ($record->fetch()) {
            if (is_dir($this->root . '/' . $record->field('media_dir'))) {
                $dir[] = ('.' == $record->field('media_dir') ? '' : $record->field('media_dir'));
            }
        }

        return $dir;
    }

    /**
     * Extract zip file in current location.
     *
     * @param MediaItem $f          Media item instance
     * @param bool      $create_dir Create dir
     *
     * @throws InvalidValueReference
     *
     * @return string Destination
     */
    public function inflateZipFile(MediaItem $f, bool $create_dir = true): string
    {
        $zip = new Unzip($f->file);
        $zip->setExcludePattern($this->exclude_pattern);
        $list = $zip->getList(false, '#(^|/)(__MACOSX|\.svn|\.hg.*|\.git.*|\.DS_Store|\.directory|Thumbs\.db)(/|$)#');

        if ($create_dir) {
            $zip_root_dir = $zip->getRootDir();
            if (false != $zip_root_dir) {
                $destination = $zip_root_dir;
                $target      = $f->dir;
            } else {
                $destination = preg_replace('/\.([^.]+)$/', '', $f->basename);
                $target      = $f->dir . '/' . $destination;
            }

            if (is_dir($f->dir . '/' . $destination)) {
                throw new InvalidValueReference(sprintf(__('Extract destination directory %s already exists.'), dirname($f->relname) . '/' . $destination));
            }
        } else {
            $target      = $f->dir;
            $destination = '';
        }

        $zip->unzipAll($target);
        $zip->close();

        // Clean-up all extracted filenames
        $clean = function ($name) {
            $n = Text::deaccent($name);
            $n = preg_replace('/^[.]/u', '', $n);

            return preg_replace('/[^A-Za-z0-9._\-\/]/u', '_', $n);
        };
        foreach ($list as $zk => $zv) {
            // Check if extracted file exists
            $zf = $target . '/' . $zk;
            if (!$zv['is_dir'] && file_exists($zf)) {
                $zt = $clean($zf);
                if ($zt != $zf) {
                    rename($zf, $zt);
                }
            }
        }

        return dirname($f->relname) . '/' . $destination;
    }

    /**
     * Get the zip content.
     *
     * @param MediaItem $f Media item instance
     *
     * @return array the zip content
     */
    public function getZipContent(MediaItem $f): array
    {
        $zip  = new Unzip($f->file);
        $list = $zip->getList(false, '#(^|/)(__MACOSX|\.svn|\.hg.*|\.git.*|\.DS_Store|\.directory|Thumbs\.db)(/|$)#');
        $zip->close();

        return $list;
    }

    /**
     * Call file handlers registered for recreate event.
     *
     * @param MediaItem $f Media item instance
     */
    public function mediaFireRecreateEvent(MediaItem $f)
    {
        $media_type = Files::getMimeType($f->basename);
        $this->callFileHandler($media_type, 'recreate', null, $f->basename); // Args list to be completed as necessary (Franck)
    }

    /* Image handlers
    ------------------------------------------------------- */
    /**
     * Create image thumbnails.
     *
     * @param null|Cursor $cur   The cursor
     * @param string      $f     Image filename
     * @param bool        $force Force creation
     */
    public function imageThumbCreate(?Cursor $cur, string $f, bool $force = true): bool
    {
        $file = $this->pwd . '/' . $f;

        if (!file_exists($file)) {
            return false;
        }

        $p     = Path::info($file);
        $alpha = strtolower($p['extension']) === 'png';
        $webp  = strtolower($p['extension']) === 'webp';
        $thumb = sprintf(
            ($alpha ? $this->thumb_tp_alpha :
            ($webp ? $this->thumb_tp_webp :
                $this->thumb_tp)),
            $p['dirname'],
            $p['base'],
            '%s'
        );

        try {
            $img = new ImageTools();
            $img->loadImage($file);

            $w = $img->getW();
            $h = $img->getH();

            if ($force) {
                $this->imageThumbRemove($f);
            }

            foreach ($this->thumbsize()->getSizes() as $code => $size) {
                $thumb_file = sprintf($thumb, $code);
                if (!file_exists($thumb_file) && 0 < $size && ('sq' == $code || $w > $size || $h > $size)) {
                    $rate = (100 > $size ? 95 : (600 > $size ? 90 : 85));
                    $img->resize($size, $size, $this->thumbsize()->getCrop($code));
                    $img->output(($alpha || $webp ? strtolower($p['extension']) : 'jpeg'), $thumb_file, $rate);
                    $img->loadImage($file);
                }
            }
            $img->close();
        } catch (Exception $e) {
            if (null === $cur) {
                // Called only if cursor is null (public call)
                throw $e;
            }
        }

        return true;
    }

    /**
     * Update image thumbnails.
     *
     * @param MediaItem $file    The file
     * @param MediaItem $newFile The new file
     */
    private function imageThumbUpdate(MediaItem $file, MediaItem $newFile): void
    {
        if ($file->relname != $newFile->relname) {
            $p         = Path::info($file->relname);
            $alpha     = 'png'  === strtolower($p['extension']);
            $webp      = 'webp' === strtolower($p['extension']);
            $thumb_old = sprintf(
                ($alpha ? $this->thumb_tp_alpha :
                ($webp ? $this->thumb_tp_webp :
                    $this->thumb_tp)),
                $p['dirname'],
                $p['base'],
                '%s'
            );

            $p         = Path::info($newFile->relname);
            $alpha     = 'png'  === strtolower($p['extension']);
            $webp      = 'webp' === strtolower($p['extension']);
            $thumb_new = sprintf(
                ($alpha ? $this->thumb_tp_alpha :
                ($webp ? $this->thumb_tp_webp :
                    $this->thumb_tp)),
                $p['dirname'],
                $p['base'],
                '%s'
            );

            foreach ($this->thumbsize()->getCodes() as $code) {
                try {
                    $this->moveFile(sprintf($thumb_old, $code), sprintf($thumb_new, $code));
                } catch (\Exception) {
                }
            }
        }
    }

    /**
     * Remove image thumbnails.
     *
     * @param string $f Image filename
     */
    public function imageThumbRemove(string $f): void
    {
        $p     = Path::info($f);
        $alpha = 'png'  === strtolower($p['extension']);
        $webp  = 'webp' === strtolower($p['extension']);
        $thumb = sprintf(
            ($alpha ? $this->thumb_tp_alpha :
            ($webp ? $this->thumb_tp_webp :
                $this->thumb_tp)),
            '',
            $p['base'],
            '%s'
        );

        foreach ($this->thumbsize()->getCodes() as $code) {
            try {
                $this->removeFile(sprintf($thumb, $code));
            } catch (\Exception) {
            }
        }
    }

    /**
     * Create image meta.
     *
     * @param Cursor $cursor   The cursor
     * @param string $filename Image filename
     * @param int    $id       The media identifier
     */
    private function imageMetaCreate(Cursor $cursor, string $filename, int $id): bool
    {
        $file = $this->pwd . '/' . $filename;

        if (!file_exists($file)) {
            return false;
        }

        $xml  = new XmlTag('meta');
        $meta = ImageMeta::readMeta($file);
        $xml->insertNode($meta);

        $c = App::core()->con()->openCursor(App::core()->getPrefix() . 'media');
        $c->setField('media_meta', $xml->toXML());

        if (null !== $cursor->getField('media_title') && basename($cursor->getField('media_file')) == $cursor->getField('media_title')) {
            if ($meta['Title']) {
                $c->setField('media_title', $meta['Title']);
            }
        }

        if ($meta['DateTimeOriginal'] && '' === $cursor->getField('media_dt')) {
            $media_ts = Clock::ts(date: $meta['DateTimeOriginal']);
            if ($media_ts) {
                $c->setField('media_dt', Clock::database(date: $media_ts));
            }
        }

        // --BEHAVIOR-- coreBeforeCreateImageMeta, Cursor
        App::core()->behavior('coreBeforeCreateImageMeta')->call(cursor: $c);

        $sql = new UpdateStatement();
        $sql->where('media_id = ' . $id);

        $sql->update($c);

        return true;
    }

    /**
     * Get HTML code for audio player (HTML5).
     *
     * @param string      $type     The audio mime type (not used)
     * @param string      $url      The audio URL to play
     * @param null|string $player   The player URL (not used)
     * @param null|array  $args     The player arguments (not used)
     * @param bool        $fallback The fallback (not more used)
     * @param bool        $preload  Add preload="auto" attribute if true, else preload="none"
     */
    public static function audioPlayer(string $type, string $url, ?string $player = null, ?array $args = null, bool $fallback = false, bool $preload = true): string
    {
        return
            '<audio controls preload="' . ($preload ? 'auto' : 'none') . '">' .
            '<source src="' . $url . '">' .
            '</audio>';
    }

    /**
     * Get HTML code for video player (HTML5).
     *
     * @param string      $type     The audio mime type (not used)
     * @param string      $url      The audio URL to play
     * @param null|string $player   The player URL (not used)
     * @param null|array  $args     The player arguments (not used)
     * @param bool        $fallback The fallback (not more used)
     * @param bool        $preload  Add preload="auto" attribute if true, else preload="none"
     */
    public static function videoPlayer(string $type, string $url, ?string $player = null, ?array $args = null, bool $fallback = false, bool $preload = true): string
    {
        $video = '';

        if ('video/x-flv' != $type) {
            // Cope with width and height, if given
            $width  = 400;
            $height = 300;
            if (is_array($args)) {
                if (!empty($args['width'])) {
                    $width = (int) $args['width'];
                }
                if (!empty($args['height'])) {
                    $height = (int) $args['height'];
                }
            }

            $video = '<video controls preload="' . ($preload ? 'auto' : 'none') . '"' .
                ($width ? ' width="' . $width . '"' : '') .
                ($height ? ' height="' . $height . '"' : '') . '>' .
                '<source src="' . $url . '">' .
                '</video>';
        }

        return $video;
    }

    /**
     * Get HTML code for MP3 player (HTML5).
     *
     * @param string      $url      The audio URL to play
     * @param null|string $player   The player URL (not used)
     * @param null|array  $args     The player arguments (not used)
     * @param bool        $fallback The fallback (not more used)
     * @param bool        $preload  Add preload="auto" attribute if true, else preload="none"
     */
    public static function mp3player(string $url, ?string $player = null, ?array $args = null, bool $fallback = false, bool $preload = true): string
    {
        return self::audioPlayer('audio/mp3', $url, $player, $args, false, $preload);
    }

    /**
     * SortHandler.
     *
     * This method is called by {@link getDir()} to sort files. Can be overrided
     * in inherited classes.
     *
     * @param MediaItem $a Media item instance
     * @param MediaItem $b Media item instance
     */
    private function sortHandler(MediaItem $a, MediaItem $b): int
    {
        if ($a->parent && !$b->parent || !$a->parent && $b->parent) {
            return ($a->parent) ? -1 : 1;
        }

        return strcasecmp($a->basename, $b->basename);
    }
}
