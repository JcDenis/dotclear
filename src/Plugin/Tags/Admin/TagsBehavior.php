<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Admin;

// Dotclear\Plugin\Tags\Admin\TagsBehavior
use ArrayObject;
use Dotclear\App;
use Dotclear\Core\User\UserContainer;
use Dotclear\Database\Cursor;
use Dotclear\Database\Param;
use Dotclear\Database\Record;
use Dotclear\Exception\ModuleException;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\GPC\GPCGroup;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Plugin\CKEditor\Admin\CKEditorPluginItem;
use Dotclear\Plugin\CKEditor\Admin\CKEditorPluginItems;
use Dotclear\Process\Admin\Action\Action;
use Dotclear\Process\Admin\Action\ActionItem;

/**
 * Admin behaviors for plugin Tags.
 *
 * @ingroup  Plugin Tags Behavior
 */
class TagsBehavior
{
    public function __construct()
    {
        App::core()->behavior('adminPostFormItems')->add([$this, 'tagsField']);
        App::core()->behavior('adminAfterPostCreate')->add([$this, 'setTags']);
        App::core()->behavior('adminAfterPostUpdate')->add([$this, 'setTags']);
        App::core()->behavior('adminPostHeaders')->add([$this, 'postHeaders']);
        App::core()->behavior('adminPostsActionsPage')->add([$this, 'adminPostsActionsPage']);
        App::core()->behavior('adminPreferencesForm')->add([$this, 'adminUserForm']);
        App::core()->behavior('adminBeforeUserOptionsUpdate')->add([$this, 'setTagListFormat']);
        App::core()->behavior('adminUserForm')->add([$this, 'adminUserForm']);
        App::core()->behavior('adminBeforeUserCreate')->add([$this, 'setTagListFormat']);
        App::core()->behavior('adminBeforeUserUpdate')->add([$this, 'setTagListFormat']);
        App::core()->behavior('adminPageHelpBlock')->add([$this, 'adminPageHelpBlock']);
        App::core()->behavior('adminPostEditor')->add([$this, 'adminPostEditor']);
        App::core()->behavior('adminBeforeAddCKEditorPlugins')->add([$this, 'adminBeforeAddCKEditorPlugins']);
    }

    public function adminPostEditor(string $editor = '', string $context = '', array $tags = [], string $syntax = ''): string
    {
        if (!in_array($editor, ['LegacyEditor', 'CKEditor']) || 'post' != $context) {
            return '';
        }

        $tag_url = App::core()->blog()->getURLFor('tag');

        if ('LegacyEditor' == $editor) {
            return
            App::core()->resource()->json('legacy_editor_tags', [
                'tag' => [
                    'title' => __('Tag'),
                    'url'   => $tag_url,
                ],
            ]) .
            App::core()->resource()->load('legacy-post.js', 'Plugin', 'tags');
        }

        return
            App::core()->resource()->json('ck_editor_tags', [
                'tag_title' => __('Tag'),
                'tag_url'   => $tag_url,
            ]);
    }

    public function adminBeforeAddCKEditorPlugins(CKEditorPluginItems $items, string $context): void
    {
        if ('post' == $context) {
            $items->addItem(new CKEditorPluginItem(
                name: 'dctags',
                button: 'dcTags',
                url: App::core()->resource()->url('ckeditor-tags-plugin.js', 'Plugin', 'Tags', 'js'),
            ));
        }
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
        if (!GPC::post()->empty('post_tags')) {
            $value = GPC::post()->string('post_tags');
        } else {
            $value = $post ? App::core()->meta()->getMetaStr((string) $post->field('post_meta'), 'tag') : '';
        }
        $sidebar['metas-box']['items']['post_tags'] = '<h5><label class="s-tags" for="post_tags">' . __('Tags') . '</label></h5>' .
        '<div class="p s-tags" id="tags-edit">' . Form::textarea('post_tags', 20, 3, (string) $value, 'maximal') . '</div>';
    }

    public function setTags(Cursor $cur, int $post_id): void
    {
        if (GPC::post()->isset('post_tags')) {
            $tags = GPC::post()->string('post_tags');
            App::core()->meta()->delPostMeta($post_id, 'tag');

            foreach (App::core()->meta()->splitMetaValues($tags) as $tag) {
                App::core()->meta()->setPostMeta($post_id, 'tag', $tag);
            }
        }
    }

    public function adminPostsActionsPage(Action $ap)
    {
        $ap->addAction(new ActionItem(
            group: __('Tags'),
            actions: [__('Add tags') => 'tags'],
            callback: [$this, 'adminAddTags'],
        ));

        if (App::core()->user()->check('delete,contentadmin', App::core()->blog()->id)) {
            $ap->addAction(new ActionItem(
                group: __('Tags'),
                actions: [__('Remove tags') => 'tags_remove'],
                callback: [$this, 'adminRemoveTags'],
            ));
        }
    }

    public function adminAddTags(Action $ap, GPCGroup $from): void
    {
        if (!$from->empty('new_tags')) {
            $tags  = App::core()->meta()->splitMetaValues($from->string('new_tags'));
            $posts = $ap->getRS();
            while ($posts->fetch()) {
                // Get tags for post
                $param = new Param();
                $param->set('meta_type', 'tag');
                $param->set('post_id', $posts->integer('post_id'));
                $post_meta = App::core()->meta()->getMetadata(param: $param);
                $pm        = [];
                while ($post_meta->fetch()) {
                    $pm[] = $post_meta->field('meta_id');
                }
                foreach ($tags as $t) {
                    if (!in_array($t, $pm)) {
                        App::core()->meta()->setPostMeta($posts->integer('post_id'), 'tag', $t);
                    }
                }
            }
            App::core()->notice()->addSuccessNotice(
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
            $tag_url = App::core()->blog()->getURLFor('tag');

            $opts = App::core()->user()->getOptions();
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
                    Html::escapeHTML(App::core()->blog()->name) => '',
                    __('Entries')                               => $ap->getRedirection(true),
                    __('Add tags to this selection')            => '',
                ]
            );
            $ap->setPageHead(
                App::core()->resource()->metaEditor() .
                App::core()->resource()->json('editor_tags_options', $editor_tags_options) .
                App::core()->resource()->json('editor_tags_msg', $msg) .
                App::core()->resource()->load('jquery/jquery.autocomplete.js') .
                App::core()->resource()->load('posts_actions.js', 'Plugin', 'Tags') .
                App::core()->resource()->Load('style.css', 'Plugin', 'Tags')
            );
            $ap->setPageContent(
                '<form action="' . $ap->getURI() . '" method="post">' .
                $ap->getCheckboxes() .
                '<div><label for="new_tags" class="area">' . __('Tags to add:') . '</label> ' .
                Form::textarea('new_tags', 60, 3) .
                '</div>' .
                App::core()->nonce()->form() . $ap->getHiddenFields() .
                Form::hidden(['action'], 'tags') .
                '<p><input type="submit" value="' . __('Save') . '" ' .
                    'name="save_tags" /></p>' .
                    '</form>'
            );
        }
    }

    public function adminRemoveTags(Action $ap, GPCGroup $from): void
    {
        if (!$from->empty('meta_id') && App::core()->user()->check('delete,contentadmin', App::core()->blog()->id)) {
            $posts = $ap->getRS();
            while ($posts->fetch()) {
                foreach ($from->array('meta_id') as $v) {
                    App::core()->meta()->delPostMeta($posts->integer('post_id'), 'tag', $v);
                }
            }
            App::core()->notice()->addSuccessNotice(
                sprintf(
                    __(
                        'Tag has been successfully removed from selected entries',
                        'Tags have been successfully removed from selected entries',
                        count($from->array('meta_id'))
                    )
                )
            );
            $ap->redirect(true);
        } else {
            $tags  = [];
            $param = new Param();
            $param->set('meta_type', 'tag');

            foreach ($ap->getIDS() as $id) {
                $param->set('post_id', (int) $id);
                $post_tags = App::core()->meta()->getMetadata(param: $param)->toStatic()->rows();
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
                    Html::escapeHTML(App::core()->blog()->name)     => '',
                    __('Entries')                                   => 'posts.php',
                    __('Remove selected tags from this selection')  => '',
                ]
            );
            $posts_count = count($from->array('entries'));

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

                App::core()->nonce()->form() . $ap->getHiddenFields() .
                Form::hidden(['action'], 'tags_remove') .
                    '</p></div></form>'
            );
        }
    }

    public function postHeaders(): string
    {
        $tag_url = App::core()->blog()->getURLFor('tag');

        $opts = App::core()->user()->getOptions();
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
        App::core()->resource()->json('editor_tags_options', $editor_tags_options) .
        App::core()->resource()->json('editor_tags_msg', $msg) .
        App::core()->resource()->load('jquery/jquery.autocomplete.js') .
        App::core()->resource()->load('post.js', 'Plugin', 'Tags') .
        App::core()->resource()->load('style.css', 'Plugin', 'Tags');
    }

    public function adminUserForm(UserContainer $user): void
    {
        $combo                 = [];
        $combo[__('Short')]    = 'more';
        $combo[__('Extended')] = 'all';

        echo '<div class="fieldset"><h5 id="tags_prefs">' . __('Tags') . '</h5>' .
        '<p><label for="user_tag_list_format" class="classic">' . __('Tags list format:') . '</label> ' .
        Form::combo('user_tag_list_format', $combo, $user->getOption('tag_list_format') ?? 'more') .
            '</p></div>';
    }

    public function setTagListFormat(Cursor $cur, ?string $user_id = null): void
    {
        if (!is_null($user_id)) {
            $opt                    = $cur->getField('user_options');
            $opt['tag_list_format'] = GPC::post()->string('user_tag_list_format');
            $cur->setField('user_options', $opt);
        }
    }
}
