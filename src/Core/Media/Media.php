<?php
/**
 * @class Dotclear\Core\Media\Media
 * @brief Dotclear core media class
 *
 * This class handles Dotclear media items.
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Media;

use Closure;
use Dotclear\Core\Media\PostMedia;
use Dotclear\Core\Media\Image\ImageTools;
use Dotclear\Core\Media\Image\ImageMeta;
use Dotclear\Core\Media\Manager\Manager;
use Dotclear\Core\Media\Manager\Item;
use Dotclear\Database\Cursor;
use Dotclear\Database\Record;
use Dotclear\Database\Statement\SelectStatement;
use Dotclear\Database\Statement\DeleteStatement;
use Dotclear\Database\Statement\UpdateStatement;
use Dotclear\Exception\CoreException;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\File\Zip\Unzip;
use Dotclear\Helper\Html\XmlTag;
use Dotclear\Helper\Dt;
use Dotclear\Helper\Text;

class Media extends Manager
{
    /** @var    string  $table  Media table name */
    protected $table = 'media';

    /** @var    string  $file_sort  Sort field */
    protected $file_sort = 'name-asc';

    /** @var    string  $path   Media path */
    protected $path = '';

    /** @var    string  $relpwd     Relative path */
    protected $relpwd = '';

    /** @var    array   $file_handler   File handler callback */
    protected $file_handler = [];

    /** @var    string  $thumb_tp   Thumbnail file pattern */
    public $thumb_tp = '%s/.%s_%s.jpg';

    /** @var    string  $thumb_tp_alpha     Thumbnail file pattern (with alpha layer) */ 
    public $thumb_tp_alpha = '%s/.%s_%s.png';

    /** @var    string  $thumb_tp_webp  Thumbnail file pattern (webp) */
    public $thumb_tp_webp = '%s/.%s_%s.webp';

    /**
     * Tubmnail sizes:
     * - m: medium image
     * - s: small image
     * - t: thumbnail image
     * - sq: square image
     *
     * @var     array   $thumb_sizes    Tubmnail sizes
     */
    public $thumb_sizes = [
        'm'  => [448, 'ratio', 'medium'],
        's'  => [240, 'ratio', 'small'],
        't'  => [100, 'ratio', 'thumbnail'],
        'sq' => [48, 'crop', 'square'],
    ];

    /** @var    string  $icon_img   Icon file URL pattern */
    public $icon_img = '?df=images/media/%s.png';

    /**
     * Constructor
     *
     * @param   string  $type   The media type filter
     *
     * @throws  CoreException
     */
    public function __construct(protected string $type = '')
    {
        if (!dotclear()->blog()) {
            throw new CoreException(__('No blog defined.'));
        }

        $this->table = dotclear()->prefix . 'media';
        $root        = dotclear()->blog()->public_path;

        if (!$root || !is_dir($root)) {
            # Check public directory
            if (dotclear()->user()->isSuperAdmin()) {
                throw new CoreException(__('There is no writable directory /public/ at the location set in about:config "public_path". You must create this directory with sufficient rights (or change this setting).'));
            }

            throw new CoreException(__('There is no writable root directory for the media manager. You should contact your administrator.'));
        }

        $root_url    = rawurldecode(dotclear()->blog()->public_url);

        parent::__construct($root, $root_url);

        $this->chdir('');

        $this->path = (string) dotclear()->blog()->settings()->get('system')->get('public_path');
//!
        $this->addExclusion(dotclear()->config()->get('root_dir'));
        $this->addExclusion(__DIR__ . '/../');

        $this->exclude_pattern = dotclear()->blog()->settings()->get('system')->get('media_exclusion');

        # Event handlers
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

        # Thumbnails sizes
        $this->thumb_sizes['m'][0] = abs(dotclear()->blog()->settings()->get('system')->get('media_img_m_size'));
        $this->thumb_sizes['s'][0] = abs(dotclear()->blog()->settings()->get('system')->get('media_img_s_size'));
        $this->thumb_sizes['t'][0] = abs(dotclear()->blog()->settings()->get('system')->get('media_img_t_size'));

        # Thumbnails sizes names
        $this->thumb_sizes['m'][2]  = __($this->thumb_sizes['m'][2]);
        $this->thumb_sizes['s'][2]  = __($this->thumb_sizes['s'][2]);
        $this->thumb_sizes['t'][2]  = __($this->thumb_sizes['t'][2]);
        $this->thumb_sizes['sq'][2] = __($this->thumb_sizes['sq'][2]);

        # --BEHAVIOR-- coreMediaConstruct
        dotclear()->behavior()->call('coreMediaConstruct', $this);
    }

    /**
     * Changes working directory.
     *
     * @param   string|null     $dir    The directory name
     */
    public function chdir(?string $dir): void
    {
        parent::chdir($dir);
        $this->relpwd = preg_replace('/^' . preg_quote($this->root, '/') . '\/?/', '', $this->pwd);
    }

    /**
     * Adds a new file handler for a given media type and event.
     *
     * Available events are:
     * - create: file creation
     * - update: file update
     * - remove: file deletion
     *
     * @param   string                  $type       The media type
     * @param   string                  $event      The event
     * @param   string|array|Closure    $function   The callback
     */
    public function addFileHandler(string $type, string $event, string|array|Closure $function): void
    {
        if (is_callable($function)) {
            $this->file_handler[$type][$event][] = $function;
        }
    }

    /**
     * Call file handler
     * 
     * @param   string  $type   The media type
     * @param   string  $event  The event
     * @param   mixed   $args   The callback
     */
    protected function callFileHandler(string $type, string $event, mixed ...$args): void
    {
        if (!empty($this->file_handler[$type][$event])) {
            foreach ($this->file_handler[$type][$event] as $f) {
                call_user_func_array($f, $args);
            }
        }
    }

    /**
     * Returns HTML breadCrumb for media manager navigation.
     *
     * @param   string  $href   The URL pattern
     * @param   string  $last   The last item pattern
     *
     * @return  string          HTML code
     */
    public function breadCrumb(string $href, string $last = ''): string
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
     * Build record from mdeia item
     * 
     * @param   Record  $rs     Media record
     * 
     * @return  Item|null       Media Item
     */
    protected function fileRecord(Record $rs): ?Item
    {
        if (!$rs->isEmpty() 
            && !$this->isFileExclude($this->root . '/' . $rs->f('media_file')) 
            && is_file($this->root . '/' . $rs->f('media_file'))
        ) {
            $f = new Item($this->root . '/' . $rs->f('media_file'), $this->root, $this->root_url);

            if ($this->type && $f->type_prefix != $this->type) {
                return null;
            }

            $meta = @simplexml_load_string((string) $rs->f('media_meta'));

            $f->editable    = true;
            $f->media_id    = $rs->f('media_id');
            $f->media_title = $rs->f('media_title');
            $f->media_meta  = $meta instanceof \SimpleXMLElement ? $meta : simplexml_load_string('<meta></meta>');
            $f->media_user  = $rs->f('user_id');
            $f->media_priv  = (bool) $rs->f('media_private');
            $f->media_dt    = (int) strtotime($rs->f('media_dt'));
            $f->media_dtstr = Dt::str('%Y-%m-%d %H:%M', $f->media_dt);

            $f->media_image = false;

            if (!dotclear()->user()->check('media_admin', dotclear()->blog()->id)
                && dotclear()->user()->userID() != $f->media_user
            ) {
                $f->del      = false;
                $f->editable = false;
            }

            $type_prefix = explode('/', $f->type);
            $type_prefix = $type_prefix[0];

            $f->media_icon = match ($type_prefix) {
                'image' => 'image',
                'audio' => 'audio',
                'text'  => 'text',
                'video' => 'video',
                default => 'blank',
            };
            $f->media_image = 'image' == $f->media_icon;
            $f->media_icon = match ($f->type) {
                'application/msword',
                'application/vnd.oasis.opendocument.text',
                'application/vnd.sun.xml.writer',
                'application/pdf',
                'application/postscript',
                    => 'document',
                'application/msexcel',
                'application/vnd.oasis.opendocument.spreadsheet',
                'application/vnd.sun.xml.calc',
                    => 'spreadsheet',
                'application/mspowerpoint',
                'application/vnd.oasis.opendocument.presentation',
                'application/vnd.sun.xml.impress',
                    => 'presentation',
                'application/x-debian-package',
                'application/x-bzip',
                'application/x-gzip',
                'application/x-java-archive',
                'application/rar',
                'application/x-redhat-package-manager',
                'application/x-tar',
                'application/x-gtar',
                'application/zip',
                    => 'package',
                'application/octet-stream',
                    => 'executable',
                'application/x-shockwave-flash',
                    => 'video',
                'application/ogg',
                    => 'audio',
                'text/html',
                    => 'html',
                default 
                    => $f->media_icon,
            };

            $f->media_type = $f->media_icon;
            $f->media_icon = sprintf($this->icon_img, $f->media_icon);

            # Thumbnails
            $f->media_thumb = [];
            $p              = Path::info($f->relname);

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

            # Cleaner URLs
            $thumb_url = preg_replace('#\./#', '/', $thumb_url);
            $thumb_url = preg_replace('#(?<!:)/+#', '/', $thumb_url);

            $thumb_alt     = '';
            $thumb_url_alt = '';

            if ($alpha || $webp) {
                $thumb_alt     = sprintf($this->thumb_tp, $this->root . '/' . $p['dirname'], $p['base'], '%s');
                $thumb_url_alt = sprintf($this->thumb_tp, $this->root_url . $p['dirname'], $p['base'], '%s');
                # Cleaner URLs
                $thumb_url_alt = preg_replace('#\./#', '/', $thumb_url_alt);
                $thumb_url_alt = preg_replace('#(?<!:)/+#', '/', $thumb_url_alt);
            }

            foreach ($this->thumb_sizes as $suffix => $s) {
                if (file_exists(sprintf($thumb, $suffix))) {
                    $f->media_thumb[$suffix] = sprintf($thumb_url, $suffix);
                } elseif (($alpha || $webp) && file_exists(sprintf($thumb_alt, $suffix))) {
                    $f->media_thumb[$suffix] = sprintf($thumb_url_alt, $suffix);
                }
            }

            if ('image' === $f->media_type) {
                if (isset($f->media_thumb['sq'])) {
                    $f->media_icon = $f->media_thumb['sq'];
                } elseif ('svg' === strtolower($p['extension'])) {
                    $f->media_icon = $this->root_url . $p['dirname'] . '/' . $p['base'] . '.' . $p['extension'];
                }
            }

            return $f;
        }

        return null;
    }

    /**
     * Sets the file sort.
     *
     * @param   string  $type   The type
     */
    public function setFileSort(string $type = 'name'): void
    {
        if (in_array($type, ['size-asc', 'size-desc', 'name-asc', 'name-desc', 'date-asc', 'date-desc'])) {
            $this->file_sort = $type;
        }
    }

    /**
     * Sort file handler
     * 
     * @param   Item|null   $a
     * @param   Item|null   $b
     * 
     * @return  int     Comparison result
     */
    protected function sortFileHandler(?Item $a, ?Item $b): int
    {
        if (is_null($a) || is_null($b)) {
            return is_null($a) ? 1 : -1;
        }

        return match ($this->file_sort) {
            'size-asc'  => $a->size == $b->size ? 0 : ($a->size < $b->size ? -1 : 1),
            'size-desc' =>  $a->size == $b->size ? 0 : ($a->size > $b->size ? -1 : 1),
            'date-asc'  => $a->media_dt == $b->media_dt ? 0 : ($a->media_dt < $b->media_dt ? -1 : 1),
            'date-desc' => $a->media_dt == $b->media_dt ? 0 : ($a->media_dt > $b->media_dt ? -1 : 1),
            'name-desc' => strcasecmp($b->basename, $a->basename),
            default     => strcasecmp($a->basename, $b->basename),
        };
    }

    /**
     * Gets current working directory content (using filesystem).
     */
    public function getFSDir(): void
    {
        parent::getDir();
    }

    /**
     * Gets current working directory content.
     *
     * @param   string|null     $type   The media type filter
     */
    public function getDir(?string $type = null): void
    {
        if ($type) {
            $this->type = $type;
        }

        $media_dir = $this->relpwd ?: '.';

        $sql = new SelectStatement('dcMediaGetDir');
        $sql
            ->columns([
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
            ])
            ->from($this->table)
            ->where('media_path = ' . $sql->quote($this->path))
            ->and('media_dir = ' . $sql->quote($media_dir, true));

        if (!dotclear()->user()->check('media_admin', dotclear()->blog()->id)) {
            $list = ['media_private <> 1'];
            if ($user_id = dotclear()->user()->userID()) {
                $list[] = 'user_id = ' . $sql->quote($user_id, true);
            }
            $sql->and($sql->orGroup($list));
        }

        $rs = $sql->select();

        // Get list of private files in dir
        $sql = new SelectStatement('dcMediaGetDir');
        $sql
            ->columns([
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
            ])
            ->from($this->table)
            ->where('media_path = ' . $sql->quote($this->path))
            ->and('media_dir = ' . $sql->quote($media_dir, true))
            ->and('media_private = 1');

        $rsp      = $sql->select();
        $privates = [];
        while ($rsp->fetch()) {
            # File in subdirectory, forget about it!
            if ('.' != dirname($rsp->f('media_file')) && $this->relpwd != dirname($rsp->f('media_file'))) {
                continue;
            }
            if ($f = $this->fileRecord($rsp)) {
                $privates[] = $f->relname;
            }
        }

        parent::getDir();

        $f_res = [];
        $p_dir = $this->dir;

        # If type is set, remove items from p_dir
        if ($this->type) {
            foreach ($p_dir['files'] as $k => $f) {
                if ($f->type_prefix != $this->type) {
                    unset($p_dir['files'][$k]);
                }
            }
        }

        $f_reg = [];

        while ($rs->fetch()) {
            # File in subdirectory, forget about it!
            if ('.' != dirname($rs->f('media_file')) && $this->relpwd != dirname($rs->f('media_file'))) {
                continue;
            }

            if ($this->inFiles($rs->f('media_file'))) {
                $f = $this->fileRecord($rs);
                if (null !== $f) {
                    if (isset($f_reg[$rs->f('media_file')])) {
                        # That media is duplicated in the database,
                        # time to do a bit of house cleaning.
                        $sql = new DeleteStatement('dcMediaGetDir');
                        $sql
                            ->from($this->table)
                            ->where('media_id = ' . $this->fileRecord($rs)->media_id);

                        $sql->delete();
                    } else {
                        $f_res[]                = $this->fileRecord($rs);
                        $f_reg[$rs->f('media_file')] = 1;
                    }
                }
            } elseif (!empty($p_dir['files']) && '' == $this->relpwd) {
                # Physical file does not exist remove it from DB
                # Because we don't want to erase everything on
                # dotclear upgrade, do it only if there are files
                # in directory and directory is root
                $sql = new DeleteStatement('dcMediaGetDir');
                $sql
                    ->from($this->table)
                    ->where('media_path = ' . $sql->quote($this->path, true))
                    ->and('media_file = ' . $sql->quote($rs->f('media_file'), true));

                $sql->delete();
                $this->callFileHandler(Files::getMimeType($rs->f('media_file')), 'remove', $this->pwd . '/' . $rs->f('media_file'));
            }
        }

        $this->dir['files'] = $f_res;
        foreach ($this->dir['dirs'] as $k => $v) {
            $v->media_icon = sprintf($this->icon_img, ($v->parent ? 'folder-up' : 'folder'));
        }

        # Check files that don't exist in database and create them
        if (dotclear()->user()->check('media,media_admin', dotclear()->blog()->id)) {
            foreach ($p_dir['files'] as $f) {
                // Warning a file may exist in DB but in private mode for the user, so we don't have to recreate it
                if (!isset($f_reg[$f->relname]) && !in_array($f->relname, $privates)) {
                    if (false !== ($id = $this->createFile($f->basename, null, false, null, false))) {
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
     * Gets file by its id.
     *
     * @param   int     $id     The file identifier
     *
     * @return  Item|null       The file.
     */
    public function getFile(int $id): ?Item
    {
        $sql = new SelectStatement('dcMediaGetFile');
        $sql
            ->from($this->table)
            ->columns([
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
            ])
            ->where('media_path = ' . $sql->quote($this->path))
            ->and('media_id = ' . (int) $id);

        if (!dotclear()->user()->check('media_admin', dotclear()->blog()->id)) {
            $list = ['media_private <> 1'];
            if ($user_id = dotclear()->user()->userID()) {
                $list[] = 'user_id = ' . $sql->quote($user_id, true);
            }
            $sql->and($sql->orGroup($list));
        }

        $rs = $sql->select();

        return $this->fileRecord($rs);
    }

    /**
     * Search into media db (only).
     *
     * @param   string  $query  The search query
     *
     * @return  bool            True or false if nothing found
     */
    public function searchMedia(string $query): bool
    {
        if ($query == '') {
            return false;
        }

        $sql = new SelectStatement('dcMediaGetFile');
        $sql
            ->from($this->table)
            ->columns([
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
            ])
            ->where('media_path = ' . $sql->quote($this->path))
            ->and($sql->orGroup([
                $sql->like('media_title', '%' . $sql->escape($query) . '%'),
                $sql->like('media_file', '%' . $sql->escape($query) . '%'),
                $sql->like('media_meta', '%<Description>%' . $sql->escape($query) . '%</Description>%'),
            ]));

        if (!dotclear()->user()->check('media_admin', dotclear()->blog()->id)) {
            $list = ['media_private <> 1'];
            if ($user_id = dotclear()->user()->userID()) {
                $list[] = 'user_id = ' . $sql->quote($user_id, true);
            }
            $sql->and($sql->orGroup($list));
        }

        $rs = $sql->select();

        $this->dir = ['dirs' => [], 'files' => []];
        $f_res     = [];
        while ($rs->fetch()) {
            $fr = $this->fileRecord($rs);
            if ($fr) {
                $f_res[] = $fr;
            }
        }
        $this->dir['files'] = $f_res;

        try {
            usort($this->dir['files'], [$this, 'sortFileHandler']);
        } catch (\Exception) {
        }

        return (count($f_res) > 0 ? true : false);
    }

    /**
     * Returns media items attached to a blog post. 
     * 
     * Result is an array containing Item objects.
     *
     * @param   int             $post_id    The post identifier
     * @param   int|null        $media_id   The media identifier
     * @param   string|null     $link_type  The link type
     *
     * @return  array                       Array of Item.
     */
    public function getPostMedia(int $post_id, ?int $media_id = null, ?string $link_type = null): array
    {
        $params = [
            'post_id'    => $post_id,
            'media_path' => $this->path,
        ];
        if ($media_id) {
            $params['media_id'] = (int) $media_id;
        }
        if ($link_type) {
            $params['link_type'] = $link_type;
        }
        $postmedia = new PostMedia();
        $rs = $postmedia->getPostMedia($params);

        $res = [];

        while ($rs->fetch()) {
            $f = $this->fileRecord($rs);
            if (null !== $f) {
                $res[] = $f;
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
     * @param   string  $pwd    The directory to rebuild
     *
     * @throws  CoreException
     */
    public function rebuild(string $pwd = ''): void
    {
        if (!dotclear()->user()->isSuperAdmin()) {
            throw new CoreException(__('You are not a super administrator.'));
        }

        $this->chdir($pwd);
        parent::getDir();

        $dir = $this->dir;

        foreach ($dir['dirs'] as $d) {
            if (!$d->parent) {
                $this->rebuild($d->relname);
            }
        }

        foreach ($dir['files'] as $f) {
            $this->chdir(dirname($f->relname));
            $this->createFile($f->basename);
        }

        $this->rebuildDB($pwd);
    }

    /**
     * Rebuild database
     *
     * @param   string  $pwd    The directory to rebuild
     */
    protected function rebuildDB(string $pwd): void
    {
        $media_dir = $pwd ?: '.';

        $sql = new SelectStatement('dcMediaRebuildDB');
        $sql
            ->from($this->table)
            ->columns([
                'media_file',
                'media_id',
            ])
            ->where('media_path = ' . $sql->quote($this->path))
            ->and('media_dir = ' . $sql->quote($media_dir, true));

        $rs = $sql->select();

        $del_ids = [];
        while ($rs->fetch()) {
            if (!is_file($this->root . '/' . $rs->f('media_file'))) {
                $del_ids[] = $rs->fInt('media_id');
            }
        }
        if (!empty($del_ids)) {
            $sql = new DeleteStatement('dcMediaRebuildDB');
            $sql
                ->from(dotclear())
                ->where('media_id' . $sql->in($del_ids));

            $sql->delete();
        }
    }

    /**
     * Makes a dir.
     *
     * @param   string  $d  the directory to create
     */
    public function makeDir(string $d): void
    {
        $d = Files::tidyFileName($d);
        parent::makeDir($d);
    }

    /**
     * Creates or updates a file in database. Returns new media ID or false if
     * file does not exist.
     *
     * @param   string          $name       The file name (relative to working directory)
     * @param   string|null     $title      The file title
     * @param   bool            $private    File is private
     * @param   string|null     $dt         File date
     * @param   bool            $force      The force flag
     *
     * @throws  CoreException
     *
     * @return  int|false                   New media ID or false
     */
    public function createFile(string $name, ?string $title = null, bool $private = false, ?string $dt = null, bool $force = true): int|false
    {
        if (!dotclear()->user()->check('media,media_admin', dotclear()->blog()->id)) {
            throw new CoreException(__('Permission denied.'));
        }

        $file = $this->pwd . '/' . $name;
        if (!file_exists($file)) {
            return false;
        }

        $media_file = $this->relpwd ? Path::clean($this->relpwd . '/' . $name) : Path::clean($name);
        $media_type = Files::getMimeType($name);

        $cur = dotclear()->con()->openCursor($this->table);

        $sql = new SelectStatement('dcMediaCreateFile');
        $sql
            ->from($this->table)
            ->column('media_id')
            ->where('media_path = ' . $sql->quote($this->path, true))
            ->and('media_file = ' . $sql->quote($media_file, true));

        $rs = $sql->select();

        if ($rs->isEmpty()) {
            dotclear()->con()->writeLock($this->table);

            try {
                $sql = new SelectStatement('dcMediaCreateFile');
                $sql
                    ->from($this->table)
                    ->column($sql->max('media_id'));

                $media_id = $sql->select()->fInt() + 1;

                $cur->setField('media_id', $media_id);
                $cur->setField('user_id', (string) dotclear()->user()->userID());
                $cur->setField('media_path', (string) $this->path);
                $cur->setField('media_file', (string) $media_file);
                $cur->setField('media_dir', (string) dirname($media_file));
                $cur->setField('media_creadt', date('Y-m-d H:i:s'));
                $cur->setField('media_upddt', date('Y-m-d H:i:s'));
                $cur->setField('media_title', !$title ? (string) $name : (string) $title);
                $cur->setField('media_private', (int) (bool) $private);
                $cur->setField('media_dt', (string) ($dt ? $dt : @strftime('%Y-%m-%d %H:%M:%S', filemtime($file))));

                try {
                    $cur->insert();
                } catch (\Exception $e) {
                    @unlink($name);

                    throw $e;
                }
                dotclear()->con()->unlock();
            } catch (\Exception $e) {
                dotclear()->con()->unlock();

                throw $e;
            }
        } else {
            $media_id = $rs->fInt('media_id');

            $cur->setField('media_upddt', date('Y-m-d H:i:s'));

            $sql = new UpdateStatement('dcMediaCreateFile');
            $sql->where('media_id = ' . $media_id);

            $sql->update($cur);
        }

        $this->callFileHandler($media_type, 'create', $cur, $name, $media_id, $force);

        return $media_id;
    }

    /**
     * Updates a file in database.
     *
     * @param   Item    $file       The file
     * @param   Item    $newFile    The new file
     *
     * @throws  CoreException
     */
    public function updateFile(Item $file, Item $newFile): void
    {
        if (!dotclear()->user()->check('media,media_admin', dotclear()->blog()->id)) {
            throw new CoreException(__('Permission denied.'));
        }

        $id = (int) $file->media_id;

        if (!$id) {
            throw new CoreException('No file ID');
        }

        if (!dotclear()->user()->check('media_admin', dotclear()->blog()->id)
            && dotclear()->user()->userID() != $file->media_user) {
            throw new CoreException(__('You are not the file owner.'));
        }

        $cur = dotclear()->con()->openCursor($this->table);

        # We need to tidy newFile basename. If dir isn't empty, concat to basename
        $newFile->relname = Files::tidyFileName($newFile->basename);
        if ($newFile->dir) {
            $newFile->relname = $newFile->dir . '/' . $newFile->relname;
        }

        if ($file->relname != $newFile->relname) {
            $newFile->file = $this->root . '/' . $newFile->relname;

            if ($this->isFileExclude($newFile->relname)) {
                throw new CoreException(__('This file is not allowed.'));
            }

            if (file_exists($newFile->file)) {
                throw new CoreException(__('New file already exists.'));
            }

            $this->moveFile($file->relname, $newFile->relname);

            $cur->setField('media_file', (string) $newFile->relname);
            $cur->setField('media_dir', (string) dirname($newFile->relname));
        }

        $cur->setField('media_title', (string) $newFile->media_title);
        $cur->setField('media_dt', (string) $newFile->media_dtstr);
        $cur->setField('media_upddt', date('Y-m-d H:i:s'));
        $cur->setField('media_private', (int) $newFile->media_priv);

        if ($newFile->media_meta instanceof \SimpleXMLElement) {
            $cur->setField('media_meta', $newFile->media_meta->asXML());
        }

        $sql = new UpdateStatement('dcMediaCreateFile');
        $sql->where('media_id = ' . $id);

        $sql->update($cur);

        $this->callFileHandler($file->type, 'update', $file, $newFile);
    }

    /**
     * Uploads a file.
     *
     * @param   string          $tmp        The full path of temporary uploaded file
     * @param   string          $name       The file name (relative to working directory)me
     * @param   string|null     $title      The file title
     * @param   bool            $private    File is private
     * @param   bool            $overwrite  File should be overwrite
     *
     * @throws  CoreException
     *
     * @return  int|false                   New media ID or false
     */
    public function uploadMediaFile(string $tmp, string $name, ?string $title = null, bool $private = false, bool $overwrite = false): int|false
    {
        if (!dotclear()->user()->check('media,media_admin', dotclear()->blog()->id)) {
            throw new CoreException(__('Permission denied.'));
        }

        $name = Files::tidyFileName($name);

        parent::uploadFile($tmp, $name, $overwrite);

        return $this->createFile($name, $title, $private);
    }

    /**
     * Creates a file from binary content.
     *
     * @param   string  $name   The file name (relative to working directory)
     * @param   string  $bits   The binary file contentits
     *
     * @throws  CoreException
     *
     * @return  int|false       New media ID or false
     */
    public function uploadMediaBits($name, $bits): int|false
    {
        if (!dotclear()->user()->check('media,media_admin', dotclear()->blog()->id)) {
            throw new CoreException(__('Permission denied.'));
        }

        $name = Files::tidyFileName($name);

        parent::uploadBits($name, $bits);

        return $this->createFile($name, null, false);
    }

    /**
     * Removes a file.
     *
     * @param   string  $f  The filename
     *
     * @throws  CoreException
     */
    public function removeFile(string $f): void
    {
        if (!dotclear()->user()->check('media,media_admin', dotclear()->blog()->id)) {
            throw new CoreException(__('Permission denied.'));
        }

        $media_file = $this->relpwd ? Path::clean($this->relpwd . '/' . $f) : Path::clean($f);

        $sql = new DeleteStatement('dcMediaRemoveFile');
        $sql
            ->from($this->table)
            ->where('media_path = ' . $sql->quote($this->path, true))
            ->and('media_file = ' . $sql->quote($media_file));

        if (!dotclear()->user()->check('media_admin', dotclear()->blog()->id)) {
            $sql->and('user_id = ' . $sql->quote(dotclear()->user()->userID(), true));
        }

        $sql->delete();

        if (dotclear()->con()->changes() == 0) {
            throw new CoreException(__('File does not exist in the database.'));
        }

        parent::removeFile($f);

        $this->callFileHandler(Files::getMimeType($media_file), 'remove', $f);
    }

    /**
     * Root directories
     *
     * Returns an array of directory under {@link $root} directory.
     *
     * @uses    Item
     *
     * @return  array
     */
    public function getDBDirs(): array
    {
        $dir       = [];
        $media_dir = $this->relpwd ?: '.';

        $sql = new SelectStatement('dcMediaGetDBDirs');
        $sql
            ->from($this->table)
            ->column('distinct media_dir')
            ->where('media_path = ' . $sql->quote($this->path));

        $rs = $sql->select();
        while ($rs->fetch()) {
            if (is_dir($this->root . '/' . $rs->f('media_dir'))) {
                $dir[] = ('.' == $rs->f('media_dir') ? '' : $rs->f('media_dir'));
            }
        }

        return $dir;
    }

    /**
     * Extract zip file in current location.
     *
     * @param   Item    $f              Item object
     * @param   bool    $create_dir     Create dir
     *
     * @throws  CoreException
     *
     * @return  string                  Destination
     */
    public function inflateZipFile(Item $f, bool $create_dir = true): string
    {
        $zip = new Unzip($f->file);
        $zip->setExcludePattern($this->exclude_pattern);
        $list = $zip->getList(false, '#(^|/)(__MACOSX|\.svn|\.hg.*|\.git.*|\.DS_Store|\.directory|Thumbs\.db)(/|$)#');

        if ($create_dir) {
            $zip_root_dir = $zip->getRootDir();
            if ($zip_root_dir != false) {
                $destination = $zip_root_dir;
                $target      = $f->dir;
            } else {
                $destination = preg_replace('/\.([^.]+)$/', '', $f->basename);
                $target      = $f->dir . '/' . $destination;
            }

            if (is_dir($f->dir . '/' . $destination)) {
                throw new CoreException(sprintf(__('Extract destination directory %s already exists.'), dirname($f->relname) . '/' . $destination));
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
     * Gets the zip content.
     *
     * @param   Item    $f  Item object
     *
     * @return  array       The zip content.
     */
    public function getZipContent(Item $f): array
    {
        $zip  = new Unzip($f->file);
        $list = $zip->getList(false, '#(^|/)(__MACOSX|\.svn|\.hg.*|\.git.*|\.DS_Store|\.directory|Thumbs\.db)(/|$)#');
        $zip->close();

        return $list;
    }

    /**
     * Calls file handlers registered for recreate event.
     *
     * @param   Item    $f  Item object
     */
    public function mediaFireRecreateEvent(Item $f)
    {
        $media_type = Files::getMimeType($f->basename);
        $this->callFileHandler($media_type, 'recreate', null, $f->basename); // Args list to be completed as necessary (Franck)
    }

    /* Image handlers
    ------------------------------------------------------- */
    /**
     * Create image thumbnails
     *
     * @param   Cursor|null     $cur    The cursor
     * @param   string          $f      Image filename
     * @param   bool            $force  Force creation
     *
     * @return  bool
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

            foreach ($this->thumb_sizes as $suffix => $s) {
                $thumb_file = sprintf($thumb, $suffix);
                if (!file_exists($thumb_file) && $s[0] > 0 && ('sq' == $suffix || $w > $s[0] || $h > $s[0])) {
                    $rate = ($s[0] < 100 ? 95 : ($s[0] < 600 ? 90 : 85));
                    $img->resize($s[0], $s[0], $s[1]);
                    $img->output(($alpha || $webp ? strtolower($p['extension']) : 'jpeg'), $thumb_file, $rate);
                    $img->loadImage($file);
                }
            }
            $img->close();
        } catch (\Exception $e) {
            if ($cur === null) {
                # Called only if cursor is null (public call)
                throw $e;
            }
        }

        return true;
    }

    /**
     * Update image thumbnails
     *
     * @param   Item    $file       The file
     * @param   Item    $newFile    The new file
     */
    protected function imageThumbUpdate(Item $file, Item $newFile): void
    {
        if ($file->relname != $newFile->relname) {
            $p         = Path::info($file->relname);
            $alpha     = 'png' === strtolower($p['extension']);
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
            $alpha     = 'png' === strtolower($p['extension']);
            $webp      = 'webp' === strtolower($p['extension']);
            $thumb_new = sprintf(
                ($alpha ? $this->thumb_tp_alpha :
                ($webp ? $this->thumb_tp_webp :
                    $this->thumb_tp)),
                $p['dirname'],
                $p['base'],
                '%s'
            );

            foreach ($this->thumb_sizes as $suffix => $s) {
                try {
                    parent::moveFile(sprintf($thumb_old, $suffix), sprintf($thumb_new, $suffix));
                } catch (\Exception) {
                }
            }
        }
    }

    /**
     * Remove image thumbnails
     *
     * @param   string  $f  Image filename
     */
    public function imageThumbRemove(string $f): void
    {
        $p     = Path::info($f);
        $alpha = 'png' === strtolower($p['extension']);
        $webp  = 'webp' === strtolower($p['extension']);
        $thumb = sprintf(
            ($alpha ? $this->thumb_tp_alpha :
            ($webp ? $this->thumb_tp_webp :
                $this->thumb_tp)),
            '',
            $p['base'],
            '%s'
        );

        foreach ($this->thumb_sizes as $suffix => $s) {
            try {
                parent::removeFile(sprintf($thumb, $suffix));
            } catch (\Exception) {
            }
        }
    }

    /**
     * Create image meta
     *
     * @param   Cursor  $cur    The cursor
     * @param   string  $f      Image filename
     * @param   int     $id     The media identifier
     *
     * @return  bool
     */
    protected function imageMetaCreate(Cursor $cur, string $f, int $id): bool
    {
        $file = $this->pwd . '/' . $f;

        if (!file_exists($file)) {
            return false;
        }

        $xml  = new XmlTag('meta');
        $meta = ImageMeta::readMeta($file);
        $xml->insertNode($meta);

        $c = dotclear()->con()->openCursor($this->table);
        $c->setField('media_meta', $xml->toXML());

        if (null !== $cur->getField('media_title') && basename($cur->getField('media_file')) == $cur->getField('media_title')) {
            if ($meta['Title']) {
                $c->setField('media_title', $meta['Title']);
            }
        }

        if ($meta['DateTimeOriginal'] && '' === $cur->getField('media_dt')) {
            # We set picture time to user timezone
            $media_ts = strtotime($meta['DateTimeOriginal']);
            if (false !== $media_ts) {
                $o           = Dt::getTimeOffset(dotclear()->user()->getInfo('user_tz'), $media_ts);
                $c->setField('media_dt', Dt::str('%Y-%m-%d %H:%M:%S', $media_ts + $o));
            }
        }

        # --BEHAVIOR-- coreBeforeImageMetaCreate
        dotclear()->behavior()->call('coreBeforeImageMetaCreate', $c);

        $sql = new UpdateStatement('dcMediaImageMetaCreate');
        $sql->where('media_id = ' . $id);

        $sql->update($c);

        return true;
    }

    /**
     * Returns HTML code for audio player (HTML5)
     *
     * @param   string          $type       The audio mime type (not used)
     * @param   string          $url        The audio URL to play
     * @param   string|null     $player     The player URL (not used)
     * @param   array|null      $args       The player arguments (not used)
     * @param   bool            $fallback   The fallback (not more used)
     * @param   bool            $preload    Add preload="auto" attribute if true, else preload="none"
     *
     * @return  string
     */
    public static function audioPlayer(string $type, string $url, ?string $player = null, ?array $args = null, bool $fallback = false, bool $preload = true): string
    {
        return
            '<audio controls preload="' . ($preload ? 'auto' : 'none') . '">' .
            '<source src="' . $url . '">' .
            '</audio>';
    }

    /**
     * Returns HTML code for video player (HTML5)
     *
     * @param   string          $type       The audio mime type (not used)
     * @param   string          $url        The audio URL to play
     * @param   string|null     $player     The player URL (not used)
     * @param   array|null      $args       The player arguments (not used)
     * @param   bool            $fallback   The fallback (not more used)
     * @param   bool            $preload    Add preload="auto" attribute if true, else preload="none"
     *
     * @return  string
     */
    public static function videoPlayer(string $type, string $url, ?string $player = null, ?array $args = null, bool $fallback = false, bool $preload = true): string
    {
        $video = '';

        if ($type != 'video/x-flv') {
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
     * Returns HTML code for MP3 player (HTML5)
     *
     * @param   string          $url        The audio URL to play
     * @param   string|null     $player     The player URL (not used)
     * @param   array|null      $args       The player arguments (not used)
     * @param   bool            $fallback   The fallback (not more used)
     * @param   bool            $preload    Add preload="auto" attribute if true, else preload="none"
     *
     * @return     string
     */
    public static function mp3player(string $url, ?string $player = null, ?array $args = null, bool $fallback = false, bool $preload = true): string
    {
        return self::audioPlayer('audio/mp3', $url, $player, $args, false, $preload);
    }
}
