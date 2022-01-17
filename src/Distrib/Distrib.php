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

use Dotclear\Core\Exception as Exception;

use Dotclear\Database\Connection;
use Dotclear\Database\Structure;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

class Distrib
{
    public static function getConfigFile(): string
    {
        $file = implode(DIRECTORY_SEPARATOR, [dirname(__FILE__), 'config.php.distrib']);
        if (!is_file($file)) {
            throw new Exception(sprintf(__('File %s does not exist.'), $file));
        }

        return file_get_contents($file);
    }

    public static function getCoreConstants(): void
    {
        # Dev
        //*== DOTCLEAR_MODE_DEBUG ==
        if (!defined('DOTCLEAR_MODE_DEBUG')) {
            define('DOTCLEAR_MODE_DEBUG', true);
        }
        if (DOTCLEAR_MODE_DEBUG) { // @phpstan-ignore-line
            ini_set('display_errors', '1');
            error_reporting(E_ALL | E_STRICT);
        }
        //*/

        if (!defined('DOTCLEAR_MODE_DEBUG')) {
            define('DOTCLEAR_MODE_DEBUG', false);
        }

        if (!defined('DOTCLEAR_MODE_DEV')) {
            define('DOTCLEAR_MODE_DEV', false);
        }

        # Core
        define('DOTCLEAR_CORE_VERSION',
            trim(file_get_contents(DOTCLEAR_ROOT_DIR . DIRECTORY_SEPARATOR . 'version'))
        );

        define('DOTCLEAR_CORE_VERSION_BREAK',
            '3.0'
        );

        if (!defined('DOTCLEAR_CORE_UPDATE_URL')) {
            define('DOTCLEAR_CORE_UPDATE_URL',
                'https://download.dotclear.org/versions.xml'
            );
        }

        if (!defined('DOTCLEAR_CORE_UPDATE_CHANNEL')) {
            define('DOTCLEAR_CORE_UPDATE_CHANNEL',
                'stable'
            );
        }

        if (!defined('DOTCLEAR_CORE_UPDATE_NOAUTO')) {
            define('DOTCLEAR_CORE_UPDATE_NOAUTO',
                false
            );
        }

        if (!defined('DOTCLEAR_OTHER_DIR')) {
            define('DOTCLEAR_OTHER_DIR',
                implode(DIRECTORY_SEPARATOR, [DOTCLEAR_ROOT_DIR, '..'])
            );
        }

        if (!defined('DOTCLEAR_CACHE_DIR')) {
            define('DOTCLEAR_CACHE_DIR',
                implode(DIRECTORY_SEPARATOR, [DOTCLEAR_OTHER_DIR, 'cache'])
            );
        }

        if (!defined('DOTCLEAR_VAR_DIR')) {
            define('DOTCLEAR_VAR_DIR',
                implode(DIRECTORY_SEPARATOR, [DOTCLEAR_OTHER_DIR, 'var'])
            );
        }

        if (!defined('DOTCLEAR_DIGESTS_DIR')) {
            define('DOTCLEAR_DIGESTS_DIR',
                implode(DIRECTORY_SEPARATOR, [DOTCLEAR_OTHER_DIR, 'digests'])
            );
        }

        # Modules
        define('DOTCLEAR_PLUGIN_OFFICIAL',
            'AboutConfig,Akismet,Antispam,Attachments,Blogroll,BlowupConfig,Dclegacy,FairTrackbacks,ImportExport,Maintenance,Pages,Pings,SimpleMenu,Tags,ThemeEditor,UserPref,Widgets,LegacyEditor,CKEditor,Breadcrumb'
        );

        define('DOTCLEAR_THEME_OFFICIAL',
            'Berlin,BlueSilence,BlowupConfig,CustomCSS,Default,Ductile'
        );

        define('DOTCLEAR_ICONSET_OFFICIAL',
            'Legacy,ThomasDaveluy'
        );

        if (!defined('DOTCLEAR_PLUGIN_DIR')) {
            define('DOTCLEAR_PLUGIN_DIR',
                implode(DIRECTORY_SEPARATOR, [DOTCLEAR_ROOT_DIR, 'Plugin'])
            );
        }

        if (!defined('DOTCLEAR_THEME_DIR')) {
            define('DOTCLEAR_THEME_DIR',
                implode(DIRECTORY_SEPARATOR, [DOTCLEAR_ROOT_DIR, 'Theme'])
            );
        }

        if (!defined('DOTCLEAR_ICONSET_DIR')) {
            define('DOTCLEAR_ICONSET_DIR',
                implode(DIRECTORY_SEPARATOR, [DOTCLEAR_ROOT_DIR, 'Iconset'])
            );
        }

        if (!defined('DOTCLEAR_L10N_DIR')) {
            define('DOTCLEAR_L10N_DIR',
                implode(DIRECTORY_SEPARATOR, [DOTCLEAR_ROOT_DIR, 'locales'])
            );
        }

        if (!defined('DOTCLEAR_PLUGIN_UPDATE_URL')) {
            define('DOTCLEAR_PLUGIN_UPDATE_URL',
                'https://update.dotaddict.org/dc2/themes.xml'
            );
        }

        if (!defined('DOTCLEAR_THEME_UPDATE_URL')) {
            define('DOTCLEAR_THEME_UPDATE_URL',
                'https://update.dotaddict.org/dc2/plugins.xml'
            );
        }

        if (!defined('DOTCLEAR_ICONSET_UPDATE_URL')) {
            define('DOTCLEAR_ICONSET_UPDATE_URL',
                ''
            );
        }

        if (!defined('DOTCLEAR_L10N_UPDATE_URL')) {
            define('DOTCLEAR_L10N_UPDATE_URL',
                'https://services.dotclear.net/dc2.l10n/?version=%s'
            );
        }

        if (!defined('DOTCLEAR_MODULES_ALLOWMULTI')) {
            define('DOTCLEAR_MODULES_ALLOWMULTI',
                false
            );
        }

        if (!defined('DOTCLEAR_STORE_UPDATE_NOAUTO')) {
            define('DOTCLEAR_STORE_UPDATE_NOAUTO',
                false
            );
        }

        if (!defined('DOTCLEAR_STORE_ALLOWREPO')) {
            define('DOTCLEAR_STORE_ALLOWREPO',
                true
            );
        }

        # Diverse
        if (!defined('DOTCLEAR_TEMPLATE_DEFAULT')) {
            define('DOTCLEAR_TEMPLATE_DEFAULT',
                'mustek'
            );
        }

        if (!defined('DOTCLEAR_JQUERY_DEFAULT')) {
            define('DOTCLEAR_JQUERY_DEFAULT',
                '3.6.0'
            );
        }

        if (!defined('DOTCLEAR_PHP_NEXT_REQUIRED')) {
            define('DOTCLEAR_PHP_NEXT_REQUIRED',
                '7.4'
            );
        }

        if (!defined('DOTCLEAR_VENDOR_NAME')) {
            define('DOTCLEAR_VENDOR_NAME',
                'Dotclear'
            );
        }

        if (!defined('DOTCLEAR_XMLRPC_URL')) {
            define('DOTCLEAR_XMLRPC_URL',
                '%1$sxmlrpc/%2$s'
            );
        }

        if (!defined('DOTCLEAR_ADMIN_SSL')) {
            define('DOTCLEAR_ADMIN_SSL',
                true
            );
        }

        if (!defined('DOTCLEAR_FORCE_SCHEME_443')) {
            define('DOTCLEAR_FORCE_SCHEME_443',
                true
            );
        }

        if (!defined('DOTCLEAR_REVERSE_PROXY')) {
            define('DOTCLEAR_REVERSE_PROXY',
                true
            );
        }

        if (!defined('DOTCLEAR_DATABASE_PERSIST')) {
            define('DOTCLEAR_DATABASE_PERSIST',
                false
            );
        }

        if (!defined('DOTCLEAR_SESSION_NAME')) {
            define('DOTCLEAR_SESSION_NAME',
                'dcxd'
            );
        }

        if (!defined('DOTCLEAR_SESSION_TTL')) {
            define('DOTCLEAR_SESSION_TTL',
                null
            );
        }

        if (!defined('DOTCLEAR_QUERY_TIMEOUT')) {
            define('DOTCLEAR_QUERY_TIMEOUT',
                4
            );
        }

        if (!defined('DOTCLEAR_CRYPT_ALGO')) {
            define('DOTCLEAR_CRYPT_ALGO',
                'sha1'
            );
        }
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
            ->comment_spam_status('varchar', 128, true, 0)
            ->comment_spam_filter('varchar', 32, true, null)
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
}
