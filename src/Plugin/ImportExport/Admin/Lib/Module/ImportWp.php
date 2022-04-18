<?php
/**
 * @note Dotclear\Plugin\ImportExport\Admin\Lib\Module\ImportWp
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginImportExport
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Admin\Lib\Module;

use ArrayObject;
use Dotclear\Database\AbstractConnection;
use Dotclear\Exception\ModuleException;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Plugin\ImportExport\Admin\Lib\Module;
use Dotclear\Helper\Crypt;
use Dotclear\Helper\Text;
use Exception;

class ImportWp extends Module
{
    protected $action;
    protected $step = 1;

    protected $post_offset = 0;
    protected $post_limit  = 20;
    protected $post_count  = 0;

    protected $has_table = [];

    protected $vars;
    protected $base_vars = [
        'db_host'            => '',
        'db_name'            => '',
        'db_user'            => '',
        'db_pwd'             => '',
        'db_prefix'          => 'wp_',
        'ignore_first_cat'   => 1,
        'cat_import'         => 1,
        'cat_as_tags'        => '',
        'cat_tags_prefix'    => 'cat: ',
        'post_limit'         => 20,
        'post_formater'      => 'xhtml',
        'comment_formater'   => 'xhtml',
        'user_ids'           => [],
        'cat_ids'            => [],
        'permalink_template' => 'p=%post_id%',
        'permalink_tags'     => [
            '%year%',
            '%monthnum%',
            '%day%',
            '%hour%',
            '%minute%',
            '%second%',
            '%postname%',
            '%post_id%',
            '%category%',
            '%author%',
        ],
    ];
    protected $formaters;

    protected function setInfo()
    {
        $this->type        = 'import';
        $this->name        = __('WordPress import');
        $this->description = __('Import a WordPress installation into your current blog.');
    }

    public function init()
    {
        if (!isset($_SESSION['wp_import_vars'])) {
            $_SESSION['wp_import_vars'] = $this->base_vars;
        }
        $this->vars = &$_SESSION['wp_import_vars'];

        if (0 < $this->vars['post_limit']) {
            $this->post_limit = $this->vars['post_limit'];
        }

        $this->formaters = dotclear()->combo()->getFormatersCombo();
    }

    public function resetVars()
    {
        $this->vars = $this->base_vars;
        unset($_SESSION['wp_import_vars']);
    }

    public function process($do)
    {
        $this->action = $do;
    }

    // We handle process in another way to always display something to
    // user
    protected function guiprocess($do)
    {
        switch ($do) {
            case 'step1':
                $this->vars['db_host']          = $_POST['db_host'];
                $this->vars['db_name']          = $_POST['db_name'];
                $this->vars['db_user']          = $_POST['db_user'];
                $this->vars['db_pwd']           = $_POST['db_pwd'];
                $this->vars['db_prefix']        = $_POST['db_prefix'];
                $this->vars['ignore_first_cat'] = isset($_POST['ignore_first_cat']);
                $this->vars['cat_import']       = isset($_POST['cat_import']);
                $this->vars['cat_as_tags']      = isset($_POST['cat_as_tags']);
                $this->vars['cat_tags_prefix']  = $_POST['cat_tags_prefix'];
                $this->vars['post_limit']       = abs((int) $_POST['post_limit']) > 0 ? $_POST['post_limit'] : 0;
                $this->vars['post_formater']    = isset($this->formaters[$_POST['post_formater']]) ? $_POST['post_formater'] : 'xhtml';
                $this->vars['comment_formater'] = isset($this->formaters[$_POST['comment_formater']]) ? $_POST['comment_formater'] : 'xhtml';
                $db                             = $this->db();
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
        } catch (Exception $e) {
            $this->error($e);
        }

        switch ($this->step) {
            case 1:
                echo '<p>' . sprintf(
                    __('This will import your WordPress content as new content in the current blog: %s.'),
                    '<strong>' . Html::escapeHTML(dotclear()->blog()->name) . '</strong>'
                ) . '</p>' .
                '<p class="warning">' . __('Please note that this process ' .
                    'will empty your categories, blogroll, entries and comments on the current blog.') . '</p>';

                printf(
                    $this->imForm(1, __('General information'), __('Import my blog now')),
                    '<p>' . __('We first need some information about your old WordPress installation.') . '</p>' .
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
                    '<div class="two-cols">' .

                    '<div class="col">' .
                    '<p>' . __('WordPress and Dotclear\'s handling of categories are quite different. ' .
                        'You can assign several categories to a single post in WordPress. In the Dotclear world, ' .
                        'we see it more like "One category, several tags." Therefore Dotclear can only import one ' .
                        'category per post and will chose the lowest numbered one. If you want to keep a trace of ' .
                        'every category, you can import them as tags, with an optional prefix.') . '</p>' .
                    '<p>' . __('On the other hand, in WordPress, a post can not be uncategorized, and a ' .
                        'default installation has a first category labelised <i>"Uncategorized"</i>.' .
                        'If you did not change that category, you can just ignore it while ' .
                        'importing your blog, as Dotclear allows you to actually keep your posts ' .
                        'uncategorized.') . '</p>' .
                    '</div>' .

                    '<div class="col">' .
                    '<p><label for="ignore_first_cat" class="classic">' . Form::checkbox('ignore_first_cat', 1, $this->vars['ignore_first_cat']) . ' ' .
                    __('Ignore the first category:') . '</label></p>' .
                    '<p><label for="cat_import" class="classic">' . Form::checkbox('cat_import', 1, $this->vars['cat_import']) . ' ' .
                    __('Import lowest numbered category on posts:') . '</label></p>' .
                    '<p><label for="cat_as_tags" class="classic">' . Form::checkbox('cat_as_tags', 1, $this->vars['cat_as_tags']) . ' ' .
                    __('Import all categories as tags:') . '</label></p>' .
                    '<p><label for="cat_tags_prefix">' . __('Prefix such tags with:') . '</label> ' .
                    Form::field('cat_tags_prefix', 10, 20, Html::escapeHTML($this->vars['cat_tags_prefix'])) . '</p>' .
                    '<p><label for="post_limit">' . __('Number of entries to import at once:') . '</label> ' .
                    Form::number('post_limit', 0, 999, Html::escapeHTML($this->vars['post_limit'])) . '</p>' .
                    '</div>' .

                    '</div>' .

                    '<h3 class="clear vertical-separator">' . __('Content filters') . '</h3>' .
                    '<p>' . __('You may want to process your post and/or comment content with the following filters.') . '</p>' .
                    '<p><label for="post_formater">' . __('Post content formatter:') . '</label> ' .
                    Form::combo('post_formater', $this->formaters, $this->vars['post_formater']) . '</p>' .
                    '<p><label for="comment_formater">' . __('Comment content formatter:') . '</label> '
                    . Form::combo('comment_formater', $this->formaters, $this->vars['comment_formater']) . '</p>'
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
                echo '<p class="message">' . __('Every newly imported user has received a random password ' .
                    'and will need to ask for a new one by following the "I forgot my password" link on the login page ' .
                    '(Their registered email address has to be valid.)') . '</p>' .
                $this->congratMessage();

                break;
        }
    }

    // Simple form for step by step process
    protected function imForm($step, $legend, $submit_value = null)
    {
        if (!$submit_value) {
            $submit_value = __('next step') . ' >';
        }

        return
        '<form action="' . $this->getURL(true) . '" method="post">' .
        '<h3 class="vertical-separator">' . $legend . '</h3>' .
        '<div>' .
        dotclear()->nonce()->form() .
        Form::hidden(['handler'], 'admin.plugin.ImportExport') .
        Form::hidden(['do'], 'step' . $step) .
        '%s' . '</div>' .
        '<p><input type="submit" value="' . $submit_value . '" /></p>' .
        '<p class="form-note info">' . __('Depending on the size of your blog, it could take a few minutes.') . '</p>' .
            '</form>';
    }

    // Error display
    protected function error($e)
    {
        echo '<div class="error"><strong>' . __('Errors:') . '</strong>' .
        '<p>' . $e->getMessage() . '</p></div>';
    }

    // Database init
    protected function db()
    {
        $db = AbstractConnection::init('mysqli', $this->vars['db_host'], $this->vars['db_name'], $this->vars['db_user'], $this->vars['db_pwd']);

        $rs = $db->select("SHOW TABLES LIKE '" . $this->vars['db_prefix'] . "%'");
        if ($rs->isEmpty()) {
            throw new ModuleException(__('WordPress tables not found'));
        }

        while ($rs->fetch()) {
            $this->has_table[$rs->f(0)] = true;
        }

        // Set this to read data as they were written
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
            'SELECT COUNT(ID) FROM ' . $this->vars['db_prefix'] . 'posts ' .
            'WHERE post_type = \'post\' OR post_type = \'page\''
        )->fInt();

        return $db;
    }

    protected function cleanStr($str)
    {
        return Text::cleanUTF8(@Text::toUTF8($str));
    }

    // Users import
    protected function importUsers()
    {
        $db     = $this->db();
        $prefix = $this->vars['db_prefix'];
        $rs     = $db->select('SELECT * FROM ' . $prefix . 'users');

        try {
            dotclear()->con()->begin();

            while ($rs->fetch()) {
                $user_login                           = preg_replace('/[^A-Za-z0-9@._-]/', '-', $rs->f('user_login'));
                $this->vars['user_ids'][$rs->f('ID')] = $user_login;
                if (!dotclear()->users()->userExists($user_login)) {
                    $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'user');
                    $cur->setField('user_id', $user_login);
                    $cur->setField('user_pwd', Crypt::createPassword());
                    $cur->setField('user_displayname', $rs->f('user_nicename'));
                    $cur->setField('user_email', $rs->f('user_email'));
                    $cur->setField('user_url', $rs->f('user_url'));
                    $cur->setField('user_creadt', $rs->f('user_registered'));
                    $cur->setField('user_lang', dotclear()->blog()->settings()->get('system')->get('lang'));
                    $cur->setField('user_tz', dotclear()->blog()->settings()->get('system')->get('blog_timezone'));
                    $permissions = [];

                    $rs_meta = $db->select('SELECT * FROM ' . $prefix . 'usermeta WHERE user_id = ' . $rs->fInt('ID'));
                    while ($rs_meta->fetch()) {
                        switch ($rs_meta->f('meta_key')) {
                            case 'first_name':
                                $cur->setField('user_firstname', $this->cleanStr($rs_meta->f('meta_value')));

                                break;

                            case 'last_name':
                                $cur->setField('user_name', $this->cleanStr($rs_meta->f('meta_value')));

                                break;

                            case 'description':
                                $cur->setField('user_desc', $this->cleanStr($rs_meta->f('meta_value')));

                                break;

                            case 'rich_editing':
                                $cur->setField('user_options', new ArrayObject([
                                    'enable_wysiwyg' => 'true' == $rs_meta->f('meta_value') ? true : false,
                                ]));

                                break;

                            case 'wp_user_level':
                                switch ($rs_meta->f('meta_value')) {
                                    case '0': // Subscriber
                                        $cur->setField('user_status', 0);

                                        break;

                                    case '1': // Contributor
                                        $permissions['usage']   = true;
                                        $permissions['publish'] = true;
                                        $permissions['delete']  = true;

                                        break;

                                    case '2': // Author
                                    case '3':
                                    case '4':
                                        $permissions['contentadmin'] = true;
                                        $permissions['media']        = true;

                                        break;

                                    case '5': // Editor
                                    case '6':
                                    case '7':
                                        $permissions['contentadmin'] = true;
                                        $permissions['categories']   = true;
                                        $permissions['media_admin']  = true;
                                        $permissions['pages']        = true;
                                        $permissions['blogroll']     = true;

                                        break;

                                    case '8': // Administrator
                                    case '9':
                                    case '10':
                                        $permissions['admin'] = true;

                                        break;
                                }

                                break;
                        }
                    }
                    dotclear()->users()->addUser($cur);
                    dotclear()->users()->setUserBlogPermissions(
                        $cur->getField('user_id'),
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
    protected function importCategories()
    {
        $db     = $this->db();
        $prefix = $this->vars['db_prefix'];
        $rs     = $db->select(
            'SELECT * FROM ' . $prefix . 'terms AS t, ' . $prefix . 'term_taxonomy AS x ' .
            'WHERE x.taxonomy = \'category\' ' .
            'AND t.term_id = x.term_id ' .
            ($this->vars['ignore_first_cat'] ? 'AND t.term_id <> 1 ' : '') .
            'ORDER BY t.term_id ASC'
        );

        try {
            dotclear()->con()->execute(
                'DELETE FROM ' . dotclear()->prefix . 'category ' .
                "WHERE blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' "
            );

            $ord = 2;
            while ($rs->fetch()) {
                $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'category');
                $cur->setField('blog_id', dotclear()->blog()->id);
                $cur->setField('cat_title', $this->cleanStr($rs->f('name')));
                $cur->setField('cat_desc', $this->cleanStr($rs->f('description')));
                $cur->setField('cat_url', $this->cleanStr($rs->f('slug')));
                $cur->setField('cat_lft', $ord++);
                $cur->setField('cat_rgt', $ord++);

                $cur->setField('cat_id', dotclear()->con()->select(
                    'SELECT MAX(cat_id) FROM ' . dotclear()->prefix . 'category'
                )->fInt() + 1);
                $this->vars['cat_ids'][$rs->f('term_id')] = $cur->getField('cat_id');
                $cur->insert();
            }

            $db->close();
        } catch (Exception $e) {
            $db->close();

            throw $e;
        }
    }

    // Blogroll import
    protected function importLinks()
    {
        $db     = $this->db();
        $prefix = $this->vars['db_prefix'];
        $rs     = $db->select('SELECT * FROM ' . $prefix . 'links ORDER BY link_id ASC');

        try {
            dotclear()->con()->execute(
                'DELETE FROM ' . dotclear()->prefix . 'link ' .
                "WHERE blog_id = '" . dotclear()->con()->escape(dotclear()->blog()->id) . "' "
            );

            while ($rs->fetch()) {
                $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'link');
                $cur->setField('blog_id', dotclear()->blog()->id);
                $cur->setField('link_href', $this->cleanStr($rs->f('link_url')));
                $cur->setField('link_title', $this->cleanStr($rs->f('link_name')));
                $cur->setField('link_desc', $this->cleanStr($rs->f('link_description')));
                $cur->setField('link_xfn', $this->cleanStr($rs->f('link_rel')));
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
    protected function importPosts(&$percent)
    {
        $db     = $this->db();
        $prefix = $this->vars['db_prefix'];

        $plink = $db->select(
            'SELECT option_value FROM ' . $prefix . 'options ' .
            "WHERE option_name = 'permalink_structure'"
        )->f('option_value');
        if ($plink) {
            $this->vars['permalink_template'] = substr($plink, 1);
        }

        $rs = $db->select(
            'SELECT * FROM ' . $prefix . 'posts ' .
            'WHERE post_type = \'post\' OR post_type = \'page\' ' .
            'ORDER BY ID ASC ' .
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
            $percent = $this->post_offset * 100 / $this->post_count;
        }
    }

    protected function importPost($rs, $db)
    {
        $post_date = !@strtotime($rs->f('post_date')) ? '1970-01-01 00:00' : $rs->f('post_date');
        if (!isset($this->vars['user_ids'][$rs->f('post_author')])) {
            $user_id = dotclear()->user()->userID();
        } else {
            $user_id = $this->vars['user_ids'][$rs->f('post_author')];
        }

        $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'post');
        $cur->setField('blog_id', dotclear()->blog()->id);
        $cur->setField('user_id', $user_id);
        $cur->setField('post_dt', $post_date);
        $cur->setField('post_creadt', $post_date);
        $cur->setField('post_upddt', $rs->f('post_modified'));
        $cur->setField('post_title', $this->cleanStr($rs->f('post_title')));

        if (!$cur->getField('post_title')) {
            $cur->setField('post_title', 'No title');
        }

        if ($this->vars['cat_import'] || $this->vars['cat_as_tags']) {
            $old_cat_ids = $db->select(
                'SELECT * FROM ' . $this->vars['db_prefix'] . 'terms AS t, ' .
                $this->vars['db_prefix'] . 'term_taxonomy AS x, ' .
                $this->vars['db_prefix'] . 'term_relationships AS r ' .
                'WHERE t.term_id = x.term_id ' .
                ($this->vars['ignore_first_cat'] ? 'AND t.term_id <> 1 ' : '') .
                'AND x.taxonomy = \'category\' ' .
                'AND t.term_id = r.term_taxonomy_id ' .
                'AND r.object_id =' . $rs->fInt('ID') .
                ' ORDER BY t.term_id ASC '
            );
            if (!$old_cat_ids->isEmpty() && $this->vars['cat_import']) {
                $cur->setField('cat_id', $this->vars['cat_ids'][$old_cat_ids->fInt('term_id')]);
            }
        }

        $permalink_infos = [
            date('Y', strtotime($cur->getField('post_dt'))),
            date('m', strtotime($cur->getField('post_dt'))),
            date('d', strtotime($cur->getField('post_dt'))),
            date('H', strtotime($cur->getField('post_dt'))),
            date('i', strtotime($cur->getField('post_dt'))),
            date('s', strtotime($cur->getField('post_dt'))),
            $rs->f('post_name'),
            $rs->fInt('ID'),
            $cur->fInt('cat_id'),
            $cur->f('user_id'),
        ];
        $cur->setField('post_url', str_replace(
            $this->vars['permalink_tags'],
            $permalink_infos,
            'post' == $rs->f('post_type') ? $this->vars['permalink_template'] : '%postname%'
        ));
        $cur->setField('post_url', substr($cur->getField('post_url'), 0, 255));

        if (!$cur->getField('post_url')) {
            $cur->setField('post_url', $rs->fInt('ID'));
        }

        $cur->setField('post_format', $this->vars['post_formater']);
        $_post_content = explode('<!--more-->', $rs->f('post_content'), 2);
        if (count($_post_content) == 1) {
            $cur->setField('post_excerpt', null);
            $cur->setField('post_content', $this->cleanStr(array_shift($_post_content)));
        } else {
            $cur->setField('post_excerpt', $this->cleanStr(array_shift($_post_content)));
            $cur->setField('post_content', $this->cleanStr(array_shift($_post_content)));
        }

        $cur->setField('post_content_xhtml', dotclear()->formater()->callEditorFormater('LegacyEditor', $this->vars['post_formater'], $cur->f('post_content')));
        $cur->setField('post_excerpt_xhtml', dotclear()->formater()->callEditorFormater('LegacyEditor', $this->vars['post_formater'], $cur->f('post_excerpt')));

        $cur->setField('post_status', match ($rs->fint('post_status')) {
            'publish' => 1,
            'draft'   => 0,
            default   => -2,
        });
        $cur->setField('post_type', $rs->f('post_type'));
        $cur->setField('post_password', $rs->f('post_password') ?: null);
        $cur->setField('post_open_comment', $rs->f('comment_status') == 'open' ? 1 : 0);
        $cur->setField('post_open_tb', $rs->f('ping_status')         == 'open' ? 1 : 0);

        $cur->setField('post_words', implode(' ', Text::splitWords(
            $cur->getField('post_title') . ' ' .
            $cur->getField('post_excerpt_xhtml') . ' ' .
            $cur->getField('post_content_xhtml')
        )));

        $cur->setField('post_id', dotclear()->con()->select(
            'SELECT MAX(post_id) FROM ' . dotclear()->prefix . 'post'
        )->fInt() + 1);

        $cur->setField('post_url', dotclear()->blog()->posts()->getPostURL($cur->getField('post_url'), $cur->getField('post_dt'), $cur->getField('post_title'), (int) $cur->getField('post_id')));

        $cur->insert();
        $this->importComments($rs->fInt('ID'), $cur->getField('post_id'), $db);
        $this->importPings($rs->fInt('ID'), $cur->getField('post_id'), $db);

        // Create tags
        $this->importTags($rs->fInt('ID'), $cur->getField('post_id'), $db);

        if (isset($old_cat_ids)) {
            if (!$old_cat_ids->isEmpty() && $this->vars['cat_as_tags']) {
                $old_cat_ids->moveStart();
                while ($old_cat_ids->fetch()) {
                    dotclear()->meta()->setPostMeta($cur->fInt('post_id'), 'tag', $this->cleanStr($this->vars['cat_tags_prefix'] . $old_cat_ids->f('name')));
                }
            }
        }
    }

    // Comments import
    protected function importComments($post_id, $new_post_id, $db)
    {
        $count_c = $count_t = 0;

        $rs = $db->select(
            'SELECT * FROM ' . $this->vars['db_prefix'] . 'comments ' .
            'WHERE comment_post_ID = ' . (int) $post_id . ' '
        );

        while ($rs->fetch()) {
            $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'comment');
            $cur->setField('post_id', (int) $new_post_id);
            $cur->setField('comment_author', $this->cleanStr($rs->f('comment_author')));
            $cur->setField('comment_status', $rs->fInt('comment_approved'));
            $cur->setField('comment_dt', $rs->f('comment_date'));
            $cur->setField('comment_email', $this->cleanStr($rs->f('comment_author_email')));
            $cur->setField('comment_content', dotclear()->formater()->callEditorFormater('LegacyEditor', $this->vars['comment_formater'], $this->cleanStr($rs->f('comment_content'))));
            $cur->setField('comment_ip', $rs->f('comment_author_IP'));
            $cur->setField('comment_trackback', $rs->f('comment_type') == 'trackback' ? 1 : 0);
            $cur->setField('comment_site', substr($this->cleanStr($rs->f('comment_author_url')), 0, 255));
            if ('' == $cur->getField('comment_site')) {
                $cur->setField('comment_site', null);
            }

            if ('spam' == $rs->f('comment_approved')) {
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
    protected function importPings($post_id, $new_post_id, $db)
    {
        $urls  = [];
        $pings = [];

        $rs = $db->select(
            'SELECT pinged FROM ' . $this->vars['db_prefix'] . 'posts ' .
            'WHERE ID = ' . (int) $post_id
        );
        $pings = explode("\n", $rs->f('pinged'));
        unset($pings[0]);

        foreach ($pings as $ping_url) {
            $url = $this->cleanStr($ping_url);
            if (isset($urls[$url])) {
                continue;
            }

            $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'ping');
            $cur->setField('post_id', (int) $new_post_id);
            $cur->setField('ping_url', $url);
            $cur->insert();

            $urls[$url] = true;
        }
    }

    // Meta import
    protected function importTags($post_id, $new_post_id, $db)
    {
        $rs = $db->select(
            'SELECT * FROM ' . $this->vars['db_prefix'] . 'terms AS t, ' .
            $this->vars['db_prefix'] . 'term_taxonomy AS x, ' .
            $this->vars['db_prefix'] . 'term_relationships AS r ' .
            'WHERE t.term_id = x.term_id ' .
            'AND x.taxonomy = \'post_tag\' ' .
            'AND t.term_id = r.term_taxonomy_id ' .
            'AND r.object_id =' . $post_id .
            ' ORDER BY t.term_id ASC'
        );

        if ($rs->isEmpty()) {
            return;
        }

        while ($rs->fetch()) {
            dotclear()->meta()->setPostMeta($new_post_id, 'tag', $this->cleanStr($rs->f('name')));
        }
    }
}
