<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Distrib;

// Dotclear\process\Distrib\Distrib
use Dotclear\Core\Blog\Settings\Settings;
use Dotclear\Database\AbstractConnection;
use Dotclear\Database\Structure;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Mapper\Strings;
use Exception;

/**
 * Distrib methods.
 *
 * This class provides default structures of Dotclear.
 *
 * @ingroup Distrib
 */
class Distrib
{
    public static function getConfigFile(): string
    {
        $file = Path::implode(__DIR__, 'dotclear.conf.distrib');
        if (!is_file($file)) {
            throw new Exception(sprintf(__('File %s does not exist.'), $file));
        }

        return file_get_contents($file);
    }

    public static function getDatabaseStructure(Structure $_s): void
    {
        /* Tables
        -------------------------------------------------------- */
        $_s->table('blog')
            ->field('blog_id', 'varchar', 32, false)
            ->field('blog_uid', 'varchar', 32, false)
            ->field('blog_creadt', 'timestamp', 0, false, 'now()')
            ->field('blog_upddt', 'timestamp', 0, false, 'now()')
            ->field('blog_url', 'varchar', 255, false)
            ->field('blog_name', 'varchar', 255, false)
            ->field('blog_desc', 'text', 0, true)
            ->field('blog_status', 'smallint', 0, false, 1)

            ->primary('pk_blog', 'blog_id')
        ;

        $_s->table('category')
            ->field('cat_id', 'bigint', 0, false)
            ->field('blog_id', 'varchar', 32, false)
            ->field('cat_title', 'varchar', 255, false)
            ->field('cat_url', 'varchar', 255, false)
            ->field('cat_desc', 'text', 0, true)
            ->field('cat_position', 'integer', 0, true, 0)
            ->field('cat_lft', 'integer', 0, true)
            ->field('cat_rgt', 'integer', 0, true)

            ->primary('pk_category', 'cat_id')

            ->unique('uk_cat_url', 'cat_url', 'blog_id')
        ;

        $_s->table('session')
            ->field('ses_id', 'varchar', 40, false)
            ->field('ses_time', 'integer', 0, false, 0)
            ->field('ses_start', 'integer', 0, false, 0)
            ->field('ses_value', 'text', 0, false)

            ->primary('pk_session', 'ses_id')
        ;

        $_s->table('setting')
            ->field('setting_id', 'varchar', 255, false)
            ->field('blog_id', 'varchar', 32, true)
            ->field('setting_ns', 'varchar', 32, false, "'system'")
            ->field('setting_value', 'text', 0, true, null)
            ->field('setting_type', 'varchar', 8, false, "'string'")
            ->field('setting_label', 'text', 0, true)

            ->unique('uk_setting', 'setting_ns', 'setting_id', 'blog_id')
        ;

        $_s->table('user')
            ->field('user_id', 'varchar', 32, false)
            ->field('user_super', 'smallint', 0, true)
            ->field('user_status', 'smallint', 0, false, 1)
            ->field('user_pwd', 'varchar', 255, false)
            ->field('user_change_pwd', 'smallint', 0, false, 0)
            ->field('user_recover_key', 'varchar', 32, true, null)
            ->field('user_name', 'varchar', 255, true, null)
            ->field('user_firstname', 'varchar', 255, true, null)
            ->field('user_displayname', 'varchar', 255, true, null)
            ->field('user_email', 'varchar', 255, true, null)
            ->field('user_url', 'varchar', 255, true, null)
            ->field('user_desc', 'text', 0, true)
            ->field('user_default_blog', 'varchar', 32, true, null)
            ->field('user_options', 'text', 0, true)
            ->field('user_lang', 'varchar', 5, true, null)
            ->field('user_tz', 'varchar', 128, false, "'UTC'")
            ->field('user_post_status', 'smallint', 0, false, -2)
            ->field('user_creadt', 'timestamp', 0, false, 'now()')
            ->field('user_upddt', 'timestamp', 0, false, 'now()')

            ->primary('pk_user', 'user_id')
        ;

        $_s->table('permissions')
            ->field('user_id', 'varchar', 32, false)
            ->field('blog_id', 'varchar', 32, false)
            ->field('permissions', 'text', 0, true)

            ->primary('pk_permissions', 'user_id', 'blog_id')
        ;

        $_s->table('post')
            ->field('post_id', 'bigint', 0, false)
            ->field('blog_id', 'varchar', 32, false)
            ->field('user_id', 'varchar', 32, false)
            ->field('cat_id', 'bigint', 0, true)
            ->field('post_dt', 'timestamp', 0, false, 'now()')
            ->field('post_creadt', 'timestamp', 0, false, 'now()')
            ->field('post_upddt', 'timestamp', 0, false, 'now()')
            ->field('post_password', 'varchar', 32, true, null)
            ->field('post_type', 'varchar', 32, false, "'post'")
            ->field('post_format', 'varchar', 32, false, "'xhtml'")
            ->field('post_url', 'varchar', 255, false)
            ->field('post_lang', 'varchar', 5, true, null)
            ->field('post_title', 'varchar', 255, true, null)
            ->field('post_excerpt', 'text', 0, true, null)
            ->field('post_excerpt_xhtml', 'text', 0, true, null)
            ->field('post_content', 'text', 0, true, null)
            ->field('post_content_xhtml', 'text', 0, false)
            ->field('post_notes', 'text', 0, true, null)
            ->field('post_meta', 'text', 0, true, null)
            ->field('post_words', 'text', 0, true, null)
            ->field('post_status', 'smallint', 0, false, 0)
            ->field('post_firstpub', 'smallint', 0, false, 0)
            ->field('post_selected', 'smallint', 0, false, 0)
            ->field('post_position', 'integer', 0, false, 0)
            ->field('post_open_comment', 'smallint', 0, false, 0)
            ->field('post_open_tb', 'smallint', 0, false, 0)
            ->field('nb_comment', 'integer', 0, false, 0)
            ->field('nb_trackback', 'integer', 0, false, 0)

            ->primary('pk_post', 'post_id')

            ->unique('uk_post_url', 'post_url', 'post_type', 'blog_id')
        ;

        $_s->table('media')
            ->field('media_id', 'bigint', 0, false)
            ->field('user_id', 'varchar', 32, false)
            ->field('media_path', 'varchar', 255, false)
            ->field('media_title', 'varchar', 255, false)
            ->field('media_file', 'varchar', 255, false)
            ->field('media_dir', 'varchar', 255, false, "'.'")
            ->field('media_meta', 'text', 0, true, null)
            ->field('media_dt', 'timestamp', 0, false, 'now()')
            ->field('media_creadt', 'timestamp', 0, false, 'now()')
            ->field('media_upddt', 'timestamp', 0, false, 'now()')
            ->field('media_private', 'smallint', 0, false, 0)

            ->primary('pk_media', 'media_id')
        ;

        $_s->table('post_media')
            ->field('media_id', 'bigint', 0, false)
            ->field('post_id', 'bigint', 0, false)
            ->field('link_type', 'varchar', 32, false, "'attachment'")

            ->primary('pk_post_media', 'media_id', 'post_id', 'link_type')
        ;

        $_s->table('log')
            ->field('log_id', 'bigint', 0, false)
            ->field('user_id', 'varchar', 32, true)
            ->field('blog_id', 'varchar', 32, true)
            ->field('log_table', 'varchar', 255, false)
            ->field('log_dt', 'timestamp', 0, false, 'now()')
            ->field('log_ip', 'varchar', 39, false)
            ->field('log_msg', 'text', 0, true, null)

            ->primary('pk_log', 'log_id')
        ;

        $_s->table('version')
            ->field('module', 'varchar', 64, false)
            ->field('version', 'varchar', 32, false)

            ->primary('pk_version', 'module')
        ;

        $_s->table('ping')
            ->field('post_id', 'bigint', 0, false)
            ->field('ping_url', 'varchar', 255, false)
            ->field('ping_dt', 'timestamp', 0, false, 'now()')

            ->primary('pk_ping', 'post_id', 'ping_url')
        ;

        $_s->table('comment')
            ->field('comment_id', 'bigint', 0, false)
            ->field('post_id', 'bigint', 0, false)
            ->field('comment_dt', 'timestamp', 0, false, 'now()')
            ->field('comment_upddt', 'timestamp', 0, false, 'now()')
            ->field('comment_author', 'varchar', 255, true, null)
            ->field('comment_email', 'varchar', 255, true, null)
            ->field('comment_site', 'varchar', 255, true, null)
            ->field('comment_content', 'text', 0, true)
            ->field('comment_words', 'text', 0, true, null)
            ->field('comment_ip', 'varchar', 39, true, null)
            ->field('comment_status', 'smallint', 0, true, 0)
            ->field('comment_trackback', 'smallint', 0, false, 0)

            ->primary('pk_comment', 'comment_id')
        ;

        $_s->table('meta')
            ->field('meta_id', 'varchar', 255, false)
            ->field('meta_type', 'varchar', 64, false)
            ->field('post_id', 'bigint', 0, false)

            ->primary('pk_meta', 'meta_id', 'meta_type', 'post_id')
        ;

        $_s->table('pref')
            ->field('pref_id', 'varchar', 255, false)
            ->field('user_id', 'varchar', 32, true)
            ->field('pref_ws', 'varchar', 32, false, "'system'")
            ->field('pref_value', 'text', 0, true, null)
            ->field('pref_type', 'varchar', 8, false, "'string'")
            ->field('pref_label', 'text', 0, true)

            ->unique('uk_pref', 'pref_ws', 'pref_id', 'user_id')
        ;

        $_s->table('notice')
            ->field('notice_id', 'bigint', 0, false)
            ->field('ses_id', 'varchar', 40, false)
            ->field('notice_type', 'varchar', 32, true)
            ->field('notice_ts', 'timestamp', 0, false, 'now()')
            ->field('notice_msg', 'text', 0, true, null)
            ->field('notice_format', 'varchar', 32, true, "'text'")
            ->field('notice_options', 'text', 0, true, null)

            ->primary('pk_notice', 'notice_id')
        ;

        /* References indexes
        -------------------------------------------------------- */
        $_s->table('category')->index('idx_category_blog_id', 'btree', 'blog_id');
        $_s->table('category')->index('idx_category_cat_lft_blog_id', 'btree', 'blog_id', 'cat_lft');
        $_s->table('category')->index('idx_category_cat_rgt_blog_id', 'btree', 'blog_id', 'cat_rgt');
        $_s->table('setting')->index('idx_setting_blog_id', 'btree', 'blog_id');
        $_s->table('user')->index('idx_user_user_default_blog', 'btree', 'user_default_blog');
        $_s->table('permissions')->index('idx_permissions_blog_id', 'btree', 'blog_id');
        $_s->table('post')->index('idx_post_cat_id', 'btree', 'cat_id');
        $_s->table('post')->index('idx_post_user_id', 'btree', 'user_id');
        $_s->table('post')->index('idx_post_blog_id', 'btree', 'blog_id');
        $_s->table('media')->index('idx_media_user_id', 'btree', 'user_id');
        $_s->table('post_media')->index('idx_post_media_post_id', 'btree', 'post_id');
        $_s->table('post_media')->index('idx_post_media_media_id', 'btree', 'media_id');
        $_s->table('log')->index('idx_log_user_id', 'btree', 'user_id');
        $_s->table('comment')->index('idx_comment_post_id', 'btree', 'post_id');
        $_s->table('meta')->index('idx_meta_post_id', 'btree', 'post_id');
        $_s->table('meta')->index('idx_meta_meta_type', 'btree', 'meta_type');
        $_s->table('pref')->index('idx_pref_user_id', 'btree', 'user_id');

        /* Performance indexes
        -------------------------------------------------------- */
        $_s->table('comment')->index('idx_comment_post_id_dt_status', 'btree', 'post_id', 'comment_dt', 'comment_status');
        $_s->table('post')->index('idx_post_post_dt', 'btree', 'post_dt');
        $_s->table('post')->index('idx_post_post_dt_post_id', 'btree', 'post_dt', 'post_id');
        $_s->table('post')->index('idx_blog_post_post_dt_post_id', 'btree', 'blog_id', 'post_dt', 'post_id');
        $_s->table('post')->index('idx_blog_post_post_status', 'btree', 'blog_id', 'post_status');
        $_s->table('blog')->index('idx_blog_blog_upddt', 'btree', 'blog_upddt');
        $_s->table('user')->index('idx_user_user_super', 'btree', 'user_super');

        /* Foreign keys
        -------------------------------------------------------- */
        $_s->table('category')->reference('fk_category_blog', 'blog_id', 'blog', 'blog_id', 'cascade', 'cascade');
        $_s->table('setting')->reference('fk_setting_blog', 'blog_id', 'blog', 'blog_id', 'cascade', 'cascade');
        $_s->table('user')->reference('fk_user_default_blog', 'user_default_blog', 'blog', 'blog_id', 'cascade', 'set null');
        $_s->table('permissions')->reference('fk_permissions_blog', 'blog_id', 'blog', 'blog_id', 'cascade', 'cascade');
        $_s->table('permissions')->reference('fk_permissions_user', 'user_id', 'user', 'user_id', 'cascade', 'cascade');
        $_s->table('post')->reference('fk_post_category', 'cat_id', 'category', 'cat_id', 'cascade', 'set null');
        $_s->table('post')->reference('fk_post_user', 'user_id', 'user', 'user_id', 'cascade', 'cascade');
        $_s->table('post')->reference('fk_post_blog', 'blog_id', 'blog', 'blog_id', 'cascade', 'cascade');
        $_s->table('media')->reference('fk_media_user', 'user_id', 'user', 'user_id', 'cascade', 'cascade');
        $_s->table('post_media')->reference('fk_media', 'media_id', 'media', 'media_id', 'cascade', 'cascade');
        $_s->table('post_media')->reference('fk_media_post', 'post_id', 'post', 'post_id', 'cascade', 'cascade');
        $_s->table('ping')->reference('fk_ping_post', 'post_id', 'post', 'post_id', 'cascade', 'cascade');
        $_s->table('comment')->reference('fk_comment_post', 'post_id', 'post', 'post_id', 'cascade', 'cascade');
        $_s->table('log')->reference('fk_log_blog', 'blog_id', 'blog', 'blog_id', 'cascade', 'set null');
        $_s->table('meta')->reference('fk_meta_post', 'post_id', 'post', 'post_id', 'cascade', 'cascade');
        $_s->table('pref')->reference('fk_pref_user', 'user_id', 'user', 'user_id', 'cascade', 'cascade');
        $_s->table('notice')->reference('fk_notice_session', 'ses_id', 'session', 'ses_id', 'cascade', 'cascade');

        /* PostgreSQL specific indexes
        -------------------------------------------------------- */
        if ('pgsql' == $_s->driver()) {
            $_s->table('setting')->index('idx_setting_blog_id_null', 'btree', '(blog_id IS NULL)');
            $_s->table('media')->index('idx_media_media_path', 'btree', 'media_path', 'media_dir');
            $_s->table('pref')->index('idx_pref_user_id_null', 'btree', '(user_id IS NULL)');
        }
    }

    public static function checkRequirements(AbstractConnection $con, Strings $err): bool
    {
        if (version_compare(phpversion(), '8.1', '<')) {
            $err->add(sprintf(__('PHP version is %s (%s or earlier needed).'), phpversion(), '8.1'));
        }

        if (!function_exists('mb_detect_encoding')) {
            $err->add(__('Multibyte string module (mbstring) is not available.'));
        }

        if (!function_exists('iconv')) {
            $err->add(__('Iconv module is not available.'));
        }

        if (!function_exists('ob_start')) {
            $err->add(__('Output control functions are not available.'));
        }

        if (!function_exists('simplexml_load_string')) {
            $err->add(__('SimpleXML module is not available.'));
        }

        if (!function_exists('dom_import_simplexml')) {
            $err->add(__('DOM XML module is not available.'));
        }

        $pcre_str = base64_decode('w6nDqMOgw6o=');
        if (!@preg_match('/' . $pcre_str . '/u', $pcre_str)) {
            $err->add(__('PCRE engine does not support UTF-8 strings.'));
        }

        if (!function_exists('spl_classes')) {
            $err->add(__('SPL module is not available.'));
        }

        if ($con->syntax() == 'mysql') {
            if (version_compare($con->version(), '4.1', '<')) {
                $err->add(sprintf(__('MySQL version is %s (4.1 or earlier needed).'), $con->version()));
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
                    $err->add(__('MySQL InnoDB engine is not available.'));
                }
            }
        } elseif ($con->driver() == 'pgsql') {
            if (version_compare($con->version(), '8.0', '<')) {
                $err->add(sprintf(__('PostgreSQL version is %s (8.0 or earlier needed).'), $con->version()));
            }
        }

        return $err->count() == 0;
    }

    /**
     * Get blog default settings.
     *
     * Creates default settings for active blog. Optionnal parameter
     * <var>defaults</var> replaces default params while needed.
     *
     * @param array $defaults The defaults settings
     */
    public static function setBlogDefaultSettings(array $defaults = null): void
    {
        if (!is_array($defaults)) {
            $defaults = [
                ['allow_comments', 'boolean', true,
                    'Allow comments on blog', ],
                ['allow_trackbacks', 'boolean', true,
                    'Allow trackbacks on blog', ],
                ['blog_timezone', 'string', 'Europe/London',
                    'Blog timezone', ],
                ['comments_nofollow', 'boolean', true,
                    'Add rel="nofollow" to comments URLs', ],
                ['comments_pub', 'boolean', true,
                    'Publish comments immediately', ],
                ['comments_ttl', 'integer', 0,
                    'Number of days to keep comments open (0 means no ttl)', ],
                ['copyright_notice', 'string', '', 'Copyright notice (simple text)'],
                ['date_format', 'string', '%A, %B %e %Y',
                    'Date format. See PHP strftime function for patterns', ],
                ['editor', 'string', '',
                    'Person responsible of the content', ],
                ['enable_html_filter', 'boolean', 0,
                    'Enable HTML filter', ],
                ['enable_xmlrpc', 'boolean', 0,
                    'Enable XML/RPC interface', ],
                ['lang', 'string', 'en',
                    'Default blog language', ],
                ['media_exclusion', 'string', '/\.(phps?|pht(ml)?|phl|.?html?|xml|js|htaccess)[0-9]*$/i',
                    'File name exclusion pattern in media manager. (PCRE value)', ],
                ['media_img_m_size', 'integer', 448,
                    'Image medium size in media manager', ],
                ['media_img_s_size', 'integer', 240,
                    'Image small size in media manager', ],
                ['media_img_t_size', 'integer', 100,
                    'Image thumbnail size in media manager', ],
                ['media_img_title_pattern', 'string', 'Title ;; Date(%b %Y) ;; separator(, )',
                    'Pattern to set image title when you insert it in a post', ],
                ['media_video_width', 'integer', 400,
                    'Video width in media manager', ],
                ['media_video_height', 'integer', 300,
                    'Video height in media manager', ],
                ['module_plugin_dir', 'string', '',
                    'Blog exclusive plugins path', ],
                ['module_theme_dir', 'string', '',
                    'Blog exclusive themes path', ],
                ['nb_post_for_home', 'integer', 20,
                    'Number of entries on first home page', ],
                ['nb_post_per_page', 'integer', 20,
                    'Number of entries on home pages and category pages', ],
                ['nb_post_per_feed', 'integer', 20,
                    'Number of entries on feeds', ],
                ['nb_comment_per_feed', 'integer', 20,
                    'Number of comments on feeds', ],
                ['post_url_format', 'string', '{y}/{m}/{d}/{t}',
                    'Post URL format. {y}: year, {m}: month, {d}: day, {id}: post id, {t}: entry title', ],
                ['public_path', 'string', 'public',
                    'Path to public directory, begins with a / for a full system path', ],
                ['robots_policy', 'string', 'INDEX,FOLLOW',
                    'Search engines robots policy', ],
                ['short_feed_items', 'boolean', false,
                    'Display short feed items', ],
                ['theme', 'string', 'Berlin',
                    'Blog theme', ],
                ['time_format', 'string', '%H:%M',
                    'Time format. See PHP strftime function for patterns', ],
                ['tpl_allow_php', 'boolean', false,
                    'Allow PHP code in templates', ],
                ['tpl_use_cache', 'boolean', true,
                    'Use template caching', ],
                ['trackbacks_pub', 'boolean', true,
                    'Publish trackbacks immediately', ],
                ['trackbacks_ttl', 'integer', 0,
                    'Number of days to keep trackbacks open (0 means no ttl)', ],
                ['url_scan', 'string', 'query_string',
                    'URL handle mode (path_info or query_string)', ],
                ['use_smilies', 'boolean', false,
                    'Show smilies on entries and comments', ],
                ['no_search', 'boolean', false,
                    'Disable search', ],
                ['inc_subcats', 'boolean', false,
                    'Include sub-categories in category page and category posts feed', ],
                ['wiki_comments', 'boolean', false,
                    'Allow commenters to use a subset of wiki syntax', ],
                ['import_feed_url_control', 'boolean', true,
                    'Control feed URL before import', ],
                ['import_feed_no_private_ip', 'boolean', true,
                    'Prevent import feed from private IP', ],
                ['import_feed_ip_regexp', 'string', '',
                    'Authorize import feed only from this IP regexp', ],
                ['import_feed_port_regexp', 'string', '/^(80|443)$/',
                    'Authorize import feed only from this port regexp', ],
                ['jquery_needed', 'boolean', true,
                    'Load jQuery library', ],
            ];
        }

        $settings = new Settings(blog: null);

        foreach ($defaults as $v) {
            $settings->getGroup('system')->putSetting($v[0], $v[2], $v[1], $v[3], false, true);
        }
    }
}
