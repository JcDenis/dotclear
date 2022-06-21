<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\BlogPref
use Dotclear\App;
use Dotclear\Core\Blog\Settings\Settings;
use Dotclear\Database\Param;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Clock;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Lexical;
use Dotclear\Helper\Network\Http;
use Dotclear\Helper\Network\NetHttp\NetHttp;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * Admin blog preference page.
 *
 * @ingroup  Admin Blog Settings Handler
 */
class BlogPref extends AbstractPage
{
    private $blog_id     = '';
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

    protected function getPermissions(): string|bool
    {
        return $this->standalone ? 'admin' : '';
    }

    protected function getPagePrepend(): ?bool
    {
        // Blog params
        if ($this->standalone) {
            $this->blog_id       = App::core()->blog()->id;
            $this->blog_status   = App::core()->blog()->status;
            $this->blog_name     = App::core()->blog()->name;
            $this->blog_desc     = App::core()->blog()->desc;
            $this->blog_settings = App::core()->blog()->settings();
            $this->blog_url      = App::core()->blog()->url;

            $this->blog_action = App::core()->adminurl()->get('admin.blog.pref');
            $redir             = App::core()->adminurl()->get('admin.blog.pref');
        } else {
            try {
                if (GPC::request()->empty('id')) {
                    throw new AdminException(__('No given blog id.'));
                }
                $param = new Param();
                $param->set('blog_id', GPC::request()->string('id'));

                $record = App::core()->blogs()->getBlogs(param: $param);
                if ($record->isEmpty()) {
                    throw new AdminException(__('No such blog.'));
                }

                $this->blog_id       = $record->field('blog_id');
                $this->blog_status   = $record->integer('blog_status');
                $this->blog_name     = $record->field('blog_name');
                $this->blog_desc     = $record->field('blog_desc');
                $this->blog_settings = new Settings(blog: $this->blog_id);
                $this->blog_url      = $record->field('blog_url');
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }

            $this->blog_action = App::core()->adminurl()->get('admin.blog');
            $redir             = App::core()->adminurl()->get('admin.blog', ['id' => '%s'], '&', true);
        }

        // Update a blog
        if ($this->blog_id && GPC::post()->count() && App::core()->user()->check('admin', $this->blog_id)) {
            // URL scan modes
            $url_scan_combo = [
                'PATH_INFO'    => 'path_info',
                'QUERY_STRING' => 'query_string',
            ];

            // Status combo
            $status_combo = App::core()->combo()->getBlogStatusesCombo();

            $cur = App::core()->con()->openCursor(App::core()->getPrefix() . 'blog');
            $cur->setField('blog_id', GPC::post()->string('blog_id'));
            $cur->setField('blog_url', preg_replace('/\?+$/', '?', GPC::post()->string('blog_url')));
            $cur->setField('blog_name', GPC::post()->string('blog_name'));
            $cur->setField('blog_desc', GPC::post()->string('blog_desc'));

            if (App::core()->user()->isSuperAdmin() && in_array(GPC::post()->string('blog_status'), $status_combo)) {
                $cur->setField('blog_status', GPC::post()->int('blog_status'));
            }

            $media_img_t_size = GPC::post()->int('media_img_t_size');
            if (0 > $media_img_t_size) {
                $media_img_t_size = 100;
            }

            $media_img_s_size = GPC::post()->int('media_img_s_size');
            if (0 > $media_img_s_size) {
                $media_img_s_size = 240;
            }

            $media_img_m_size = GPC::post()->int('media_img_m_size');
            if (0 > $media_img_m_size) {
                $media_img_m_size = 448;
            }

            $media_video_width = GPC::post()->int('media_video_width');
            if (0 > $media_video_width) {
                $media_video_width = 400;
            }

            $media_video_height = GPC::post()->int('media_video_height');
            if (0 > $media_video_height) {
                $media_video_height = 300;
            }

            $nb_post_for_home = abs(GPC::post()->int('nb_post_for_home'));
            if (1 > $nb_post_for_home) {
                $nb_post_for_home = 1;
            }

            $nb_post_per_page = abs(GPC::post()->int('nb_post_per_page'));
            if (1 > $nb_post_per_page) {
                $nb_post_per_page = 1;
            }

            $nb_post_per_feed = abs(GPC::post()->int('nb_post_per_feed'));
            if (1 > $nb_post_per_feed) {
                $nb_post_per_feed = 1;
            }

            $nb_comment_per_feed = abs(GPC::post()->int('nb_comment_per_feed'));
            if (1 > $nb_comment_per_feed) {
                $nb_comment_per_feed = 1;
            }

            try {
                if ($cur->getField('blog_id') != null && $cur->getField('blog_id') != $this->blog_id) {
                    $param = new Param();
                    $param->set('blog_id', $cur->getField('blog_id'));

                    $record = App::core()->blogs()->getBlogs(param: $param);
                    if (!$record->isEmpty()) {
                        throw new AdminException(__('This blog ID is already used.'));
                    }
                }

                if (!preg_match('/^[a-z]{2}(-[a-z]{2})?$/', GPC::post()->string('lang'))) {
                    throw new AdminException(__('Invalid language code'));
                }

                App::core()->blogs()->updateBlog(id: $this->blog_id, cursor: $cur);

                if ($cur->getField('blog_id') != null && $cur->getField('blog_id') != $this->blog_id) {
                    if (App::core()->blog()->id == $this->blog_id) {
                        App::core()->setBlog($cur->getField('blog_id'));
                        $_SESSION['sess_blog_id'] = $cur->getField('blog_id');
                        $this->blog_settings      = App::core()->blog()->settings();
                    } else {
                        $this->blog_settings = new Settings(blog: $cur->getField('blog_id'));
                    }

                    $this->blog_id = $cur->getField('blog_id');
                }

                $system = $this->blog_settings->getGroup('system');
                $system->putSetting('editor', GPC::post()->string('editor'));
                $system->putSetting('copyright_notice', GPC::post()->string('copyright_notice'));
                $system->putSetting('post_url_format', GPC::post()->string('post_url_format'));
                $system->putSetting('lang', GPC::post()->string('lang'));
                $system->putSetting('blog_timezone', GPC::post()->string('blog_timezone'));
                $system->putSetting('date_format', GPC::post()->string('date_format'));
                $system->putSetting('time_format', GPC::post()->string('time_format'));
                $system->putSetting('comments_ttl', abs(GPC::post()->int('comments_ttl')));
                $system->putSetting('trackbacks_ttl', abs(GPC::post()->int('trackbacks_ttl')));
                $system->putSetting('allow_comments', !GPC::post()->empty('allow_comments'));
                $system->putSetting('allow_trackbacks', !GPC::post()->empty('allow_trackbacks'));
                $system->putSetting('comments_pub', GPC::post()->empty('comments_pub'));
                $system->putSetting('trackbacks_pub', GPC::post()->empty('trackbacks_pub'));
                $system->putSetting('comments_nofollow', !GPC::post()->empty('comments_nofollow'));
                $system->putSetting('wiki_comments', !GPC::post()->empty('wiki_comments'));
                $system->putSetting('comment_preview_optional', !GPC::post()->empty('comment_preview_optional'));
                $system->putSetting('enable_xmlrpc', !GPC::post()->empty('enable_xmlrpc'));
                $system->putSetting('note_title_tag', GPC::post()->string('note_title_tag'));
                $system->putSetting('nb_post_for_home', $nb_post_for_home);
                $system->putSetting('nb_post_per_page', $nb_post_per_page);
                $system->putSetting('use_smilies', !GPC::post()->empty('use_smilies'));
                $system->putSetting('no_search', !GPC::post()->empty('no_search'));
                $system->putSetting('inc_subcats', !GPC::post()->empty('inc_subcats'));
                $system->putSetting('media_img_t_size', $media_img_t_size);
                $system->putSetting('media_img_s_size', $media_img_s_size);
                $system->putSetting('media_img_m_size', $media_img_m_size);
                $system->putSetting('media_video_width', $media_video_width);
                $system->putSetting('media_video_height', $media_video_height);
                $system->putSetting('media_img_title_pattern', GPC::post()->string('media_img_title_pattern'));
                $system->putSetting('media_img_use_dto_first', !GPC::post()->empty('media_img_use_dto_first'));
                $system->putSetting('media_img_no_date_alone', !GPC::post()->empty('media_img_no_date_alone'));
                $system->putSetting('media_img_default_size', GPC::post()->string('media_img_default_size'));
                $system->putSetting('media_img_default_alignment', GPC::post()->string('media_img_default_alignment'));
                $system->putSetting('media_img_default_link', !GPC::post()->empty('media_img_default_link'));
                $system->putSetting('media_img_default_legend', GPC::post()->string('media_img_default_legend'));
                $system->putSetting('nb_post_per_feed', $nb_post_per_feed);
                $system->putSetting('nb_comment_per_feed', $nb_comment_per_feed);
                $system->putSetting('short_feed_items', !GPC::post()->empty('short_feed_items'));
                if (GPC::post()->isset('robots_policy')) {
                    $system->putSetting('robots_policy', GPC::post()->string('robots_policy'));
                }
                $system->putSetting('jquery_needed', !GPC::post()->empty('jquery_needed'));
                $system->putSetting('jquery_version', GPC::post()->string('jquery_version'));
                $system->putSetting('prevents_clickjacking', !GPC::post()->empty('prevents_clickjacking'));
                $system->putSetting('static_home', !GPC::post()->empty('static_home'));
                $system->putSetting('static_home_url', GPC::post()->string('static_home_url'));

                // --BEHAVIOR-- adminBeforeBlogSettingsUpdate
                App::core()->behavior('adminBeforeBlogSettingsUpdate')->call($this->blog_settings);

                if (App::core()->user()->isSuperAdmin() && in_array(GPC::post()->string('url_scan'), $url_scan_combo)) {
                    $system->putSetting('url_scan', GPC::post()->string('url_scan'));
                }
                App::core()->notice()->addSuccessNotice(__('Blog has been successfully updated.'));

                Http::redirect(sprintf($redir, $this->blog_id));
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Page setup
        $desc_editor = App::core()->user()->getOption('editor');
        $rte_flag    = true;
        $rte_flags   = @App::core()->user()->preference()->get('interface')->get('rte_flags');
        if (is_array($rte_flags) && in_array('blog_descr', $rte_flags)) {
            $rte_flag = $rte_flags['blog_descr'];
        }

        $this
            ->setPageTitle(__('Blog settings'))
            ->setPageHelp('core_blog_pref')
            ->setPageHead(
                App::core()->resource()->json('blog_pref', [
                    'warning_path_info'    => __('Warning: except for special configurations, it is generally advised to have a trailing "/" in your blog URL in PATH_INFO mode.'),
                    'warning_query_string' => __('Warning: except for special configurations, it is generally advised to have a trailing "?" in your blog URL in QUERY_STRING mode.'),
                ]) .
                App::core()->resource()->confirmClose('blog-form') .
                ($rte_flag ? App::core()->behavior('adminPostEditor')->call($desc_editor['xhtml'], 'blog_desc', ['#blog_desc'], 'xhtml') : '') .
                App::core()->resource()->load('_blog_pref.js') .

                // --BEHAVIOR-- adminBlogPreferencesHeaders
                App::core()->behavior('adminBlogPreferencesHeaders')->call() .

                App::core()->resource()->pageTabs()
            )
            ->setPageBreadcrumb($this->standalone ? [
                Html::escapeHTML($this->blog_name) => '',
                __('Blog settings')                => '',
            ] : [
                __('System')                                                     => '',
                __('Blogs')                                                      => App::core()->adminurl()->get('admin.blogs'),
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
        $system = $this->blog_settings->getGroup('system');

        // Language codes
        $lang_combo = App::core()->combo()->getAdminLangsCombo();

        // Status combo
        $status_combo = App::core()->combo()->getBlogStatusescombo();

        // Date format combo
        $date_formats       = $system->getSetting('date_formats');
        $time_formats       = $system->getSetting('time_formats');
        $date_formats_combo = ['' => ''];
        foreach ($date_formats as $format) {
            $date_formats_combo[Clock::str($format)] = $format;
        }
        $time_formats_combo = ['' => ''];
        foreach ($time_formats as $format) {
            $time_formats_combo[Clock::str($format)] = $format;
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
        if (!in_array($system->getSetting('post_url_format'), $post_url_combo)) {
            $post_url_combo[Html::escapeHTML($system->getSetting('post_url_format'))] = Html::escapeHTML($system->getSetting('post_url_format'));
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
        if (!in_array($system->getSetting('media_img_title_pattern'), $img_title_combo)) {
            $img_title_combo[Html::escapeHTML($system->getSetting('media_img_title_pattern'))] = Html::escapeHTML($system->getSetting('media_img_title_pattern'));
        }

        // Image default size combo
        $img_default_size_combo[__('original')] = 'o';

        try {
            if (!App::core()->media()) {
                throw new AdminException('No media path');
            }
            foreach (App::core()->media()->thumbsize()->getNames() as $code => $name) {
                $img_default_size_combo[$name] = $code;
            }
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());
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
        $jquery_root           = Path::implodeSrc('Core', 'resources', 'js', 'jquery');
        $jquery_versions_combo = [__('Default') . ' (' . App::core()->config()->get('jquery_default') . ')' => ''];
        if (is_dir($jquery_root) && is_readable($jquery_root)) {
            if (false !== ($d = @dir($jquery_root))) {
                while (false !== ($entry = $d->read())) {
                    if ('.' != $entry && '..' != $entry && substr($entry, 0, 1) != '.' && is_dir($jquery_root . '/' . $entry)) {
                        if (App::core()->config()->get('jquery_default') != $entry) {
                            $jquery_versions_combo[$entry] = $entry;
                        }
                    }
                }
            }
        }

        if (!GPC::get()->empty('add')) {
            App::core()->notice()->success(__('Blog has been successfully created.'));
        } elseif (!GPC::get()->empty('upd')) {
            App::core()->notice()->success(__('Blog has been successfully updated.'));
        }

        echo '<div class="multi-part" id="params" title="' . __('Parameters') . '">' .
        '<div id="standard-pref"><h3>' . __('Blog parameters') . '</h3>' .
            '<form action="' . $this->blog_action . '" method="post" id="blog-form">';

        echo '<div class="fieldset"><h4>' . __('Blog details') . '</h4>' .
        App::core()->nonce()->form();

        echo '<p><label for="blog_name" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Blog name:') . '</label>' .
        Form::field(
            'blog_name',
            30,
            255,
            [
                'default'    => Html::escapeHTML($this->blog_name),
                'extra_html' => 'required placeholder="' . __('Blog name') . '" lang="' . $system->getSetting('lang') . '" spellcheck="true"',
            ]
        ) . '</p>';

        echo '<p class="area"><label for="blog_desc">' . __('Blog description:') . '</label>' .
        Form::textarea(
            'blog_desc',
            60,
            5,
            [
                'default'    => Html::escapeHTML($this->blog_desc),
                'extra_html' => 'lang="' . $system->getSetting('lang') . '" spellcheck="true"',
            ]
        ) . '</p>';

        if (App::core()->user()->isSuperAdmin()) {
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
        Form::field('editor', 30, 255, Html::escapeHTML($system->getSetting('editor'))) .
        '</p>' .

        '<p><label for="lang">' . __('Default language:') . '</label>' .
        Form::combo('lang', $lang_combo, $system->getSetting('lang'), 'l10n') .
        '</p>' .

        '<p><label for="blog_timezone">' . __('Blog timezone:') . '</label>' .
        Form::combo('blog_timezone', Clock::getZones(true, true), Html::escapeHTML($system->getSetting('blog_timezone'))) .
        '</p>' .

        '<p><label for="copyright_notice">' . __('Copyright notice:') . '</label>' .
        Form::field(
            'copyright_notice',
            30,
            255,
            [
                'default'    => Html::escapeHTML($system->getSetting('copyright_notice')),
                'extra_html' => 'lang="' . $system->getSetting('lang') . '" spellcheck="true"',
            ]
        ) .
            '</p>' .

            '</div>';

        echo '<div class="fieldset"><h4>' . __('Comments and trackbacks') . '</h4>' .

        '<div class="two-cols">' .

        '<div class="col">' .
        '<p><label for="allow_comments" class="classic">' .
        Form::checkbox('allow_comments', '1', $system->getSetting('allow_comments')) .
        __('Accept comments') . '</label></p>' .
        '<p><label for="comments_pub" class="classic">' .
        Form::checkbox('comments_pub', '1', !$system->getSetting('comments_pub')) .
        __('Moderate comments') . '</label></p>' .
        '<p><label for="comments_ttl" class="classic">' . sprintf(
            __('Leave comments open for %s days') . '.',
            Form::number(
                'comments_ttl',
                [
                    'min'        => 0,
                    'max'        => 999,
                    'default'    => $system->getSetting('comments_ttl'),
                    'extra_html' => 'aria-describedby="comments_ttl_help"', ]
            )
        ) .
        '</label></p>' .
        '<p class="form-note" id="comments_ttl_help">' . __('No limit: leave blank.') . '</p>' .
        '<p><label for="wiki_comments" class="classic">' .
        Form::checkbox('wiki_comments', '1', $system->getSetting('wiki_comments')) .
        __('Wiki syntax for comments') . '</label></p>' .
        '<p><label for="comment_preview_optional" class="classic">' .
        Form::checkbox('comment_preview_optional', '1', $system->getSetting('comment_preview_optional')) .
        __('Preview of comment before submit is not mandatory') . '</label></p>' .
        '</div>' .

        '<div class="col">' .
        '<p><label for="allow_trackbacks" class="classic">' .
        Form::checkbox('allow_trackbacks', '1', $system->getSetting('allow_trackbacks')) .
        __('Accept trackbacks') . '</label></p>' .
        '<p><label for="trackbacks_pub" class="classic">' .
        Form::checkbox('trackbacks_pub', '1', !$system->getSetting('trackbacks_pub')) .
        __('Moderate trackbacks') . '</label></p>' .
        '<p><label for="trackbacks_ttl" class="classic">' . sprintf(
            __('Leave trackbacks open for %s days') . '.',
            Form::number(
                'trackbacks_ttl',
                [
                    'min'        => 0,
                    'max'        => 999,
                    'default'    => $system->getSetting('trackbacks_ttl'),
                    'extra_html' => 'aria-describedby="trackbacks_ttl_help"', ]
            )
        ) .
        '</label></p>' .
        '<p class="form-note" id="trackbacks_ttl_help">' . __('No limit: leave blank.') . '</p>' .
        '<p><label for="comments_nofollow" class="classic">' .
        Form::checkbox('comments_nofollow', '1', $system->getSetting('comments_nofollow')) .
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
        Form::field('date_format', 30, 255, Html::escapeHTML($system->getSetting('date_format')), '', '', false, 'aria-describedby="date_format_help"') .
        Form::combo('date_format_select', $date_formats_combo, ['extra_html' => 'title="' . __('Pattern of date') . '"']) .
        '</p>' .
        '<p class="chosen form-note" id="date_format_help">' . __('Sample:') . ' ' . Clock::str(format: Html::escapeHTML($system->getSetting('date_format'))) . '</p>' .

        '<p><label for="time_format">' . __('Time format:') . '</label>' .
        Form::field('time_format', 30, 255, Html::escapeHTML($system->getSetting('time_format')), '', '', false, 'aria-describedby="time_format_help"') .
        Form::combo('time_format_select', $time_formats_combo, ['extra_html' => 'title="' . __('Pattern of time') . '"']) .
        '</p>' .
        '<p class="chosen form-note" id="time_format_help">' . __('Sample:') . ' ' . Clock::str(format: Html::escapeHTML($system->getSetting('time_format'))) . '</p>' .

        '<p><label for="use_smilies" class="classic">' .
        Form::checkbox('use_smilies', '1', $system->getSetting('use_smilies')) .
        __('Display smilies on entries and comments') . '</label></p>' .

        '<p><label for="no_search" class="classic">' .
        Form::checkbox('no_search', '1', $system->getSetting('no_search')) .
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
                    'default' => $system->getSetting('nb_post_for_home'), ]
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
                    'default' => $system->getSetting('nb_post_per_page'), ]
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
                    'default' => $system->getSetting('nb_post_per_feed'), ]
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
                    'default' => $system->getSetting('nb_comment_per_feed'), ]
            )
        ) .
        '</label></p>' .

        '<p><label for="short_feed_items" class="classic">' .
        Form::checkbox('short_feed_items', '1', $system->getSetting('short_feed_items')) .
        __('Truncate feeds') . '</label></p>' .

        '<p><label for="inc_subcats" class="classic">' .
        Form::checkbox('inc_subcats', '1', $system->getSetting('inc_subcats')) .
        __('Include sub-categories in category page and category posts feed') . '</label></p>' .
        '</div>' .
        '</div>' .
        '<br class="clear" />' . // Opera sucks

        '<hr />' .

        '<p><label for="static_home" class="classic">' .
        Form::checkbox('static_home', '1', $system->getSetting('static_home')) .
        __('Display an entry as static home page') . '</label></p>' .

        '<p><label for="static_home_url" class="classic">' . __('Entry URL (its content will be used for the static home page):') . '</label> ' .
        Form::field('static_home_url', 30, 255, Html::escapeHTML($system->getSetting('static_home_url')), '', '', false, 'aria-describedby="static_home_url_help"') .
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
            'default' => $system->getSetting('media_img_t_size'),
        ]) .
        '</p>' .

        '<p class="field"><label for="media_img_s_size">' . __('Small') . '</label> ' .
        Form::number('media_img_s_size', [
            'min'     => -1,
            'max'     => 999,
            'default' => $system->getSetting('media_img_s_size'),
        ]) .
        '</p>' .

        '<p class="field"><label for="media_img_m_size">' . __('Medium') . '</label> ' .
        Form::number('media_img_m_size', [
            'min'     => -1,
            'max'     => 999,
            'default' => $system->getSetting('media_img_m_size'),
        ]) .
        '</p>' .

        '<h5>' . __('Default size of the inserted video (in pixels)') . '</h5>' .
        '<p class="field"><label for="media_video_width">' . __('Width') . '</label> ' .
        Form::number('media_video_width', [
            'min'     => -1,
            'max'     => 999,
            'default' => $system->getSetting('media_video_width'),
        ]) .
        '</p>' .

        '<p class="field"><label for="media_video_height">' . __('Height') . '</label> ' .
        Form::number('media_video_height', [
            'min'     => -1,
            'max'     => 999,
            'default' => $system->getSetting('media_video_height'),
        ]) .
        '</p>' .
        '</div>' .

        '<div class="col">' .
        '<h5>' . __('Default image insertion attributes') . '</h5>' .
        '<p class="vertical-separator"><label for="media_img_title_pattern">' . __('Inserted image title') . '</label>' .
        Form::combo('media_img_title_pattern', $img_title_combo, Html::escapeHTML($system->getSetting('media_img_title_pattern'))) . '</p>' .
        '<p><label for="media_img_use_dto_first" class="classic">' .
        Form::checkbox('media_img_use_dto_first', '1', $system->getSetting('media_img_use_dto_first')) .
        __('Use original media date if possible') . '</label></p>' .
        '<p><label for="media_img_no_date_alone" class="classic">' .
        Form::checkbox('media_img_no_date_alone', '1', $system->getSetting('media_img_no_date_alone'), '', '', false, 'aria-describedby="media_img_no_date_alone_help"') .
        __('Do not display date if alone in title') . '</label></p>' .
        '<p class="form-note info" id="media_img_no_date_alone_help">' . __('It is retrieved from the picture\'s metadata.') . '</p>' .

        '<p class="field vertical-separator"><label for="media_img_default_size">' . __('Size of inserted image:') . '</label>' .
        Form::combo(
            'media_img_default_size',
            $img_default_size_combo,
            (Html::escapeHTML($system->getSetting('media_img_default_size')) != '' ? Html::escapeHTML($system->getSetting('media_img_default_size')) : 'm')
        ) .
        '</p>' .
        '<p class="field"><label for="media_img_default_alignment">' . __('Image alignment:') . '</label>' .
        Form::combo('media_img_default_alignment', $img_default_alignment_combo, Html::escapeHTML($system->getSetting('media_img_default_alignment'))) .
        '</p>' .
        '<p><label for="media_img_default_link">' .
        Form::checkbox('media_img_default_link', '1', $system->getSetting('media_img_default_link')) .
        __('Insert a link to the original image') . '</label></p>' .
        '<p class="field"><label for="media_img_default_legend">' . __('Image legend and title:') . '</label>' .
        Form::combo('media_img_default_legend', $img_default_legend_combo, Html::escapeHTML($system->getSetting('media_img_default_legend'))) .
        '</p>' .
        '</div>' .
        '</div>' .
        '<br class="clear" />' . // Opera sucks

        '</div>' .
            '</div>';

        echo '<div id="advanced-pref"><h3>' . __('Advanced parameters') . '</h3>';

        if (App::core()->user()->isSuperAdmin()) {
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
            Form::combo('url_scan', $url_scan_combo, $system->getSetting('url_scan')) . '</p>';

            try {
                // Test URL of blog by testing it's ATOM feed
                $file    = $this->blog_url . App::core()->url()->getURLFor('feed', 'atom');
                $path    = '';
                $status  = '404';
                $content = '';

                $client = NetHttp::initClient($file, $path);
                if (false !== $client) {
                    $client->setTimeout(App::core()->config()->get('query_timeout'));
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
                App::core()->error()->add($e->getMessage());
            }
            echo '</div>';
        }

        echo '<div class="fieldset"><h4>' . __('Blog configuration') . '</h4>' .

        '<p><label for="post_url_format">' . __('New post URL format:') . '</label>' .
        Form::combo('post_url_format', $post_url_combo, Html::escapeHTML($system->getSetting('post_url_format')), '', '', false, 'aria-describedby="post_url_format_help"') .
        '</p>' .
        '<p class="chosen form-note" id="post_url_format_help">' . __('Sample:') . ' ' . App::core()->blog()->posts()->getPostURL(date: Clock::format(format: 'Y-m-d H:i:00'), title: __('Dotclear'), id: 42) . '</p>' .
        '</p>' .

        '<p><label for="note_title_tag">' . __('HTML tag for the title of the notes on the blog:') . '</label>' .
        Form::combo('note_title_tag', $note_title_tag_combo, $system->getSetting('note_title_tag')) .
        '</p>' .

        '<p><label for="enable_xmlrpc" class="classic">' .
        Form::checkbox('enable_xmlrpc', '1', $system->getSetting('enable_xmlrpc'), '', '', false, 'aria-describedby="enable_xmlrpc_help"') .
        __('Enable XML/RPC interface') . '</label>' . '</p>' .
        '<p class="form-note info" id="enable_xmlrpc_help">' . __('XML/RPC interface allows you to edit your blog with an external client.') . '</p>';

        if ($system->getSetting('enable_xmlrpc')) {
            echo '<p>' . __('XML/RPC interface is active. You should set the following parameters on your XML/RPC client:') . '</p>' .
            '<ul>' .
            '<li>' . __('Server URL:') . ' <strong><code>' .
            sprintf(App::core()->config()->get('xmlrpc_url'), App::core()->blog()->url, App::core()->blog()->id) .
            '</code></strong></li>' .
            '<li>' . __('Blogging system:') . ' <strong><code>Movable Type</code></strong></li>' .
            '<li>' . __('User name:') . ' <strong><code>' . App::core()->user()->userID() . '</code></strong></li>' .
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
            Form::radio(['robots_policy', 'robots_policy-' . $i], $k, $system->getSetting('robots_policy') == $k) . ' ' . $v . '</label></p>';
            ++$i;
        }

        echo '</div>';

        echo '<div class="fieldset"><h4>' . __('jQuery javascript library') . '</h4>' .

        '<p><label for="jquery_needed" class="classic">' .
        Form::checkbox('jquery_needed', '1', $system->getSetting('jquery_needed')) .
        __('Load the jQuery library') . '</label></p>' .

        '<p><label for="jquery_version" class="classic">' . __('jQuery version to be loaded for this blog:') . '</label>' . ' ' .
        Form::combo('jquery_version', $jquery_versions_combo, $system->getSetting('jquery_version')) .
        '</p>' .
        '<br class="clear" />' . // Opera sucks

        '</div>';

        echo '<div class="fieldset"><h4>' . __('Blog security') . '</h4>' .

        '<p><label for="prevents_clickjacking" class="classic">' .
        Form::checkbox('prevents_clickjacking', '1', $system->getSetting('prevents_clickjacking')) .
        __('Protect the blog from Clickjacking (see <a href="https://en.wikipedia.org/wiki/Clickjacking">Wikipedia</a>)') . '</label></p>' .
        '<br class="clear" />' . // Opera sucks

        '</div>';

        echo '</div>'; // End advanced

        echo '<div id="plugins-pref"><h3>' . __('Plugins parameters') . '</h3>';

        // --BEHAVIOR-- adminBlogPreferencesForm
        App::core()->behavior('adminBlogPreferencesForm')->call($this->blog_settings);

        echo '</div>'; // End 3rd party, aka plugins

        echo '<p><input type="submit" accesskey="s" value="' . __('Save') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
            (!$this->standalone ? Form::hidden('id', $this->blog_id) : '') .
            '</p>' .
            '</form>';

        if (App::core()->user()->isSuperAdmin() && App::core()->blog()->id != $this->blog_id) {
            echo '<form action="' . App::core()->adminurl()->root() . '" method="post">' .
            '<p><input type="submit" class="delete" value="' . __('Delete this blog') . '" />' .
            App::core()->adminurl()->getHiddenFormFields('admin.blog.del', ['blog_id' => $this->blog_id], true) . '</p>' .
                '</form>';
        } else {
            if (App::core()->blog()->id == $this->blog_id) {
                echo '<p class="message">' . __('The current blog cannot be deleted.') . '</p>';
            } else {
                echo '<p class="message">' . __('Only superadmin can delete a blog.') . '</p>';
            }
        }

        echo '</div>';

        //
        // Users on the blog (with permissions)

        $blog_users = App::core()->permission()->getBlogPermissions(id: $this->blog_id, super: App::core()->user()->isSuperAdmin());

        echo '<div class="multi-part" id="users" title="' . __('Users') . '">' .
        '<h3 class="out-of-screen-if-js">' . __('Users on this blog') . '</h3>';

        if (empty($blog_users)) {
            echo '<p>' . __('No users') . '</p>';
        } else {
            $user_url_p = App::core()->user()->isSuperAdmin() ?
                '<a href="' . App::core()->adminurl()->get('admin.user', ['id' => '%1$s'], '&amp;', true) . '">%1$s</a>' :
                '%1$s';

            // Sort users list on user_id key
            Lexical::lexicalKeySort($blog_users);

            $post_types      = App::core()->posttype()->getItems();
            $current_blog_id = App::core()->blog()->id;
            if (App::core()->blog()->id != $this->blog_id) {
                // Need to change blog to count user posts
                App::core()->setBlog($this->blog_id);
            }

            echo '<div>';
            foreach ($blog_users as $user) {
                if (0 < $user->perm->count() || $user->super) {
                    echo '<div class="user-perm' . ($user->super ? ' user_super' : '') . '">' .
                    '<h4>' . sprintf($user_url_p, Html::escapeHTML($user->id)) .
                    ' (' . Html::escapeHTML($user->getUserCN()) . ')</h4>';

                    if (App::core()->user()->isSuperAdmin()) {
                        echo '<p>' . __('Email:') . ' ' .
                            ('' != $user->email ? '<a href="mailto:' . $user->email . '">' . $user->email . '</a>' : __('(none)')) .
                            '</p>';
                    }

                    echo '<h5>' . __('Publications on this blog:') . '</h5>' .
                        '<ul>';
                    $param = new Param();
                    foreach ($post_types as $post_type) {
                        $param->set('post_type', $post_type->type);
                        $param->set('user_id', $user->id);

                        echo '<li>' . sprintf(__('%1$s: %2$s'), $post_type->label, App::core()->blog()->posts()->countPosts(param: $param)) . '</li>';
                    }
                    echo '</ul>';

                    echo '<h5>' . __('Permissions:') . '</h5>' .
                        '<ul>';
                    if ($user->super) {
                        echo '<li class="user_super">' . __('Super administrator') . '<br />' .
                        '<span class="form-note">' . __('All rights on all blogs.') . '</span></li>';
                    } else {
                        foreach ($user->perm->dump() as $type) {
                            echo '<li ' . ('admin' == $type ? 'class="user_admin"' : '') . '>' . App::core()->permission()->getItem(type: $type)->label;

                            if ('admin' == $type) {
                                echo '<br /><span class="form-note">' . __('All rights on this blog.') . '</span>';
                            }
                            echo '</li>';
                        }
                    }
                    echo '</ul>';

                    if (!$user->super && App::core()->user()->isSuperAdmin()) {
                        echo '<form action="' . App::core()->adminurl()->root() . '" method="post">' .
                        '<p class="change-user-perm"><input type="submit" class="reset" value="' . __('Change permissions') . '" />' .
                        App::core()->adminurl()->getHiddenFormFields('admin.user.actions', [
                            'redir'   => App::core()->adminurl()->get('admin.blog.pref', ['id' => $user->id], '&'),
                            'action'  => 'perms',
                            'users[]' => $user->id,
                            'blogs[]' => $this->blog_id,
                        ], true) . '</p>' .
                            '</form>';
                    }
                    echo '</div>';
                }
            }
            echo '</div>';
            if (App::core()->blog()->id != $current_blog_id) {
                App::core()->setBlog($current_blog_id);
            }
        }

        echo '</div>';
    }
}
