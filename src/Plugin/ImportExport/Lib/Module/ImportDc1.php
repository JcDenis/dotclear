<?php
/**
 * @class Dotclear\Plugin\ImportExport\Lib\Module\ImportDc1
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginImportExport
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Lib\Module;

use Dotclear\Database\Connection;
use Dotclear\Exception\ModuleException;
use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Network\Http;
use Dotclear\Plugin\ImportExport\Lib\Module;
use Dotclear\Utils\Crypt;
use Dotclear\Utils\Text;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class ImportDc1 extends Module
{
    protected $action = null;
    protected $step   = 1;

    protected $post_offset = 0;
    protected $post_limit  = 20;
    protected $post_count  = 0;

    protected $has_table = [];

    protected $vars;
    protected $base_vars = [
        'db_driver'  => 'mysqli',
        'db_host'    => '',
        'db_name'    => '',
        'db_user'    => '',
        'db_pwd'     => '',
        'db_prefix'  => 'dc_',
        'post_limit' => 20,
        'cat_ids'    => [],
    ];

    protected function setInfo()
    {
        $this->type        = 'import';
        $this->name        = __('Dotclear 1.2 import');
        $this->description = __('Import a Dotclear 1.2 installation into your current blog.');
    }

    public function init()
    {
        if (!isset($_SESSION['dc1_import_vars'])) {
            $_SESSION['dc1_import_vars'] = $this->base_vars;
        }
        $this->vars = &$_SESSION['dc1_import_vars'];

        if ($this->vars['post_limit'] > 0) {
            $this->post_limit = $this->vars['post_limit'];
        }
    }

    public function resetVars()
    {
        $this->vars = $this->base_vars;
        unset($_SESSION['dc1_import_vars']);
    }

    public function process($do)
    {
        $this->action = $do;
    }

    # We handle process in another way to always display something to
    # user
    protected function guiprocess($do)
    {
        switch ($do) {
            case 'step1':
                $this->vars['db_driver']  = $_POST['db_driver'];
                $this->vars['db_host']    = $_POST['db_host'];
                $this->vars['db_name']    = $_POST['db_name'];
                $this->vars['db_user']    = $_POST['db_user'];
                $this->vars['db_pwd']     = $_POST['db_pwd'];
                $this->vars['post_limit'] = abs((int) $_POST['post_limit']) > 0 ? $_POST['post_limit'] : 0;
                $this->vars['db_prefix']  = $_POST['db_prefix'];
                $db                       = $this->db();
                $db->close();
                $this->step = 2;
                echo $this->progressBar(1);

                break;
            case 'step2':
                $this->step = 2;
                $this->importUsers();
                $this->step = 3;
                echo $this->progressBar(3);

                break;
            case 'step3':
                $this->step = 3;
                $this->importCategories();
                if (dotclear()->plugins->moduleExists('blogroll')) {
                    $this->step = 4;
                    echo $this->progressBar(5);
                } else {
                    $this->step = 5;
                    echo $this->progressBar(7);
                }

                break;
            case 'step4':
                $this->step = 4;
                $this->importLinks();
                $this->step = 5;
                echo $this->progressBar(7);

                break;
            case 'step5':
                $this->step        = 5;
                $this->post_offset = !empty($_REQUEST['offset']) ? abs((int) $_REQUEST['offset']) : 0;
                if ($this->importPosts($percent) === -1) {
                    Http::redirect($this->getURL() . '&do=ok');
                } else {
                    echo $this->progressBar(ceil($percent * 0.93) + 7);
                }

                break;
            case 'ok':
                $this->resetVars();
                dotclear()->blog()->triggerBlog();
                $this->step = 6;
                echo $this->progressBar(100);

                break;
        }
    }

    public function gui()
    {
        try {
            $this->guiprocess($this->action);
        } catch (\Exception $e) {
            $this->error($e);
        }

        # db drivers
        $db_drivers = [
            'mysqli' => 'mysqli',
        ];

        switch ($this->step) {
            case 1:
                echo
                '<p>' . sprintf(
                    __('Import the content of a Dotclear 1.2\'s blog in the current blog: %s.'),
                    '<strong>' . Html::escapeHTML(dotclear()->blog()->name) . '</strong>'
                ) . '</p>' .
                '<p class="warning">' . __('Please note that this process ' .
                    'will empty your categories, blogroll, entries and comments on the current blog.') . '</p>';

                printf(
                    $this->imForm(1, __('General information'), __('Import my blog now')),
                    '<p>' . __('We first need some information about your old Dotclear 1.2 installation.') . '</p>' .
                    '<p><label for="db_driver">' . __('Database driver:') . '</label> ' .
                    Form::combo('db_driver', $db_drivers, Html::escapeHTML($this->vars['db_driver'])) . '</p>' .
                    '<p><label for="db_host">' . __('Database Host Name:') . '</label> ' .
                    Form::field('db_host', 30, 255, Html::escapeHTML($this->vars['db_host'])) . '</p>' .
                    '<p><label for="db_name">' . __('Database Name:', Html::escapeHTML($this->vars['db_name'])) . '</label> ' .
                    Form::field('db_name', 30, 255, Html::escapeHTML($this->vars['db_name'])) . '</p>' .
                    '<p><label for="db_user">' . __('Database User Name:') . '</label> ' .
                    Form::field('db_user', 30, 255, Html::escapeHTML($this->vars['db_user'])) . '</p>' .
                    '<p><label for="db_pwd">' . __('Database Password:') . '</label> ' .
                    Form::password('db_pwd', 30, 255) . '</p>' .
                    '<p><label for="db_prefix">' . __('Database Tables Prefix:') . '</label> ' .
                    Form::field('db_prefix', 30, 255, Html::escapeHTML($this->vars['db_prefix'])) . '</p>' .
                    '<h3 class="vertical-separator">' . __('Entries import options') . '</h3>' .
                    '<p><label for="post_limit">' . __('Number of entries to import at once:') . '</label> ' .
                    Form::number('post_limit', 0, 999, Html::escapeHTML($this->vars['post_limit'])) . '</p>'
                );

                break;
            case 2:
                printf(
                    $this->imForm(2, __('Importing users')),
                    $this->autoSubmit()
                );

                break;
            case 3:
                printf(
                    $this->imForm(3, __('Importing categories')),
                    $this->autoSubmit()
                );

                break;
            case 4:
                printf(
                    $this->imForm(4, __('Importing blogroll')),
                    $this->autoSubmit()
                );

                break;
            case 5:
                $t = sprintf(
                    __('Importing entries from %d to %d / %d'),
                    $this->post_offset,
                    min([$this->post_offset + $this->post_limit, $this->post_count]),
                    $this->post_count
                );
                printf(
                    $this->imForm(5, $t),
                    Form::hidden(['offset'], $this->post_offset) .
                    $this->autoSubmit()
                );

                break;
            case 6:
                echo
                '<h3 class="vertical-separator">' . __('Please read carefully') . '</h3>' .
                '<ul>' .
                '<li>' . __('Every newly imported user has received a random password ' .
                    'and will need to ask for a new one by following the "I forgot my password" link on the login page ' .
                    '(Their registered email address has to be valid.)') . '</li>' .

                '<li>' . sprintf(
                    __('Please note that Dotclear 2 has a new URL layout. You can avoid broken ' .
                    'links by installing <a href="%s">DC1 redirect</a> plugin and activate it in your blog configuration.'),
                    'https://plugins.dotaddict.org/dc2/details/dc1redirect'
                ) . '</li>' .
                '</ul>' .

                $this->congratMessage();

                break;
        }
    }

    # Simple form for step by step process
    protected function imForm($step, $legend, $submit_value = null)
    {
        if (!$submit_value) {
            $submit_value = __('next step') . ' >';
        }

        return
        '<form action="' . $this->getURL(true) . '" method="post">' .
        '<h3 class="vertical-separator">' . $legend . '</h3>' .
        '<div>' . dotclear()->nonce()->form() .
        Form::hidden(['do'], 'step' . $step) .
        Form::hidden(['handler'], 'admin.plugin.ImportExport') .
        '%s' . '</div>' .
        '<p class="vertical-separator"><input type="submit" value="' . $submit_value . '" /></p>' .
        '<p class="form-note info">' . __('Depending on the size of your blog, it could take a few minutes.') . '</p>' .
            '</form>';
    }

    # Error display
    protected function error($e)
    {
        echo '<div class="error"><strong>' . __('Errors:') . '</strong>' .
        '<p>' . $e->getMessage() . '</p></div>';
    }

    # Database init
    protected function db()
    {
        $db = Connection::init($this->vars['db_driver'], $this->vars['db_host'], $this->vars['db_name'], $this->vars['db_user'], $this->vars['db_pwd']);

        $rs = $db->select("SHOW TABLES LIKE '" . $this->vars['db_prefix'] . "%'");
        if ($rs->isEmpty()) {
            throw new ModuleException(__('Dotclear tables not found'));
        }

        while ($rs->fetch()) {
            $this->has_table[$rs->f(0)] = true;
        }

        # Set this to read data as they were written in Dotclear 1
        try {
            $db->execute('SET NAMES DEFAULT');
        } catch (\Exception $e) {
        }

        $db->execute('SET CHARACTER SET DEFAULT');
        $db->execute('SET COLLATION_CONNECTION = DEFAULT');
        $db->execute('SET COLLATION_SERVER = DEFAULT');
        $db->execute('SET CHARACTER_SET_SERVER = DEFAULT');
        $db->execute('SET CHARACTER_SET_DATABASE = DEFAULT');

        $this->post_count = $db->select(
            'SELECT COUNT(post_id) FROM ' . $this->vars['db_prefix'] . 'post '
        )->f(0);

        return $db;
    }

    protected function cleanStr($str)
    {
        return Text::cleanUTF8(@Text::toUTF8($str));
    }

    # Users import
    protected function importUsers()
    {
        $db     = $this->db();
        $prefix = $this->vars['db_prefix'];
        $rs     = $db->select('SELECT * FROM ' . $prefix . 'user');

        try {
            dotclear()->con()->begin();

            while ($rs->fetch()) {
                if (!dotclear()->users()->userExists($rs->user_id)) {
                    $cur                   = dotclear()->con()->openCursor(dotclear()->prefix . 'user');
                    $cur->user_id          = $rs->user_id;
                    $cur->user_name        = $rs->user_nom;
                    $cur->user_firstname   = $rs->user_prenom;
                    $cur->user_displayname = $rs->user_pseudo;
                    $cur->user_pwd         = Crypt::createPassword();
                    $cur->user_email       = $rs->user_email;
                    $cur->user_lang        = $rs->user_lang;
                    $cur->user_tz          = dotclear()->blog()->settings()->system->blog_timezone;
                    $cur->user_post_status = $rs->user_post_pub ? 1 : -2;
                    $cur->user_options     = new ArrayObject([
                        'edit_size'   => (int) $rs->user_edit_size,
                        'post_format' => $rs->user_post_format,
                    ]);

                    $permissions = [];
                    switch ($rs->user_level) {
                        case '0':
                            $cur->user_status = 0;

                            break;
                        case '1': # editor
                            $permissions['usage'] = true;

                            break;
                        case '5': # advanced editor
                            $permissions['contentadmin'] = true;
                            $permissions['categories']   = true;
                            $permissions['media_admin']  = true;

                            break;
                        case '9': # admin
                            $permissions['admin'] = true;

                            break;
                    }

                    dotclear()->users()->addUser($cur);
                    dotclear()->users()->setUserBlogPermissions(
                        $rs->user_id,
                        dotclear()->blog()->id,
                        $permissions
                    );
                }
            }

            dotclear()->con()->commit();
            $db->close();
        } catch (\Exception $e) {
            dotclear()->con()->rollback();
            $db->close();

            throw $e;
        }
    }

    # Categories import
    protected function importCategories()
    {
        $db     = $this->db();
        $prefix = $this->vars['db_prefix'];
        $rs     = $db->select('SELECT * FROM ' . $prefix . 'categorie ORDER BY cat_ord ASC');

        try {
            dotclear()->con()->execute(
                'DELETE FROM ' . dotclear()->prefix . 'category ' .
                "WHERE blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' "
            );

            $ord = 2;
            while ($rs->fetch()) {
                $cur            = dotclear()->con()->openCursor(dotclear()->prefix . 'category');
                $cur->blog_id   = dotclear()->blog()->id;
                $cur->cat_title = $this->cleanStr(htmlspecialchars_decode($rs->cat_libelle));
                $cur->cat_desc  = $this->cleanStr($rs->cat_desc);
                $cur->cat_url   = $this->cleanStr($rs->cat_libelle_url);
                $cur->cat_lft   = $ord++;
                $cur->cat_rgt   = $ord++;

                $cur->cat_id = dotclear()->con()->select(
                    'SELECT MAX(cat_id) FROM ' . dotclear()->prefix . 'category'
                )->f(0) + 1;
                $this->vars['cat_ids'][$rs->cat_id] = $cur->cat_id;
                $cur->insert();
            }

            $db->close();
        } catch (\Exception $e) {
            $db->close();

            throw $e;
        }
    }

    # Blogroll import
    protected function importLinks()
    {
        $db     = $this->db();
        $prefix = $this->vars['db_prefix'];
        $rs     = $db->select('SELECT * FROM ' . $prefix . 'link ORDER BY link_id ASC');

        try {
            dotclear()->con()->execute(
                'DELETE FROM ' . dotclear()->prefix . 'link ' .
                "WHERE blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' "
            );

            while ($rs->fetch()) {
                $cur                = dotclear()->con()->openCursor(dotclear()->prefix . 'link');
                $cur->blog_id       = dotclear()->blog()->id;
                $cur->link_href     = $this->cleanStr($rs->href);
                $cur->link_title    = $this->cleanStr($rs->label);
                $cur->link_desc     = $this->cleanStr($rs->title);
                $cur->link_lang     = $this->cleanStr($rs->lang);
                $cur->link_xfn      = $this->cleanStr($rs->rel);
                $cur->link_position = (int) $rs->position;

                $cur->link_id = dotclear()->con()->select(
                    'SELECT MAX(link_id) FROM ' . dotclear()->prefix . 'link'
                )->f(0) + 1;
                $cur->insert();
            }

            $db->close();
        } catch (\Exception $e) {
            $db->close();

            throw $e;
        }
    }

    # Entries import
    protected function importPosts(&$percent)
    {
        $db     = $this->db();
        $prefix = $this->vars['db_prefix'];

        $count = $db->select('SELECT COUNT(post_id) FROM ' . $prefix . 'post')->f(0);

        $rs = $db->select(
            'SELECT * FROM ' . $prefix . 'post ORDER BY post_id ASC ' .
            $db->limit($this->post_offset, $this->post_limit)
        );

        try {
            if ($this->post_offset == 0) {
                dotclear()->con()->execute(
                    'DELETE FROM ' . dotclear()->prefix . 'post ' .
                    "WHERE blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' "
                );
            }

            while ($rs->fetch()) {
                $this->importPost($rs, $db);
            }

            $db->close();
        } catch (\Exception $e) {
            $db->close();

            throw $e;
        }

        if ($rs->count() < $this->post_limit) {
            return -1;
        }
        $this->post_offset += $this->post_limit;

        if ($this->post_offset > $this->post_count) {
            $percent = 100;
        } else {
            $percent = $this->post_offset * 100 / $this->post_count;
        }
    }

    protected function importPost($rs, $db)
    {
        $cur              = dotclear()->con()->openCursor(dotclear()->prefix . 'post');
        $cur->blog_id     = dotclear()->blog()->id;
        $cur->user_id     = $rs->user_id;
        $cur->cat_id      = (int) $this->vars['cat_ids'][$rs->cat_id];
        $cur->post_dt     = $rs->post_dt;
        $cur->post_creadt = $rs->post_creadt;
        $cur->post_upddt  = $rs->post_upddt;
        $cur->post_title  = Html::decodeEntities($this->cleanStr($rs->post_titre));

        $cur->post_url = date('Y/m/d/', strtotime($cur->post_dt)) . $rs->post_id . '-' . $rs->post_titre_url;
        $cur->post_url = substr($cur->post_url, 0, 255);

        $cur->post_format        = $rs->post_content_wiki == '' ? 'xhtml' : 'wiki';
        $cur->post_content_xhtml = $this->cleanStr($rs->post_content);
        $cur->post_excerpt_xhtml = $this->cleanStr($rs->post_chapo);

        if ($cur->post_format == 'wiki') {
            $cur->post_content = $this->cleanStr($rs->post_content_wiki);
            $cur->post_excerpt = $this->cleanStr($rs->post_chapo_wiki);
        } else {
            $cur->post_content = $this->cleanStr($rs->post_content);
            $cur->post_excerpt = $this->cleanStr($rs->post_chapo);
        }

        $cur->post_notes        = $this->cleanStr($rs->post_notes);
        $cur->post_status       = (int) $rs->post_pub;
        $cur->post_selected     = (int) $rs->post_selected;
        $cur->post_open_comment = (int) $rs->post_open_comment;
        $cur->post_open_tb      = (int) $rs->post_open_tb;
        $cur->post_lang         = $rs->post_lang;

        $cur->post_words = implode(' ', Text::splitWords(
            $cur->post_title . ' ' .
            $cur->post_excerpt_xhtml . ' ' .
            $cur->post_content_xhtml
        ));

        $cur->post_id = dotclear()->con()->select(
            'SELECT MAX(post_id) FROM ' . dotclear()->prefix . 'post'
        )->f(0) + 1;

        $cur->insert();
        $this->importComments($rs->post_id, $cur->post_id, $db);
        $this->importPings($rs->post_id, $cur->post_id, $db);

        # Load meta if we have some in DC1
        if (isset($this->has_table[$this->vars['db_prefix'] . 'post_meta'])) {
            $this->importMeta($rs->post_id, $cur->post_id, $db);
        }
    }

    # Comments import
    protected function importComments($post_id, $new_post_id, $db)
    {
        $count_c = $count_t = 0;

        $rs = $db->select(
            'SELECT * FROM ' . $this->vars['db_prefix'] . 'comment ' .
            'WHERE post_id = ' . (int) $post_id . ' '
        );

        while ($rs->fetch()) {
            $cur                    = dotclear()->con()->openCursor(dotclear()->prefix . 'comment');
            $cur->post_id           = (int) $new_post_id;
            $cur->comment_author    = $this->cleanStr($rs->comment_auteur);
            $cur->comment_status    = (int) $rs->comment_pub;
            $cur->comment_dt        = $rs->comment_dt;
            $cur->comment_upddt     = $rs->comment_upddt;
            $cur->comment_email     = $this->cleanStr($rs->comment_email);
            $cur->comment_content   = $this->cleanStr($rs->comment_content);
            $cur->comment_ip        = $rs->comment_ip;
            $cur->comment_trackback = (int) $rs->comment_trackback;

            $cur->comment_site = $this->cleanStr($rs->comment_site);
            if ($cur->comment_site != '' && !preg_match('!^http(s)?://.*$!', $cur->comment_site)) {
                $cur->comment_site = substr('http://' . $cur->comment_site, 0, 255);
            }

            if ($rs->exists('spam') && $rs->spam && $rs->comment_status == 0) {
                $cur->comment_status = -2;
            }

            $cur->comment_words = implode(' ', Text::splitWords($cur->comment_content));

            $cur->comment_id = dotclear()->con()->select(
                'SELECT MAX(comment_id) FROM ' . dotclear()->prefix . 'comment'
            )->f(0) + 1;

            $cur->insert();

            if ($cur->comment_trackback && $cur->comment_status == 1) {
                $count_t++;
            } elseif ($cur->comment_status == 1) {
                $count_c++;
            }
        }

        if ($count_t > 0 || $count_c > 0) {
            dotclear()->con()->execute(
                'UPDATE ' . dotclear()->prefix . 'post SET ' .
                'nb_comment = ' . $count_c . ', ' .
                'nb_trackback = ' . $count_t . ' ' .
                'WHERE post_id = ' . (int) $new_post_id . ' '
            );
        }
    }

    # Pings import
    protected function importPings($post_id, $new_post_id, $db)
    {
        $urls = [];

        $rs = $db->select(
            'SELECT * FROM ' . $this->vars['db_prefix'] . 'ping ' .
            'WHERE post_id = ' . (int) $post_id
        );

        while ($rs->fetch()) {
            $url = $this->cleanStr($rs->ping_url);
            if (isset($urls[$url])) {
                continue;
            }

            $cur           = dotclear()->con()->openCursor(dotclear()->prefix . 'ping');
            $cur->post_id  = (int) $new_post_id;
            $cur->ping_url = $url;
            $cur->ping_dt  = $rs->ping_dt;
            $cur->insert();

            $urls[$url] = true;
        }
    }

    # Meta import
    protected function importMeta($post_id, $new_post_id, $db)
    {
        $rs = $db->select(
            'SELECT * FROM ' . $this->vars['db_prefix'] . 'post_meta ' .
            'WHERE post_id = ' . (int) $post_id . ' '
        );

        if ($rs->isEmpty()) {
            return;
        }

        while ($rs->fetch()) {
            dotclear()->meta()->setPostMeta($new_post_id, $this->cleanStr($rs->meta_key), $this->cleanStr($rs->meta_value));
        }
    }
}
