<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Admin\Lib\Module\Flat;

// Dotclear\Plugin\ImportExport\Admin\Lib\Module\Flat\FlatImport
use Dotclear\App;
use Dotclear\Database\Cursor;
use Dotclear\Database\Record;
use Dotclear\Exception\ModuleException;
use Dotclear\Helper\Html\Html;
use Exception;

/**
 * Flat import for plugin ImportExport.
 *
 * @ingroup  Plugin ImportExport
 */
class FlatImport extends FlatBackup
{
    /**
     * @var string $dc_version
     *             dotclear version
     */
    private $dc_version;

    /**
     * @var string $dc_major_version
     *             dotclear major version
     */
    private $dc_major_version;

    /**
     * @var string $mode
     *             Import mode (full or single)
     */
    private $mode;

    /**
     * @var null|string $blog_id
     *                  The blog id
     */
    private $blog_id;

    /**
     * @var Cursor $cur_blog
     *             blog cursor
     */
    private $cur_blog;

    /**
     * @var Cursor $cur_category
     *             category cursor
     */
    private $cur_category;

    /**
     * @var Cursor $cur_link
     *             link cursor
     */
    private $cur_link;

    /**
     * @var Cursor $cur_setting
     *             setting cursor
     */
    private $cur_setting;

    /**
     * @var Cursor $cur_user
     *             user cursor
     */
    private $cur_user;

    /**
     * @var Cursor $cur_pref
     *             pref cursor
     */
    private $cur_pref;

    /**
     * @var Cursor $cur_permissions
     *             permissions cursor
     */
    private $cur_permissions;

    /**
     * @var Cursor $cur_post
     *             post cursor
     */
    private $cur_post;

    /**
     * @var Cursor $cur_meta
     *             meta cursor
     */
    private $cur_meta;

    /**
     * @var Cursor $cur_media
     *             media cursor
     */
    private $cur_media;

    /**
     * @var Cursor $cur_post_media
     *             post media cursor
     */
    private $cur_post_media;

    /**
     * @var Cursor $cur_log
     *             log cursor
     */
    private $cur_log;

    /**
     * @var Cursor $cur_ping
     *             ping cursor
     */
    private $cur_ping;

    /**
     * @var Cursor $cur_comment
     *             comment cursor
     */
    private $cur_comment;

    /**
     * @var Cursor $cur_spamrule
     *             spamrule cursor
     */
    private $cur_spamrule;

    /**
     * @var array<string,array> $old_ids
     *                          List of source ids
     */
    public $old_ids = [
        'category' => [],
        'post'     => [],
        'media'    => [],
    ];

    /**
     * @var array<string,int> $stack
     *                        The stack of ids
     */
    public $stack = [
        'cat_id'     => 1,
        'post_id'    => 1,
        'media_id'   => 1,
        'comment_id' => 1,
        'link_id'    => 1,
        'log_id'     => 1,
    ];

    /**
     * @var array<string,int> $lft_categories
     *                        The lft categories
     */
    public $lft_categories = [];

    /**
     * @var Record $categories
     *             The record of categories
     */
    public $categories;

    /**
     * @var array<string,bool> $users
     *                         The stack of users
     */
    public $users = [];

    /**
     * @var bool $has_categories
     *           True if it has categories
     */
    public $has_categories = false;

    public function __construct(string $file)
    {
        parent::__construct($file);

        $first_line = fgets($this->fp);
        if (!str_starts_with($first_line, '///DOTCLEAR|')) {
            throw new ModuleException(__('File is not a DotClear backup.'));
        }

        @set_time_limit(300);

        $l = explode('|', $first_line);

        if (isset($l[1])) {
            $this->dc_version = $l[1];
        }

        $this->mode = isset($l[2]) ? strtolower(trim((string) $l[2])) : 'single';
        if ('full' != $this->mode && 'single' != $this->mode) {
            $this->mode = 'single';
        }

        if (version_compare('1.2', $this->dc_version, '<=') && version_compare('1.3', $this->dc_version, '>')) {
            $this->dc_major_version = '1.2';
        } else {
            $this->dc_major_version = '2.0';
        }

        $this->cur_blog        = App::core()->con()->openCursor(App::core()->prefix . 'blog');
        $this->cur_category    = App::core()->con()->openCursor(App::core()->prefix . 'category');
        $this->cur_link        = App::core()->con()->openCursor(App::core()->prefix . 'link');
        $this->cur_setting     = App::core()->con()->openCursor(App::core()->prefix . 'setting');
        $this->cur_user        = App::core()->con()->openCursor(App::core()->prefix . 'user');
        $this->cur_pref        = App::core()->con()->openCursor(App::core()->prefix . 'pref');
        $this->cur_permissions = App::core()->con()->openCursor(App::core()->prefix . 'permissions');
        $this->cur_post        = App::core()->con()->openCursor(App::core()->prefix . 'post');
        $this->cur_meta        = App::core()->con()->openCursor(App::core()->prefix . 'meta');
        $this->cur_media       = App::core()->con()->openCursor(App::core()->prefix . 'media');
        $this->cur_post_media  = App::core()->con()->openCursor(App::core()->prefix . 'post_media');
        $this->cur_log         = App::core()->con()->openCursor(App::core()->prefix . 'log');
        $this->cur_ping        = App::core()->con()->openCursor(App::core()->prefix . 'ping');
        $this->cur_comment     = App::core()->con()->openCursor(App::core()->prefix . 'comment');
        $this->cur_spamrule    = App::core()->con()->openCursor(App::core()->prefix . 'spamrule');
        // $this->cur_version     = App::core()->con()->openCursor(App::core()->prefix . 'version');

        // --BEHAVIOR-- importInit
        App::core()->behavior()->call('importInit', $this);
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function importSingle(): void
    {
        if ('single' != $this->mode) {
            throw new ModuleException(__('File is not a single blog export.'));
        }

        if (!App::core()->user()->check('admin', App::core()->blog()->id)) {
            throw new ModuleException(__('Permission denied.'));
        }

        $this->blog_id = App::core()->blog()->id;

        $this->categories = App::core()->con()->select(
            'SELECT cat_id, cat_title, cat_url ' .
            'FROM ' . App::core()->prefix . 'category ' .
            "WHERE blog_id = '" . App::core()->con()->escape($this->blog_id) . "' "
        );

        $this->stack['cat_id']     = App::core()->con()->select('SELECT MAX(cat_id) FROM ' . App::core()->prefix . 'category')->fInt()    + 1;
        $this->stack['link_id']    = App::core()->con()->select('SELECT MAX(link_id) FROM ' . App::core()->prefix . 'link')->fInt()       + 1;
        $this->stack['post_id']    = App::core()->con()->select('SELECT MAX(post_id) FROM ' . App::core()->prefix . 'post')->fInt()       + 1;
        $this->stack['media_id']   = App::core()->con()->select('SELECT MAX(media_id) FROM ' . App::core()->prefix . 'media')->fInt()     + 1;
        $this->stack['comment_id'] = App::core()->con()->select('SELECT MAX(comment_id) FROM ' . App::core()->prefix . 'comment')->fInt() + 1;
        $this->stack['log_id']     = App::core()->con()->select('SELECT MAX(log_id) FROM ' . App::core()->prefix . 'log')->fInt()         + 1;

        $rs = App::core()->con()->select(
            'SELECT MAX(cat_rgt) AS cat_rgt FROM ' . App::core()->prefix . 'category ' .
            "WHERE blog_id = '" . App::core()->con()->escape(App::core()->blog()->id) . "'"
        );

        if (0 < (int) $rs->fInt('cat_rgt')) {
            $this->has_categories                            = true;
            $this->lft_categories[App::core()->blog()->id]   = (int) $rs->fInt('cat_rgt') + 1;
        }

        App::core()->con()->begin();

        $line = false;

        try {
            $last_line_name = '';
            $constrained    = ['post', 'meta', 'post_media', 'ping', 'comment'];

            while (false !== ($line = $this->getLine())) {
                // import DC 1.2.x, we fix lines before insert
                if ('1.2' == $this->dc_major_version) {
                    $this->prepareDC12line($line);
                }

                if ($last_line_name != $line->__name) {
                    if (in_array($last_line_name, $constrained)) {
                        // UNDEFER
                        if ('mysql' == App::core()->con()->syntax()) {
                            App::core()->con()->execute('SET foreign_key_checks = 1');
                        }

                        if ('postgresql' == App::core()->con()->syntax()) {
                            App::core()->con()->execute('SET CONSTRAINTS ALL DEFERRED');
                        }
                    }

                    if (in_array($line->__name, $constrained)) {
                        // DEFER
                        if ('mysql' == App::core()->con()->syntax()) {
                            App::core()->con()->execute('SET foreign_key_checks = 0');
                        }

                        if ('postgresql' == App::core()->con()->syntax()) {
                            App::core()->con()->execute('SET CONSTRAINTS ALL IMMEDIATE');
                        }
                    }

                    $last_line_name = $line->__name;
                }

                match ($line->__name) {
                    'category'   => $this->insertCategorySingle($line),
                    'link'       => $this->insertLinkSingle($line),
                    'post'       => $this->insertPostSingle($line),
                    'meta'       => $this->insertMetaSingle($line),
                    'media'      => $this->insertMediaSingle($line),
                    'post_media' => $this->insertPostMediaSingle($line),
                    'ping'       => $this->insertPingSingle($line),
                    'comment'    => $this->insertCommentSingle($line),
                    default      => '',
                };

                // --BEHAVIOR-- importSingle, FlatBackupItem, FlatImport
                App::core()->behavior()->call('importSingle', $line, $this);
            }

            if ('mysql' == App::core()->con()->syntax()) {
                App::core()->con()->execute('SET foreign_key_checks = 1');
            }

            if ('postgresql' == App::core()->con()->syntax()) {
                App::core()->con()->execute('SET CONSTRAINTS ALL DEFERRED');
            }
        } catch (Exception $e) {
            @fclose($this->fp);
            App::core()->con()->rollback();

            throw new ModuleException($e->getMessage() . ' - ' . sprintf(__('Error raised at line %s'), $line->__line));
        }
        @fclose($this->fp);
        App::core()->con()->commit();
    }

    public function importFull(): void
    {
        if ('full' != $this->mode) {
            throw new ModuleException(__('File is not a full export.'));
        }

        if (!App::core()->user()->isSuperAdmin()) {
            throw new ModuleException(__('Permission denied.'));
        }

        App::core()->con()->begin();
        App::core()->con()->execute('DELETE FROM ' . App::core()->prefix . 'blog');
        App::core()->con()->execute('DELETE FROM ' . App::core()->prefix . 'media');
        App::core()->con()->execute('DELETE FROM ' . App::core()->prefix . 'spamrule');
        App::core()->con()->execute('DELETE FROM ' . App::core()->prefix . 'setting');
        App::core()->con()->execute('DELETE FROM ' . App::core()->prefix . 'log');

        $line = false;

        try {
            while (false !== ($line = $this->getLine())) {
                match ($line->__name) {
                    'blog'        => $this->insertBlog($line),
                    'category'    => $this->insertCategory($line),
                    'link'        => $this->insertLink($line),
                    'setting'     => $this->insertSetting($line),
                    'user'        => $this->insertUser($line),
                    'pref'        => $this->insertPref($line),
                    'permissions' => $this->insertPermissions($line),
                    'post'        => $this->insertPost($line),
                    'meta'        => $this->insertMeta($line),
                    'media'       => $this->insertMedia($line),
                    'post_media'  => $this->insertPostMedia($line),
                    'log'         => $this->insertLog($line),
                    'ping'        => $this->insertPing($line),
                    'comment'     => $this->insertComment($line),
                    'spamrule'    => $this->insertSpamRule($line),
                    default       => '',
                };
                // --BEHAVIOR-- importFull
                App::core()->behavior()->call('importFull', $line, $this);
            }
        } catch (Exception $e) {
            @fclose($this->fp);
            App::core()->con()->rollback();

            throw new ModuleException($e->getMessage() . ' - ' . sprintf(__('Error raised at line %s'), $line->__line));
        }
        @fclose($this->fp);
        App::core()->con()->commit();
    }

    private function insertBlog(FlatBackupItem $blog): void
    {
        $this->cur_blog->clean();

        $this->cur_blog->setField('blog_id', (string) $blog->f('blog_id'));
        $this->cur_blog->setField('blog_uid', (string) $blog->f('blog_uid'));
        $this->cur_blog->setField('blog_creadt', (string) $blog->f('blog_creadt'));
        $this->cur_blog->setField('blog_upddt', (string) $blog->f('blog_upddt'));
        $this->cur_blog->setField('blog_url', (string) $blog->f('blog_url'));
        $this->cur_blog->setField('blog_name', (string) $blog->f('blog_name'));
        $this->cur_blog->setField('blog_desc', (string) $blog->f('blog_desc'));

        $this->cur_blog->setField('blog_status', ($blog->exists('blog_status') ? (int) $blog->f('blog_status') : 1));

        $this->cur_blog->insert();
    }

    private function insertCategory(FlatBackupItem $category): void
    {
        $this->cur_category->clean();

        $this->cur_category->setField('cat_id', (string) $category->f('cat_id'));
        $this->cur_category->setField('blog_id', (string) $category->f('blog_id'));
        $this->cur_category->setField('cat_title', (string) $category->f('cat_title'));
        $this->cur_category->setField('cat_url', (string) $category->f('cat_url'));
        $this->cur_category->setField('cat_desc', (string) $category->f('cat_desc'));

        if (!$this->has_categories && $category->exists('cat_lft') && $category->exists('cat_rgt')) {
            $this->cur_category->setField('cat_lft', (int) $category->f('cat_lft'));
            $this->cur_category->setField('cat_rgt', (int) $category->f('cat_rgt'));
        } else {
            if (!isset($this->lft_categories[$category->f('blog_id')])) {
                $this->lft_categories[$category->f('blog_id')] = 2;
            }
            $this->cur_category->setField('cat_lft', $this->lft_categories[$category->f('blog_id')]++);
            $this->cur_category->setField('cat_rgt', $this->lft_categories[$category->f('blog_id')]++);
        }

        $this->cur_category->insert();
    }

    private function insertLink(FlatBackupItem $link): void
    {
        $this->cur_link->clean();

        $this->cur_link->setField('link_id', (int) $link->f('link_id'));
        $this->cur_link->setField('blog_id', (string) $link->f('blog_id'));
        $this->cur_link->setField('link_href', (string) $link->f('link_href'));
        $this->cur_link->setField('link_title', (string) $link->f('link_title'));
        $this->cur_link->setField('link_desc', (string) $link->f('link_desc'));
        $this->cur_link->setField('link_lang', (string) $link->f('link_lang'));
        $this->cur_link->setField('link_xfn', (string) $link->f('link_xfn'));
        $this->cur_link->setField('link_position', (int) $link->f('link_position'));

        $this->cur_link->insert();
    }

    private function insertSetting(FlatBackupItem $setting): void
    {
        $this->cur_setting->clean();

        $this->cur_setting->setField('setting_id', (string) $setting->f('setting_id'));
        $this->cur_setting->setField('blog_id', !$setting->f('blog_id') ? null : (string) $setting->f('blog_id'));
        $this->cur_setting->setField('setting_ns', (string) $setting->f('setting_ns'));
        $this->cur_setting->setField('setting_value', (string) $setting->f('setting_value'));
        $this->cur_setting->setField('setting_type', (string) $setting->f('setting_type'));
        $this->cur_setting->setField('setting_label', (string) $setting->f('setting_label'));

        $this->cur_setting->insert();
    }

    private function insertPref(FlatBackupItem $pref): void
    {
        if ($this->prefExists($pref->f('pref_ws'), $pref->f('pref_id'), $pref->f('user_id'))) {
            return;
        }

        $this->cur_pref->clean();

        $this->cur_pref->setField('pref_id', (string) $pref->f('pref_id'));
        $this->cur_pref->setField('user_id', !$pref->f('user_id') ? null : (string) $pref->f('user_id'));
        $this->cur_pref->setField('pref_ws', (string) $pref->f('pref_ws'));
        $this->cur_pref->setField('pref_value', (string) $pref->f('pref_value'));
        $this->cur_pref->setField('pref_type', (string) $pref->f('pref_type'));
        $this->cur_pref->setField('pref_label', (string) $pref->f('pref_label'));

        $this->cur_pref->insert();
    }

    private function insertUser(FlatBackupItem $user): void
    {
        if ($this->userExists($user->f('user_id'))) {
            return;
        }

        $this->cur_user->clean();

        $this->cur_user->setField('user_id', (string) $user->f('user_id'));
        $this->cur_user->setField('user_super', (int) $user->f('user_super'));
        $this->cur_user->setField('user_pwd', (string) $user->f('user_pwd'));
        $this->cur_user->setField('user_recover_key', (string) $user->f('user_recover_key'));
        $this->cur_user->setField('user_name', (string) $user->f('user_name'));
        $this->cur_user->setField('user_firstname', (string) $user->f('user_firstname'));
        $this->cur_user->setField('user_displayname', (string) $user->f('user_displayname'));
        $this->cur_user->setField('user_email', (string) $user->f('user_email'));
        $this->cur_user->setField('user_url', (string) $user->f('user_url'));
        $this->cur_user->setField('user_default_blog', !$user->f('user_default_blog') ? null : (string) $user->f('user_default_blog'));
        $this->cur_user->setField('user_lang', (string) $user->f('user_lang'));
        $this->cur_user->setField('user_tz', (string) $user->f('user_tz'));
        $this->cur_user->setField('user_post_status', (int) $user->f('user_post_status'));
        $this->cur_user->setField('user_creadt', (string) $user->f('user_creadt'));
        $this->cur_user->setField('user_upddt', (string) $user->f('user_upddt'));

        $this->cur_user->setField('user_desc', $user->exists('user_desc') ? (string) $user->f('user_desc') : null);
        $this->cur_user->setField('user_options', $user->exists('user_options') ? (string) $user->f('user_options') : null);
        $this->cur_user->setField('user_status', $user->exists('user_status') ? (int) $user->f('user_status') : 1);

        $this->cur_user->insert();

        $this->users[$user->f('user_id')] = true;
    }

    private function insertPermissions(FlatBackupItem $permissions): void
    {
        $this->cur_permissions->clean();

        $this->cur_permissions->setField('user_id', (string) $permissions->f('user_id'));
        $this->cur_permissions->setField('blog_id', (string) $permissions->f('blog_id'));
        $this->cur_permissions->setField('permissions', (string) $permissions->f('permissions'));

        $this->cur_permissions->insert();
    }

    private function insertPost(FlatBackupItem $post): void
    {
        $this->cur_post->clean();

        $cat_id = (int) $post->f('cat_id');
        if (!$cat_id) {
            $cat_id = null;
        }

        $post_password = $post->f('post_password') ? (string) $post->f('post_password') : null;

        $this->cur_post->setField('post_id', (int) $post->f('post_id'));
        $this->cur_post->setField('blog_id', (string) $post->f('blog_id'));
        $this->cur_post->setField('user_id', (string) $this->getUserId($post->f('user_id')));
        $this->cur_post->setField('cat_id', $cat_id);
        $this->cur_post->setField('post_dt', (string) $post->f('post_dt'));
        $this->cur_post->setField('post_creadt', (string) $post->f('post_creadt'));
        $this->cur_post->setField('post_upddt', (string) $post->f('post_upddt'));
        $this->cur_post->setField('post_password', $post_password);
        $this->cur_post->setField('post_type', (string) $post->f('post_type'));
        $this->cur_post->setField('post_format', (string) $post->f('post_format'));
        $this->cur_post->setField('post_url', (string) $post->f('post_url'));
        $this->cur_post->setField('post_lang', (string) $post->f('post_lang'));
        $this->cur_post->setField('post_title', (string) $post->f('post_title'));
        $this->cur_post->setField('post_excerpt', (string) $post->f('post_excerpt'));
        $this->cur_post->setField('post_excerpt_xhtml', (string) $post->f('post_excerpt_xhtml'));
        $this->cur_post->setField('post_content', (string) $post->f('post_content'));
        $this->cur_post->setField('post_content_xhtml', (string) $post->f('post_content_xhtml'));
        $this->cur_post->setField('post_notes', (string) $post->f('post_notes'));
        $this->cur_post->setField('post_words', (string) $post->f('post_words'));
        $this->cur_post->setField('post_meta', (string) $post->f('post_meta'));
        $this->cur_post->setField('post_status', (int) $post->f('post_status'));
        $this->cur_post->setField('post_selected', (int) $post->f('post_selected'));
        $this->cur_post->setField('post_open_comment', (int) $post->f('post_open_comment'));
        $this->cur_post->setField('post_open_tb', (int) $post->f('post_open_tb'));
        $this->cur_post->setField('nb_comment', (int) $post->f('nb_comment'));
        $this->cur_post->setField('nb_trackback', (int) $post->f('nb_trackback'));
        $this->cur_post->setField('post_position', (int) $post->f('post_position'));
        $this->cur_post->setField('post_firstpub', (int) $post->f('post_firstpub'));

        $this->cur_post->setField('post_tz', ($post->exists('post_tz') ? (string) $post->f('post_tz') : 'UTC'));

        $this->cur_post->insert();
    }

    private function insertMeta(FlatBackupItem $meta): void
    {
        $this->cur_meta->clean();

        $this->cur_meta->setField('meta_id', (string) $meta->f('meta_id'));
        $this->cur_meta->setField('meta_type', (string) $meta->f('meta_type'));
        $this->cur_meta->setField('post_id', (int) $meta->f('post_id'));

        $this->cur_meta->insert();
    }

    private function insertMedia(FlatBackupItem $media): void
    {
        $this->cur_media->clean();

        $this->cur_media->setField('media_id', (int) $media->f('media_id'));
        $this->cur_media->setField('user_id', (string) $media->f('user_id'));
        $this->cur_media->setField('media_path', (string) $media->f('media_path'));
        $this->cur_media->setField('media_title', (string) $media->f('media_title'));
        $this->cur_media->setField('media_file', (string) $media->f('media_file'));
        $this->cur_media->setField('media_meta', (string) $media->f('media_meta'));
        $this->cur_media->setField('media_dt', (string) $media->f('media_dt'));
        $this->cur_media->setField('media_creadt', (string) $media->f('media_creadt'));
        $this->cur_media->setField('media_upddt', (string) $media->f('media_upddt'));
        $this->cur_media->setField('media_private', (int) $media->f('media_private'));

        $this->cur_media->setField('media_dir', ($media->exists('media_dir') ? (string) $media->f('media_dir') : dirname($media->f('media_file'))));

        if (!$this->mediaExists()) {
            $this->cur_media->insert();
        }
    }

    private function insertPostMedia(FlatBackupItem $post_media): void
    {
        $this->cur_post_media->clean();

        $this->cur_post_media->setField('media_id', (int) $post_media->f('media_id'));
        $this->cur_post_media->setField('post_id', (int) $post_media->f('post_id'));

        $this->cur_post_media->insert();
    }

    private function insertLog(FlatBackupItem $log): void
    {
        $this->cur_log->clean();

        $this->cur_log->setField('og_id', (int) $log->f('log_id'));
        $this->cur_log->setField('user_id', (string) $log->f('user_id'));
        $this->cur_log->setField('log_table', (string) $log->f('log_table'));
        $this->cur_log->setField('log_dt', (string) $log->f('log_dt'));
        $this->cur_log->setField('log_ip', (string) $log->f('log_ip'));
        $this->cur_log->setField('log_msg', (string) $log->f('log_msg'));

        $this->cur_log->insert();
    }

    private function insertPing(FlatBackupItem $ping): void
    {
        $this->cur_ping->clean();

        $this->cur_ping->setField('post_id', (int) $ping->f('post_id'));
        $this->cur_ping->setField('ping_url', (string) $ping->f('ping_url'));
        $this->cur_ping->setField('ping_dt', (string) $ping->f('ping_dt'));

        $this->cur_ping->insert();
    }

    private function insertComment(FlatBackupItem $comment): void
    {
        $this->cur_comment->clean();

        $this->cur_comment->setField('comment_id', (int) $comment->f('comment_id'));
        $this->cur_comment->setField('post_id', (int) $comment->f('post_id'));
        $this->cur_comment->setField('comment_dt', (string) $comment->f('comment_dt'));
        $this->cur_comment->setField('comment_upddt', (string) $comment->f('comment_upddt'));
        $this->cur_comment->setField('comment_author', (string) $comment->f('comment_author'));
        $this->cur_comment->setField('comment_email', (string) $comment->f('comment_email'));
        $this->cur_comment->setField('comment_site', (string) $comment->f('comment_site'));
        $this->cur_comment->setField('comment_content', (string) $comment->f('comment_content'));
        $this->cur_comment->setField('comment_words', (string) $comment->f('comment_words'));
        $this->cur_comment->setField('comment_ip', (string) $comment->f('comment_ip'));
        $this->cur_comment->setField('comment_status', (int) $comment->f('comment_status'));
        $this->cur_comment->setField('comment_spam_status', (string) $comment->f('comment_spam_status'));
        $this->cur_comment->setField('comment_trackback', (int) $comment->f('comment_trackback'));

        $this->cur_comment->setField('comment_tz', ($comment->exists('comment_tz') ? (string) $comment->f('comment_tz') : 'UTC'));
        $this->cur_comment->setField('comment_spam_filter', ($comment->exists('comment_spam_filter') ? (string) $comment->f('comment_spam_filter') : null));

        $this->cur_comment->insert();
    }

    private function insertSpamRule(FlatBackupItem $spamrule): void
    {
        $this->cur_spamrule->clean();

        $this->cur_spamrule->setField('rule_id', (int) $spamrule->f('rule_id'));
        $this->cur_spamrule->setField('blog_id', (!$spamrule->f('blog_id') ? null : (string) $spamrule->f('blog_id')));
        $this->cur_spamrule->setField('rule_type', (string) $spamrule->f('rule_type'));
        $this->cur_spamrule->setField('rule_content', (string) $spamrule->f('rule_content'));

        $this->cur_spamrule->insert();
    }

    private function insertCategorySingle(FlatBackupItem $category): void
    {
        $this->cur_category->clean();

        $m = $this->searchCategory($this->categories, $category->f('cat_url'));

        $old_id = $category->f('cat_id');
        if (false !== $m) {
            $cat_id = $m;
        } else {
            $cat_id = $this->stack['cat_id'];
            $category->set('cat_id', $cat_id);
            $category->set('blog_id', $this->blog_id);

            $this->insertCategory($category);
            ++$this->stack['cat_id'];
        }

        $this->old_ids['category'][(int) $old_id] = $cat_id;
    }

    private function insertLinkSingle(FlatBackupItem $link): void
    {
        $link->set('blog_id', $this->blog_id);
        $link->set('link_id', $this->stack['link_id']);

        $this->insertLink($link);
        ++$this->stack['link_id'];
    }

    private function insertPostSingle(FlatBackupItem $post): void
    {
        if (!$post->f('cat_id') || isset($this->old_ids['category'][(int) $post->f('cat_id')])) {
            $post_id                                          = $this->stack['post_id'];
            $this->old_ids['post'][(int) $post->f('post_id')] = $post_id;

            $cat_id = $post->f('cat_id') ? $this->old_ids['category'][(int) $post->f('cat_id')] : null;

            $post->set('post_id', $post_id);
            $post->set('cat_id', $cat_id);
            $post->set('blog_id', $this->blog_id);

            $post->set('post_url', App::core()->blog()->posts()->getPostURL(
                (string) $post->f('post_url'),
                (string) $post->f('post_dt'),
                (string) $post->f('post_title'),
                (int) $post->f('post_id')
            ));

            $this->insertPost($post);
            ++$this->stack['post_id'];
        } else {
            $this->throwIdError($post->__name, $post->__line, 'category');
        }
    }

    private function insertMetaSingle(FlatBackupItem $meta): void
    {
        if (isset($this->old_ids['post'][(int) $meta->f('post_id')])) {
            $meta->set('post_id', $this->old_ids['post'][(int) $meta->f('post_id')]);
            $this->insertMeta($meta);
        } else {
            $this->throwIdError($meta->__name, $meta->__line, 'post');
        }
    }

    private function insertMediaSingle(FlatBackupItem $media): void
    {
        $media_id = $this->stack['media_id'];
        $old_id   = $media->f('media_id');

        $media->set('media_id', $media_id);
        $media->set('media_path', App::core()->blog()->settings()->get('system')->get('public_path'));
        $media->set('user_id', $this->getUserId($media->f('user_id')));

        $this->insertMedia($media);
        ++$this->stack['media_id'];
        $this->old_ids['media'][(int) $old_id] = $media_id;
    }

    private function insertPostMediaSingle(FlatBackupItem $post_media): void
    {
        if (isset($this->old_ids['media'][(int) $post_media->f('media_id')], $this->old_ids['post'][(int) $post_media->f('post_id')])) {
            $post_media->set('media_id', $this->old_ids['media'][(int) $post_media->f('media_id')]);
            $post_media->set('post_id', $this->old_ids['post'][(int) $post_media->f('post_id')]);

            $this->insertPostMedia($post_media);
        } elseif (!isset($this->old_ids['media'][(int) $post_media->f('media_id')])) {
            $this->throwIdError($post_media->__name, $post_media->__line, 'media');
        } else {
            $this->throwIdError($post_media->__name, $post_media->__line, 'post');
        }
    }

    private function insertPingSingle(FlatBackupItem $ping): void
    {
        if (isset($this->old_ids['post'][(int) $ping->f('post_id')])) {
            $ping->set('post_id', $this->old_ids['post'][(int) $ping->f('post_id')]);

            $this->insertPing($ping);
        } else {
            $this->throwIdError($ping->__name, $ping->__line, 'post');
        }
    }

    private function insertCommentSingle(FlatBackupItem $comment): void
    {
        if (isset($this->old_ids['post'][(int) $comment->f('post_id')])) {
            $comment_id = $this->stack['comment_id'];

            $comment->set('comment_id', $comment_id);
            $comment->set('post_id', $this->old_ids['post'][(int) $comment->f('post_id')]);

            $this->insertComment($comment);
            ++$this->stack['comment_id'];
        } else {
            $this->throwIdError($comment->__name, $comment->__line, 'post');
        }
    }

    private function throwIdError(string $name, int|string $line, string $related): void
    {
        throw new ModuleException(sprintf(
            __('ID of "%3$s" does not match on record "%1$s" at line %2$s of backup file.'),
            html::escapeHTML($name),
            html::escapeHTML($line),
            html::escapeHTML($related)
        ));
    }

    public function searchCategory(Record $rs, string $url): int|false
    {
        while ($rs->fetch()) {
            if ($rs->f('cat_url') == $url) {
                return $rs->fInt('cat_id');
            }
        }

        return false;
    }

    public function getUserId(string $user_id): string
    {
        if (!$this->userExists($user_id)) {
            if (App::core()->user()->isSuperAdmin()) {
                // Sanitizes user_id and create a lambda user
                $user_id = preg_replace('/[^A-Za-z0-9]$/', '', $user_id);
                $user_id .= strlen($user_id) < 2 ? '-a' : '';

                // We change user_id, we need to check again
                if (!$this->userExists($user_id)) {
                    $this->cur_user->clean();
                    $this->cur_user->setField('user_id', (string) $user_id);
                    $this->cur_user->setField('user_pwd', md5(uniqid()));

                    App::core()->users()->addUser($this->cur_user);

                    $this->users[$user_id] = true;
                }
            } else {
                // Returns current user id
                $user_id = App::core()->user()->userID();
            }
        }

        return $user_id;
    }

    private function userExists(string $user_id): bool
    {
        if (isset($this->users[$user_id])) {
            return $this->users[$user_id];
        }

        $strReq = 'SELECT user_id ' .
        'FROM ' . App::core()->prefix . 'user ' .
        "WHERE user_id = '" . App::core()->con()->escape($user_id) . "' ";

        $rs = App::core()->con()->select($strReq);

        $this->users[$user_id] = !$rs->isEmpty();

        return $this->users[$user_id];
    }

    private function prefExists(string $pref_ws, string $pref_id, string $user_id): bool
    {
        $strReq = 'SELECT pref_id,pref_ws,user_id ' .
        'FROM ' . App::core()->prefix . 'pref ' .
        "WHERE pref_id = '" . App::core()->con()->escape($pref_id) . "' " .
        "AND pref_ws = '" . App::core()->con()->escape($pref_ws) . "' ";
        if (!$user_id) {
            $strReq .= 'AND user_id IS NULL ';
        } else {
            $strReq .= "AND user_id = '" . App::core()->con()->escape($user_id) . "' ";
        }

        $rs = App::core()->con()->select($strReq);

        return !$rs->isEmpty();
    }

    private function mediaExists(): bool
    {
        $strReq = 'SELECT media_id ' .
        'FROM ' . App::core()->prefix . 'media ' .
        "WHERE media_path = '" . App::core()->con()->escape($this->cur_media->getField('media_path')) . "' " .
        "AND media_file = '" . App::core()->con()->escape($this->cur_media->getField('media_file')) . "' ";

        $rs = App::core()->con()->select($strReq);

        return !$rs->isEmpty();
    }

    private function prepareDC12line(FlatBackupItem &$line): void
    {
        $settings = ['dc_theme', 'dc_nb_post_per_page', 'dc_allow_comments',
            'dc_allow_trackbacks', 'dc_comment_pub', 'dc_comments_ttl',
            'dc_wiki_comments', 'dc_use_smilies', 'dc_date_format', 'dc_time_format',
            'dc_url_scan', ];

        switch ($line->__name) {
            case 'categorie':
                $line->substitute('cat_libelle', 'cat_title');
                $line->substitute('cat_libelle_url', 'cat_url');
                $line->__name = 'category';
                $line->set('blog_id', 'default');

                break;

            case 'link':
                $line->substitute('href', 'link_href');
                $line->substitute('label', 'link_title');
                $line->substitute('title', 'link_desc');
                $line->substitute('lang', 'link_lang');
                $line->substitute('rel', 'link_xfn');
                $line->substitute('position', 'link_position');
                $line->set('blog_id', 'default');

                break;

            case 'post':
                $line->substitute('post_titre', 'post_title');
                $line->set('post_title', html::decodeEntities($line->f('post_title')));
                $line->set('post_url', date('Y/m/d/', strtotime($line->f('post_dt'))) . $line->f('post_id') . '-' . $line->f('post_titre_url'));
                $line->set('post_url', substr($line->f('post_url'), 0, 255));
                $line->set('post_format', ('' == $line->f('post_content_wiki') ? 'xhtml' : 'wiki'));
                $line->set('post_content_xhtml', $line->f('post_content'));
                $line->set('post_excerpt_xhtml', $line->f('post_chapo'));

                if ('wiki' == $line->f('post_format')) {
                    $line->set('post_content', $line->f('post_content_wiki'));
                    $line->set('post_excerpt', $line->f('post_chapo_wiki'));
                } else {
                    $line->set('post_content', $line->f('post_content'));
                    $line->set('post_excerpt', $line->f('post_chapo'));
                }

                $line->set('post_status', (int) $line->f('post_pub'));
                $line->set('post_type', 'post');
                $line->set('blog_id', 'default');

                $line->drop('post_titre_url', 'post_content_wiki', 'post_chapo', 'post_chapo_wiki', 'post_pub');

                break;

            case 'post_meta':
                $line->drop('meta_id');
                $line->substitute('meta_key', 'meta_type');
                $line->substitute('meta_value', 'meta_id');
                $line->__name = 'meta';
                $line->set('blog_id', 'default');

                break;

            case 'comment':
                $line->substitute('comment_auteur', 'comment_author');
                if ('' != $line->f('comment_site') && !preg_match('!^http(s)?://.*$!', $line->f('comment_site'), $m)) {
                    $line->set('comment_site', 'http://' . $line->f('comment_site'));
                }
                $line->set('comment_status', (int) $line->f('comment_pub'));
                $line->drop('comment_pub');

                break;
        }

        // --BEHAVIOR-- importPrepareDC12
        App::core()->behavior()->call('importPrepareDC12', $line, $this);
    }
}
