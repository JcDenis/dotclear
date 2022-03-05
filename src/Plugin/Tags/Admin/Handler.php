<?php
/**
 * @class Dotclear\Plugin\Tags\Admin\Handler
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

use Dotclear\Process\Admin\Action\Action\PostAction;
use Dotclear\Process\Admin\Inventory\Inventory\PostInventory;
use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Module\AbstractPage;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Handler extends AbstractPage
{
    private $t_tag = '';
    private $t_posts = null;
    private $t_post_list = null;
    private $t_posts_actions_page = null;
    private $t_page = 1;
    private $t_nb_per_page = 30;

    protected function getPermissions(): string|null|false
    {
        return 'usage,contentadmin';
    }


    protected function getPagePrepend(): ?bool
    {
        $this->t_tag = $_REQUEST['tag'] ?? '';

        if (empty($this->t_tag)) {
            $this
                ->setPageTitle(__('Tags'))
                ->setPageHelp('tags')
                ->setPageHead(dotclear()->resource()->load('style.css', 'Plugin', 'Tags'))
                ->setPageBreadcrumb([
                    html::escapeHTML(dotclear()->blog()->name) => '',
                    __('Tags')                          => '',
                ])
            ;
        } else {
            $this->t_page        = !empty($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

            # Rename a tag
            if (isset($_POST['new_tag_id'])) {
                $new_id = dotclear()->meta()::sanitizeMetaID($_POST['new_tag_id']);

                try {
                    if (dotclear()->meta()->updateMeta($this->t_tag, $new_id, 'tag')) {
                        dotclear()->notice()->addSuccessNotice(__('Tag has been successfully renamed'));
                        dotclear()->adminurl()->redirect('admin.plugin.Tags', ['tag' => $new_id]);
                    }
                } catch (\Exception $e) {
                    dotclear()->error()->add($e->getMessage());
                }
            }

            # Delete a tag
            if (!empty($_POST['delete']) && dotclear()->user()->check('publish,contentadmin', dotclear()->blog()->id)) {
                try {
                    dotclear()->meta()->delMeta($this->t_tag, 'tag');
                    dotclear()->adminurl()->addSuccessNotice(__('Tag has been successfully removed'));
                    dotclear()->adminurl()->redirect('admin.plugin.Tags');
                } catch (\Exception $e) {
                    dotclear()->error()->add($e->getMessage());
                }
            }

            $params               = [];
            $params['limit']      = [(($this->t_page - 1) * $this->t_nb_per_page), $this->t_nb_per_page];
            $params['no_content'] = true;

            $params['meta_id']   = $this->t_tag;
            $params['meta_type'] = 'tag';
            $params['post_type'] = '';

            # Get posts
            try {
                $this->t_posts     = dotclear()->meta()->getPostsByMeta($params);
                $counter   = dotclear()->meta()->getPostsByMeta($params, true);
                $this->t_post_list = new PostInventory($this->t_posts, (int) $counter->f(0));
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }

            $this->t_posts_actions_page = new PostAction(dotclear()->adminurl()->get('admin.plugin.Tags'), ['tag' => $this->t_tag]);

            if ($this->t_posts_actions_page->getPagePrepend()) {
                return null;
            }

            $this
                ->setPageTitle(__('Tags'))
                ->setPageHelp('tag_posts')
                ->setPageHead(
                    dotclear()->resource()->load('style.css', 'Plugin', 'Tags') .
                    dotclear()->resource()->load('_posts_list.js') .
                    dotclear()->resource()->json('posts_tags_msg', [
                        'confirm_tag_delete' => sprintf(__('Are you sure you want to remove tag: “%s”?'), html::escapeHTML($this->t_tag)),
                    ]) .
                    dotclear()->resource()->load('posts.js', 'Plugin', 'Tags') .
                    static::jsConfirmClose('tag_rename')
                )
                ->setPageBreadcrumb(
                    [
                        html::escapeHTML(dotclear()->blog()->name)                         => '',
                        __('Tags')                                                  => dotclear()->adminurl()->get('admin.plugin.Tags'),
                        __('Tag') . ' &ldquo;' . html::escapeHTML($this->t_tag) . '&rdquo;' => '',
                    ]
                )
            ;
        }

        return true;
    }

    protected function getPageContent(): void
    {
        if (empty($this->t_tag)) {
            $tags = dotclear()->meta()->getMetadata(['meta_type' => 'tag']);
            $tags = dotclear()->meta()->computeMetaStats($tags);
            $tags->sort('meta_id_lower', 'asc');

            $last_letter = null;
            $cols        = ['', ''];
            $col         = 0;
            while ($tags->fetch()) {
                $letter = mb_strtoupper(mb_substr($tags->meta_id_lower, 0, 1));

                if ($last_letter != $letter) {
                    if ($tags->index() >= round($tags->count() / 2)) {
                        $col = 1;
                    }
                    $cols[$col] .= '<tr class="tagLetter"><td colspan="2"><span>' . $letter . '</span></td></tr>';
                }

                $cols[$col] .= '<tr class="line">' .
                '<td class="maximal"><a href="' . dotclear()->adminurl()->get('admin.plugin.Tags', ['tag' => rawurlencode($tags->meta_id)]) . '">' .
                    $tags->meta_id . '</a></td>' .
                '<td class="nowrap count"><strong>' . $tags->count . '</strong> ' .
                    (($tags->count == 1) ? __('entry') : __('entries')) . '</td>' .
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
            echo '<p><a class="back" href="' . dotclear()->adminurl()->get('admin.plugin.Tags') . '">' . __('Back to tags list') . '</a></p>';

            if (!dotclear()->error()->flag()) {
                if (!$this->t_posts->isEmpty()) {
                    echo
                    '<div class="tag-actions vertical-separator">' .
                    '<h3>' . html::escapeHTML($this->t_tag) . '</h3>' .
                    '<form action="' . dotclear()->adminurl()->root() . '" method="post" id="tag_rename">' .
                    '<p><label for="new_tag_id" class="classic">' . __('Rename') . '</label> ' .
                    form::field('new_tag_id', 20, 255, html::escapeHTML($this->t_tag)) .
                    '<input type="submit" value="' . __('OK') . '" />' .
                    ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
                    dotclear()->adminurl()->getHiddenFormFields('admin.plugin.Tags', ['tag' => $this->t_tag], true) .
                        '</p></form>';
                    # Remove tag
                    if (!$this->t_posts->isEmpty() && dotclear()->user()->check('contentadmin', dotclear()->blog()->id)) {    // @phpstan-ignore-line
                        echo
                        '<form id="tag_delete" action="' . dotclear()->adminurl()->root() . '" method="post">' .
                        '<p><input type="submit" class="delete" name="delete" value="' . __('Delete this tag') . '" />' .
                    dotclear()->adminurl()->getHiddenFormFields('admin.plugin.Tags', ['tag' => $this->t_tag], true) .
                            '</p></form>';
                    }
                    echo '</div>';
                }

                # Show posts
                echo '<h4 class="vertical-separator pretty-title">' . sprintf(__('List of entries with the tag “%s”'), html::escapeHTML($this->t_tag)) . '</h4>';
                $this->t_post_list->display(
                    $this->t_page,
                    $this->t_nb_per_page,
                    '<form action="' . dotclear()->adminurl()->root() . '" method="post" id="form-entries">' .

                    '%s' .

                    '<div class="two-cols">' .
                    '<p class="col checkboxes-helpers"></p>' .

                    '<p class="col right"><label for="action" class="classic">' . __('Selected entries action:') . '</label> ' .
                    form::combo('action', $this->t_posts_actions_page->getCombo()) .
                    '<input id="do-action" type="submit" value="' . __('OK') . '" /></p>' .
                    dotclear()->adminurl()->getHiddenFormFields('admin.plugin.Tags', ['post_type' => '', 'tag' => $this->t_tag], true) .
                    '</div>' .
                    '</form>'
                );
            }
        }
    }
}
