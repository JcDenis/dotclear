<?php
/**
 * @class Dotclear\Distrib\Upgrade
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

use Dotclear\Exception;
use Dotclear\Exception\DistribException;

use Dotclear\File\Files;
use Dotclear\File\Path;

use Dotclear\Database\Structure;
use Dotclear\Database\Schema;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

if (!defined('DOTCLEAR_OLD_ROOT_DIR')) {
    define('DOTCLEAR_OLD_ROOT_DIR', DOTCLEAR_ROOT_DIR . '/../');
}

class Upgrade
{
    public static function dotclearUpgrade(): bool
    {
        $upgrade = new Upgrade();

        return $upgrade->doUpgrade();
    }

    protected function doUpgrade()
    {
        $version = dcCore()->getVersion('core');

        if ($version === null) {
            return false;
        }

        if (version_compare($version, DOTCLEAR_CORE_VERSION, '<') == 1 || strpos(DOTCLEAR_CORE_VERSION, 'dev')) {
            try {
                if (dcCore()->con->driver() == 'sqlite') {
                    return false; // Need to find a way to upgrade sqlite database
                }

                # Database upgrade
                $_s = new Structure(dcCore()->con, dcCore()->prefix);
                Distrib::getDatabaseStructure($_s);

                $si      = new Structure(dcCore()->con, dcCore()->prefix);
                $changes = $si->synchronize($_s);

                /* Some other upgrades
                ------------------------------------ */
                $cleanup_sessions = $this->growUp($version);

                # Drop content from session table if changes or if needed
                if ($changes != 0 || $cleanup_sessions) {
                    dcCore()->con->execute('DELETE FROM ' . dcCore()->prefix . 'session ');
                }

                # Empty templates cache directory
                try {
                    dcCore()->emptyTemplatesCache();
                } catch (Exception $e) {
                }

                return (bool) $changes;
            } catch (Exception $e) {
                throw new DistribException(__('Something went wrong with auto upgrade:') .
                    ' ' . $e->getMessage());
            }
        }

        # No upgrade?
        return false;
    }

    public function growUp(?string $version): bool
    {
        if ($version === null) {
            return false;
        }

        $cleanup_sessions = false; // update it in a step that needed sessions to be removed

        # Populate media_dir field (since 2.0-beta3.3)
        if (version_compare($version, '2.0-beta3.3', '<')) {
            $strReq = 'SELECT media_id, media_file FROM ' . dcCore()->prefix . 'media ';
            $rs_m   = dcCore()->con->select($strReq);
            while ($rs_m->fetch()) {
                $cur            = dcCore()->con->openCursor(dcCore()->prefix . 'media');
                $cur->media_dir = dirname($rs_m->media_file);
                $cur->update('WHERE media_id = ' . (int) $rs_m->media_id);
            }
        }

        if (version_compare($version, '2.0-beta7.3', '<')) {
            # Blowup becomes default theme
            $strReq = 'UPDATE ' . dcCore()->prefix . 'setting ' .
                "SET setting_value = '%s' " .
                "WHERE setting_id = 'theme' " .
                "AND setting_value = '%s' " .
                'AND blog_id IS NOT NULL ';
            dcCore()->con->execute(sprintf($strReq, 'blueSilence', 'default'));
            dcCore()->con->execute(sprintf($strReq, 'default', 'Blowup'));
        }

        if (version_compare($version, '2.1-alpha2-r2383', '<')) {
            $schema = Schema::init(dcCore()->con);
            $schema->dropUnique(dcCore()->prefix . 'category', dcCore()->prefix . 'uk_cat_title');

            # Reindex categories
            $rs = dcCore()->con->select(
                'SELECT cat_id, cat_title, blog_id ' .
                'FROM ' . dcCore()->prefix . 'category ' .
                'ORDER BY blog_id ASC , cat_position ASC '
            );
            $cat_blog = $rs->blog_id;
            $i        = 2;
            while ($rs->fetch()) {
                if ($cat_blog != $rs->blog_id) {
                    $i = 2;
                }
                dcCore()->con->execute(
                    'UPDATE ' . dcCore()->prefix . 'category SET '
                    . 'cat_lft = ' . ($i++) . ', cat_rgt = ' . ($i++) . ' ' .
                    'WHERE cat_id = ' . (int) $rs->cat_id
                );
                $cat_blog = $rs->blog_id;
            }
        }

        if (version_compare($version, '2.1.6', '<=')) {
            # ie7js has been upgraded
            $ie7files = [
                'ie7-base64.php ',
                'ie7-content.htc',
                'ie7-core.js',
                'ie7-css2-selectors.js',
                'ie7-css3-selectors.js',
                'ie7-css-strict.js',
                'ie7-dhtml.js',
                'ie7-dynamic-attributes.js',
                'ie7-fixed.js',
                'ie7-graphics.js',
                'ie7-html4.js',
                'ie7-ie5.js',
                'ie7-layout.js',
                'ie7-load.htc',
                'ie7-object.htc',
                'ie7-overflow.js',
                'ie7-quirks.js',
                'ie7-server.css',
                'ie7-standard-p.js',
                'ie7-xml-extras.js',
            ];
            foreach ($ie7files as $f) {
                @unlink(DOTCLEAR_OLD_ROOT_DIR . '/admin/js/ie7/' . $f);
            }
        }

        if (version_compare($version, '2.2-alpha1-r3043', '<')) {
            # metadata has been integrated to the core.
            dcCore()->plugins->loadModules(DOTCLEAR_PLUGINS_DIR);
            if (dcCore()->plugins->moduleExists('metadata')) {
                dcCore()->plugins->deleteModule('metadata');
            }

            # Tags template class has been renamed
            $sqlstr = 'SELECT blog_id, setting_id, setting_value ' .
            'FROM ' . dcCore()->prefix . 'setting ' .
                'WHERE (setting_id = \'widgets_nav\' OR setting_id = \'widgets_extra\') ' .
                'AND setting_ns = \'widgets\';';
            $rs = dcCore()->con->select($sqlstr);
            while ($rs->fetch()) {
                $widgetsettings     = base64_decode($rs->setting_value);
                $widgetsettings     = str_replace('s:11:"tplMetadata"', 's:7:"tplTags"', $widgetsettings);
                $cur                = dcCore()->con->openCursor(dcCore()->prefix . 'setting');
                $cur->setting_value = base64_encode($widgetsettings);
                $sqlstr             = 'WHERE setting_id = \'' . $rs->setting_id . '\' AND setting_ns = \'widgets\' ' .
                    'AND blog_id ' .
                    ($rs->blog_id == null ? 'is NULL' : '= \'' . dcCore()->con->escape($rs->blog_id) . '\'');
                $cur->update($sqlstr);
            }
        }

        if (version_compare($version, '2.3', '<')) {
            # Add global favorites
            $init_fav = [];

            $init_fav['new_post'] = ['new_post', 'New entry', 'post.php',
                'images/menu/edit.png', 'images/menu/edit-b.png',
                'usage,contentadmin', null, null, ];
            $init_fav['newpage'] = ['newpage', 'New page', 'plugin.php?p=pages&amp;act=page',
                'index.php?mf=Plugin/pages/icon-np.png', 'index.php?mf=Plugin/pages/icon-np-big.png',
                'contentadmin,pages', null, null, ];
            $init_fav['media'] = ['media', 'Media manager', 'media.php',
                'images/menu/media.png', 'images/menu/media-b.png',
                'media,media_admin', null, null, ];
            $init_fav['widgets'] = ['widgets', 'Presentation widgets', 'plugin.php?p=widgets',
                'index.php?mf=Plugin/widgets/icon.png', 'index.php?mf=Plugin/widgets/icon-big.png',
                'admin', null, null, ];
            $init_fav['blog_theme'] = ['blog_theme', 'Blog appearance', 'blog_theme.php',
                'images/menu/themes.png', 'images/menu/blog-theme-b.png',
                'admin', null, null, ];

            $count = 0;
            foreach ($init_fav as $k => $f) {
                $t = ['name'     => $f[0], 'title' => $f[1], 'url' => $f[2], 'small-icon' => $f[3],
                    'large-icon' => $f[4], 'permissions' => $f[5], 'id' => $f[6], 'class' => $f[7], ];
                $sqlstr = 'INSERT INTO ' . dcCore()->prefix . 'pref (pref_id, user_id, pref_ws, pref_value, pref_type, pref_label) VALUES (' .
                '\'' . sprintf('g%03s', $count) . '\',NULL,\'favorites\',\'' . serialize($t) . '\',\'string\',NULL);';
                dcCore()->con->execute($sqlstr);
                $count++;
            }

            # A bit of housecleaning for no longer needed files
            $remfiles = [
                'admin/style/cat-bg.png',
                'admin/style/footer-bg.png',
                'admin/style/head-logo.png',
                'admin/style/tab-bg.png',
                'admin/style/tab-c-l.png',
                'admin/style/tab-c-r.png',
                'admin/style/tab-l-l.png',
                'admin/style/tab-l-r.png',
                'admin/style/tab-n-l.png',
                'admin/style/tab-n-r.png',
                'inc/clearbricks/_common.php',
                'inc/clearbricks/common/lib.crypt.php',
                'inc/clearbricks/common/lib.date.php',
                'inc/clearbricks/common/lib.files.php',
                'inc/clearbricks/common/lib.form.php',
                'inc/clearbricks/common/lib.html.php',
                'inc/clearbricks/common/lib.http.php',
                'inc/clearbricks/common/lib.l10n.php',
                'inc/clearbricks/common/lib.text.php',
                'inc/clearbricks/common/tz.dat',
                'inc/clearbricks/common/_main.php',
                'inc/clearbricks/dblayer/class.cursor.php',
                'inc/clearbricks/dblayer/class.mysql.php',
                'inc/clearbricks/dblayer/class.pgsql.php',
                'inc/clearbricks/dblayer/class.sqlite.php',
                'inc/clearbricks/dblayer/dblayer.php',
                'inc/clearbricks/Schema/class.Schema.php',
                'inc/clearbricks/Schema/class.Structure.php',
                'inc/clearbricks/Schema/class.mysql.Schema.php',
                'inc/clearbricks/Schema/class.pgsql.Schema.php',
                'inc/clearbricks/Schema/class.sqlite.Schema.php',
                'inc/clearbricks/diff/lib.diff.php',
                'inc/clearbricks/diff/lib.unified.diff.php',
                'inc/clearbricks/filemanager/class.filemanager.php',
                'inc/clearbricks/html.filter/class.html.filter.php',
                'inc/clearbricks/html.validator/class.html.validator.php',
                'inc/clearbricks/image/class.image.meta.php',
                'inc/clearbricks/image/class.image.tools.php',
                'inc/clearbricks/mail/class.mail.php',
                'inc/clearbricks/mail/class.socket.mail.php',
                'inc/clearbricks/net/class.net.socket.php',
                'inc/clearbricks/net.http/class.net.http.php',
                'inc/clearbricks/net.http.feed/class.feed.parser.php',
                'inc/clearbricks/net.http.feed/class.feed.reader.php',
                'inc/clearbricks/net.xmlrpc/class.net.xmlrpc.php',
                'inc/clearbricks/pager/class.pager.php',
                'inc/clearbricks/rest/class.rest.php',
                'inc/clearbricks/session.db/class.session.db.php',
                'inc/clearbricks/template/class.template.php',
                'inc/clearbricks/text.wiki2xhtml/class.wiki2xhtml.php',
                'inc/clearbricks/url.handler/class.url.handler.php',
                'inc/clearbricks/zip/class.unzip.php',
                'inc/clearbricks/zip/class.zip.php',
                'themes/default/tpl/.htaccess',
                'themes/default/tpl/404.html',
                'themes/default/tpl/archive.html',
                'themes/default/tpl/archive_month.html',
                'themes/default/tpl/category.html',
                'themes/default/tpl/home.html',
                'themes/default/tpl/post.html',
                'themes/default/tpl/search.html',
                'themes/default/tpl/tag.html',
                'themes/default/tpl/tags.html',
                'themes/default/tpl/user_head.html',
                'themes/default/tpl/_flv_player.html',
                'themes/default/tpl/_footer.html',
                'themes/default/tpl/_head.html',
                'themes/default/tpl/_mp3_player.html',
                'themes/default/tpl/_top.html',
            ];
            $remfolders = [
                'inc/clearbricks/common',
                'inc/clearbricks/dblayer',
                'inc/clearbricks/Schema',
                'inc/clearbricks/diff',
                'inc/clearbricks/filemanager',
                'inc/clearbricks/html.filter',
                'inc/clearbricks/html.validator',
                'inc/clearbricks/image',
                'inc/clearbricks/mail',
                'inc/clearbricks/net',
                'inc/clearbricks/net.http',
                'inc/clearbricks/net.http.feed',
                'inc/clearbricks/net.xmlrpc',
                'inc/clearbricks/pager',
                'inc/clearbricks/rest',
                'inc/clearbricks/session.db',
                'inc/clearbricks/template',
                'inc/clearbricks/text.wiki2xhtml',
                'inc/clearbricks/url.handler',
                'inc/clearbricks/zip',
                'inc/clearbricks',
                'themes/default/tpl',
            ];

            foreach ($remfiles as $f) {
                @unlink(DOTCLEAR_OLD_ROOT_DIR . '/' . $f);
            }
            foreach ($remfolders as $f) {
                @rmdir(DOTCLEAR_OLD_ROOT_DIR . '/' . $f);
            }
        }

        if (version_compare($version, '2.3.1', '<')) {
            # Remove unecessary file
            @unlink(DOTCLEAR_OLD_ROOT_DIR . '/' . 'inc/libs/clearbricks/.hgignore');
        }

        if (version_compare($version, '2.5', '<=')) {
            # Try to disable daInstaller plugin if it has been installed outside the default plugins directory
            $path    = explode(PATH_SEPARATOR, DOTCLEAR_PLUGINS_DIR);
            $default = Path::real(DOTCLEAR_OLD_ROOT_DIR . '/plugins/');
            foreach ($path as $root) {
                if (!is_dir($root) || !is_readable($root)) {
                    continue;
                }
                if (substr($root, -1) != '/') {
                    $root .= '/';
                }
                if (($p = @dir($root)) === false) {
                    continue;
                }
                if (Path::real($root) == $default) {
                    continue;
                }
                if (($d = @dir($root . 'daInstaller')) === false) {
                    continue;
                }
                $f = $root . '/daInstaller/_disabled';
                if (!file_exists($f)) {
                    @file_put_contents($f, '');
                }
            }
        }

        if (version_compare($version, '2.5.1', '<=')) {
            // Flash enhanced upload no longer needed
            @unlink(DOTCLEAR_OLD_ROOT_DIR . '/' . 'inc/swf/swfupload.swf');
        }

        if (version_compare($version, '2.6', '<=')) {
            // README has been replaced by README.md and CONTRIBUTING.md
            @unlink(DOTCLEAR_OLD_ROOT_DIR . '/' . 'README');

            // trackbacks are now merged into posts
            @unlink(DOTCLEAR_OLD_ROOT_DIR . '/' . 'admin/trackbacks.php');

            # daInstaller has been integrated to the core.
            # Try to remove it
            $path = explode(PATH_SEPARATOR, DOTCLEAR_PLUGINS_DIR);
            foreach ($path as $root) {
                if (!is_dir($root) || !is_readable($root)) {
                    continue;
                }
                if (substr($root, -1) != '/') {
                    $root .= '/';
                }
                if (($p = @dir($root)) === false) {
                    continue;
                }
                if (($d = @dir($root . 'daInstaller')) === false) {
                    continue;
                }
                Files::deltree($root . '/daInstaller');
            }

            # Some settings change, prepare db queries
            $strReqFormat = 'INSERT INTO ' . dcCore()->prefix . 'setting';
            $strReqFormat .= ' (setting_id,setting_ns,setting_value,setting_type,setting_label)';
            $strReqFormat .= ' VALUES(\'%s\',\'system\',\'%s\',\'string\',\'%s\')';

            $strReqSelect = 'SELECT count(1) FROM ' . dcCore()->prefix . 'setting';
            $strReqSelect .= ' WHERE setting_id = \'%s\'';
            $strReqSelect .= ' AND setting_ns = \'system\'';
            $strReqSelect .= ' AND blog_id IS NULL';

            # Add date and time formats
            $date_formats = ['%Y-%m-%d', '%m/%d/%Y', '%d/%m/%Y', '%Y/%m/%d', '%d.%m.%Y', '%b %e %Y', '%e %b %Y', '%Y %b %e',
                '%a, %Y-%m-%d', '%a, %m/%d/%Y', '%a, %d/%m/%Y', '%a, %Y/%m/%d', '%B %e, %Y', '%e %B, %Y', '%Y, %B %e', '%e. %B %Y',
                '%A, %B %e, %Y', '%A, %e %B, %Y', '%A, %Y, %B %e', '%A, %Y, %B %e', '%A, %e. %B %Y', ];
            $time_formats = ['%H:%M', '%I:%M', '%l:%M', '%Hh%M', '%Ih%M', '%lh%M'];
            if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
                $date_formats = array_map(function ($f) {return str_replace('%e', '%#d', $f);}, $date_formats);
            }

            $rs = dcCore()->con->select(sprintf($strReqSelect, 'date_formats'));
            if ($rs->f(0) == 0) {
                $strReq = sprintf($strReqFormat, 'date_formats', serialize($date_formats), 'Date formats examples');
                dcCore()->con->execute($strReq);
            }
            $rs = dcCore()->con->select(sprintf($strReqSelect, 'time_formats'));
            if ($rs->f(0) == 0) {
                $strReq = sprintf($strReqFormat, 'time_formats', serialize($time_formats), 'Time formats examples');
                dcCore()->con->execute($strReq);
            }

            # Add repository URL for themes and plugins as daInstaller move to core
            $rs = dcCore()->con->select(sprintf($strReqSelect, 'store_plugin_url'));
            if ($rs->f(0) == 0) {
                $strReq = sprintf($strReqFormat, 'store_plugin_url', 'http://update.dotaddict.org/dc2/plugins.xml', 'Plugins XML feed location');
                dcCore()->con->execute($strReq);
            }
            $rs = dcCore()->con->select(sprintf($strReqSelect, 'store_theme_url'));
            if ($rs->f(0) == 0) {
                $strReq = sprintf($strReqFormat, 'store_theme_url', 'http://update.dotaddict.org/dc2/themes.xml', 'Themes XML feed location');
                dcCore()->con->execute($strReq);
            }
        }

        if (version_compare($version, '2.7', '<=')) {
            # Some new settings should be initialized, prepare db queries
            $strReqFormat = 'INSERT INTO ' . dcCore()->prefix . 'setting';
            $strReqFormat .= ' (setting_id,setting_ns,setting_value,setting_type,setting_label)';
            $strReqFormat .= ' VALUES(\'%s\',\'system\',\'%s\',\'string\',\'%s\')';

            $strReqCount = 'SELECT count(1) FROM ' . dcCore()->prefix . 'setting';
            $strReqCount .= ' WHERE setting_id = \'%s\'';
            $strReqCount .= ' AND setting_ns = \'system\'';
            $strReqCount .= ' AND blog_id IS NULL';

            $strReqSelect = 'SELECT setting_value FROM ' . dcCore()->prefix . 'setting';
            $strReqSelect .= ' WHERE setting_id = \'%s\'';
            $strReqSelect .= ' AND setting_ns = \'system\'';
            $strReqSelect .= ' AND blog_id IS NULL';

            # Add nb of posts for home (first page), copying nb of posts on every page
            $rs = dcCore()->con->select(sprintf($strReqCount, 'nb_post_for_home'));
            if ($rs->f(0) == 0) {
                $rs     = dcCore()->con->select(sprintf($strReqSelect, 'nb_post_per_page'));
                $strReq = sprintf($strReqFormat, 'nb_post_for_home', $rs->f(0), 'Nb of posts on home (first page only)');
                dcCore()->con->execute($strReq);
            }
        }

        if (version_compare($version, '2.8.1', '<=')) {
            # switch from jQuery 1.11.1 to 1.11.2
            $strReq = 'UPDATE ' . dcCore()->prefix . 'setting ' .
                " SET setting_value = '1.11.3' " .
                " WHERE setting_id = 'jquery_version' " .
                " AND setting_ns = 'system' " .
                " AND setting_value = '1.11.1' ";
            dcCore()->con->execute($strReq);
            # Some new settings should be initialized, prepare db queries
            $strReq = 'INSERT INTO ' . dcCore()->prefix . 'setting' .
                ' (setting_id,setting_ns,setting_value,setting_type,setting_label)' .
                ' VALUES(\'%s\',\'system\',\'%s\',\'boolean\',\'%s\')';
            dcCore()->con->execute(sprintf($strReq, 'no_search', '0', 'Disable internal search system'));
        }

        if (version_compare($version, '2.9', '<=')) {
            # Some new settings should be initialized, prepare db queries
            $strReq = 'INSERT INTO ' . dcCore()->prefix . 'setting' .
                ' (setting_id,setting_ns,setting_value,setting_type,setting_label)' .
                ' VALUES(\'%s\',\'system\',\'%s\',\'%s\',\'%s\')';
            dcCore()->con->execute(
                sprintf($strReq, 'media_video_width', '400', 'integer', 'Media video insertion width')
            );
            dcCore()->con->execute(
                sprintf($strReq, 'media_video_height', '300', 'integer', 'Media video insertion height')
            );
            dcCore()->con->execute(
                sprintf($strReq, 'media_flash_fallback', '1', 'boolean', 'Flash player fallback for audio and video media')
            );

            # Some settings and prefs should be moved from string to array
            $this->settings2array('system', 'date_formats');
            $this->settings2array('system', 'time_formats');
            $this->settings2array('antispam', 'antispam_filters');
            $this->settings2array('pings', 'pings_uris');
            $this->settings2array('system', 'simpleMenu');
            $this->prefs2array('dashboard', 'favorites');
        }

        if (version_compare($version, '2.9.1', '<=')) {
            # Some settings and prefs should be moved from string to array
            $this->prefs2array('dashboard', 'favorites');
            $this->prefs2array('interface', 'media_last_dirs');
        }

        if (version_compare($version, '2.10', '<')) {
            @unlink(DOTCLEAR_OLD_ROOT_DIR . '/' . 'admin/js/jsUpload/vendor/jquery.ui.widget.js');
            @rmdir(DOTCLEAR_OLD_ROOT_DIR . '/' . 'admin/js/jsUpload/vendor');

            # Create new var directory and its .htaccess file
            @Files::makeDir(DC_VAR);
            $f = DC_VAR . '/.htaccess';
            if (!file_exists($f)) {
                @file_put_contents($f, 'Require all denied' . "\n" . 'Deny from all' . "\n");
            }

            # Some new settings should be initialized, prepare db queries
            $strReq = 'INSERT INTO ' . dcCore()->prefix . 'setting' .
                ' (setting_id,setting_ns,setting_value,setting_type,setting_label)' .
                ' VALUES(\'%s\',\'system\',\'%s\',\'%s\',\'%s\')';
            # Import feed control
            dcCore()->con->execute(
                sprintf($strReq, 'import_feed_url_control', true, 'boolean', 'Control feed URL before import')
            );
            dcCore()->con->execute(
                sprintf($strReq, 'import_feed_no_private_ip', true, 'boolean', 'Prevent import feed from private IP')
            );
            dcCore()->con->execute(
                sprintf($strReq, 'import_feed_ip_regexp', '', 'string', 'Authorize import feed only from this IP regexp')
            );
            dcCore()->con->execute(
                sprintf($strReq, 'import_feed_port_regexp', '/^(80|443)$/', 'string', 'Authorize import feed only from this port regexp')
            );
            # CSP directive (admin part)
            dcCore()->con->execute(
                sprintf($strReq, 'csp_admin_on', true, 'boolean', 'Send CSP header (admin)')
            );
            dcCore()->con->execute(
                sprintf($strReq, 'csp_admin_default', "''self''", 'string', 'CSP default-src directive')
            );
            dcCore()->con->execute(
                sprintf($strReq, 'csp_admin_script', "''self'' ''unsafe-inline'' ''unsafe-eval''", 'string', 'CSP script-src directive')
            );
            dcCore()->con->execute(
                sprintf($strReq, 'csp_admin_style', "''self'' ''unsafe-inline''", 'string', 'CSP style-src directive')
            );
            dcCore()->con->execute(
                sprintf($strReq, 'csp_admin_img', "''self'' data: media.dotaddict.org", 'string', 'CSP img-src directive')
            );
        }

        if (version_compare($version, '2.11', '<')) {
            // Remove the CSP report file from it's old place
            @unlink(DOTCLEAR_OLD_ROOT_DIR . '/admin/csp_report.txt');

            # Some new settings should be initialized, prepare db queries
            $strReq = 'INSERT INTO ' . dcCore()->prefix . 'setting' .
                ' (setting_id,setting_ns,setting_value,setting_type,setting_label)' .
                ' VALUES(\'%s\',\'system\',\'%s\',\'%s\',\'%s\')';
            dcCore()->con->execute(
                sprintf($strReq, 'csp_admin_report_only', false, 'boolean', 'CSP Report only violations (admin)')
            );

            // SQlite Clearbricks driver does not allow using single quote at beginning or end of a field value
                                                                                // so we have to use neutral values (localhost and 127.0.0.1) for some CSP directives
            $csp_prefix = dcCore()->con->driver() == 'sqlite' ? 'localhost ' : ''; // Hack for SQlite Clearbricks driver
            $csp_suffix = dcCore()->con->driver() == 'sqlite' ? ' 127.0.0.1' : ''; // Hack for SQlite Clearbricks driver

            # Try to fix some CSP directive wrongly stored for SQLite drivers
            $strReq = 'UPDATE ' . dcCore()->prefix . 'setting ' .
                " SET setting_value = '" . $csp_prefix . "''self''" . $csp_suffix . "' " .
                " WHERE setting_id = 'csp_admin_default' " .
                " AND setting_ns = 'system' " .
                " AND setting_value = 'self' ";
            dcCore()->con->execute($strReq);
            $strReq = 'UPDATE ' . dcCore()->prefix . 'setting ' .
                " SET setting_value = '" . $csp_prefix . "''self'' ''unsafe-inline'' ''unsafe-eval''" . $csp_suffix . "' " .
                " WHERE setting_id = 'csp_admin_script' " .
                " AND setting_ns = 'system' " .
                " AND setting_value = 'self'' ''unsafe-inline'' ''unsafe-eval' ";
            dcCore()->con->execute($strReq);
            $strReq = 'UPDATE ' . dcCore()->prefix . 'setting ' .
                " SET setting_value = '" . $csp_prefix . "''self'' ''unsafe-inline''" . $csp_suffix . "' " .
                " WHERE setting_id = 'csp_admin_style' " .
                " AND setting_ns = 'system' " .
                " AND setting_value = 'self'' ''unsafe-inline' ";
            dcCore()->con->execute($strReq);
            $strReq = 'UPDATE ' . dcCore()->prefix . 'setting ' .
                " SET setting_value = '" . $csp_prefix . "''self'' data: media.dotaddict.org blob:' " .
                " WHERE setting_id = 'csp_admin_img' " .
                " AND setting_ns = 'system' " .
                " AND setting_value = 'self'' data: media.dotaddict.org' ";
            dcCore()->con->execute($strReq);

            # Update CSP img-src default directive
            $strReq = 'UPDATE ' . dcCore()->prefix . 'setting ' .
                " SET setting_value = '" . $csp_prefix . "''self'' data: media.dotaddict.org blob:' " .
                " WHERE setting_id = 'csp_admin_img' " .
                " AND setting_ns = 'system' " .
                " AND setting_value = '''self'' data: media.dotaddict.org' ";
            dcCore()->con->execute($strReq);

            # Update first publication on published posts
            $strReq = 'UPDATE ' . dcCore()->prefix . 'post ' .
                'SET post_firstpub = 1 ' .
                'WHERE post_status = 1 ';
            dcCore()->con->execute($strReq);

            # A bit of housecleaning for no longer needed files
            $remfiles = [
                'admin/js/jquery/jquery.modal.js',
                'admin/style/modal/close.png',
                'admin/style/modal/loader.gif',
                'admin/style/modal/modal.css',
                'admin/js/dragsort-tablerows.js',
                'admin/js/tool-man/cookies.js',
                'admin/js/tool-man/coordinates.js',
                'admin/js/tool-man/core.js',
                'admin/js/tool-man/css.js',
                'admin/js/tool-man/drag.js',
                'admin/js/tool-man/dragsort.js',
                'admin/js/tool-man/events.js',
                'admin/js/ie7/IE7.js',
                'admin/js/ie7/IE8.js',
                'admin/js/ie7/IE9.js',
                'admin/js/ie7/blank.gif',
                'admin/js/ie7/ie7-hashchange.js',
                'admin/js/ie7/ie7-recalc.js',
                'admin/js/ie7/ie7-squish.js',
                'admin/style/iesucks.css',
                'plugins/tags/js/jquery.autocomplete.js',
                'theme/ductile/ie.css',
            ];
            $remfolders = [
                'admin/style/modal',
                'admin/js/tool-man',
                'admin/js/ie7',
            ];

            foreach ($remfiles as $f) {
                @unlink(DOTCLEAR_OLD_ROOT_DIR . '/' . $f);
            }
            foreach ($remfolders as $f) {
                @rmdir(DOTCLEAR_OLD_ROOT_DIR . '/' . $f);
            }
        }

        if (version_compare($version, '2.12', '<')) {
            # switch from jQuery 2.2.0 to 2.2.4
            $strReq = 'UPDATE ' . dcCore()->prefix . 'setting ' .
                " SET setting_value = '2.2.4' " .
                " WHERE setting_id = 'jquery_version' " .
                " AND setting_ns = 'system' " .
                " AND setting_value = '2.2.0' ";
            dcCore()->con->execute($strReq);
        }

        if (version_compare($version, '2.12.2', '<')) {
            // SQlite Clearbricks driver does not allow using single quote at beginning or end of a field value
                                                                                // so we have to use neutral values (localhost and 127.0.0.1) for some CSP directives
            $csp_prefix = dcCore()->con->driver() == 'sqlite' ? 'localhost ' : ''; // Hack for SQlite Clearbricks driver

            # Update CSP img-src default directive
            $strReq = 'UPDATE ' . dcCore()->prefix . 'setting ' .
                " SET setting_value = '" . $csp_prefix . "''self'' data: http://media.dotaddict.org blob:' " .
                " WHERE setting_id = 'csp_admin_img' " .
                " AND setting_ns = 'system' " .
                " AND setting_value = '" . $csp_prefix . "''self'' data: media.dotaddict.org blob:' ";
            dcCore()->con->execute($strReq);
        }

        if (version_compare($version, '2.14', '<')) {
            // File not more needed
            @unlink(DOTCLEAR_OLD_ROOT_DIR . '/' . 'admin/js/jquery/jquery.bgFade.js');
        }

        if (version_compare($version, '2.14.3', '<')) {
            # Update flie exclusion upload regex
            $strReq = 'UPDATE ' . dcCore()->prefix . 'setting ' .
                " SET setting_value = '/\\.(phps?|pht(ml)?|phl|.?html?|xml|js|htaccess)[0-9]*$/i' " .
                " WHERE setting_id = 'media_exclusion' " .
                " AND setting_ns = 'system' " .
                " AND (setting_value = '/\\.php[0-9]*$/i' " .
                "   OR setting_value = '/\\.php$/i') " .
                "   OR setting_value = '/\\.(phps?|pht(ml)?|phl)[0-9]*$/i' " .
                "   OR setting_value = '/\\.(phps?|pht(ml)?|phl|s?html?|js)[0-9]*$/i'" .
                "   OR setting_value = '/\\.(phps?|pht(ml)?|phl|s?html?|js|htaccess)[0-9]*$/i'";
            dcCore()->con->execute($strReq);
        }

        if (version_compare($version, '2.15', '<')) {
            # switch from jQuery 1.11.3 to 1.12.4
            $strReq = 'UPDATE ' . dcCore()->prefix . 'setting ' .
                " SET setting_value = '1.12.4' " .
                " WHERE setting_id = 'jquery_version' " .
                " AND setting_ns = 'system' " .
                " AND setting_value = '1.11.3' ";
            dcCore()->con->execute($strReq);

            # A bit of housecleaning for no longer needed files
            $remfiles = [
                'plugins/dcLegacyEditor/tpl/index.tpl',
                'plugins/dcCKEditor/tpl/index.tpl',
            ];
            foreach ($remfiles as $f) {
                @unlink(DOTCLEAR_OLD_ROOT_DIR . '/' . $f);
            }
        }

        if (version_compare($version, '2.15.1', '<')) {
            // Remove unsafe-inline from CSP script directives
            $strReq = 'UPDATE ' . dcCore()->prefix . 'setting ' .
                " SET setting_value = REPLACE(setting_value, '''unsafe-inline''', '') " .
                " WHERE setting_id = 'csp_admin_script' " .
                " AND setting_ns = 'system' ";
            dcCore()->con->execute($strReq);
        }

        if (version_compare($version, '2.16', '<')) {
            // Update DotAddict plugins store URL
            $strReq = 'UPDATE ' . dcCore()->prefix . 'setting ' .
                " SET setting_value = REPLACE(setting_value, 'http://update.dotaddict.org', 'https://update.dotaddict.org') " .
                " WHERE setting_id = 'store_plugin_url' " .
                " AND setting_ns = 'system' ";
            dcCore()->con->execute($strReq);
            // Update DotAddict themes store URL
            $strReq = 'UPDATE ' . dcCore()->prefix . 'setting ' .
                " SET setting_value = REPLACE(setting_value, 'http://update.dotaddict.org', 'https://update.dotaddict.org') " .
                " WHERE setting_id = 'store_theme_url' " .
                " AND setting_ns = 'system' ";
            dcCore()->con->execute($strReq);
            // Update CSP img-src default directive for media.dotaddict.org
            $strReq = 'UPDATE ' . dcCore()->prefix . 'setting ' .
                " SET setting_value = REPLACE(setting_value, 'http://media.dotaddict.org', 'https://media.dotaddict.org') " .
                " WHERE setting_id = 'csp_admin_img' " .
                " AND setting_ns = 'system' ";
            dcCore()->con->execute($strReq);
            // Set default jQuery loading for blog
            $strReq = 'INSERT INTO ' . dcCore()->prefix . 'setting' .
                ' (setting_id,setting_ns,setting_value,setting_type,setting_label)' .
                ' VALUES(\'%s\',\'system\',\'%s\',\'%s\',\'%s\')';
            dcCore()->con->execute(
                sprintf($strReq, 'jquery_needed', true, 'boolean', 'Load jQuery library')
            );

            # A bit of housecleaning for no longer needed files
            $remfiles = [
                // jQuery farbtastic Color picker
                'admin/js/color-picker.js',
                'admin/js/jquery/jquery.farbtastic.js',
                'admin/style/farbtastic/farbtastic.css',
                'admin/style/farbtastic/marker.png',
                'admin/style/farbtastic/mask.png',
                'admin/style/farbtastic/wheel.png',
            ];
            $remfolders = [
                // jQuery farbtastic Color picker
                'admin/style/farbtastic',
            ];
            foreach ($remfiles as $f) {
                @unlink(DOTCLEAR_OLD_ROOT_DIR . '/' . $f);
            }
            foreach ($remfolders as $f) {
                @rmdir(DOTCLEAR_OLD_ROOT_DIR . '/' . $f);
            }
        }

        if (version_compare($version, '2.16.1', '<')) {
            # A bit of housecleaning for no longer needed files
            $remfiles = [
                // Oldest jQuery public lib
                'inc/js/jquery/1.4.2/jquery.js',
                'inc/js/jquery/1.4.2/jquery.cookie.js',
                'inc/js/jquery/1.11.1/jquery.js',
                'inc/js/jquery/1.11.1/jquery.cookie.js',
                'inc/js/jquery/1.11.3/jquery.js',
                'inc/js/jquery/1.11.3/jquery.cookie.js',
                'inc/js/jquery/1.12.4/jquery.js',
                'inc/js/jquery/1.12.4/jquery.cookie.js',
                'inc/js/jquery/2.2.0/jquery.js',
                'inc/js/jquery/2.2.0/jquery.cookie.js',
                'inc/js/jquery/2.2.4/jquery.js',
                'inc/js/jquery/2.2.4/jquery.cookie.js',
                'inc/js/jquery/3.3.1/jquery.js',
                'inc/js/jquery/3.3.1/jquery.cookie.js',
            ];
            $remfolders = [
                // Oldest jQuery public lib
                'inc/js/jquery/1.4.2',
                'inc/js/jquery/1.11.1',
                'inc/js/jquery/1.11.3',
                'inc/js/jquery/1.12.4',
                'inc/js/jquery/2.2.0',
                'inc/js/jquery/2.2.4',
                'inc/js/jquery/3.3.1',
            ];
            foreach ($remfiles as $f) {
                @unlink(DOTCLEAR_OLD_ROOT_DIR . '/' . $f);
            }
            foreach ($remfolders as $f) {
                @rmdir(DOTCLEAR_OLD_ROOT_DIR . '/' . $f);
            }
        }

        if (version_compare($version, '2.16.9', '<')) {
            // Fix 87,5% which should be 87.5% in pref for htmlfontsize
            $strReq = 'UPDATE ' . dcCore()->prefix . 'pref ' .
                " SET pref_value = REPLACE(pref_value, '87,5%', '87.5%') " .
                " WHERE pref_id = 'htmlfontsize' " .
                " AND pref_ws = 'interface' ";
            dcCore()->con->execute($strReq);
        }

        if (version_compare($version, '2.17', '<')) {
            # A bit of housecleaning for no longer needed files
            $remfiles = [
                'inc/admin/class.dc.notices.php',
            ];
            $remfolders = [
                // Oldest jQuery public lib
                'inc/js/jquery/3.4.1',
            ];
            foreach ($remfiles as $f) {
                @unlink(DOTCLEAR_OLD_ROOT_DIR . '/' . $f);
            }
            foreach ($remfolders as $f) {
                @rmdir(DOTCLEAR_OLD_ROOT_DIR . '/' . $f);
            }
            # Help specific (files was moved)
            $remtree  = scandir(DOTCLEAR_OLD_ROOT_DIR . '/locales');
            $remfiles = [
                'help/BlowupConfig.html',
                'help/themeEditor.html',
            ];
            foreach ($remtree as $dir) {
                if (is_dir(DOTCLEAR_OLD_ROOT_DIR . '/' . 'locales' . '/' . $dir) && $dir !== '.' && $dir !== '.') {
                    foreach ($remfiles as $f) {
                        @unlink(DOTCLEAR_OLD_ROOT_DIR . '/' . 'locales' . '/' . $dir . '/' . $f);
                    }
                }
            }
        }

        if (version_compare($version, '2.19', '<')) {
            # A bit of housecleaning for no longer needed files
            $remfiles = [
                // No more used in Berlin theme
                'themes/berlin/scripts/boxsizing.htc',
                // That old easter egg is not more present
                'admin/images/thanks.mp3',
                // No more used jQuery pwd strength and cookie plugins
                'admin/js/jquery/jquery.pwstrength.js',
                'admin/js/jquery/jquery.biscuit.js',
                // No more need of this fake common.js (was used by install)
                'admin/js/mini-common.js',
            ];
            $remfolders = [
                // Oldest jQuery public lib
                'inc/js/jquery/3.5.1',
                // No more used in Berlin theme
                'themes/berlin/scripts',
            ];
            foreach ($remfiles as $f) {
                @unlink(DOTCLEAR_OLD_ROOT_DIR . '/' . $f);
            }
            foreach ($remfolders as $f) {
                @rmdir(DOTCLEAR_OLD_ROOT_DIR . '/' . $f);
            }

            # Global settings
            $strReq = 'INSERT INTO ' . dcCore()->prefix . 'setting' .
                ' (setting_id,setting_ns,setting_value,setting_type,setting_label)' .
                ' VALUES(\'%s\',\'system\',\'%s\',\'%s\',\'%s\')';
            dcCore()->con->execute(
                sprintf($strReq, 'prevents_clickjacking', true, 'boolean', 'Prevents Clickjacking')
            );
            dcCore()->con->execute(
                sprintf($strReq, 'prevents_floc', true, 'boolean', 'Prevents FLoC tracking')
            );
        }

        if (version_compare($version, '2.21', '<')) {
            # A bit of housecleaning for no longer needed files
            $remfiles = [
                // The old js datepicker has gone
                'admin/js/date-picker.js',
                'admin/style/date-picker.css',
                'admin/images/date-picker.png',
            ];
            foreach ($remfiles as $f) {
                @unlink(DOTCLEAR_OLD_ROOT_DIR . '/' . $f);
            }
        }

        dcCore()->setVersion('core', DOTCLEAR_CORE_VERSION);
        dcCore()->blogDefaults();

        return $cleanup_sessions;
    }

    /**
     * Convert old-fashion serialized array setting to new-fashion json encoded array
     *
     * @param      string  $ns        namespace name
     * @param      string  $setting   The setting ID
     */
    protected function settings2array(string $ns, string $setting): void
    {
        $strReqSelect = 'SELECT setting_id,blog_id,setting_ns,setting_type,setting_value FROM ' . dcCore()->prefix . 'setting ' .
            "WHERE setting_id = '%s' " .
            "AND setting_ns = '%s' " .
            "AND setting_type = 'string'";
        $rs = dcCore()->con->select(sprintf($strReqSelect, $setting, $ns));
        while ($rs->fetch()) {
            $value = @unserialize($rs->setting_value);
            if (!$value) {
                $value = [];
            }
            settype($value, 'array');
            $value = json_encode($value);
            $rs2   = 'UPDATE ' . dcCore()->prefix . 'setting ' .
            "SET setting_type='array', setting_value = '" . dcCore()->con->escape($value) . "' " .
            "WHERE setting_id='" . dcCore()->con->escape($rs->setting_id) . "' " .
            "AND setting_ns='" . dcCore()->con->escape($rs->setting_ns) . "' ";
            if ($rs->blog_id == '') {
                $rs2 .= 'AND blog_id IS null';
            } else {
                $rs2 .= "AND blog_id = '" . dcCore()->con->escape($rs->blog_id) . "'";
            }
            dcCore()->con->execute($rs2);
        }
    }

    /**
     * Convert old-fashion serialized array pref to new-fashion json encoded array
     *
     * @param      string  $ws     workspace name
     * @param      string  $pref   The preference ID
     */
    protected function prefs2array(string $ws, string $pref): void
    {
        $strReqSelect = 'SELECT pref_id,user_id,pref_ws,pref_type,pref_value FROM ' . dcCore()->prefix . 'pref ' .
            "WHERE pref_id = '%s' " .
            "AND pref_ws = '%s' " .
            "AND pref_type = 'string'";
        $rs = dcCore()->con->select(sprintf($strReqSelect, $pref, $ws));
        while ($rs->fetch()) {
            $value = @unserialize($rs->pref_value);
            if (!$value) {
                $value = [];
            }
            settype($value, 'array');
            $value = json_encode($value);
            $rs2   = 'UPDATE ' . dcCore()->prefix . 'pref ' .
            "SET pref_type='array', pref_value = '" . dcCore()->con->escape($value) . "' " .
            "WHERE pref_id='" . dcCore()->con->escape($rs->pref_id) . "' " .
            "AND pref_ws='" . dcCore()->con->escape($rs->pref_ws) . "' ";
            if ($rs->user_id == '') {
                $rs2 .= 'AND user_id IS null';
            } else {
                $rs2 .= "AND user_id = '" . dcCore()->con->escape($rs->user_id) . "'";
            }
            dcCore()->con->execute($rs2);
        }
    }
}
