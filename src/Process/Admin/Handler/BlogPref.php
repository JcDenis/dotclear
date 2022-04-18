<?php
/**
 * @note Dotclear\Process\Admin\Handler\BlogPref
 * @brief Dotclear admin blog preference page
 *
 * @ingroup  Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

use Dotclear\Core\User\UserContainer;
use Dotclear\Core\Blog\Settings\Settings;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Dt;
use Dotclear\Helper\Lexical;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Network\NetHttp\NetHttp;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

class BlogPref extends AbstractPage
{
    private $blog_id     = true;
    private $blog_status = 0;
    private $blog_name   = '';
    private $blog_desc   = '';
    private $blog_settings;
    private $blog_url    = '';
    private $blog_action = '';

    public function __construct(string $handler = 'admin.home', private bool $standalone = true)
    {
        parent::__construct($handler);
    }

    protected function getPermissions(): string|null|false
    {
        return $this->standalone ? 'admin' : null;
    }

    protected function getPagePrepend(): ?bool
    {
        // Blog params
        if ($this->standalone) {
            $this->blog_id       = dotclear()->blog()->id;
            $this->blog_status   = dotclear()->blog()->status;
            $this->blog_name     = dotclear()->blog()->name;
            $this->blog_desc     = dotclear()->blog()->desc;
            $this->blog_settings = dotclear()->blog()->settings();
            $this->blog_url      = dotclear()->blog()->url;

            $this->blog_action = dotclear()->adminurl()->get('admin.blog.pref');
            $redir             = dotclear()->adminurl()->get('admin.blog.pref');
        } else {
            try {
                if (empty($_REQUEST['id'])) {
                    throw new AdminException(__('No given blog id.'));
                }
                $rs = dotclear()->blogs()->getBlog($_REQUEST['id']);

                if (!$rs) {
                    throw new AdminException(__('No such blog.'));
                }

                $this->blog_id       = $rs->f('blog_id');
                $this->blog_status   = $rs->fInt('blog_status');
                $this->blog_name     = $rs->f('blog_name');
                $this->blog_desc     = $rs->f('blog_desc');
                $this->blog_settings = new Settings($this->blog_id);
                $this->blog_url      = $rs->f('blog_url');
            } catch (Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }

            $this->blog_action = dotclear()->adminurl()->get('admin.blog');
            $redir             = dotclear()->adminurl()->get('admin.blog', ['id' => '%s'], '&', true);
        }

        // Update a blog
        if ($this->blog_id && !empty($_POST) && dotclear()->user()->check('admin', $this->blog_id)) {
            // URL scan modes
            $url_scan_combo = [
                'PATH_INFO'    => 'path_info',
                'QUERY_STRING' => 'query_string',
            ];

            // Status combo
            $status_combo = dotclear()->combo()->getBlogStatusescombo();

            $cur = dotclear()->con()->openCursor(dotclear()->prefix . 'blog');
            $cur->setField('blog_id', $_POST['blog_id']);
            $cur->setField('blog_url', preg_replace('/\?+$/', '?', $_POST['blog_url']));
            $cur->setField('blog_name', $_POST['blog_name']);
            $cur->setField('blog_desc', $_POST['blog_desc']);

            if (dotclear()->user()->isSuperAdmin() && in_array($_POST['blog_status'], $status_combo)) {
                $cur->setField('blog_status', (int) $_POST['blog_status']);
            }

            $media_img_t_size = (int) $_POST['media_img_t_size'];
            if (0 > $media_img_t_size) {
                $media_img_t_size = 100;
            }

            $media_img_s_size = (int) $_POST['media_img_s_size'];
            if (0 > $media_img_s_size) {
                $media_img_s_size = 240;
            }

            $media_img_m_size = (int) $_POST['media_img_m_size'];
            if (0 > $media_img_m_size) {
                $media_img_m_size = 448;
            }

            $media_video_width = (int) $_POST['media_video_width'];
            if (0 > $media_video_width) {
                $media_video_width = 400;
            }

            $media_video_height = (int) $_POST['media_video_height'];
            if (0 > $media_video_height) {
                $media_video_height = 300;
            }

            $nb_post_for_home = abs((int) $_POST['nb_post_for_home']);
            if (1 > $nb_post_for_home) {
                $nb_post_for_home = 1;
            }

            $nb_post_per_page = abs((int) $_POST['nb_post_per_page']);
            if (1 > $nb_post_per_page) {
                $nb_post_per_page = 1;
            }

            $nb_post_per_feed = abs((int) $_POST['nb_post_per_feed']);
            if (1 > $nb_post_per_feed) {
                $nb_post_per_feed = 1;
            }

            $nb_comment_per_feed = abs((int) $_POST['nb_comment_per_feed']);
            if (1 > $nb_comment_per_feed) {
                $nb_comment_per_feed = 1;
            }

            try {
                if ($cur->getField('blog_id') != null && $cur->getField('blog_id') != $this->blog_id) {
                    $rs = dotclear()->blogs()->getBlog($cur->getField('blog_id'));

                    if ($rs) {
                        throw new AdminException(__('This blog ID is already used.'));
                    }
                }

                // --BEHAVIOR-- adminBeforeBlogUpdate
                dotclear()->behavior()->call('adminBeforeBlogUpdate', $cur, $this->blog_id);

                if (!preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $_POST['lang'])) {
                    throw new AdminException(__('Invalid language code'));
                }

                dotclear()->blogs()->updBlog($this->blog_id, $cur);

                // --BEHAVIOR-- adminAfterBlogUpdate
                dotclear()->behavior()->call('adminAfterBlogUpdate', $cur, $this->blog_id);

                if ($cur->getField('blog_id') != null && $cur->getField('blog_id') != $this->blog_id) {
                    if (dotclear()->blog()->id == $this->blog_id) {
                        dotclear()->setBlog($cur->getField('blog_id'));
                        $_SESSION['sess_blog_id'] = $cur->getField('blog_id');
                        $this->blog_settings      = dotclear()->blog()->settings();
                    } else {
                        $this->blog_settings = new Settings($cur->getField('blog_id'));
                    }

                    $this->blog_id = $cur->getField('blog_id');
                }

                $this->blog_settings->get('system')->put('editor', $_POST['editor']);
                $this->blog_settings->get('system')->put('copyright_notice', $_POST['copyright_notice']);
                $this->blog_settings->get('system')->put('post_url_format', $_POST['post_url_format']);
                $this->blog_settings->get('system')->put('lang', $_POST['lang']);
                $this->blog_settings->get('system')->put('blog_timezone', $_POST['blog_timezone']);
                $this->blog_settings->get('system')->put('date_format', $_POST['date_format']);
                $this->blog_settings->get('system')->put('time_format', $_POST['time_format']);
                $this->blog_settings->get('system')->put('comments_ttl', abs((int) $_POST['comments_ttl']));
                $this->blog_settings->get('system')->put('trackbacks_ttl', abs((int) $_POST['trackbacks_ttl']));
                $this->blog_settings->get('system')->put('allow_comments', !empty($_POST['allow_comments']));
                $this->blog_settings->get('system')->put('allow_trackbacks', !empty($_POST['allow_trackbacks']));
                $this->blog_settings->get('system')->put('comments_pub', empty($_POST['comments_pub']));
                $this->blog_settings->get('system')->put('trackbacks_pub', empty($_POST['trackbacks_pub']));
                $this->blog_settings->get('system')->put('comments_nofollow', !empty($_POST['comments_nofollow']));
                $this->blog_settings->get('system')->put('wiki_comments', !empty($_POST['wiki_comments']));
                $this->blog_settings->get('system')->put('comment_preview_optional', !empty($_POST['comment_preview_optional']));
                $this->blog_settings->get('system')->put('enable_xmlrpc', !empty($_POST['enable_xmlrpc']));
                $this->blog_settings->get('system')->put('note_title_tag', $_POST['note_title_tag']);
                $this->blog_settings->get('system')->put('nb_post_for_home', $nb_post_for_home);
                $this->blog_settings->get('system')->put('nb_post_per_page', $nb_post_per_page);
                $this->blog_settings->get('system')->put('use_smilies', !empty($_POST['use_smilies']));
                $this->blog_settings->get('system')->put('no_search', !empty($_POST['no_search']));
                $this->blog_settings->get('system')->put('inc_subcats', !empty($_POST['inc_subcats']));
                $this->blog_settings->get('system')->put('media_img_t_size', $media_img_t_size);
                $this->blog_settings->get('system')->put('media_img_s_size', $media_img_s_size);
                $this->blog_settings->get('system')->put('media_img_m_size', $media_img_m_size);
                $this->blog_settings->get('system')->put('media_video_width', $media_video_width);
                $this->blog_settings->get('system')->put('media_video_height', $media_video_height);
                $this->blog_settings->get('system')->put('media_img_title_pattern', $_POST['media_img_title_pattern']);
                $this->blog_settings->get('system')->put('media_img_use_dto_first', !empty($_POST['media_img_use_dto_first']));
                $this->blog_settings->get('system')->put('media_img_no_date_alone', !empty($_POST['media_img_no_date_alone']));
                $this->blog_settings->get('system')->put('media_img_default_size', $_POST['media_img_default_size']);
                $this->blog_settings->get('system')->put('media_img_default_alignment', $_POST['media_img_default_alignment']);
                $this->blog_settings->get('system')->put('media_img_default_link', !empty($_POST['media_img_default_link']));
                $this->blog_settings->get('system')->put('media_img_default_legend', $_POST['media_img_default_legend']);
                $this->blog_settings->get('system')->put('nb_post_per_feed', $nb_post_per_feed);
                $this->blog_settings->get('system')->put('nb_comment_per_feed', $nb_comment_per_feed);
                $this->blog_settings->get('system')->put('short_feed_items', !empty($_POST['short_feed_items']));
                if (isset($_POST['robots_policy'])) {
                    $this->blog_settings->get('system')->put('robots_policy', $_POST['robots_policy']);
                }
                $this->blog_settings->get('system')->put('jquery_needed', !empty($_POST['jquery_needed']));
                $this->blog_settings->get('system')->put('jquery_version', $_POST['jquery_version']);
                $this->blog_settings->get('system')->put('prevents_clickjacking', !empty($_POST['prevents_clickjacking']));
                $this->blog_settings->get('system')->put('static_home', !empty($_POST['static_home']));
                $this->blog_settings->get('system')->put('static_home_url', $_POST['static_home_url']);

                // --BEHAVIOR-- adminBeforeBlogSettingsUpdate
                dotclear()->behavior()->call('adminBeforeBlogSettingsUpdate', $this->blog_settings);

                if (dotclear()->user()->isSuperAdmin() && in_array($_POST['url_scan'], $url_scan_combo)) {
                    $this->blog_settings->get('system')->put('url_scan', $_POST['url_scan']);
                }
                dotclear()->notice()->addSuccessNotice(__('Blog has been successfully updated.'));

                Http::redirect(sprintf($redir, $this->blog_id));
            } catch (Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        // Page setup
        $desc_editor = dotclear()->user()->getOption('editor');
        $rte_flag    = true;
        $rte_flags   = @dotclear()->user()->preference()->get('interface')->get('rte_flags');
        if (is_array($rte_flags) && in_array('blog_descr', $rte_flags)) {
            $rte_flag = $rte_flags['blog_descr'];
        }

        $this
            ->setPageTitle(__('Blog settings'))
            ->setPageHelp('core_blog_pref')
            ->setPageHead(
                dotclear()->resource()->json('blog_pref', [
                    'warning_path_info'    => __('Warning: except for special configurations, it is generally advised to have a trailing "/" in your blog URL in PATH_INFO mode.'),
                    'warning_query_string' => __('Warning: except for special configurations, it is generally advised to have a trailing "?" in your blog URL in QUERY_STRING mode.'),
                ]) .
                dotclear()->resource()->confirmClose('blog-form') .
                ($rte_flag ? dotclear()->behavior()->call('adminPostEditor', $desc_editor['xhtml'], 'blog_desc', ['#blog_desc'], 'xhtml') : '') .
                dotclear()->resource()->load('_blog_pref.js') .

                // --BEHAVIOR-- adminBlogPreferencesHeaders
                dotclear()->behavior()->call('adminBlogPreferencesHeaders') .

                dotclear()->resource()->pageTabs()
            )
            ->setPageBreadcrumb($this->standalone ? [
                Html::escapeHTML($this->blog_name) => '',
                __('Blog settings')                => '',
            ] : [
                __('System')                                                     => '',
                __('Blogs')                                                      => dotclear()->adminurl()->get('admin.blogs'),
                __('Blog settings') . ' : ' . Html::escapeHTML($this->blog_name) => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (!$this->blog_id) {
            return;
        }

        // Language codes
        $lang_combo = dotclear()->combo()->getAdminLangsCombo();

        // Status combo
        $status_combo = dotclear()->combo()->getBlogStatusescombo();

        // Date format combo
        $now                = time();
        $date_formats       = $this->blog_settings->get('system')->get('date_formats');
        $time_formats       = $this->blog_settings->get('system')->get('time_formats');
        $date_formats_combo = ['' => ''];
        foreach ($date_formats as $format) {
            $date_formats_combo[Dt::str($format, $now)] = $format;
        }
        $time_formats_combo = ['' => ''];
        foreach ($time_formats as $format) {
            $time_formats_combo[Dt::str($format, $now)] = $format;
        }

        // URL scan modes
        $url_scan_combo = [
            'PATH_INFO'    => 'path_info',
            'QUERY_STRING' => 'query_string',
        ];

        // Post URL combo
        $post_url_combo = [
            __('year/month/day/title') => '{y}/{m}/{d}/{t}',
            __('year/month/title')     => '{y}/{m}/{t}',
            __('year/title')           => '{y}/{t}',
            __('title')                => '{t}',
            __('post id/title')        => '{id}/{t}',
            __('post id')              => '{id}',
        ];
        if (!in_array($this->blog_settings->get('system')->get('post_url_format'), $post_url_combo)) {
            $post_url_combo[Html::escapeHTML($this->blog_settings->get('system')->get('post_url_format'))] = Html::escapeHTML($this->blog_settings->get('system')->get('post_url_format'));
        }

        // Note title tag combo
        $note_title_tag_combo = [
            __('H4') => 0,
            __('H3') => 1,
            __('P')  => 2,
        ];

        // Image title combo
        $img_title_combo = [
            __('(none)')                     => '',
            __('Title')                      => 'Title ;; separator(, )',
            __('Title, Date')                => 'Title ;; Date(%b %Y) ;; separator(, )',
            __('Title, Country, Date')       => 'Title ;; Country ;; Date(%b %Y) ;; separator(, )',
            __('Title, City, Country, Date') => 'Title ;; City ;; Country ;; Date(%b %Y) ;; separator(, )',
        ];
        if (!in_array($this->blog_settings->get('system')->get('media_img_title_pattern'), $img_title_combo)) {
            $img_title_combo[Html::escapeHTML($this->blog_settings->get('system')->get('media_img_title_pattern'))] = Html::escapeHTML($this->blog_settings->get('system')->get('media_img_title_pattern'));
        }

        // Image default size combo
        $img_default_size_combo[__('original')] = 'o';

        try {
            if (!dotclear()->media()) {
                throw new AdminException('No media path');
            }
            foreach (dotclear()->media()->thumb_sizes as $code => $size) {
                $img_default_size_combo[__($size[2])] = $code;
            }
        } catch (Exception $e) {
            dotclear()->error()->add($e->getMessage());
        }

        // Image default alignment combo
        $img_default_alignment_combo = [
            __('None')   => 'none',
            __('Left')   => 'left',
            __('Right')  => 'right',
            __('Center') => 'center',
        ];

        // Image default legend and title combo
        $img_default_legend_combo = [
            __('Legend and title') => 'legend',
            __('Title')            => 'title',
            __('None')             => 'none',
        ];

        // Robots policy options
        $robots_policy_options = [
            'INDEX,FOLLOW'               => __("I would like search engines and archivers to index and archive my blog's content."),
            'INDEX,FOLLOW,NOARCHIVE'     => __("I would like search engines and archivers to index but not archive my blog's content."),
            'NOINDEX,NOFOLLOW,NOARCHIVE' => __("I would like to prevent search engines and archivers from indexing or archiving my blog's content."),
        ];

        // jQuery available versions
        $jquery_root           = Path::implodeRoot('Core', 'resources', 'js', 'jquery');
        $jquery_versions_combo = [__('Default') . ' (' . dotclear()->config()->get('jquery_default') . ')' => ''];
        if (is_dir($jquery_root) && is_readable($jquery_root)) {
            if (false !== ($d = @dir($jquery_root))) {
                while (false !== ($entry = $d->read())) {
                    if ('.' != $entry && '..' != $entry && substr($entry, 0, 1) != '.' && is_dir($jquery_root . '/' . $entry)) {
                        if (dotclear()->config()->get('jquery_default') != $entry) {
                            $jquery_versions_combo[$entry] = $entry;
                        }
                    }
                }
            }
        }

        if (!empty($_GET['add'])) {
            dotclear()->notice()->success(__('Blog has been successfully created.'));
        }

        if (!empty($_GET['upd'])) {
            dotclear()->notice()->success(__('Blog has been successfully updated.'));
        }

        echo '<div class="multi-part" id="params" title="' . __('Parameters') . '">' .
        '<div id="standard-pref"><h3>' . __('Blog parameters') . '</h3>' .
            '<form action="' . $this->blog_action . '" method="post" id="blog-form">';

        echo '<div class="fieldset"><h4>' . __('Blog details') . '</h4>' .
        dotclear()->nonce()->form();

        echo '<p><label for="blog_name" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Blog name:') . '</label>' .
        Form::field(
            'blog_name',
            30,
            255,
            [
                'default'    => Html::escapeHTML($this->blog_name),
                'extra_html' => 'required placeholder="' . __('Blog name') . '" lang="' . $this->blog_settings->get('system')->get('lang') . '" spellcheck="true"',
            ]
        ) . '</p>';

        echo '<p class="area"><label for="blog_desc">' . __('Blog description:') . '</label>' .
        Form::textarea(
            'blog_desc',
            60,
            5,
            [
                'default'    => Html::escapeHTML($this->blog_desc),
                'extra_html' => 'lang="' . $this->blog_settings->get('system')->get('lang') . '" spellcheck="true"',
            ]
        ) . '</p>';

        if (dotclear()->user()->isSuperAdmin()) {
            echo '<p><label for="blog_status">' . __('Blog status:') . '</label>' .
            Form::combo('blog_status', $status_combo, $this->blog_status) . '</p>';
        } else {
            /*
            Only super admins can change the blog ID and URL, but we need to pass
            their values to the POST request via hidden html input values  so as
            to allow admins to update other settings.
            Otherwise dcCore::getBlogCursor() throws an exception.
             */
            echo Form::hidden('blog_id', Html::escapeHTML($this->blog_id)) .
            Form::hidden('blog_url', Html::escapeHTML($this->blog_url));
        }

        echo '</div>';

        echo '<div class="fieldset"><h4>' . __('Blog configuration') . '</h4>' .

        '<p><label for="editor">' . __('Blog editor name:') . '</label>' .
        Form::field('editor', 30, 255, Html::escapeHTML($this->blog_settings->get('system')->get('editor'))) .
        '</p>' .

        '<p><label for="lang">' . __('Default language:') . '</label>' .
        Form::combo('lang', $lang_combo, $this->blog_settings->get('system')->get('lang'), 'l10n') .
        '</p>' .

        '<p><label for="blog_timezone">' . __('Blog timezone:') . '</label>' .
        Form::combo('blog_timezone', Dt::getZones(true, true), Html::escapeHTML($this->blog_settings->get('system')->get('blog_timezone'))) .
        '</p>' .

        '<p><label for="copyright_notice">' . __('Copyright notice:') . '</label>' .
        Form::field(
            'copyright_notice',
            30,
            255,
            [
                'default'    => Html::escapeHTML($this->blog_settings->get('system')->get('copyright_notice')),
                'extra_html' => 'lang="' . $this->blog_settings->get('system')->get('lang') . '" spellcheck="true"',
            ]
        ) .
            '</p>' .

            '</div>';

        echo '<div class="fieldset"><h4>' . __('Comments and trackbacks') . '</h4>' .

        '<div class="two-cols">' .

        '<div class="col">' .
        '<p><label for="allow_comments" class="classic">' .
        Form::checkbox('allow_comments', '1', $this->blog_settings->get('system')->get('allow_comments')) .
        __('Accept comments') . '</label></p>' .
        '<p><label for="comments_pub" class="classic">' .
        Form::checkbox('comments_pub', '1', !$this->blog_settings->get('system')->get('comments_pub')) .
        __('Moderate comments') . '</label></p>' .
        '<p><label for="comments_ttl" class="classic">' . sprintf(
            __('Leave comments open for %s days') . '.',
            Form::number(
                'comments_ttl',
                [
                    'min'        => 0,
                    'max'        => 999,
                    'default'    => $this->blog_settings->get('system')->get('comments_ttl'),
                    'extra_html' => 'aria-describedby="comments_ttl_help"', ]
            )
        ) .
        '</label></p>' .
        '<p class="form-note" id="comments_ttl_help">' . __('No limit: leave blank.') . '</p>' .
        '<p><label for="wiki_comments" class="classic">' .
        Form::checkbox('wiki_comments', '1', $this->blog_settings->get('system')->get('wiki_comments')) .
        __('Wiki syntax for comments') . '</label></p>' .
        '<p><label for="comment_preview_optional" class="classic">' .
        Form::checkbox('comment_preview_optional', '1', $this->blog_settings->get('system')->get('comment_preview_optional')) .
        __('Preview of comment before submit is not mandatory') . '</label></p>' .
        '</div>' .

        '<div class="col">' .
        '<p><label for="allow_trackbacks" class="classic">' .
        Form::checkbox('allow_trackbacks', '1', $this->blog_settings->get('system')->get('allow_trackbacks')) .
        __('Accept trackbacks') . '</label></p>' .
        '<p><label for="trackbacks_pub" class="classic">' .
        Form::checkbox('trackbacks_pub', '1', !$this->blog_settings->get('system')->get('trackbacks_pub')) .
        __('Moderate trackbacks') . '</label></p>' .
        '<p><label for="trackbacks_ttl" class="classic">' . sprintf(
            __('Leave trackbacks open for %s days') . '.',
            Form::number(
                'trackbacks_ttl',
                [
                    'min'        => 0,
                    'max'        => 999,
                    'default'    => $this->blog_settings->get('system')->get('trackbacks_ttl'),
                    'extra_html' => 'aria-describedby="trackbacks_ttl_help"', ]
            )
        ) .
        '</label></p>' .
        '<p class="form-note" id="trackbacks_ttl_help">' . __('No limit: leave blank.') . '</p>' .
        '<p><label for="comments_nofollow" class="classic">' .
        Form::checkbox('comments_nofollow', '1', $this->blog_settings->get('system')->get('comments_nofollow')) .
        __('Add "nofollow" relation on comments and trackbacks links') . '</label></p>' .
        '</div>' .
        '<br class="clear" />' . // Opera sucks

        '</div>' .
        '<br class="clear" />' . // Opera sucks
        '</div>';

        echo '<div class="fieldset"><h4>' . __('Blog presentation') . '</h4>' .
        '<div class="two-cols">' .
        '<div class="col">' .
        '<p><label for="date_format">' . __('Date format:') . '</label> ' .
        Form::field('date_format', 30, 255, Html::escapeHTML($this->blog_settings->get('system')->get('date_format')), '', '', false, 'aria-describedby="date_format_help"') .
        Form::combo('date_format_select', $date_formats_combo, ['extra_html' => 'title="' . __('Pattern of date') . '"']) .
        '</p>' .
        '<p class="chosen form-note" id="date_format_help">' . __('Sample:') . ' ' . Dt::str(Html::escapeHTML($this->blog_settings->get('system')->get('date_format'))) . '</p>' .

        '<p><label for="time_format">' . __('Time format:') . '</label>' .
        Form::field('time_format', 30, 255, Html::escapeHTML($this->blog_settings->get('system')->get('time_format')), '', '', false, 'aria-describedby="time_format_help"') .
        Form::combo('time_format_select', $time_formats_combo, ['extra_html' => 'title="' . __('Pattern of time') . '"']) .
        '</p>' .
        '<p class="chosen form-note" id="time_format_help">' . __('Sample:') . ' ' . Dt::str(Html::escapeHTML($this->blog_settings->get('system')->get('time_format'))) . '</p>' .

        '<p><label for="use_smilies" class="classic">' .
        Form::checkbox('use_smilies', '1', $this->blog_settings->get('system')->get('use_smilies')) .
        __('Display smilies on entries and comments') . '</label></p>' .

        '<p><label for="no_search" class="classic">' .
        Form::checkbox('no_search', '1', $this->blog_settings->get('system')->get('no_search')) .
        __('Disable internal search system') . '</label></p>' .

        '</div>' .

        '<div class="col">' .

        '<p><label for="nb_post_for_home" class="classic">' . sprintf(
            __('Display %s entries on first page'),
            Form::number(
                'nb_post_for_home',
                [
                    'min'     => 1,
                    'max'     => 999,
                    'default' => $this->blog_settings->get('system')->get('nb_post_for_home'), ]
            )
        ) .
        '</label></p>' .

        '<p><label for="nb_post_per_page" class="classic">' . sprintf(
            __('Display %s entries per page'),
            Form::number(
                'nb_post_per_page',
                [
                    'min'     => 1,
                    'max'     => 999,
                    'default' => $this->blog_settings->get('system')->get('nb_post_per_page'), ]
            )
        ) .
        '</label></p>' .

        '<p><label for="nb_post_per_feed" class="classic">' . sprintf(
            __('Display %s entries per feed'),
            Form::number(
                'nb_post_per_feed',
                [
                    'min'     => 1,
                    'max'     => 999,
                    'default' => $this->blog_settings->get('system')->get('nb_post_per_feed'), ]
            )
        ) .
        '</label></p>' .

        '<p><label for="nb_comment_per_feed" class="classic">' . sprintf(
            __('Display %s comments per feed'),
            Form::number(
                'nb_comment_per_feed',
                [
                    'min'     => 1,
                    'max'     => 999,
                    'default' => $this->blog_settings->get('system')->get('nb_comment_per_feed'), ]
            )
        ) .
        '</label></p>' .

        '<p><label for="short_feed_items" class="classic">' .
        Form::checkbox('short_feed_items', '1', $this->blog_settings->get('system')->get('short_feed_items')) .
        __('Truncate feeds') . '</label></p>' .

        '<p><label for="inc_subcats" class="classic">' .
        Form::checkbox('inc_subcats', '1', $this->blog_settings->get('system')->get('inc_subcats')) .
        __('Include sub-categories in category page and category posts feed') . '</label></p>' .
        '</div>' .
        '</div>' .
        '<br class="clear" />' . // Opera sucks

        '<hr />' .

        '<p><label for="static_home" class="classic">' .
        Form::checkbox('static_home', '1', $this->blog_settings->get('system')->get('static_home')) .
        __('Display an entry as static home page') . '</label></p>' .

        '<p><label for="static_home_url" class="classic">' . __('Entry URL (its content will be used for the static home page):') . '</label> ' .
        Form::field('static_home_url', 30, 255, Html::escapeHTML($this->blog_settings->get('system')->get('static_home_url')), '', '', false, 'aria-describedby="static_home_url_help"') .
        ' <button type="button" id="static_home_url_selector">' . __('Choose an entry') . '</button>' .
        '</p>' .
        '<p class="form-note" id="static_home_url_help">' . __('Leave empty to use the default presentation.') . '</p> ' .

        '</div>';

        echo '<div class="fieldset"><h4 id="medias-settings">' . __('Media and images') . '</h4>' .
        '<p class="form-note warning">' .
        __('Please note that if you change current settings bellow, they will now apply to all new images in the media manager.') .
        ' ' . __('Be carefull if you share it with other blogs in your installation.') . '<br />' .
        __('Set -1 to use the default size, set 0 to ignore this thumbnail size (images only).') . '</p>' .

        '<div class="two-cols">' .
        '<div class="col">' .
        '<h5>' . __('Generated image sizes (max dimension in pixels)') . '</h5>' .
        '<p class="field"><label for="media_img_t_size">' . __('Thumbnail') . '</label> ' .
        Form::number('media_img_t_size', [
            'min'     => -1,
            'max'     => 999,
            'default' => $this->blog_settings->get('system')->get('media_img_t_size'),
        ]) .
        '</p>' .

        '<p class="field"><label for="media_img_s_size">' . __('Small') . '</label> ' .
        Form::number('media_img_s_size', [
            'min'     => -1,
            'max'     => 999,
            'default' => $this->blog_settings->get('system')->get('media_img_s_size'),
        ]) .
        '</p>' .

        '<p class="field"><label for="media_img_m_size">' . __('Medium') . '</label> ' .
        Form::number('media_img_m_size', [
            'min'     => -1,
            'max'     => 999,
            'default' => $this->blog_settings->get('system')->get('media_img_m_size'),
        ]) .
        '</p>' .

        '<h5>' . __('Default size of the inserted video (in pixels)') . '</h5>' .
        '<p class="field"><label for="media_video_width">' . __('Width') . '</label> ' .
        Form::number('media_video_width', [
            'min'     => -1,
            'max'     => 999,
            'default' => $this->blog_settings->get('system')->get('media_video_width'),
        ]) .
        '</p>' .

        '<p class="field"><label for="media_video_height">' . __('Height') . '</label> ' .
        Form::number('media_video_height', [
            'min'     => -1,
            'max'     => 999,
            'default' => $this->blog_settings->get('system')->get('media_video_height'),
        ]) .
        '</p>' .
        '</div>' .

        '<div class="col">' .
        '<h5>' . __('Default image insertion attributes') . '</h5>' .
        '<p class="vertical-separator"><label for="media_img_title_pattern">' . __('Inserted image title') . '</label>' .
        Form::combo('media_img_title_pattern', $img_title_combo, Html::escapeHTML($this->blog_settings->get('system')->get('media_img_title_pattern'))) . '</p>' .
        '<p><label for="media_img_use_dto_first" class="classic">' .
        Form::checkbox('media_img_use_dto_first', '1', $this->blog_settings->get('system')->get('media_img_use_dto_first')) .
        __('Use original media date if possible') . '</label></p>' .
        '<p><label for="media_img_no_date_alone" class="classic">' .
        Form::checkbox('media_img_no_date_alone', '1', $this->blog_settings->get('system')->get('media_img_no_date_alone'), '', '', false, 'aria-describedby="media_img_no_date_alone_help"') .
        __('Do not display date if alone in title') . '</label></p>' .
        '<p class="form-note info" id="media_img_no_date_alone_help">' . __('It is retrieved from the picture\'s metadata.') . '</p>' .

        '<p class="field vertical-separator"><label for="media_img_default_size">' . __('Size of inserted image:') . '</label>' .
        Form::combo(
            'media_img_default_size',
            $img_default_size_combo,
            (Html::escapeHTML($this->blog_settings->get('system')->get('media_img_default_size')) != '' ? Html::escapeHTML($this->blog_settings->get('system')->get('media_img_default_size')) : 'm')
        ) .
        '</p>' .
        '<p class="field"><label for="media_img_default_alignment">' . __('Image alignment:') . '</label>' .
        Form::combo('media_img_default_alignment', $img_default_alignment_combo, Html::escapeHTML($this->blog_settings->get('system')->get('media_img_default_alignment'))) .
        '</p>' .
        '<p><label for="media_img_default_link">' .
        Form::checkbox('media_img_default_link', '1', $this->blog_settings->get('system')->get('media_img_default_link')) .
        __('Insert a link to the original image') . '</label></p>' .
        '<p class="field"><label for="media_img_default_legend">' . __('Image legend and title:') . '</label>' .
        Form::combo('media_img_default_legend', $img_default_legend_combo, Html::escapeHTML($this->blog_settings->get('system')->get('media_img_default_legend'))) .
        '</p>' .
        '</div>' .
        '</div>' .
        '<br class="clear" />' . // Opera sucks

        '</div>' .
            '</div>';

        echo '<div id="advanced-pref"><h3>' . __('Advanced parameters') . '</h3>';

        if (dotclear()->user()->isSuperAdmin()) {
            echo '<div class="fieldset"><h4>' . __('Blog details') . '</h4>';
            echo '<p><label for="blog_id" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Blog ID:') . '</label>' .
            Form::field('blog_id', 30, 32, Html::escapeHTML($this->blog_id), '', '', false, 'required placeholder="' . __('Blog ID') . '" aria-describedby="blog_id_help blog_id_warn"') . '</p>' .
            '<p class="form-note" id="blog_id_help">' . __('At least 2 characters using letters, numbers or symbols.') . '</p> ' .
            '<p class="form-note warn" id="blog_id_warn">' . __('Please note that changing your blog ID may require changes in your public index.php file.') . '</p>';

            echo '<p><label for="blog_url" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Blog URL:') . '</label>' .
            Form::url('blog_url', [
                'size'       => 50,
                'max'        => 255,
                'default'    => Html::escapeHTML($this->blog_url),
                'extra_html' => 'required placeholder="' . __('Blog URL') . '"',
            ]) .
            '</p>' .

            '<p><label for="url_scan">' . __('URL scan method:') . '</label>' .
            Form::combo('url_scan', $url_scan_combo, $this->blog_settings->get('system')->get('url_scan')) . '</p>';

            try {
                // Test URL of blog by testing it's ATOM feed
                $file    = $this->blog_url . dotclear()->url()->getURLFor('feed', 'atom');
                $path    = '';
                $status  = '404';
                $content = '';

                $client = NetHttp::initClient($file, $path);
                if (false !== $client) {
                    $client->setTimeout(dotclear()->config()->get('query_timeout'));
                    $client->setUserAgent($_SERVER['HTTP_USER_AGENT']);
                    $client->get($path);
                    $status  = $client->getStatus();
                    $content = $client->getContent();
                }
                if ('200' != $status) {
                    // Might be 404 (URL not found), 670 (blog not online), ...
                    echo '<p class="form-note warn">' .
                    sprintf(
                        __('The URL of blog or the URL scan method might not be well set (<code>%s</code> return a <strong>%s</strong> status).'),
                        Html::escapeHTML($file),
                        $status
                    ) .
                        '</p>';
                } else {
                    if (substr($content, 0, 6) != '<?xml ') {
                        // Not well formed XML feed
                        echo '<p class="form-note warn">' .
                        sprintf(
                            __('The URL of blog or the URL scan method might not be well set (<code>%s</code> does not return an ATOM feed).'),
                            Html::escapeHTML($file)
                        ) .
                            '</p>';
                    }
                }
            } catch (Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
            echo '</div>';
        }

        echo '<div class="fieldset"><h4>' . __('Blog configuration') . '</h4>' .

        '<p><label for="post_url_format">' . __('New post URL format:') . '</label>' .
        Form::combo('post_url_format', $post_url_combo, Html::escapeHTML($this->blog_settings->get('system')->get('post_url_format')), '', '', false, 'aria-describedby="post_url_format_help"') .
        '</p>' .
        '<p class="chosen form-note" id="post_url_format_help">' . __('Sample:') . ' ' . dotclear()->blog()->posts()->getPostURL('', date('Y-m-d H:i:00', $now), __('Dotclear'), 42) . '</p>' .
        '</p>' .

        '<p><label for="note_title_tag">' . __('HTML tag for the title of the notes on the blog:') . '</label>' .
        Form::combo('note_title_tag', $note_title_tag_combo, $this->blog_settings->get('system')->get('note_title_tag')) .
        '</p>' .

        '<p><label for="enable_xmlrpc" class="classic">' .
        Form::checkbox('enable_xmlrpc', '1', $this->blog_settings->get('system')->get('enable_xmlrpc'), '', '', false, 'aria-describedby="enable_xmlrpc_help"') .
        __('Enable XML/RPC interface') . '</label>' . '</p>' .
        '<p class="form-note info" id="enable_xmlrpc_help">' . __('XML/RPC interface allows you to edit your blog with an external client.') . '</p>';

        if ($this->blog_settings->get('system')->get('enable_xmlrpc')) {
            echo '<p>' . __('XML/RPC interface is active. You should set the following parameters on your XML/RPC client:') . '</p>' .
            '<ul>' .
            '<li>' . __('Server URL:') . ' <strong><code>' .
            sprintf(dotclear()->config()->get('xmlrpc_url'), dotclear()->blog()->url, dotclear()->blog()->id) . // @phpstan-ignore-line
            '</code></strong></li>' .
            '<li>' . __('Blogging system:') . ' <strong><code>Movable Type</code></strong></li>' .
            '<li>' . __('User name:') . ' <strong><code>' . dotclear()->user()->userID() . '</code></strong></li>' .
            '<li>' . __('Password:') . ' <strong><code>&lt;' . __('your password') . '&gt;</code></strong></li>' .
            '<li>' . __('Blog ID:') . ' <strong><code>1</code></strong></li>' .
                '</ul>';
        }

        echo '</div>';

        // Search engines policies
        echo '<div class="fieldset"><h4>' . __('Search engines robots policy') . '</h4>';

        $i = 0;
        foreach ($robots_policy_options as $k => $v) {
            echo '<p><label for="robots_policy-' . $i . '" class="classic">' .
            Form::radio(['robots_policy', 'robots_policy-' . $i], $k, $this->blog_settings->get('system')->get('robots_policy') == $k) . ' ' . $v . '</label></p>';
            ++$i;
        }

        echo '</div>';

        echo '<div class="fieldset"><h4>' . __('jQuery javascript library') . '</h4>' .

        '<p><label for="jquery_needed" class="classic">' .
        Form::checkbox('jquery_needed', '1', $this->blog_settings->get('system')->get('jquery_needed')) .
        __('Load the jQuery library') . '</label></p>' .

        '<p><label for="jquery_version" class="classic">' . __('jQuery version to be loaded for this blog:') . '</label>' . ' ' .
        Form::combo('jquery_version', $jquery_versions_combo, $this->blog_settings->get('system')->get('jquery_version')) .
        '</p>' .
        '<br class="clear" />' . // Opera sucks

        '</div>';

        echo '<div class="fieldset"><h4>' . __('Blog security') . '</h4>' .

        '<p><label for="prevents_clickjacking" class="classic">' .
        Form::checkbox('prevents_clickjacking', '1', $this->blog_settings->get('system')->get('prevents_clickjacking')) .
        __('Protect the blog from Clickjacking (see <a href="https://en.wikipedia.org/wiki/Clickjacking">Wikipedia</a>)') . '</label></p>' .
        '<br class="clear" />' . // Opera sucks

        '</div>';

        echo '</div>'; // End advanced

        echo '<div id="plugins-pref"><h3>' . __('Plugins parameters') . '</h3>';

        // --BEHAVIOR-- adminBlogPreferencesForm
        dotclear()->behavior()->call('adminBlogPreferencesForm', $this->blog_settings);

        echo '</div>'; // End 3rd party, aka plugins

        echo '<p><input type="submit" accesskey="s" value="' . __('Save') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
            (!$this->standalone ? Form::hidden('id', $this->blog_id) : '') .
            '</p>' .
            '</form>';

        if (dotclear()->user()->isSuperAdmin() && dotclear()->blog()->id != $this->blog_id) {
            echo '<form action="' . dotclear()->adminurl()->root() . '" method="post">' .
            '<p><input type="submit" class="delete" value="' . __('Delete this blog') . '" />' .
            dotclear()->adminurl()->getHiddenFormFields('admin.blog.del', ['blog_id' => $this->blog_id], true) . '</p>' .
                '</form>';
        } else {
            if (dotclear()->blog()->id == $this->blog_id) {
                echo '<p class="message">' . __('The current blog cannot be deleted.') . '</p>';
            } else {
                echo '<p class="message">' . __('Only superadmin can delete a blog.') . '</p>';
            }
        }

        echo '</div>';

        //
        // Users on the blog (with permissions)

        $blog_users = dotclear()->blogs()->getBlogPermissions($this->blog_id, dotclear()->user()->isSuperAdmin());
        $perm_types = dotclear()->user()->getPermissionsTypes();

        echo '<div class="multi-part" id="users" title="' . __('Users') . '">' .
        '<h3 class="out-of-screen-if-js">' . __('Users on this blog') . '</h3>';

        if (empty($blog_users)) {
            echo '<p>' . __('No users') . '</p>';
        } else {
            if (dotclear()->user()->isSuperAdmin()) {
                $user_url_p = '<a href="' . dotclear()->adminurl()->get('admin.user', ['id' => '%1$s'], '&amp;', true) . '">%1$s</a>';
            } else {
                $user_url_p = '%1$s';
            }

            // Sort users list on user_id key
            Lexical::lexicalKeySort($blog_users);

            $post_type       = dotclear()->posttype()->getPostTypes();
            $current_blog_id = dotclear()->blog()->id;
            if (dotclear()->blog()->id != $this->blog_id) {
                dotclear()->setBlog($this->blog_id);
            }

            echo '<div>';
            foreach ($blog_users as $k => $v) {
                if (count($v['p']) > 0) {
                    echo '<div class="user-perm' . ($v['super'] ? ' user_super' : '') . '">' .
                    '<h4>' . sprintf($user_url_p, Html::escapeHTML($k)) .
                    ' (' . Html::escapeHTML(UserContainer::getUserCN(
                        $k,
                        $v['name'],
                        $v['firstname'],
                        $v['displayname']
                    )) . ')</h4>';

                    if (dotclear()->user()->isSuperAdmin()) {
                        echo '<p>' . __('Email:') . ' ' .
                            ('' != $v['email'] ? '<a href="mailto:' . $v['email'] . '">' . $v['email'] . '</a>' : __('(none)')) .
                            '</p>';
                    }

                    echo '<h5>' . __('Publications on this blog:') . '</h5>' .
                        '<ul>';
                    foreach ($post_type as $type => $pt_info) {
                        $params = [
                            'post_type' => $type,
                            'user_id'   => $k,
                        ];
                        echo '<li>' . sprintf(__('%1$s: %2$s'), __($pt_info['label']), dotclear()->blog()->posts()->getPosts($params, true)->fInt()) . '</li>';
                    }
                    echo '</ul>';

                    echo '<h5>' . __('Permissions:') . '</h5>' .
                        '<ul>';
                    if ($v['super']) {
                        echo '<li class="user_super">' . __('Super administrator') . '<br />' .
                        '<span class="form-note">' . __('All rights on all blogs.') . '</span></li>';
                    } else {
                        foreach ($v['p'] as $p => $V) {
                            if (isset($perm_types[$p])) {
                                echo '<li ' . ('admin' == $p ? 'class="user_admin"' : '') . '>' . __($perm_types[$p]);
                            } else {
                                echo '<li>' . sprintf(__('[%s] (unreferenced permission)'), $p);
                            }

                            if ('admin' == $p) {
                                echo '<br /><span class="form-note">' . __('All rights on this blog.') . '</span>';
                            }
                            echo '</li>';
                        }
                    }
                    echo '</ul>';

                    if (!$v['super'] && dotclear()->user()->isSuperAdmin()) {
                        echo '<form action="' . dotclear()->adminurl()->root() . '" method="post">' .
                        '<p class="change-user-perm"><input type="submit" class="reset" value="' . __('Change permissions') . '" />' .
                        dotclear()->adminurl()->getHiddenFormFields('admin.user.actions', [
                            'redir'   => dotclear()->adminurl()->get('admin.blog.pref', ['id' => $k], '&'),
                            'action'  => 'perms',
                            'users[]' => $k,
                            'blogs[]' => $this->blog_id,
                        ], true) . '</p>' .
                            '</form>';
                    }
                    echo '</div>';
                }
            }
            echo '</div>';
            if (dotclear()->blog()->id != $current_blog_id) {
                dotclear()->setBlog($current_blog_id);
            }
        }

        echo '</div>';
    }
}
