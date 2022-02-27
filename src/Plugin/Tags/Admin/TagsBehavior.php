<?php
/**
 * @class Dotclear\Plugin\Tags\Admin\TagsBehavior
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginTags
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Admin;

use ArrayObject;

use Dotclear\Admin\Filer;
use Dotclear\Admin\Page\Action\Action;
use Dotclear\Core\Utils;
use Dotclear\Html\Form;
use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class TagsBehavior
{
    public static function initTags()
    {
        dotclear()->behavior()->add('adminPostFormItems', [__CLASS__, 'tagsField']);
        dotclear()->behavior()->add('adminAfterPostCreate', [__CLASS__, 'setTags']);
        dotclear()->behavior()->add('adminAfterPostUpdate', [__CLASS__, 'setTags']);
        dotclear()->behavior()->add('adminPostHeaders', [__CLASS__, 'postHeaders']);
        dotclear()->behavior()->add('adminPostsActionsPage', [__CLASS__, 'adminPostsActionsPage']);
        dotclear()->behavior()->add('adminPreferencesForm', [__CLASS__, 'adminUserForm']);
        dotclear()->behavior()->add('adminBeforeUserOptionsUpdate', [__CLASS__, 'setTagListFormat']);
        dotclear()->behavior()->add('adminUserForm', [__CLASS__, 'adminUserForm']);
        dotclear()->behavior()->add('adminBeforeUserCreate', [__CLASS__, 'setTagListFormat']);
        dotclear()->behavior()->add('adminBeforeUserUpdate', [__CLASS__, 'setTagListFormat']);
        dotclear()->behavior()->add('adminPageHelpBlock', [__CLASS__, 'adminPageHelpBlock']);
        dotclear()->behavior()->add('adminPostEditor', [__CLASS__, 'adminPostEditor']);
        dotclear()->behavior()->add('ckeditorExtraPlugins', [__CLASS__, 'ckeditorExtraPlugins']);
    }

    public static function adminPostEditor(string $editor = '', string $context = '', array $tags = [], string $syntax = ''): string
    {
        if (($editor != 'dcLegacyEditor' && $editor != 'dcCKEditor') || $context != 'post') {
            return '';
        }

        $tag_url = dotclear()->blog()->url . dotclear()->url->getURLFor('tag');

        if ($editor == 'dcLegacyEditor') {
            return
            Utils::jsJson('legacy_editor_tags', [
                'tag' => [
                    'title' => __('Tag'),
                    'url'   => $tag_url,
                ],
            ]) .
            Filer::load('js/legacy-post.js', 'Plugin', 'tags');
        } elseif ($editor == 'dcCKEditor') {
            return
            Utils::jsJson('ck_editor_tags', [
                'tag_title' => __('Tag'),
                'tag_url'   => $tag_url,
            ]);
        }
    }

    public static function ckeditorExtraPlugins(ArrayObject $extraPlugins, $context)
    {
        if ($context != 'post') {
            return;
        }
        $extraPlugins[] = [
            'name'   => 'dctags',
            'button' => 'dcTags',
            'url'    => Filer::url('js/ckeditor-tags-plugin.js', 'Plugin', 'Tags'),
        ];
    }

    public static function adminPageHelpBlock($blocks)
    {
        $found = false;
        foreach ($blocks as $block) {
            if ($block == 'core_post') {
                $found = true;

                break;
            }
        }
        if (!$found) {
            return;
        }
        $blocks[] = 'tag_post';
    }

    public static function coreInitWikiPost($wiki2xhtml)
    {
        $wiki2xhtml->registerFunction('url:tag', [__CLASS__, 'wiki2xhtmlTag']);
    }

    public static function wiki2xhtmlTag($url, $content)
    {
        $url = substr($url, 4);
        if (strpos($content, 'tag:') === 0) {
            $content = substr($content, 4);
        }

        $tag_url        = Html::stripHostURL(dotclear()->blog()->url . dotclear()->url()->getURLFor('tag'));
        $res['url']     = $tag_url . '/' . rawurlencode(dotclear()->meta()::sanitizeMetaID($url));
        $res['content'] = $content;

        return $res;
    }

    public static function tagsField($main, $sidebar, $post)
    {
        if (!empty($_POST['post_tags'])) {
            $value = $_POST['post_tags'];
        } else {
            $value = $post ? dotclear()->meta()->getMetaStr((string) $post->post_meta, 'tag') : '';
        }
        $sidebar['metas-box']['items']['post_tags'] = '<h5><label class="s-tags" for="post_tags">' . __('Tags') . '</label></h5>' .
        '<div class="p s-tags" id="tags-edit">' . Form::textarea('post_tags', 20, 3, (string) $value, 'maximal') . '</div>';
    }

    public static function setTags($cur, $post_id)
    {
        $post_id = (int) $post_id;

        if (isset($_POST['post_tags'])) {
            $tags = $_POST['post_tags'];
            dotclear()->meta()->delPostMeta($post_id, 'tag');

            foreach (dotclear()->meta()->splitMetaValues($tags) as $tag) {
                dotclear()->meta()->setPostMeta($post_id, 'tag', $tag);
            }
        }
    }

    public static function adminPostsActionsPage($ap)
    {
        $ap->addAction(
            [__('Tags') => [__('Add tags') => 'tags']],
            [__CLASS__, 'adminAddTags']
        );

        if (dotclear()->user()->check('delete,contentadmin', dotclear()->blog()->id)) {
            $ap->addAction(
                [__('Tags') => [__('Remove tags') => 'tags_remove']],
                [__CLASS__, 'adminRemoveTags']
            );
        }
    }

    public static function adminAddTags(Action $ap, $post)
    {
        if (!empty($post['new_tags'])) {
            $tags  = dotclear()->meta()->splitMetaValues($post['new_tags']);
            $posts = $ap->getRS();
            while ($posts->fetch()) {
                # Get tags for post
                $post_meta = dotclear()->meta()->getMetadata([
                    'meta_type' => 'tag',
                    'post_id'   => $posts->post_id, ]);
                $pm = [];
                while ($post_meta->fetch()) {
                    $pm[] = $post_meta->meta_id;
                }
                foreach ($tags as $t) {
                    if (!in_array($t, $pm)) {
                        dotclear()->meta()->setPostMeta($posts->post_id, 'tag', $t);
                    }
                }
            }
            dotclear()->notice()->addSuccessNotice(
                sprintf(
                    __(
                        'Tag has been successfully added to selected entries',
                        'Tags have been successfully added to selected entries',
                        count($tags)
                    )
                )
            );
            $ap->redirect(true);
        } else {
            $tag_url = dotclear()->blog()->url . dotclear()->url()->getURLFor('tag');

            $opts = dotclear()->user()->getOptions();
            $type = $opts['tag_list_format'] ?? 'more';

            $editor_tags_options = [
                'meta_url'            => '?handler=admin.plugin.Tags&amp;tag=',
                'list_type'           => $type,
                'text_confirm_remove' => __('Are you sure you want to remove this tag?'),
                'text_add_meta'       => __('Add a tag to this entry'),
                'text_choose'         => __('Choose from list'),
                'text_all'            => __('all'),
                'text_separation'     => __('Enter tags separated by comma'),
            ];

            $msg = [
                'tags_autocomplete' => __('used in %e - frequency %p%'),
                'entry'             => __('entry'),
                'entries'           => __('entries'),
            ];

            $ap->setPageBreadcrumb(
                [
                    Html::escapeHTML(dotclear()->blog()->name) => '',
                    __('Entries')                       => $ap->getRedirection(true),
                    __('Add tags to this selection')    => '',
                ]
            );
            $ap->setPageHead(
                $ap::jsMetaEditor() .
                $ap::jsJson('editor_tags_options', $editor_tags_options) .
                $ap::jsJson('editor_tags_msg', $msg) .
                Filer::load('js/jquery/jquery.autocomplete.js') .
                Filer::load('js/posts_actions.js', 'Plugin', 'Tags') .
                Filer::Load('style.css', 'Plugin', 'Tags')
            );
            $ap->setPageContent(
                '<form action="' . $ap->getURI() . '" method="post">' .
                $ap->getCheckboxes() .
                '<div><label for="new_tags" class="area">' . __('Tags to add:') . '</label> ' .
                Form::textarea('new_tags', 60, 3) .
                '</div>' .
                dotclear()->nonce()->form() . $ap->getHiddenFields() .
                Form::hidden(['action'], 'tags') .
                '<p><input type="submit" value="' . __('Save') . '" ' .
                    'name="save_tags" /></p>' .
                    '</form>'
            );
        }
    }
    public static function adminRemoveTags(Action $ap, $post)
    {
        if (!empty($post['meta_id']) && dotclear()->user()->check('delete,contentadmin', dotclear()->blog()->id)) {
            $posts = $ap->getRS();
            while ($posts->fetch()) {
                foreach ($_POST['meta_id'] as $v) {
                    dotclear()->meta()->delPostMeta($posts->post_id, 'tag', $v);
                }
            }
            dotclear()->notice()->addSuccessNotice(
                sprintf(
                    __(
                        'Tag has been successfully removed from selected entries',
                        'Tags have been successfully removed from selected entries',
                        count($_POST['meta_id'])
                    )
                )
            );
            $ap->redirect(true);
        } else {
            $tags = [];

            foreach ($ap->getIDS() as $id) {
                $post_tags = dotclear()->meta()->getMetadata([
                    'meta_type' => 'tag',
                    'post_id'   => (int) $id, ])->toStatic()->rows();
                foreach ($post_tags as $v) {
                    if (isset($tags[$v['meta_id']])) {
                        $tags[$v['meta_id']]++;
                    } else {
                        $tags[$v['meta_id']] = 1;
                    }
                }
            }
            if (empty($tags)) {
                throw new Exception(__('No tags for selected entries'));
            }
            $ap->setPageBreadcrumb(
                [
                    Html::escapeHTML(dotclear()->blog()->name)            => '',
                    __('Entries')                                  => 'posts.php',
                    __('Remove selected tags from this selection') => '',
                ]
            );
            $posts_count = count($_POST['entries']);

            $ap->setPageContent(
                '<form action="' . $ap->getURI() . '" method="post">' .
                $ap->getCheckboxes() .
                '<div><p>' . __('Following tags have been found in selected entries:') . '</p>'
            );

            foreach ($tags as $k => $n) {
                $label = '<label class="classic">%s %s</label>';
                if ($posts_count == $n) {
                    $label = sprintf($label, '%s', '<strong>%s</strong>');
                }
                $ap->setPageContent(
                    '<p>' . sprintf(
                        $label,
                        Form::checkbox(['meta_id[]'], Html::escapeHTML($k)),
                        Html::escapeHTML($k)
                    ) .
                    '</p>'
                );
            }

            $ap->setPageContent(
                '<p><input type="submit" value="' . __('ok') . '" />' .

                dotclear()->nonce()->form() . $ap->getHiddenFields() .
                Form::hidden(['action'], 'tags_remove') .
                    '</p></div></form>'
            );
        }
    }

    public static function postHeaders()
    {
        $tag_url = dotclear()->blog()->url . dotclear()->url()->getURLFor('tag');

        $opts = dotclear()->user()->getOptions();
        $type = $opts['tag_list_format'] ?? 'more';

        $editor_tags_options = [
            'meta_url'            => '?handler=admin.plugin.Tags&amp;tag=',
            'list_type'           => $type,
            'text_confirm_remove' => __('Are you sure you want to remove this tag?'),
            'text_add_meta'       => __('Add a tag to this entry'),
            'text_choose'         => __('Choose from list'),
            'text_all'            => __('all'),
            'text_separation'     => __('Enter tags separated by comma'),
        ];

        $msg = [
            'tags_autocomplete' => __('used in %e - frequency %p%'),
            'entry'             => __('entry'),
            'entries'           => __('entries'),
        ];

        return
        Utils::jsJson('editor_tags_options', $editor_tags_options) .
        Utils::jsJson('editor_tags_msg', $msg) .
        Filer::load('js/jquery/jquery.autocomplete.js') .
        Filer::load('js/post.js', 'Plugin', 'Tags') .
        Filer::load('style.css', 'Plugin', 'Tags');
    }

    public static function adminUserForm($args = null)
    {
        if ($args === null) {
            $opts = dotclear()->user()->getOptions();
        } elseif ($args instanceof Record) {
            $opts = $args->options();
        } else {
            $opts = [];
        }

        $combo                 = [];
        $combo[__('Short')]    = 'more';
        $combo[__('Extended')] = 'all';

        $value = array_key_exists('tag_list_format', $opts) ? $opts['tag_list_format'] : 'more';

        echo
        '<div class="fieldset"><h5 id="tags_prefs">' . __('Tags') . '</h5>' .
        '<p><label for="user_tag_list_format" class="classic">' . __('Tags list format:') . '</label> ' .
        Form::combo('user_tag_list_format', $combo, $value) .
            '</p></div>';
    }

    public static function setTagListFormat($cur, $user_id = null)
    {
        if (!is_null($user_id)) {
            $cur->user_options['tag_list_format'] = $_POST['user_tag_list_format'];
        }
    }
}
