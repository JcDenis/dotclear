<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Admin\Lib\Module;

// Dotclear\Plugin\ImportExport\Admin\Lib\Module\ImportDc1
use ArrayObject;
use Dotclear\Database\AbstractConnection;
use Dotclear\Database\Record;
use Dotclear\Exception\ModuleException;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Plugin\ImportExport\Admin\Lib\Module;
use Dotclear\Helper\Crypt;
use Dotclear\Helper\Text;
use Exception;

/**
 * Import dotclear 1 module for plugin ImportExport.
 *
 * @ingroup  Plugin ImportExport
 */
class ImportDc1 extends Module
{
    protected $action;
    protected $step = 1;

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

    protected function setInfo(): void
    {
        $this->type        = 'import';
        $this->name        = __('Dotclear 1.2 import');
        $this->description = __('Import a Dotclear 1.2 installation into your current blog.');
    }

    public function init(): void
    {
        if (!isset($_SESSION['dc1_import_vars'])) {
            $_SESSION['dc1_import_vars'] = $this->base_vars;
        }
        $this->vars = &$_SESSION['dc1_import_vars'];

        if (0 < $this->vars['post_limit']) {
            $this->post_limit = $this->vars['post_limit'];
        }
    }

    public function resetVars(): void
    {
        $this->vars = $this->base_vars;
        unset($_SESSION['dc1_import_vars']);
    }

    public function process(string $do): void
    {
        $this->action = $do;
    }

    // We handle process in another way to always display something to
    // user
    protected function guiprocess(string $do): void
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
                if (dotclear()->plugins()->hasModule('Blogroll')) {
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
                    echo $this->progressBar((int) (ceil($percent * 0.93) + 7));
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

    public function gui(): void
    {
        try {
            $this->guiprocess($this->action);
        } catch (Exception $e) {
            $this->error($e);
        }

        // db drivers
        $db_drivers = [
            'mysqli' => 'mysqli',
        ];

        switch ($this->step) {
            case 1:
                echo '<p>' . sprintf(
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
                echo '<h3 class="vertical-separator">' . __('Please read carefully') . '</h3>' .
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

    // Simple form for step by step process
    protected function imForm(int $step, string $legend, ?string $submit_value = null): string
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

    // Error display
    protected function error(Exception $e): void
    {
        echo '<div class="error"><strong>' . __('Errors:') . '</strong>' .
        '<p>' . $e->getMessage() . '</p></div>';
    }

    // Database init
    protected function db(): AbstractConnection
    {
        $db = AbstractConnection::init($this->vars['db_driver'], $this->vars['db_host'], $this->vars['db_name'], $this->vars['db_user'], $this->vars['db_pwd']);

        $rs = $db->select("SHOW TABLES LIKE '" . $this->vars['db_prefix'] . "%'");
        if ($rs->isEmpty()) {
            throw new ModuleException(__('Dotclear tables not found'));
        }

        while ($rs->fetch()) {
            $this->has_table[$rs->f(0)] = true;
        }

        // Set this to read data as they were written in Dotclear 1
        try {
            $db->execute('SET NAMES DEFAULT');
        } catch (\Exception) {
        }

        $db->execute('SET CHARACTER SET DEFAULT');
        $db->execute('SET COLLATION_CONNECTION = DEFAULT');
        $db->execute('SET COLLATION_SERVER = DEFAULT');
        $db->execute('SET CHARACTER_SET_SERVER = DEFAULT');
        $db->execute('SET CHARACTER_SET_DATABASE = DEFAULT');

        $this->post_count = $db->select(
            'SELECT COUNT(post_id) FROM ' . $this->vars['db_prefix'] . 'post '
        )->fInt();

        return $db;
    }

    protected function cleanStr(string $str): string
    {
        return Text::cleanUTF8(@Text::toUTF8($str));
    }

    // Users import
    protected function importUsers(): void
    {
        $db     = $this->db();
        $prefix = $this->vars['db_prefix'];
        $rs     = $db->select('SELECT * FROM ' . $prefix . 'user');

        try {
            dotclear()->con()->begin();

            while ($rs->fetch()) {
                if (!dotclear()->users()->userExists($rs->f('user_id'))) {
                    $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'user');
                    $cur->setField('user_id', $rs->f('user_id'));
                    $cur->setField('user_name', $rs->f('user_nom'));
                    $cur->setField('user_firstname', $rs->f('user_prenom'));
                    $cur->setField('user_displayname', $rs->f('user_pseudo'));
                    $cur->setField('user_pwd', Crypt::createPassword());
                    $cur->setField('user_email', $rs->f('user_email'));
                    $cur->setField('user_lang', $rs->f('user_lang'));
                    $cur->setField('user_tz', dotclear()->blog()->settings()->get('system')->get('blog_timezone'));
                    $cur->setField('user_post_status', $rs->f('user_post_pub') ? 1 : -2);
                    $cur->setField('user_options', new ArrayObject([
                        'edit_size'   => $rs->fInt('user_edit_size'),
                        'post_format' => $rs->f('user_post_format'),
                    ]));

                    $permissions = [];

                    switch ($rs->f('user_level')) {
                        case '0':
                            $cur->setField('user_status', 0);

                            break;

                        case '1': // editor
                            $permissions['usage'] = true;

                            break;

                        case '5': // advanced editor
                            $permissions['contentadmin'] = true;
                            $permissions['categories']   = true;
                            $permissions['media_admin']  = true;

                            break;

                        case '9': // admin
                            $permissions['admin'] = true;

                            break;
                    }

                    dotclear()->users()->addUser($cur);
                    dotclear()->users()->setUserBlogPermissions(
                        $rs->f('user_id'),
                        dotclear()->blog()->id,
                        $permissions
                    );
                }
            }

            dotclear()->con()->commit();
            $db->close();
        } catch (Exception $e) {
            dotclear()->con()->rollback();
            $db->close();

            throw $e;
        }
    }

    // Categories import
    protected function importCategories(): void
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
                $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'category');
                $cur->setField('blog_id', dotclear()->blog()->id);
                $cur->setField('cat_title', $this->cleanStr(htmlspecialchars_decode($rs->f('cat_libelle'))));
                $cur->setField('cat_desc', $this->cleanStr($rs->f('cat_desc')));
                $cur->setField('cat_url', $this->cleanStr($rs->f('cat_libelle_url')));
                $cur->setField('cat_lft', $ord++);
                $cur->setField('cat_rgt', $ord++);
                $cur->setField('cat_id', dotclear()->con()->select(
                    'SELECT MAX(cat_id) FROM ' . dotclear()->prefix . 'category'
                )->fInt() + 1);
                $this->vars['cat_ids'][$rs->fInt('cat_id')] = $cur->getField('cat_id');
                $cur->insert();
            }

            $db->close();
        } catch (Exception $e) {
            $db->close();

            throw $e;
        }
    }

    // Blogroll import
    protected function importLinks(): void
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
                $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'link');
                $cur->setField('blog_id', dotclear()->blog()->id);
                $cur->setField('link_href', $this->cleanStr($rs->f('href')));
                $cur->setField('link_title', $this->cleanStr($rs->f('label')));
                $cur->setField('link_desc', $this->cleanStr($rs->f('title')));
                $cur->setField('link_lang', $this->cleanStr($rs->f('lang')));
                $cur->setField('link_xfn', $this->cleanStr($rs->f('rel')));
                $cur->setField('link_position', $rs->fInt('position'));
                $cur->setField('link_id', dotclear()->con()->select(
                    'SELECT MAX(link_id) FROM ' . dotclear()->prefix . 'link'
                )->fInt() + 1);
                $cur->insert();
            }

            $db->close();
        } catch (Exception $e) {
            $db->close();

            throw $e;
        }
    }

    // Entries import
    protected function importPosts(int &$percent): int
    {
        $db     = $this->db();
        $prefix = $this->vars['db_prefix'];

        // ! $count = $db->select('SELECT COUNT(post_id) FROM ' . $prefix . 'post')->fInt();

        $rs = $db->select(
            'SELECT * FROM ' . $prefix . 'post ORDER BY post_id ASC ' .
            $db->limit($this->post_offset, $this->post_limit)
        );

        try {
            if (0 == $this->post_offset) {
                dotclear()->con()->execute(
                    'DELETE FROM ' . dotclear()->prefix . 'post ' .
                    "WHERE blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' "
                );
            }

            while ($rs->fetch()) {
                $this->importPost($rs, $db);
            }

            $db->close();
        } catch (Exception $e) {
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
            $percent = (int) ($this->post_offset * 100 / $this->post_count);
        }

        return $percent;
    }

    protected function importPost(Record $rs, AbstractConnection $db): void
    {
        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'post');
        $cur->setField('blog_id', dotclear()->blog()->id);
        $cur->setField('user_id', $rs->f('user_id'));
        $cur->setField('cat_id', (int) $this->vars['cat_ids'][$rs->fInt('cat_id')]);
        $cur->setField('post_dt', $rs->f('post_dt'));
        $cur->setField('post_creadt', $rs->f('post_creadt'));
        $cur->setField('post_upddt', $rs->f('post_upddt'));
        $cur->setField('post_title', Html::decodeEntities($this->cleanStr($rs->f('post_titre'))));

        $cur->setField('post_url', date('Y/m/d/', strtotime($cur->getField('post_dt'))) . $rs->f('post_id') . '-' . $rs->f('post_titre_url'));
        $cur->setField('post_url', substr($cur->getField('post_url'), 0, 255));

        $cur->setField('post_format', '' == $rs->f('post_content_wiki') ? 'xhtml' : 'wiki');
        $cur->setField('post_content_xhtml', $this->cleanStr($rs->f('post_content')));
        $cur->setField('post_excerpt_xhtml', $this->cleanStr($rs->f('post_chapo')));

        if ('wiki' == $cur->getField('post_format')) {
            $cur->setField('post_content', $this->cleanStr($rs->f('post_content_wiki')));
            $cur->setField('post_excerpt', $this->cleanStr($rs->f('post_chapo_wiki')));
        } else {
            $cur->setField('post_content', $this->cleanStr($rs->f('post_content')));
            $cur->setField('post_excerpt', $this->cleanStr($rs->f('post_chapo')));
        }

        $cur->setField('post_notes', $this->cleanStr($rs->f('post_notes')));
        $cur->setField('post_status', $rs->fInt('post_pub'));
        $cur->setField('post_selected', $rs->fInt('post_selected'));
        $cur->setField('post_open_comment', $rs->fInt('post_open_comment'));
        $cur->setField('post_open_tb', $rs->fInt('post_open_tb'));
        $cur->setField('post_lang', $rs->f('post_lang'));

        $cur->setField('post_words', implode(' ', Text::splitWords(
            $cur->getField('post_title') . ' ' .
            $cur->getField('post_excerpt_xhtml') . ' ' .
            $cur->getField('post_content_xhtml')
        )));

        $cur->setField('post_id', dotclear()->con()->select(
            'SELECT MAX(post_id) FROM ' . dotclear()->prefix . 'post'
        )->fInt() + 1);

        $cur->insert();
        $this->importComments($rs->fInt('post_id'), $cur->getField('post_id'), $db);
        $this->importPings($rs->fInt('post_id'), $cur->getField('post_id'), $db);

        // Load meta if we have some in DC1
        if (isset($this->has_table[$this->vars['db_prefix'] . 'post_meta'])) {
            $this->importMeta($rs->fInt('post_id'), $cur->getField('post_id'), $db);
        }
    }

    // Comments import
    protected function importComments(int $post_id, int $new_post_id, AbstractConnection $db): void
    {
        $count_c = $count_t = 0;

        $rs = $db->select(
            'SELECT * FROM ' . $this->vars['db_prefix'] . 'comment ' .
            'WHERE post_id = ' . (int) $post_id . ' '
        );

        while ($rs->fetch()) {
            $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'comment');
            $cur->setField('post_id', (int) $new_post_id);
            $cur->setField('comment_author', $this->cleanStr($rs->f('comment_auteur')));
            $cur->setField('comment_status', $rs->fInt('comment_pub'));
            $cur->setField('comment_dt', $rs->f('comment_dt'));
            $cur->setField('comment_upddt', $rs->f('comment_upddt'));
            $cur->setField('comment_email', $this->cleanStr($rs->f('comment_email')));
            $cur->setField('comment_content', $this->cleanStr($rs->f('comment_content')));
            $cur->setField('comment_ip', $rs->f('comment_ip'));
            $cur->setField('comment_trackback', $rs->fInt('comment_trackback'));
            $cur->setField('comment_site', $this->cleanStr($rs->f('comment_site')));
            if ('' != $cur->getField('comment_site') && !preg_match('!^http(s)?://.*$!', $cur->getField('comment_site'))) {
                $cur->setField('comment_site', substr('http://' . $cur->getField('comment_site'), 0, 255));
            }

            if ($rs->exists('spam') && $rs->f('spam') && 0 == $rs->fInt('comment_status')) {
                $cur->setField('comment_status', -2);
            }

            $cur->setField('comment_words', implode(' ', Text::splitWords($cur->f('comment_content'))));

            $cur->setField('comment_id', dotclear()->con()->select(
                'SELECT MAX(comment_id) FROM ' . dotclear()->prefix . 'comment'
            )->fInt() + 1);

            $cur->insert();

            if ($cur->getField('comment_trackback') && 1 == $cur->getField('comment_status')) {
                ++$count_t;
            } elseif (1 == $cur->getField('comment_status')) {
                ++$count_c;
            }
        }

        if (0 < $count_t || 0 < $count_c) {
            dotclear()->con()->execute(
                'UPDATE ' . dotclear()->prefix . 'post SET ' .
                'nb_comment = ' . $count_c . ', ' .
                'nb_trackback = ' . $count_t . ' ' .
                'WHERE post_id = ' . (int) $new_post_id . ' '
            );
        }
    }

    // Pings import
    protected function importPings(int $post_id, int $new_post_id, AbstractConnection $db): void
    {
        $urls = [];

        $rs = $db->select(
            'SELECT * FROM ' . $this->vars['db_prefix'] . 'ping ' .
            'WHERE post_id = ' . (int) $post_id
        );

        while ($rs->fetch()) {
            $url = $this->cleanStr($rs->f('ping_url'));
            if (isset($urls[$url])) {
                continue;
            }

            $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'ping');
            $cur->setField('post_id', (int) $new_post_id);
            $cur->setField('ping_url', $url);
            $cur->setField('ping_dt', $rs->f('ping_dt'));
            $cur->insert();

            $urls[$url] = true;
        }
    }

    // Meta import
    protected function importMeta(int $post_id, int $new_post_id, AbstractConnection $db): void
    {
        $rs = $db->select(
            'SELECT * FROM ' . $this->vars['db_prefix'] . 'post_meta ' .
            'WHERE post_id = ' . (int) $post_id . ' '
        );

        if ($rs->isEmpty()) {
            return;
        }

        while ($rs->fetch()) {
            dotclear()->meta()->setPostMeta($new_post_id, $this->cleanStr($rs->f('meta_key')), $this->cleanStr($rs->f('meta_value')));
        }
    }
}
