<?php
/**
 * @brief Dotclear distribution upgrade class
 *
 * @todo no files remove < dcns as entire structure change
 *
 * @package Dotclear
 * @subpackage Distrib
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Distrib;

use Dotclear\Core\Blog\Settings\Settings;
use Dotclear\Database\Connection;
use Dotclear\Database\Structure;
use Dotclear\File\Files;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

class Distrib
{
    public static function getConfigFile(): string
    {
        $file = implode(DIRECTORY_SEPARATOR, [__DIR__, 'config.php.distrib']);
        if (!is_file($file)) {
            throw new \Exception(sprintf(__('File %s does not exist.'), $file));
        }

        return file_get_contents($file);
    }

    public static function getCoreConfig(): array
    {
        return [
            'admin_mailform'        => [null, ''],
            'admin_ssl'             => [null, true],
            'admin_url'             => [null, ''],
            'backup_dir'            => [null, root_path()],
            'base_dir'              => [null, root_path('..')],
            'cache_dir'             => [null, root_path('..', 'cache')],
            'core_update_channel'   => [null, 'stable'],
            'core_update_noauto'    => [null, false],
            'core_update_url'       => [null, 'https://download.dotclear.org/versions.xml'],
            'core_version'          => [false, trim(file_get_contents(root_path('version')))],
            'core_version_break'    => [false, '3.0'],
            'crypt_algo'            => [null, 'sha1'],
            'database_driver'       => [true, ''],
            'database_host'         => [true, ''],
            'database_name'         => [true, ''],
            'database_password'     => [true, ''],
            'database_persist'      => [null, true],
            'database_prefix'       => [null, 'dc_'],
            'database_user'         => [true, ''],
            'digests_dir'           => [null, root_path('..', 'digests')],
            'force_scheme_443'      => [null, true],
            'iconset_dir'           => [null, root_path('Iconset')],
            'iconset_official'      => [false, 'Legacy,ThomasDaveluy'],
            'iconset_update_url'    => [null, ''],
            'jquery_default'        => [null, '3.6.0'],
            'l10n_dir'              => [null, root_path('locales')],
            'l10n_update_url'       => [null, 'https://services.dotclear.net/dc2.l10n/?version=%s'],
            'media_dir_showhidden'  => [null, false],
            'media_upload_maxsize'  => [false, Files::getMaxUploadFilesize()],
            'master_key'            => [true, ''],
            'module_allow_multi'    => [null, false],
            'php_next_required'     => [false, '7.4'],
            'plugin_dir'            => [null, root_path('Plugin')],
            'plugin_official'       => [false, 'AboutConfig,Akismet,Antispam,Attachments,Blogroll,Dclegacy,FairTrackbacks,ImportExport,Maintenance,Pages,Pings,SimpleMenu,Tags,ThemeEditor,UserPref,Widgets,LegacyEditor,CKEditor,Breadcrumb'],
            'plugin_update_url'     => [null,  'https://update.dotaddict.org/dc2/plugins.xml'],
            'query_timeout'         => [null, 4],
            'reverse_proxy'         => [null, true],
            'run_level'             => [null, 0],
            'root_dir'              => [false, root_path()], //Alias for DOTCLEAR_ROOT_DIR
            'session_name'          => [null, 'dcxd'],
            'session_ttl'           => [null, '-120 minutes'],
            'store_allow_repo'      => [null, true],
            'store_update_noauto'   => [null, false],
            'template_default'      => [null, 'Mustek'],
            'theme_dir'             => [null, root_path('Theme')],
            'theme_official'        => [false, 'Berlin,BlueSilence,Blowup,CustomCSS,Ductile'],
            'theme_update_url'      => [null, 'https://update.dotaddict.org/dc2/themes.xml'],
            'var_dir'               => [null, root_path('..', 'var')],
            'vendor_name'           => [null, 'Dotclear'],
            'xmlrpc_url'            => [null, '%1$sxmlrpc/%2$s'],
        ];
    }

    public static function getDatabaseStructure(Structure $_s): void
    {
        /* Tables
        -------------------------------------------------------- */
        $_s->blog
            ->blog_id('varchar', 32, false)
            ->blog_uid('varchar', 32, false)
            ->blog_creadt('timestamp', 0, false, 'now()')
            ->blog_upddt('timestamp', 0, false, 'now()')
            ->blog_url('varchar', 255, false)
            ->blog_name('varchar', 255, false)
            ->blog_desc('text', 0, true)
            ->blog_status('smallint', 0, false, 1)

            ->primary('pk_blog', 'blog_id')
        ;

        $_s->category
            ->cat_id('bigint', 0, false)
            ->blog_id('varchar', 32, false)
            ->cat_title('varchar', 255, false)
            ->cat_url('varchar', 255, false)
            ->cat_desc('text', 0, true)
            ->cat_position('integer', 0, true, 0)
            ->cat_lft('integer', 0, true)
            ->cat_rgt('integer', 0, true)

            ->primary('pk_category', 'cat_id')

            ->unique('uk_cat_url', 'cat_url', 'blog_id')
        ;

        $_s->session
            ->ses_id('varchar', 40, false)
            ->ses_time('integer', 0, false, 0)
            ->ses_start('integer', 0, false, 0)
            ->ses_value('text', 0, false)

            ->primary('pk_session', 'ses_id')
        ;

        $_s->setting
            ->setting_id('varchar', 255, false)
            ->blog_id('varchar', 32, true)
            ->setting_ns('varchar', 32, false, "'system'")
            ->setting_value('text', 0, true, null)
            ->setting_type('varchar', 8, false, "'string'")
            ->setting_label('text', 0, true)

            ->unique('uk_setting', 'setting_ns', 'setting_id', 'blog_id')
        ;

        $_s->user
            ->user_id('varchar', 32, false)
            ->user_super('smallint', 0, true)
            ->user_status('smallint', 0, false, 1)
            ->user_pwd('varchar', 255, false)
            ->user_change_pwd('smallint', 0, false, 0)
            ->user_recover_key('varchar', 32, true, null)
            ->user_name('varchar', 255, true, null)
            ->user_firstname('varchar', 255, true, null)
            ->user_displayname('varchar', 255, true, null)
            ->user_email('varchar', 255, true, null)
            ->user_url('varchar', 255, true, null)
            ->user_desc('text', 0, true)
            ->user_default_blog('varchar', 32, true, null)
            ->user_options('text', 0, true)
            ->user_lang('varchar', 5, true, null)
            ->user_tz('varchar', 128, false, "'UTC'")
            ->user_post_status('smallint', 0, false, -2)
            ->user_creadt('timestamp', 0, false, 'now()')
            ->user_upddt('timestamp', 0, false, 'now()')

            ->primary('pk_user', 'user_id')
        ;

        $_s->permissions
            ->user_id('varchar', 32, false)
            ->blog_id('varchar', 32, false)
            ->permissions('text', 0, true)

            ->primary('pk_permissions', 'user_id', 'blog_id')
        ;

        $_s->post
            ->post_id('bigint', 0, false)
            ->blog_id('varchar', 32, false)
            ->user_id('varchar', 32, false)
            ->cat_id('bigint', 0, true)
            ->post_dt('timestamp', 0, false, 'now()')
            ->post_tz('varchar', 128, false, "'UTC'")
            ->post_creadt('timestamp', 0, false, 'now()')
            ->post_upddt('timestamp', 0, false, 'now()')
            ->post_password('varchar', 32, true, null)
            ->post_type('varchar', 32, false, "'post'")
            ->post_format('varchar', 32, false, "'xhtml'")
            ->post_url('varchar', 255, false)
            ->post_lang('varchar', 5, true, null)
            ->post_title('varchar', 255, true, null)
            ->post_excerpt('text', 0, true, null)
            ->post_excerpt_xhtml('text', 0, true, null)
            ->post_content('text', 0, true, null)
            ->post_content_xhtml('text', 0, false)
            ->post_notes('text', 0, true, null)
            ->post_meta('text', 0, true, null)
            ->post_words('text', 0, true, null)
            ->post_status('smallint', 0, false, 0)
            ->post_firstpub('smallint', 0, false, 0)
            ->post_selected('smallint', 0, false, 0)
            ->post_position('integer', 0, false, 0)
            ->post_open_comment('smallint', 0, false, 0)
            ->post_open_tb('smallint', 0, false, 0)
            ->nb_comment('integer', 0, false, 0)
            ->nb_trackback('integer', 0, false, 0)

            ->primary('pk_post', 'post_id')

            ->unique('uk_post_url', 'post_url', 'post_type', 'blog_id')
        ;

        $_s->media
            ->media_id('bigint', 0, false)
            ->user_id('varchar', 32, false)
            ->media_path('varchar', 255, false)
            ->media_title('varchar', 255, false)
            ->media_file('varchar', 255, false)
            ->media_dir('varchar', 255, false, "'.'")
            ->media_meta('text', 0, true, null)
            ->media_dt('timestamp', 0, false, 'now()')
            ->media_creadt('timestamp', 0, false, 'now()')
            ->media_upddt('timestamp', 0, false, 'now()')
            ->media_private('smallint', 0, false, 0)

            ->primary('pk_media', 'media_id')
        ;

        $_s->post_media
            ->media_id('bigint', 0, false)
            ->post_id('bigint', 0, false)
            ->link_type('varchar', 32, false, "'attachment'")

            ->primary('pk_post_media', 'media_id', 'post_id', 'link_type')
        ;

        $_s->log
            ->log_id('bigint', 0, false)
            ->user_id('varchar', 32, true)
            ->blog_id('varchar', 32, true)
            ->log_table('varchar', 255, false)
            ->log_dt('timestamp', 0, false, 'now()')
            ->log_ip('varchar', 39, false)
            ->log_msg('text', 0, true, null)

            ->primary('pk_log', 'log_id')
        ;

        $_s->version
            ->module('varchar', 64, false)
            ->version('varchar', 32, false)

            ->primary('pk_version', 'module')
        ;

        $_s->ping
            ->post_id('bigint', 0, false)
            ->ping_url('varchar', 255, false)
            ->ping_dt('timestamp', 0, false, 'now()')

            ->primary('pk_ping', 'post_id', 'ping_url')
        ;

        $_s->comment
            ->comment_id('bigint', 0, false)
            ->post_id('bigint', 0, false)
            ->comment_dt('timestamp', 0, false, 'now()')
            ->comment_tz('varchar', 128, false, "'UTC'")
            ->comment_upddt('timestamp', 0, false, 'now()')
            ->comment_author('varchar', 255, true, null)
            ->comment_email('varchar', 255, true, null)
            ->comment_site('varchar', 255, true, null)
            ->comment_content('text', 0, true)
            ->comment_words('text', 0, true, null)
            ->comment_ip('varchar', 39, true, null)
            ->comment_status('smallint', 0, true, 0)
            ->comment_trackback('smallint', 0, false, 0)

            ->primary('pk_comment', 'comment_id')
        ;

        $_s->meta
            ->meta_id('varchar', 255, false)
            ->meta_type('varchar', 64, false)
            ->post_id('bigint', 0, false)

            ->primary('pk_meta', 'meta_id', 'meta_type', 'post_id')
        ;

        $_s->pref
            ->pref_id('varchar', 255, false)
            ->user_id('varchar', 32, true)
            ->pref_ws('varchar', 32, false, "'system'")
            ->pref_value('text', 0, true, null)
            ->pref_type('varchar', 8, false, "'string'")
            ->pref_label('text', 0, true)

            ->unique('uk_pref', 'pref_ws', 'pref_id', 'user_id')
        ;

        $_s->notice
            ->notice_id('bigint', 0, false)
            ->ses_id('varchar', 40, false)
            ->notice_type('varchar', 32, true)
            ->notice_ts('timestamp', 0, false, 'now()')
            ->notice_msg('text', 0, true, null)
            ->notice_format('varchar', 32, true, "'text'")
            ->notice_options('text', 0, true, null)

            ->primary('pk_notice', 'notice_id')
        ;

        /* References indexes
        -------------------------------------------------------- */
        $_s->category->index('idx_category_blog_id', 'btree', 'blog_id');
        $_s->category->index('idx_category_cat_lft_blog_id', 'btree', 'blog_id', 'cat_lft');
        $_s->category->index('idx_category_cat_rgt_blog_id', 'btree', 'blog_id', 'cat_rgt');
        $_s->setting->index('idx_setting_blog_id', 'btree', 'blog_id');
        $_s->user->index('idx_user_user_default_blog', 'btree', 'user_default_blog');
        $_s->permissions->index('idx_permissions_blog_id', 'btree', 'blog_id');
        $_s->post->index('idx_post_cat_id', 'btree', 'cat_id');
        $_s->post->index('idx_post_user_id', 'btree', 'user_id');
        $_s->post->index('idx_post_blog_id', 'btree', 'blog_id');
        $_s->media->index('idx_media_user_id', 'btree', 'user_id');
        $_s->post_media->index('idx_post_media_post_id', 'btree', 'post_id');
        $_s->post_media->index('idx_post_media_media_id', 'btree', 'media_id');
        $_s->log->index('idx_log_user_id', 'btree', 'user_id');
        $_s->comment->index('idx_comment_post_id', 'btree', 'post_id');
        $_s->meta->index('idx_meta_post_id', 'btree', 'post_id');
        $_s->meta->index('idx_meta_meta_type', 'btree', 'meta_type');
        $_s->pref->index('idx_pref_user_id', 'btree', 'user_id');

        /* Performance indexes
        -------------------------------------------------------- */
        $_s->comment->index('idx_comment_post_id_dt_status', 'btree', 'post_id', 'comment_dt', 'comment_status');
        $_s->post->index('idx_post_post_dt', 'btree', 'post_dt');
        $_s->post->index('idx_post_post_dt_post_id', 'btree', 'post_dt', 'post_id');
        $_s->post->index('idx_blog_post_post_dt_post_id', 'btree', 'blog_id', 'post_dt', 'post_id');
        $_s->post->index('idx_blog_post_post_status', 'btree', 'blog_id', 'post_status');
        $_s->blog->index('idx_blog_blog_upddt', 'btree', 'blog_upddt');
        $_s->user->index('idx_user_user_super', 'btree', 'user_super');

        /* Foreign keys
        -------------------------------------------------------- */
        $_s->category->reference('fk_category_blog', 'blog_id', 'blog', 'blog_id', 'cascade', 'cascade');
        $_s->setting->reference('fk_setting_blog', 'blog_id', 'blog', 'blog_id', 'cascade', 'cascade');
        $_s->user->reference('fk_user_default_blog', 'user_default_blog', 'blog', 'blog_id', 'cascade', 'set null');
        $_s->permissions->reference('fk_permissions_blog', 'blog_id', 'blog', 'blog_id', 'cascade', 'cascade');
        $_s->permissions->reference('fk_permissions_user', 'user_id', 'user', 'user_id', 'cascade', 'cascade');
        $_s->post->reference('fk_post_category', 'cat_id', 'category', 'cat_id', 'cascade', 'set null');
        $_s->post->reference('fk_post_user', 'user_id', 'user', 'user_id', 'cascade', 'cascade');
        $_s->post->reference('fk_post_blog', 'blog_id', 'blog', 'blog_id', 'cascade', 'cascade');
        $_s->media->reference('fk_media_user', 'user_id', 'user', 'user_id', 'cascade', 'cascade');
        $_s->post_media->reference('fk_media', 'media_id', 'media', 'media_id', 'cascade', 'cascade');
        $_s->post_media->reference('fk_media_post', 'post_id', 'post', 'post_id', 'cascade', 'cascade');
        $_s->ping->reference('fk_ping_post', 'post_id', 'post', 'post_id', 'cascade', 'cascade');
        $_s->comment->reference('fk_comment_post', 'post_id', 'post', 'post_id', 'cascade', 'cascade');
        $_s->log->reference('fk_log_blog', 'blog_id', 'blog', 'blog_id', 'cascade', 'set null');
        $_s->meta->reference('fk_meta_post', 'post_id', 'post', 'post_id', 'cascade', 'cascade');
        $_s->pref->reference('fk_pref_user', 'user_id', 'user', 'user_id', 'cascade', 'cascade');
        $_s->notice->reference('fk_notice_session', 'ses_id', 'session', 'ses_id', 'cascade', 'cascade');

        /* PostgreSQL specific indexes
        -------------------------------------------------------- */
        if ($_s->driver() == 'pgsql') {
            $_s->setting->index('idx_setting_blog_id_null', 'btree', '(blog_id IS NULL)');
            $_s->media->index('idx_media_media_path', 'btree', 'media_path', 'media_dir');
            $_s->pref->index('idx_pref_user_id_null', 'btree', '(user_id IS NULL)');
        }
    }

    public static function checkRequirements(Connection $con, array &$err): bool
    {
        $err = [];

        if (version_compare(phpversion(), '8.0', '<')) {
            $err[] = sprintf(__('PHP version is %s (%s or earlier needed).'), phpversion(), '8.0');
        }

        if (!function_exists('mb_detect_encoding')) {
            $err[] = __('Multibyte string module (mbstring) is not available.');
        }

        if (!function_exists('iconv')) {
            $err[] = __('Iconv module is not available.');
        }

        if (!function_exists('ob_start')) {
            $err[] = __('Output control functions are not available.');
        }

        if (!function_exists('simplexml_load_string')) {
            $err[] = __('SimpleXML module is not available.');
        }

        if (!function_exists('dom_import_simplexml')) {
            $err[] = __('DOM XML module is not available.');
        }

        $pcre_str = base64_decode('w6nDqMOgw6o=');
        if (!@preg_match('/' . $pcre_str . '/u', $pcre_str)) {
            $err[] = __('PCRE engine does not support UTF-8 strings.');
        }

        if (!function_exists('spl_classes')) {
            $err[] = __('SPL module is not available.');
        }

        if ($con->syntax() == 'mysql') {
            if (version_compare($con->version(), '4.1', '<')) {
                $err[] = sprintf(__('MySQL version is %s (4.1 or earlier needed).'), $con->version());
            } else {
                $rs     = $con->select('SHOW ENGINES');
                $innodb = false;
                while ($rs->fetch()) {
                    if (strtolower($rs->f(0)) == 'innodb' && strtolower($rs->f(1)) != 'disabled' && strtolower($rs->f(1)) != 'no') {
                        $innodb = true;

                        break;
                    }
                }

                if (!$innodb) {
                    $err[] = __('MySQL InnoDB engine is not available.');
                }
            }
        } elseif ($con->driver() == 'pgsql') {
            if (version_compare($con->version(), '8.0', '<')) {
                $err[] = sprintf(__('PostgreSQL version is %s (8.0 or earlier needed).'), $con->version());
            }
        }

        return count($err) == 0;
    }


    /**
     * Get blog default settings
     *
     * Creates default settings for active blog. Optionnal parameter
     * <var>defaults</var> replaces default params while needed.
     *
     * @param   array   $defaults   The defaults settings
     */
    public static function setBlogDefaultSettings($defaults = null): void
    {
        if (!is_array($defaults)) {
            $defaults = [
                ['allow_comments', 'boolean', true,
                    'Allow comments on blog'],
                ['allow_trackbacks', 'boolean', true,
                    'Allow trackbacks on blog'],
                ['blog_timezone', 'string', 'Europe/London',
                    'Blog timezone'],
                ['comments_nofollow', 'boolean', true,
                    'Add rel="nofollow" to comments URLs'],
                ['comments_pub', 'boolean', true,
                    'Publish comments immediately'],
                ['comments_ttl', 'integer', 0,
                    'Number of days to keep comments open (0 means no ttl)'],
                ['copyright_notice', 'string', '', 'Copyright notice (simple text)'],
                ['date_format', 'string', '%A, %B %e %Y',
                    'Date format. See PHP strftime function for patterns'],
                ['editor', 'string', '',
                    'Person responsible of the content'],
                ['enable_html_filter', 'boolean', 0,
                    'Enable HTML filter'],
                ['enable_xmlrpc', 'boolean', 0,
                    'Enable XML/RPC interface'],
                ['lang', 'string', 'en',
                    'Default blog language'],
                ['media_exclusion', 'string', '/\.(phps?|pht(ml)?|phl|.?html?|xml|js|htaccess)[0-9]*$/i',
                    'File name exclusion pattern in media manager. (PCRE value)'],
                ['media_img_m_size', 'integer', 448,
                    'Image medium size in media manager'],
                ['media_img_s_size', 'integer', 240,
                    'Image small size in media manager'],
                ['media_img_t_size', 'integer', 100,
                    'Image thumbnail size in media manager'],
                ['media_img_title_pattern', 'string', 'Title ;; Date(%b %Y) ;; separator(, )',
                    'Pattern to set image title when you insert it in a post'],
                ['media_video_width', 'integer', 400,
                    'Video width in media manager'],
                ['media_video_height', 'integer', 300,
                    'Video height in media manager'],
                ['module_plugin_dir', 'string', '',
                    'Blog exclusive plugins path'],
                ['module_theme_dir', 'string', '',
                    'Blog exclusive themes path'],
                ['nb_post_for_home', 'integer', 20,
                    'Number of entries on first home page'],
                ['nb_post_per_page', 'integer', 20,
                    'Number of entries on home pages and category pages'],
                ['nb_post_per_feed', 'integer', 20,
                    'Number of entries on feeds'],
                ['nb_comment_per_feed', 'integer', 20,
                    'Number of comments on feeds'],
                ['post_url_format', 'string', '{y}/{m}/{d}/{t}',
                    'Post URL format. {y}: year, {m}: month, {d}: day, {id}: post id, {t}: entry title'],
                ['public_path', 'string', 'public',
                    'Path to public directory, begins with a / for a full system path'],
                ['public_url', 'string', '/public',
                    'URL to public directory'],
                ['robots_policy', 'string', 'INDEX,FOLLOW',
                    'Search engines robots policy'],
                ['short_feed_items', 'boolean', false,
                    'Display short feed items'],
                ['theme', 'string', 'Berlin',
                    'Blog theme'],
                ['time_format', 'string', '%H:%M',
                    'Time format. See PHP strftime function for patterns'],
                ['tpl_allow_php', 'boolean', false,
                    'Allow PHP code in templates'],
                ['tpl_use_cache', 'boolean', true,
                    'Use template caching'],
                ['trackbacks_pub', 'boolean', true,
                    'Publish trackbacks immediately'],
                ['trackbacks_ttl', 'integer', 0,
                    'Number of days to keep trackbacks open (0 means no ttl)'],
                ['url_scan', 'string', 'query_string',
                    'URL handle mode (path_info or query_string)'],
                ['use_smilies', 'boolean', false,
                    'Show smilies on entries and comments'],
                ['no_search', 'boolean', false,
                    'Disable search'],
                ['inc_subcats', 'boolean', false,
                    'Include sub-categories in category page and category posts feed'],
                ['wiki_comments', 'boolean', false,
                    'Allow commenters to use a subset of wiki syntax'],
                ['import_feed_url_control', 'boolean', true,
                    'Control feed URL before import'],
                ['import_feed_no_private_ip', 'boolean', true,
                    'Prevent import feed from private IP'],
                ['import_feed_ip_regexp', 'string', '',
                    'Authorize import feed only from this IP regexp'],
                ['import_feed_port_regexp', 'string', '/^(80|443)$/',
                    'Authorize import feed only from this port regexp'],
                ['jquery_needed', 'boolean', true,
                    'Load jQuery library']
            ];
        }

        $settings = new Settings(null);
        $settings->addNamespace('system');

        foreach ($defaults as $v) {
            $settings->system->put($v[0], $v[2], $v[1], $v[3], false, true);
        }
    }
}
