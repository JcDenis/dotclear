<?php
/**
 * @class Dotclear\Admin\Page\Media
 * @brief Dotclear class for admin media page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin;

use Dotclear\Exception;
use Dotclear\Exception\AdminException;

use Dotclear\Core\Core;

use Dotclear\Admin\Page\Media as PageMedia;
use Dotclear\Admin\Filter\MediaFilter;
use Dotclear\Admin\Catalog\MediaCatalog;

use Dotclear\Database\StaticRecord;
use Dotclear\Html\Html;

class Media extends MediaFilter
{
    protected $page;

    /** @var boolean Page has a valid query */
    protected $media_has_query = false;

    /** @var boolean Media dir is writable */
    protected $media_writable = false;

    /** @var boolean Media dir is archivable */
    protected $media_archivable = null;

    /** @var array Dirs and files fileItem objects */
    protected $media_dir = null;

    /** @var array User media recents */
    protected $media_last = null;

    /** @var array User media favorites */
    protected $media_fav = null;

    /** @var boolean Uses enhance uploader */
    protected $media_uploader = null;

    /**
     * Constructs a new instance.
     *
     * @param Core $core  core instance
     */
    public function __construct(Core $core, PageMedia $page)
    {
        $this->page = $page;

        parent::__construct($core, 'media');

        $this->core->auth->user_prefs->addWorkspace('interface');
        $this->media_uploader = $this->core->auth->user_prefs->interface->enhanceduploader;

        // try to load core media and themes
        try {
            $this->core->mediaInstance();
            $this->core->media->setFileSort($this->sortby . '-' . $this->order);

            if ($this->q != '') {
                $this->media_has_query = $this->core->media->searchMedia($this->q);
            }
            if (!$this->media_has_query) {
                $try_d = $this->d;
                // Reset current dir
                $this->d = null;
                // Change directory (may cause an exception if directory doesn't exist)
                $this->core->media->chdir($try_d);
                // Restore current dir variable
                $this->d = $try_d;
                $this->core->media->getDir();
            } else {
                $this->d = null;
                $this->core->media->chdir('');
            }
            $this->media_writable = $this->core->media->writable();
            $this->media_dir      = &$this->core->media->dir;
/*
            if ($this->core->themes === null) {
                # -- Loading themes, may be useful for some configurable theme --
                $this->core->themeInstance();
                $this->core->themes->loadModules($this->core->blog->themes_path, null);
            }
*/        } catch (Exception $e) {
            $this->core->error->add($e->getMessage());
        }
    }

    /**
     * Check if page has a valid query
     *
     * @return boolean Has query
     */
    public function hasQuery()
    {
        return $this->media_has_query;
    }

    /**
     * Check if media dir is writable
     *
     * @return boolean Is writable
     */
    public function mediaWritable()
    {
        return $this->media_writable;
    }

    /**
     * Check if media dir is archivable
     *
     * @return boolean Is archivable
     */
    public function mediaArchivable()
    {
        if ($this->media_archivable === null) {
            $rs = $this->getDirsRecord();

            $this->media_archivable = $this->core->auth->check('media_admin', $this->core->blog->id)
                && !(count($rs) == 0 || (count($rs) == 1 && $rs->__data[0]->parent));
        }

        return $this->media_archivable;
    }

    /**
     * Return list of fileItem objects of current dir
     *
     * @param string $type  dir, file, all type
     *
     * @return array Dirs and/or files fileItem objects
     */
    public function getDirs($type = '')
    {
        if (!empty($type)) {
            return $this->media_dir[$type] ?? null;
        }

        return $this->media_dir;
    }

    /**
     * Return static record instance of fileItem objects
     *
     * @return staticRecord Dirs and/or files fileItem objects
     */
    public function getDirsRecord()
    {
        $dir = $this->media_dir;
        // Remove hidden directories (unless DC_SHOW_HIDDEN_DIRS is set to true)
        if (!defined('DC_SHOW_HIDDEN_DIRS') || (DC_SHOW_HIDDEN_DIRS == false)) {
            for ($i = count($dir['dirs']) - 1; $i >= 0; $i--) {
                if ($dir['dirs'][$i]->d) {
                    if (strpos($dir['dirs'][$i]->basename, '.') === 0) {
                        unset($dir['dirs'][$i]);
                    }
                }
            }
        }
        $items = array_values(array_merge($dir['dirs'], $dir['files']));

        return staticRecord::newFromArray($items);
    }

    /**
     * Return html code of an element of list or grid items list
     *
     * @param string $file_id  The file id
     *
     * @return string The element
     */
    public function mediaLine($file_id)
    {
        return MediaCatalog::mediaLine($this->core, $this, $this->core->media->getFile($file_id), 1, $this->media_has_query);
    }

    /**
     * Show enhance uploader
     *
     * @return boolean Show enhance uploader
     */
    public function showUploader()
    {
        return $this->media_uploader;
    }

    /**
     * Number of recent/fav dirs to show
     *
     * @return integer Nb of dirs
     */
    public function showLast()
    {
        return abs((integer) $this->core->auth->user_prefs->interface->media_nb_last_dirs);
    }

    /**
     * Return list of last dirs
     *
     * @return array Last dirs
     */
    public function getLast()
    {
        if ($this->media_last === null) {
            $m = $this->core->auth->user_prefs->interface->media_last_dirs;
            if (!is_array($m)) {
                $m = [];
            }
            $this->media_last = $m;
        }

        return $this->media_last;
    }

    /**
     * Update user last dirs
     *
     * @param string    $dir        The directory
     * @param boolean   $remove     Remove
     *
     * @return boolean The change
     */
    public function updateLast($dir, $remove = false)
    {
        if ($this->q) {
            return false;
        }

        $nb_last_dirs = $this->showLast();
        if (!$nb_last_dirs) {
            return false;
        }

        $done      = false;
        $last_dirs = $this->getLast();

        if ($remove) {
            if (in_array($dir, $last_dirs)) {
                unset($last_dirs[array_search($dir, $last_dirs)]);
                $done = true;
            }
        } else {
            if (!in_array($dir, $last_dirs)) {
                // Add new dir at the top of the list
                array_unshift($last_dirs, $dir);
                // Remove oldest dir(s)
                while (count($last_dirs) > $nb_last_dirs) {
                    array_pop($last_dirs);
                }
                $done = true;
            } else {
                // Move current dir at the top of list
                unset($last_dirs[array_search($dir, $last_dirs)]);
                array_unshift($last_dirs, $dir);
                $done = true;
            }
        }

        if ($done) {
            $this->media_last = $last_dirs;
            $this->core->auth->user_prefs->interface->put('media_last_dirs', $last_dirs, 'array');
        }

        return $done;
    }

    /**
     * Return list of fav dirs
     *
     * @return array Fav dirs
     */
    public function getFav()
    {
        if ($this->media_fav === null) {
            $m = $this->core->auth->user_prefs->interface->media_fav_dirs;
            if (!is_array($m)) {
                $m = [];
            }
            $this->media_fav = $m;
        }

        return $this->media_fav;
    }

    /**
     * Update user fav dirs
     *
     * @param string    $dir        The directory
     * @param boolean   $remove     Remove
     *
     * @return boolean The change
     */
    public function updateFav($dir, $remove = false)
    {
        if ($this->q) {
            return false;
        }

        $nb_last_dirs = $this->showLast();
        if (!$nb_last_dirs) {
            return false;
        }

        $done     = false;
        $fav_dirs = $this->getFav();
        if (!in_array($dir, $fav_dirs) && !$remove) {
            array_unshift($fav_dirs, $dir);
            $done = true;
        } elseif (in_array($dir, $fav_dirs) && $remove) {
            unset($fav_dirs[array_search($dir, $fav_dirs)]);
            $done = true;
        }

        if ($done) {
            $this->media_fav = $fav_dirs;
            $this->core->auth->user_prefs->interface->put('media_fav_dirs', $fav_dirs, 'array');
        }

        return $done;
    }

    /**
     * The top of media page or popup
     *
     * @param string $breadcrumb    The breadcrumb
     * @param string $header        The headers
     */
    public function openPage($breadcrumb, $header = '')
    {
        if ($this->popup) {
            $this->page->openPopup(__('Media manager'), $header, $breadcrumb);
        } else {
            $this->page->open(__('Media manager'), $header, $breadcrumb);
        }
    }

    /**
     * The end of media page or popup
     */
    public function closePage()
    {
        if ($this->popup) {
            $this->page->closePopup();
        } else {
            $this->page->helpBlock('core_media');
            $this->page->close();
        }
    }

    /**
     * The breadcrumb of media page or popup
     *
     * @param array $element  The additionnal element
     *
     * @return string The html code of breadcrumb
     */
    public function breadcrumb($element = [])
    {
        $option = $param = [];

        if (empty($element) && isset($this->core->media)) {
            $param = [
                'd' => '',
                'q' => ''
            ];

            if ($this->media_has_query || $this->q) {
                $count = $this->media_has_query ? count($this->getDirs('files')) : 0;

                $element[__('Search:') . ' ' . $this->q . ' (' . sprintf(__('%s file found', '%s files found', $count), $count) . ')'] = '';
            } else {
                $bc_url   = $this->core->adminurl->get('admin.media', array_merge($this->values(true), ['d' => '%s']), '&');
                $bc_media = $this->core->media->breadCrumb($bc_url, '<span class="page-title">%s</span>');
                if ($bc_media != '') {
                    $element[$bc_media] = '';
                    $option['hl']       = true;
                }
            }
        }

        $elements = [
            html::escapeHTML($this->core->blog->name) => '',
            __('Media manager')                       => empty($param) ? '' :
                $this->core->adminurl->get('admin.media', array_merge($this->values(), array_merge($this->values(), $param)))
        ];
        $options = [
            'home_link' => !$this->popup
        ];

        return $this->page->breadcrumb(array_merge($elements, $element), array_merge($options, $option));
    }
}
