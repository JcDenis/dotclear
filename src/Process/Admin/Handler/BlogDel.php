<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\BlogDel
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Mapper\Strings;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * Admin blog deletion page.
 *
 * @ingroup  Admin Blog Handler
 */
class BlogDel extends AbstractPage
{
    private $blog_id   = '';
    private $blog_name = '';

    protected function getPermissions(): string|bool
    {
        return '';
    }

    protected function getPagePrepend(): ?bool
    {
        // search the blog
        if (!GPC::post()->empty('blog_id')) {
            try {
                $param = new Param();
                $param->set('blog_id', GPC::post()->string('blog_id'));

                $record = App::core()->blogs()->getBlogs(param: $param);
                if ($record->isEmpty()) {
                    App::core()->error()->add(__('No such blog ID'));
                } else {
                    $this->blog_id   = $record->field('blog_id');
                    $this->blog_name = $record->field('blog_name');
                }
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Delete the blog
        if (!App::core()->error()->flag() && $this->blog_id && !GPC::post()->empty('del')) {
            if (!App::core()->user()->checkPassword(GPC::post()->string('pwd'))) {
                App::core()->error()->add(__('Password verification failed'));
            } else {
                try {
                    App::core()->blogs()->deleteBlogs(ids: new Strings($this->blog_id));
                    App::core()->notice()->addSuccessNotice(sprintf(__('Blog "%s" successfully deleted'), Html::escapeHTML($this->blog_name)));

                    App::core()->adminurl()->redirect('admin.blogs');
                } catch (Exception $e) {
                    App::core()->error()->add($e->getMessage());
                }
            }
        }

        // Page setup
        $this
            ->setPageTitle(__('Delete a blog'))
            ->setPageBreadcrumb([
                __('System')        => '',
                __('Blogs')         => App::core()->adminurl()->get('admin.blogs'),
                __('Delete a blog') => '',
            ])
        ;

        return true;
    }

    protected function getPageContent(): void
    {
        if (App::core()->error()->flag()) {
            return;
        }

        echo '<div class="warning-msg"><p><strong>' . __('Warning') . '</strong></p>' .
        '<p>' . sprintf(
            __('You are about to delete the blog %s. Every entry, comment and category will be deleted.'),
            '<strong>' . $this->blog_id . ' (' . $this->blog_name . ')</strong>'
        ) . '</p></div>' .
        '<p>' . __('Please give your password to confirm the blog deletion.') . '</p>';

        echo '<form action="' . App::core()->adminurl()->root() . '" method="post">' .
        '<p><label for="pwd">' . __('Your password:') . '</label> ' .
        Form::password('pwd', 20, 255, ['autocomplete' => 'current-password']) . '</p>' .
        '<p><input type="submit" class="delete" name="del" value="' . __('Delete this blog') . '" />' .
        ' <input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" />' .
        Form::hidden('blog_id', $this->blog_id) .
        App::core()->adminurl()->getHiddenFormFields('admin.blog.del', [], true) . '</p>' .
            '</form>';
    }
}
