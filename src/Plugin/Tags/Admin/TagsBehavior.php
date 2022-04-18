<?php
/**
 * @note Dotclear\Plugin\Tags\Admin\TagsBehavior
 * @brief Dotclear Plugins class
 *
 * @ingroup  PluginTags
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Admin;

use ArrayObject;
use Dotclear\Database\Cursor;
use Dotclear\Database\Record;
use Dotclear\Exception\ModuleException;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Action\Action;

class TagsBehavior
{
    public function __construct()
    {
        dotclear()->behavior()->add('adminPostFormItems', [$this, 'tagsField']);
        dotclear()->behavior()->add('adminAfterPostCreate', [$this, 'setTags']);
        dotclear()->behavior()->add('adminAfterPostUpdate', [$this, 'setTags']);
        dotclear()->behavior()->add('adminPostHeaders', [$this, 'postHeaders']);
        dotclear()->behavior()->add('adminPostsActionsPage', [$this, 'adminPostsActionsPage']);
        dotclear()->behavior()->add('adminPreferencesForm', [$this, 'adminUserForm']);
        dotclear()->behavior()->add('adminBeforeUserOptionsUpdate', [$this, 'setTagListFormat']);
        dotclear()->behavior()->add('adminUserForm', [$this, 'adminUserForm']);
        dotclear()->behavior()->add('adminBeforeUserCreate', [$this, 'setTagListFormat']);
        dotclear()->behavior()->add('adminBeforeUserUpdate', [$this, 'setTagListFormat']);
        dotclear()->behavior()->add('adminPageHelpBlock', [$this, 'adminPageHelpBlock']);
        dotclear()->behavior()->add('adminPostEditor', [$this, 'adminPostEditor']);
        dotclear()->behavior()->add('ckeditorExtraPlugins', [$this, 'ckeditorExtraPlugins']);
    }

    public function adminPostEditor(string $editor = '', string $context = '', array $tags = [], string $syntax = ''): string
    {
        if (!in_array($editor, ['LegacyEditor', 'CKEditor']) || 'post' != $context) {
            return '';
        }

        $tag_url = dotclear()->blog()->getURLFor('tag');

        if ('LegacyEditor' == $editor) {
            return
            dotclear()->resource()->json('legacy_editor_tags', [
                'tag' => [
                    'title' => __('Tag'),
                    'url'   => $tag_url,
                ],
            ]) .
            dotclear()->resource()->load('legacy-post.js', 'Plugin', 'tags');
        }

        return
            dotclear()->resource()->json('ck_editor_tags', [
                'tag_title' => __('Tag'),
                'tag_url'   => $tag_url,
            ]);
    }

    public function ckeditorExtraPlugins(ArrayObject $extraPlugins, string $context): void
    {
        if ('post' != $context) {
            return;
        }
        $extraPlugins[] = [
            'name'   => 'dctags',
            'button' => 'dcTags',
            'url'    => dotclear()->resource()->url('ckeditor-tags-plugin.js', 'Plugin', 'Tags', 'js'),
        ];
    }

    public function adminPageHelpBlock(ArrayObject $blocks): void
    {
        $found = false;
        foreach ($blocks as $block) {
            if ('core_post' == $block) {
                $found = true;

                break;
            }
        }
        if (!$found) {
            return;
        }
        $blocks[] = 'tag_post';
    }

    public function tagsField(ArrayObject $main, ArrayObject $sidebar, ?Record $post, string $type = null): void
    {
        if (!empty($_POST['post_tags'])) {
            $value = $_POST['post_tags'];
        } else {
            $value = $post ? dotclear()->meta()->getMetaStr((string) $post->f('post_meta'), 'tag') : '';
        }
        $sidebar['metas-box']['items']['post_tags'] = '<h5><label class="s-tags" for="post_tags">' . __('Tags') . '</label></h5>' .
        '<div class="p s-tags" id="tags-edit">' . Form::textarea('post_tags', 20, 3, (string) $value, 'maximal') . '</div>';
    }

    public function setTags(Cursor $cur, int $post_id): void
    {
        if (isset($_POST['post_tags'])) {
            $tags = $_POST['post_tags'];
            dotclear()->meta()->delPostMeta($post_id, 'tag');

            foreach (dotclear()->meta()->splitMetaValues($tags) as $tag) {
                dotclear()->meta()->setPostMeta($post_id, 'tag', $tag);
            }
        }
    }

    public function adminPostsActionsPage(Action $ap)
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

    public function adminAddTags(Action $ap, ArrayObject $post): void
    {
        if (!empty($post['new_tags'])) {
            $tags  = dotclear()->meta()->splitMetaValues($post['new_tags']);
            $posts = $ap->getRS();
            while ($posts->fetch()) {
                // Get tags for post
                $post_meta = dotclear()->meta()->getMetadata([
                    'meta_type' => 'tag',
                    'post_id'   => $posts->fInt('post_id'), ]);
                $pm = [];
                while ($post_meta->fetch()) {
                    $pm[] = $post_meta->f('meta_id');
                }
                foreach ($tags as $t) {
                    if (!in_array($t, $pm)) {
                        dotclear()->meta()->setPostMeta($posts->fInt('post_id'), 'tag', $t);
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
            $tag_url = dotclear()->blog()->getURLFor('tag');

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
                    __('Entries')                              => $ap->getRedirection(true),
                    __('Add tags to this selection')           => '',
                ]
            );
            $ap->setPageHead(
                dotclear()->resource()->metaEditor() .
                dotclear()->resource()->json('editor_tags_options', $editor_tags_options) .
                dotclear()->resource()->json('editor_tags_msg', $msg) .
                dotclear()->resource()->load('jquery/jquery.autocomplete.js') .
                dotclear()->resource()->load('posts_actions.js', 'Plugin', 'Tags') .
                dotclear()->resource()->Load('style.css', 'Plugin', 'Tags')
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

    public function adminRemoveTags(Action $ap, ArrayObject $post): void
    {
        if (!empty($post['meta_id']) && dotclear()->user()->check('delete,contentadmin', dotclear()->blog()->id)) {
            $posts = $ap->getRS();
            while ($posts->fetch()) {
                foreach ($_POST['meta_id'] as $v) {
                    dotclear()->meta()->delPostMeta($posts->fInt('post_id'), 'tag', $v);
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
                        ++$tags[$v['meta_id']];
                    } else {
                        $tags[$v['meta_id']] = 1;
                    }
                }
            }
            if (empty($tags)) {
                throw new ModuleException(__('No tags for selected entries'));
            }
            $ap->setPageBreadcrumb(
                [
                    Html::escapeHTML(dotclear()->blog()->name)     => '',
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

    public function postHeaders(): string
    {
        $tag_url = dotclear()->blog()->getURLFor('tag');

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
        dotclear()->resource()->json('editor_tags_options', $editor_tags_options) .
        dotclear()->resource()->json('editor_tags_msg', $msg) .
        dotclear()->resource()->load('jquery/jquery.autocomplete.js') .
        dotclear()->resource()->load('post.js', 'Plugin', 'Tags') .
        dotclear()->resource()->load('style.css', 'Plugin', 'Tags');
    }

    public function adminUserForm(?Record $args = null): void
    {
        if (null === $args) {
            $opts = dotclear()->user()->getOptions();
        } elseif ($args instanceof Record) {
            $opts = $args->call('options');
        } else {
            $opts = [];
        }

        $combo                 = [];
        $combo[__('Short')]    = 'more';
        $combo[__('Extended')] = 'all';

        $value = array_key_exists('tag_list_format', $opts) ? $opts['tag_list_format'] : 'more';

        echo '<div class="fieldset"><h5 id="tags_prefs">' . __('Tags') . '</h5>' .
        '<p><label for="user_tag_list_format" class="classic">' . __('Tags list format:') . '</label> ' .
        Form::combo('user_tag_list_format', $combo, $value) .
            '</p></div>';
    }

    public function setTagListFormat(Cursor $cur, ?int $user_id = null): void
    {
        if (!is_null($user_id)) {
            $opt                    = $cur->getField('user_options');
            $opt['tag_list_format'] = $_POST['user_tag_list_format'];
            $cur->setField('user_options', $opt);
        }
    }
}
