<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Tags\Admin;

// Dotclear\Plugin\Tags\Admin\Handler
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Action\Action\PostAction;
use Dotclear\Process\Admin\Inventory\Inventory\PostInventory;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * Tags Admin page.
 *
 * @ingroup  Plugin Tags
 */
class Handler extends AbstractPage
{
    private $t_tag = '';
    private $t_posts;
    private $t_post_list;
    private $t_page        = 1;
    private $t_nb_per_page = 30;

    protected function getPermissions(): string|bool
    {
        return 'usage,contentadmin';
    }

    protected function getActionInstance(): ?PostAction
    {
        return GPC::request()->empty('tag') ?
            null :
            new PostAction(App::core()->adminurl()->get('admin.plugin.Tags'), ['tag' => GPC::request()->string('tag')]);
    }

    protected function getPagePrepend(): ?bool
    {
        $this->t_tag = GPC::request()->string('tag');

        if (empty($this->t_tag)) {
            $this
                ->setPageTitle(__('Tags'))
                ->setPageHelp('tags')
                ->setPageHead(App::core()->resource()->load('style.css', 'Plugin', 'Tags'))
                ->setPageBreadcrumb([
                    html::escapeHTML(App::core()->blog()->name) => '',
                    __('Tags')                                  => '',
                ])
            ;
        } else {
            $this->t_page = !GPC::get()->empty('page') ? max(1, GPC::get()->int('page')) : 1;

            // Rename a tag
            if (GPC::post()->isset('new_tag_id')) {
                $new_id = App::core()->meta()::sanitizeMetaID(GPC::post()->string('new_tag_id'));

                try {
                    if (App::core()->meta()->updateMeta($this->t_tag, $new_id, 'tag')) {
                        App::core()->notice()->addSuccessNotice(__('Tag has been successfully renamed'));
                        App::core()->adminurl()->redirect('admin.plugin.Tags', ['tag' => $new_id]);
                    }
                } catch (Exception $e) {
                    App::core()->error()->add($e->getMessage());
                }
            }

            // Delete a tag
            if (!GPC::post()->empty('delete') && App::core()->user()->check('publish,contentadmin', App::core()->blog()->id)) {
                try {
                    App::core()->meta()->delMeta($this->t_tag, 'tag');
                    App::core()->adminurl()->addSuccessNotice(__('Tag has been successfully removed'));
                    App::core()->adminurl()->redirect('admin.plugin.Tags');
                } catch (Exception $e) {
                    App::core()->error()->add($e->getMessage());
                }
            }

            $param = new Param();
            $param->set('limit', [(($this->t_page - 1) * $this->t_nb_per_page), $this->t_nb_per_page]);
            $param->set('no_content', true);

            $param->set('meta_id', $this->t_tag);
            $param->set('meta_type', 'tag');
            $param->set('post_type', '');

            // Get posts
            try {
                $this->t_posts     = App::core()->meta()->getPostsByMeta($param);
                $count             = App::core()->meta()->countPostsByMeta($param);
                $this->t_post_list = new PostInventory($this->t_posts, $count);
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }

            $this
                ->setPageTitle(__('Tags'))
                ->setPageHelp('tag_posts')
                ->setPageHead(
                    App::core()->resource()->load('style.css', 'Plugin', 'Tags') .
                    App::core()->resource()->load('_posts_list.js') .
                    App::core()->resource()->json('posts_tags_msg', [
                        'confirm_tag_delete' => sprintf(__('Are you sure you want to remove tag: “%s”?'), html::escapeHTML($this->t_tag)),
                    ]) .
                    App::core()->resource()->load('posts.js', 'Plugin', 'Tags') .
                    App::core()->resource()->confirmClose('tag_rename')
                )
                ->setPageBreadcrumb(
                    [
                        html::escapeHTML(App::core()->blog()->name)                          => '',
                        __('Tags')                                                           => App::core()->adminurl()->get('admin.plugin.Tags'),
                        __('Tag') . ' &ldquo;' . html::escapeHTML($this->t_tag) . '&rdquo;'  => '',
                    ]
                )
            ;
        }

        return true;
    }

    protected function getPageContent(): void
    {
        if (empty($this->t_tag)) {
            $param = new Param();
            $param->set('meta_type', 'tag');
            $tags = App::core()->meta()->getMetadata(param: $param);
            $tags = App::core()->meta()->computeMetaStats($tags);
            $tags->sort('meta_id_lower', 'asc');

            $last_letter = null;
            $cols        = ['', ''];
            $col         = 0;
            while ($tags->fetch()) {
                $letter = mb_strtoupper(mb_substr($tags->f('meta_id_lower'), 0, 1));

                if ($last_letter != $letter) {
                    if ($tags->index() >= round($tags->count() / 2)) {
                        $col = 1;
                    }
                    $cols[$col] .= '<tr class="tagLetter"><td colspan="2"><span>' . $letter . '</span></td></tr>';
                }

                $cols[$col] .= '<tr class="line">' .
                '<td class="maximal"><a href="' . App::core()->adminurl()->get('admin.plugin.Tags', ['tag' => rawurlencode($tags->f('meta_id'))]) . '">' .
                    $tags->f('meta_id') . '</a></td>' .
                '<td class="nowrap count"><strong>' . $tags->f('count') . '</strong> ' .
                    (($tags->fInt('count') == 1) ? __('entry') : __('entries')) . '</td>' .
                    '</tr>';

                $last_letter = $letter;
            }

            $table = '<div class="col"><table class="tags">%s</table></div>';

            if ($cols[0]) {
                echo '<div class="two-cols">';
                printf($table, $cols[0]);
                if ($cols[1]) {
                    printf($table, $cols[1]);
                }
                echo '</div>';
            } else {
                echo '<p>' . __('No tags on this blog.') . '</p>';
            }
        } else {
            echo '<p><a class="back" href="' . App::core()->adminurl()->get('admin.plugin.Tags') . '">' . __('Back to tags list') . '</a></p>';

            if (!App::core()->error()->flag()) {
                if (!$this->t_posts->isEmpty()) {
                    echo '<div class="tag-actions vertical-separator">' .
                    '<h3>' . html::escapeHTML($this->t_tag) . '</h3>' .
                    '<form action="' . App::core()->adminurl()->root() . '" method="post" id="tag_rename">' .
                    '<p><label for="new_tag_id" class="classic">' . __('Rename') . '</label> ' .
                    form::field('new_tag_id', 20, 255, html::escapeHTML($this->t_tag)) .
                    '<input type="submit" value="' . __('OK') . '" />' .
                    ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
                    App::core()->adminurl()->getHiddenFormFields('admin.plugin.Tags', ['tag' => $this->t_tag], true) .
                        '</p></form>';
                    // Remove tag
                    if (App::core()->user()->check('contentadmin', App::core()->blog()->id)) {
                        echo '<form id="tag_delete" action="' . App::core()->adminurl()->root() . '" method="post">' .
                        '<p><input type="submit" class="delete" name="delete" value="' . __('Delete this tag') . '" />' .
                    App::core()->adminurl()->getHiddenFormFields('admin.plugin.Tags', ['tag' => $this->t_tag], true) .
                            '</p></form>';
                    }
                    echo '</div>';
                }

                // Show posts
                echo '<h4 class="vertical-separator pretty-title">' . sprintf(__('List of entries with the tag “%s”'), html::escapeHTML($this->t_tag)) . '</h4>';
                $this->t_post_list->display(
                    $this->t_page,
                    $this->t_nb_per_page,
                    '<form action="' . App::core()->adminurl()->root() . '" method="post" id="form-entries">' .

                    '%s' .

                    '<div class="two-cols">' .
                    '<p class="col checkboxes-helpers"></p>' .

                    '<p class="col right"><label for="action" class="classic">' . __('Selected entries action:') . '</label> ' .
                    form::combo('action', $this->action->getCombo()) .
                    '<input id="do-action" type="submit" value="' . __('OK') . '" /></p>' .
                    App::core()->adminurl()->getHiddenFormFields('admin.plugin.Tags', ['post_type' => '', 'tag' => $this->t_tag], true) .
                    '</div>' .
                    '</form>'
                );
            }
        }
    }
}
