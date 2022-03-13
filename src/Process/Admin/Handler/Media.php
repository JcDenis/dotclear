<?php
/**
 * @class Dotclear\Process\Admin\Handler\Media
 * @brief Dotclear class for admin media page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

use Dotclear\Process\Admin\Page\Page;
use Dotclear\Process\Admin\Filter\Filter;
use Dotclear\Process\Admin\Filter\Filter\DefaultFilter;
use Dotclear\Process\Admin\Filter\Filter\MediaFilter;
use Dotclear\Process\Admin\Inventory\Inventory;
use Dotclear\Process\Admin\Inventory\Inventory\MediaInventory;
use Dotclear\Database\StaticRecord;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\File\Zip\Zip;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Media extends Page
{
    /** @var    bool    Page has a valid query */
    protected $media_has_query = false;

    /** @var    bool   Media dir is writable */
    protected $media_writable = false;

    /** @var    bool   Media dir is archivable */
    protected $media_archivable = null;

    /** @var    array  Dirs and files fileItem objects */
    protected $media_dir = null;

    /** @var    array  User media recents */
    protected $media_last = null;

    /** @var    array  User media favorites */
    protected $media_fav = null;

    /** @var    bool   Uses enhance uploader */
    protected $media_uploader = null;

    protected $workspaces = ['interface'];

    protected function getPermissions(): string|null|false
    {
        return 'media,media_admin';
    }

    protected function getFilterInstance(): ?Filter
    {
        # AdminMedia extends MediaFilter
        return new MediaFilter();
    }

    protected function getInventoryInstance(): ?Inventory
    {
        if (!dotclear()->media()) {
            return null;
        }

        # try to load core media and themes
        try {
            dotclear()->media()->setFileSort($this->filter->sortby . '-' . $this->filter->order);

            if ($this->filter->q != '') {
                $this->media_has_query = dotclear()->media()->searchMedia($this->filter->q);
            }
            if (!$this->media_has_query) {
                $try_d = $this->filter->d;
                # Reset current dir
                $this->filter->d = null;
                # Change directory (may cause an exception if directory doesn't exist)
                dotclear()->media()->chdir($try_d);
                # Restore current dir variable
                $this->filter->d = $try_d;
                dotclear()->media()->getDir();
            } else {
                $this->filter->d = null;
                dotclear()->media()->chdir('');
            }
            $this->media_writable = dotclear()->media()->writable();
            $this->media_dir      = &dotclear()->media()->dir;

            $rs = $this->getDirsRecord();

            return new MediaInventory($rs, (int) $rs->count());
        } catch (\Exception $e) {
            dotclear()->error()->add($e->getMessage());
        }

        return null;
    }

    protected function getPagePrepend(): ?bool
    {
        try {

        if ($this->filter->popup) {
            $this->setPageType('popup');
        }
            dotclear()->media(true, true);
        } catch (\Exception $e) {
            dotclear()->error()->add($e->getMessage());

            return true;
        }

        $this->filter->add('handler', 'admin.media');

        $this->media_uploader = dotclear()->user()->preference()->interface->enhanceduploader;

        # Zip download
        if (!empty($_GET['zipdl']) && dotclear()->user()->check('media_admin', dotclear()->blog()->id)) {
            try {
                if (strpos(realpath(dotclear()->media()->root . '/' . $this->filter->d), realpath(dotclear()->media()->root)) === 0) {
                    # Media folder or one of it's sub-folder(s)
                    @set_time_limit(300);
                    $fp  = fopen('php://output', 'wb');
                    $zip = new Zip($fp);
                    $zip->addExclusion('#(^|/).(.*?)_(m|s|sq|t).jpg$#');
                    $zip->addDirectory(dotclear()->media()->root . '/' . $this->filter->d, '', true);

                    header('Content-Disposition: attachment;filename=' . date('Y-m-d') . '-' . dotclear()->blog()->id . '-' . ($this->filter->d ?: 'media') . '.zip');
                    header('Content-Type: application/x-zip');
                    $zip->write();
                    unset($zip);
                    exit;
                }
                $this->filter->d = null;
                dotclear()->media()->chdir($this->filter->d);

                throw new AdminException(__('Not a valid directory'));
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # User last and fav dirs
        if ($this->showLast()) {
            if (!empty($_GET['fav'])) {
                if ($this->updateFav(rtrim((string) $this->filter->d, '/'), $_GET['fav'] == 'n')) {
                    dotclear()->adminurl()->redirect('admin.media', $this->filter->values());
                }
            }
            $this->updateLast(rtrim((string) $this->filter->d, '/'));
        }

        # New directory
        if ($this->getDirs() && !empty($_POST['newdir'])) {
            $nd = Files::tidyFileName($_POST['newdir']);
            if (array_filter($this->getDirs('files'), function ($i) use ($nd) {return ($i->basename === $nd);})
                || array_filter($this->getDirs('dirs'), function ($i) use ($nd) {return ($i->basename === $nd);})
            ) {
                dotclear()->notice()->addWarningNotice(sprintf(
                    __('Directory or file "%s" already exists.'),
                    Html::escapeHTML($nd)
                ));
            } else {
                try {
                    dotclear()->media()->makeDir($_POST['newdir']);
                    dotclear()->notice()->addSuccessNotice(sprintf(
                        __('Directory "%s" has been successfully created.'),
                        Html::escapeHTML($nd)
                    ));
                    dotclear()->adminurl()->redirect('admin.media', $this->filter->values());
                } catch (\Exception $e) {
                    dotclear()->error()->add($e->getMessage());
                }
            }
        }

        # Adding a file
        if ($this->getDirs() && !empty($_FILES['upfile'])) {
            # only one file per request : @see option singleFileUploads in admin/js/jsUpload/jquery.fileupload
            $upfile = [
                'name'     => $_FILES['upfile']['name'][0],
                'type'     => $_FILES['upfile']['type'][0],
                'tmp_name' => $_FILES['upfile']['tmp_name'][0],
                'error'    => $_FILES['upfile']['error'][0],
                'size'     => $_FILES['upfile']['size'][0],
                'title'    => Html::escapeHTML($_FILES['upfile']['name'][0])
            ];

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-type: application/json');
                $message = [];

                try {
                    Files::uploadStatus($upfile);
                    $new_file_id = (string) dotclear()->media()->uploadFile($upfile['tmp_name'], $upfile['name'], $upfile['title']);

                    $message['files'][] = [
                        'name' => $upfile['name'],
                        'size' => $upfile['size'],
                        'html' => $this->mediaLine($new_file_id)
                    ];
                } catch (\Exception $e) {
                    $message['files'][] = [
                        'name'  => $upfile['name'],
                        'size'  => $upfile['size'],
                        'error' => $e->getMessage()
                    ];
                }
                echo json_encode($message);
                exit();
            }

            try {
                Files::uploadStatus($upfile);

                $f_title   = (isset($_POST['upfiletitle']) ? Html::escapeHTML($_POST['upfiletitle']) : '');
                $f_private = ($_POST['upfilepriv'] ?? false);

                dotclear()->media()->uploadFile($upfile['tmp_name'], $upfile['name'], $f_title, $f_private);

                dotclear()->notice()->addSuccessNotice(__('Files have been successfully uploaded.'));
                dotclear()->adminurl()->redirect('admin.media', $this->filter->values());
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Removing items
        if ($this->getDirs() && !empty($_POST['medias']) && !empty($_POST['delete_medias'])) {
            try {
                foreach ($_POST['medias'] as $media) {
                    dotclear()->media()->removeItem(rawurldecode($media));
                }
                dotclear()->notice()->addSuccessNotice(
                    sprintf(__('Successfully delete one media.',
                        'Successfully delete %d medias.',
                        count($_POST['medias'])
                    ),
                        count($_POST['medias'])
                    )
                );
                dotclear()->adminurl()->redirect('admin.media', $this->filter->values());
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Removing item from popup only
        if ($this->getDirs() && !empty($_POST['rmyes']) && !empty($_POST['remove'])) {
            $_POST['remove'] = rawurldecode($_POST['remove']);
            $forget          = false;

            try {
                if (is_dir(Path::real(dotclear()->media()->getPwd() . '/' . Path::clean($_POST['remove']), false))) {
                    $msg = __('Directory has been successfully removed.');
                    # Remove dir from recents/favs if necessary
                    $forget = true;
                } else {
                    $msg = __('File has been successfully removed.');
                }
                dotclear()->media()->removeItem($_POST['remove']);
                if ($forget) {
                    $this->updateLast($this->filter->d . '/' . Path::clean($_POST['remove']), true);
                    $this->updateFav($this->filter->d . '/' . Path::clean($_POST['remove']), true);
                }
                dotclear()->notice()->addSuccessNotice($msg);
                dotclear()->adminurl()->redirect('admin.media', $this->filter->values());
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # Rebuild directory
        if ($this->getDirs() && dotclear()->user()->isSuperAdmin() && !empty($_POST['rebuild'])) {
            try {
                dotclear()->media()->rebuild($this->filter->d);

                dotclear()->notice()->success(sprintf(
                    __('Directory "%s" has been successfully rebuilt.'),
                    Html::escapeHTML($this->filter->d))
                );
                dotclear()->adminurl()->redirect('admin.media', $this->filter->values());
            } catch (\Exception $e) {
                dotclear()->error()->add($e->getMessage());
            }
        }

        # DISPLAY confirm page for rmdir & rmfile
        if ($this->getDirs() && !empty($_GET['remove']) && empty($_GET['noconfirm'])) {
            $this->breadcrumb([__('confirm removal') => '']);
        } else {
            $this->breadcrumb();
            $this->setPageHead(
                dotclear()->resource()->modal() .
                $this->filter->js(dotclear()->adminurl()->get('admin.media', array_diff_key($this->filter->values(), $this->filter->values(false, true)), '&')) .
                dotclear()->resource()->load('_media.js') .
                ($this->mediaWritable() ? dotclear()->resource()->upload(['d=' . $this->filter->d]) : '')
            );
        }

        if ($this->filter->popup) {
            $this->setPageType('popup');
        }

        return true;
    }

    protected function getPageContent(): void
    {
        if (!dotclear()->media()) {
            return;
        }

        if ($this->getDirs() && !empty($_GET['remove']) && empty($_GET['noconfirm'])) {
            echo
            '<form action="' . dotclear()->adminurl()->root() . '" method="post">' .
            '<p>' . sprintf(__('Are you sure you want to remove %s?'),
                Html::escapeHTML($_GET['remove'])) . '</p>' .
            '<p><input type="submit" value="' . __('Cancel') . '" /> ' .
            ' &nbsp; <input type="submit" name="rmyes" value="' . __('Yes') . '" />' .
            dotclear()->adminurl()->getHiddenFormFields('admin.media', $this->filter->values(), true) .
            form::hidden('remove', Html::escapeHTML($_GET['remove'])) . '</p>' .
            '</form>';

            return;
        }

        if (!$this->mediaWritable() && !dotclear()->error()->flag()) {
            dotclear()->notice()->warning(__('You do not have sufficient permissions to write to this folder.'));
        }

        if (!$this->getDirs()) {
            return;
        }


        # Recent media folders
        $last_folders = '';
        if ($this->showLast()) {
            $last_folders_item = '';
            $fav_url           = '';
            $fav_img           = '';
            $fav_alt           = '';
            # Favorites directories
            $fav_dirs = $this->getFav();
            foreach ($fav_dirs as $ld) {
                # Add favorites dirs on top of combo
                $ld_params      = $this->filter->values();
                $ld_params['d'] = $ld;
                $ld_params['q'] = ''; # Reset search
                $last_folders_item .= '<option value="' . urldecode(dotclear()->adminurl()->get('admin.media', $ld_params)) . '"' .
                    ($ld == rtrim((string) $this->filter->d, '/') ? ' selected="selected"' : '') . '>' .
                    '/' . $ld . '</option>' . "\n";
                if ($ld == rtrim((string) $this->filter->d, '/')) {
                    # Current directory is a favorite → button will un-fav
                    $ld_params['fav'] = 'n';
                    $fav_url          = urldecode(dotclear()->adminurl()->get('admin.media', $ld_params));
                    unset($ld_params['fav']);
                    $fav_img = 'images/fav-on.png';
                    $fav_alt = __('Remove this folder from your favorites');
                }
            }
            if ($last_folders_item != '') {
                # add a separator between favorite dirs and recent dirs
                $last_folders_item .= '<option disabled>_________</option>';
            }
            # Recent directories
            $last_dirs = $this->getlast();
            foreach ($last_dirs as $ld) {
                if (!in_array($ld, $fav_dirs)) {
                    $ld_params      = $this->filter->values();
                    $ld_params['d'] = $ld;
                    $ld_params['q'] = ''; # Reset search
                    $last_folders_item .= '<option value="' . urldecode(dotclear()->adminurl()->get('admin.media', $ld_params)) . '"' .
                        ($ld == rtrim((string) $this->filter->d, '/') ? ' selected="selected"' : '') . '>' .
                        '/' . $ld . '</option>' . "\n";
                    if ($ld == rtrim((string) $this->filter->d, '/')) {
                        # Current directory is not a favorite → button will fav
                        $ld_params['fav'] = 'y';
                        $fav_url          = urldecode(dotclear()->adminurl()->get('admin.media', $ld_params));
                        unset($ld_params['fav']);
                        $fav_img = 'images/fav-off.png';
                        $fav_alt = __('Add this folder to your favorites');
                    }
                }
            }
            if ($last_folders_item != '') {
                $last_folders = '<p class="media-recent hidden-if-no-js">' .
                '<label class="classic" for="switchfolder">' . __('Goto recent folder:') . '</label> ' .
                    '<select name="switchfolder" id="switchfolder">' .
                    $last_folders_item .
                    '</select>' .
                    ' <a id="media-fav-dir" href="' . $fav_url . '" title="' . $fav_alt . '"><img src="?df=' . $fav_img . '" alt="' . $fav_alt . '" /></a>' .
                    '</p>';
            }
        }

        if ($this->filter->select) {
            # Select mode (popup or not)
            echo '<div class="' . ($this->filter->popup ? 'form-note ' : '') . 'info"><p>';
            if ($this->filter->select == 1) {
                echo sprintf(__('Select a file by clicking on %s'), '<img src="?df=images/plus.png" alt="' . __('Select this file') . '" />');
            } else {
                echo sprintf(__('Select files and click on <strong>%s</strong> button'), __('Choose selected medias'));
            }
            if ($this->mediaWritable()) {
                echo ' ' . __('or') . ' ' . sprintf('<a href="#fileupload">%s</a>', __('upload a new file'));
            }
            echo '</p></div>';
        } else {
            if ($this->filter->post_id) {
                echo '<div class="form-note info"><p>' . sprintf(__('Choose a file to attach to entry %s by clicking on %s'),
                    '<a href="' . dotclear()->posttype()->getPostAdminURL($this->filter->getPostType(), $this->filter->post_id) . '">' . Html::escapeHTML($this->filter->getPostTitle()) . '</a>',
                    '<img src="?df=images/plus.png" alt="' . __('Attach this file to entry') . '" />');
                if ($this->mediaWritable()) {
                    echo ' ' . __('or') . ' ' . sprintf('<a href="#fileupload">%s</a>', __('upload a new file'));
                }
                echo '</p></div>';
            }
            if ($this->filter->popup) {
                echo '<div class="info"><p>' . sprintf(__('Choose a file to insert into entry by clicking on %s'),
                    '<img src="?df=images/plus.png" alt="' . __('Attach this file to entry') . '" />');
                if ($this->mediaWritable()) {
                    echo ' ' . __('or') . ' ' . sprintf('<a href="#fileupload">%s</a>', __('upload a new file'));
                }
                echo '</p></div>';
            }
        }


        # add file mode into the filter box
        $this->filter->add((new DefaultFilter('file_mode'))->value($this->filter->file_mode)->html(
            '<p><span class="media-file-mode">' .
            '<a href="' . dotclear()->adminurl()->get('admin.media', array_merge($this->filter->values(), ['file_mode' => 'grid'])) . '" title="' . __('Grid display mode') . '">' .
            '<img src="?df=images/grid-' . ($this->filter->file_mode == 'grid' ? 'on' : 'off') . '.png" alt="' . __('Grid display mode') . '" />' .
            '</a>' .
            '<a href="' . dotclear()->adminurl()->get('admin.media', array_merge($this->filter->values(), ['file_mode' => 'list'])) . '" title="' . __('List display mode') . '">' .
            '<img src="?df=images/list-' . ($this->filter->file_mode == 'list' ? 'on' : 'off') . '.png" alt="' . __('List display mode') . '" />' .
            '</a>' .
            '</span></p>', false
        ));

        $fmt_form_media = '<form action="' . dotclear()->adminurl()->root() . '" method="post" id="form-medias">' .
        '<div class="files-group">%s</div>' .
        '<p class="hidden">' .
        dotclear()->adminurl()->getHiddenFormFields('admin.media', $this->filter->values(), true) .
        '</p>';

        if (!$this->filter->popup || $this->filter->select > 1) {
            # Checkboxes and action
            $fmt_form_media .= '<div class="' . (!$this->filter->popup ? 'medias-delete' : '') . ' ' . ($this->filter->select > 1 ? 'medias-select' : '') . '">' .
                '<p class="checkboxes-helpers"></p>' .
                '<p>';
            if ($this->filter->select > 1) {
                $fmt_form_media .= '<input type="submit" class="select" id="select_medias" name="select_medias" value="' . __('Choose selected medias') . '"/> ';
            }
            if (!$this->filter->popup) {
                $fmt_form_media .= '<input type="submit" class="delete" id="delete_medias" name="delete_medias" value="' . __('Remove selected medias') . '"/>';
            }
            $fmt_form_media .= '</p>' .
                '</div>';
        }
        $fmt_form_media .= '</form>';

        echo '<div class="media-list">';
        echo $last_folders;

        # remove form filters from hidden fields
        $form_filters_hidden_fields = array_diff_key($this->filter->values(), ['nb' => '', 'order' => '', 'sortby' => '', 'q' => '']);

        # display filter
        $this->filter->display('admin.media', dotclear()->adminurl()->getHiddenFormFields('admin.media', $form_filters_hidden_fields));

        # display list
        if ($this->inventory) {
            $this->inventory->display($this->filter, $fmt_form_media, $this->hasQuery());
        }

        echo '</div>';

        if ((!$this->hasQuery()) && ($this->mediaWritable() || $this->mediaArchivable())) {
            echo
            '<div class="vertical-separator">' .
            '<h3 class="out-of-screen-if-js">' . sprintf(__('In %s:'), ($this->filter->d == '' ? '“' . __('Media manager') . '”' : '“' . $this->filter->d . '”')) . '</h3>';
        }

        if ((!$this->hasQuery()) && ($this->mediaWritable() || $this->mediaArchivable())) {
            echo
                '<div class="two-boxes odd">';

            # Create directory
            if ($this->mediaWritable()) {
                echo
                '<form action="' . dotclear()->adminurl()->root() . '" method="post" class="fieldset">' .
                '<div id="new-dir-f">' .
                '<h4 class="pretty-title">' . __('Create new directory') . '</h4>' .
                '<p><label for="newdir">' . __('Directory Name:') . '</label>' .
                form::field('newdir', 35, 255) . '</p>' .
                '<p><input type="submit" value="' . __('Create') . '" />' .
                dotclear()->adminurl()->getHiddenFormFields('admin.media', $this->filter->values(), true) .
                    '</p>' .
                    '</div>' .
                    '</form>';
            }

            # Get zip directory
            if ($this->mediaArchivable() && !$this->filter->popup) {
                echo
                '<div class="fieldset">' .
                '<h4 class="pretty-title">' . sprintf(__('Backup content of %s'), ($this->filter->d == '' ? '“' . __('Media manager') . '”' : '“' . $this->filter->d . '”')) . '</h4>' .
                '<p><a class="button submit" href="' . dotclear()->adminurl()->get('admin.media',
                    array_merge($this->filter->values(), ['zipdl' => 1])) . '">' . __('Download zip file') . '</a></p>' .
                    '</div>';
            }

            echo
                '</div>';
        }

        if (!$this->hasQuery() && $this->mediaWritable()) {
            echo
                '<div class="two-boxes fieldset even">';
            if ($this->showUploader()) {
                echo
                    '<div class="enhanced_uploader">';
            } else {
                echo
                    '<div>';
            }

            echo
            '<h4>' . __('Add files') . '</h4>' .
            '<p class="more-info">' . __('Please take care to publish media that you own and that are not protected by copyright.') . '</p>' .
            '<form id="fileupload" action="' . dotclear()->adminurl()->root() . '" method="post" enctype="multipart/form-data" aria-disabled="false">' .
            '<p>' . form::hidden(['MAX_FILE_SIZE'], dotclear()->config()->media_upload_maxsize) .
            dotclear()->nonce()->form() . '</p>' .
                '<div class="fileupload-ctrl"><p class="queue-message"></p><ul class="files"></ul></div>';

            echo
                '<div class="fileupload-buttonbar clear">';

            echo
            '<p><label for="upfile">' . '<span class="add-label one-file">' . __('Choose file') . '</span>' . '</label>' .
            '<button class="button choose_files">' . __('Choose files') . '</button>' .
            '<input type="file" id="upfile" name="upfile[]"' . ($this->showUploader() ? ' multiple="mutiple"' : '') . ' data-url="' . Html::escapeURL(dotclear()->adminurl()->get('admin.media', $this->filter->values(), '&')) . '" /></p>';

            echo
            '<p class="max-sizer form-note">&nbsp;' . __('Maximum file size allowed:') . ' ' . Files::size((int) dotclear()->config()->media_upload_maxsize) . '</p>';

            echo
            '<p class="one-file"><label for="upfiletitle">' . __('Title:') . '</label>' . form::field('upfiletitle', 35, 255) . '</p>' .
            '<p class="one-file"><label for="upfilepriv" class="classic">' . __('Private') . '</label> ' .
            form::checkbox('upfilepriv', 1) . '</p>';

            if (!$this->showUploader()) {
                echo
                '<p class="one-file form-help info">' . __('To send several files at the same time, you can activate the enhanced uploader in') .
                ' <a href="' . dotclear()->adminurl()->get('admin.user.pref', ['tab' => 'user-options']) . '">' . __('My preferences') . '</a></p>';
            }

            echo
            '<p class="clear"><button class="button clean">' . __('Refresh') . '</button>' .
            '<input class="button cancel one-file" type="reset" value="' . __('Clear all') . '"/>' .
            '<input class="button start" type="submit" value="' . __('Upload') . '"/></p>' .
                '</div>';

            echo
            '<p style="clear:both;">' .
            dotclear()->adminurl()->getHiddenFormFields('admin.media', $this->filter->values(), true) .
                '</p>' .
                '</form>' .
                '</div>' .
                '</div>';
        }

        # Empty remove form (for javascript actions)
        echo
        '<form id="media-remove-hide" action="' . dotclear()->adminurl()->root() . '" method="post" class="hidden">' .
        '<div>' .
        form::hidden('rmyes', 1) .
        dotclear()->adminurl()->getHiddenFormFields('admin.media', $this->filter->values(), true) .
        form::hidden('remove', '') .
        dotclear()->nonce()->form() .
            '</div>' .
            '</form>';

        if ((!$this->hasQuery()) && ($this->mediaWritable() || $this->mediaArchivable())) {
            echo
                '</div>';
        }

        if (!$this->filter->popup) {
            echo '<div class="info"><p>' . sprintf(__('Current settings for medias and images are defined in %s'),
                '<a href="' . dotclear()->adminurl()->get('admin.blog.pref') . '#medias-settings">' . __('Blog parameters') . '</a>') . '</p></div>';

            # Go back button
            echo '<p><input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" /></p>';
        }
    }

    /**
     * The breadcrumb of media page or popup
     *
     * @param   array   $element    The additionnal element
     */
    public function breadcrumb(array $element = []): void
    {
        $option = $param = [];

        if (empty($element)) {
            $param = [
                'd' => '',
                'q' => ''
            ];

            if ($this->media_has_query || $this->filter->q) {
                $count = $this->media_has_query ? count($this->getDirs('files')) : 0;

                $element[__('Search:') . ' ' . $this->filter->q . ' (' . sprintf(__('%s file found', '%s files found', $count), $count) . ')'] = '';
            } else {
                $bc_url   = dotclear()->adminurl()->get('admin.media', array_merge($this->filter->values(true), ['d' => '%s']), '&');
                $bc_media = dotclear()->media()->breadCrumb($bc_url, '<span class="page-title">%s</span>');
                if ($bc_media != '') {
                    $element[$bc_media] = '';
                    $option['hl']       = true;
                }
            }
        }

        $elements = [
            Html::escapeHTML(dotclear()->blog()->name) => '',
            __('Media manager')                       => empty($param) ? '' :
                dotclear()->adminurl()->get('admin.media', array_merge($this->filter->values(), array_merge($this->filter->values(), $param)))
        ];
        $options = [
            'home_link' => !$this->filter->popup
        ];

        $this->setPageBreadcrumb(array_merge($elements, $element), array_merge($options, $option));
    }

    /**
     * Check if page has a valid query
     *
     * @return  bool    Has query
     */
    public function hasQuery(): bool
    {
        return $this->media_has_query;
    }

    /**
     * Check if media dir is writable
     *
     * @return  bool    Is writable
     */
    public function mediaWritable(): bool
    {
        return $this->media_writable;
    }

    /**
     * Check if media dir is archivable
     *
     * @return  bool    Is archivable
     */
    public function mediaArchivable(): bool
    {
        if ($this->media_archivable === null) {
            $rs = $this->getDirsRecord();

            $this->media_archivable = dotclear()->user()->check('media_admin', dotclear()->blog()->id)
                && !(count($rs) == 0 || (count($rs) == 1 && $rs->__data[0]->parent));
        }

        return $this->media_archivable;
    }

    /**
     * Return list of fileItem objects of current dir
     *
     * @param   string  $type   dir, file, all type
     *
     * @return  array           Dirs and/or files fileItem objects
     */
    public function getDirs(string $type = ''): array
    {
        if (!empty($type)) {
            return $this->media_dir[$type] ?? null;
        }

        return $this->media_dir;
    }

    /**
     * Return static record instance of fileItem objects
     *
     * @return  staticRecord    Dirs and/or files fileItem objects
     */
    public function getDirsRecord(): staticRecord
    {
        $dir = $this->media_dir;
        # Remove hidden directories (unless 'media_dir_showhidden' is set to true)
        if (dotclear()->config()->media_dir_showhidden === false) {
            for ($i = count($dir['dirs']) - 1; $i >= 0; $i--) {
                if ($dir['dirs'][$i]->d) {
                    if (strpos($dir['dirs'][$i]->basename, '.') === 0) {
                        unset($dir['dirs'][$i]);
                    }
                }
            }
        }
        $items = array_values(array_merge($dir['dirs'], $dir['files']));

        return staticRecord::newFromArray($items);
    }

    /**
     * Return html code of an element of list or grid items list
     *
     * @param   string  $file_id    The file id
     *
     * @return  string              The element
     */
    public function mediaLine(string $file_id): string
    {
        return $this->inventory ? $this->inventory->mediaLine($this->filter, dotclear()->media()->getFile($file_id), 1, $this->media_has_query) : '';
    }

    /**
     * Show enhance uploader
     *
     * @return  bool    Show enhance uploader
     */
    public function showUploader(): bool
    {
        return $this->media_uploader;
    }

    /**
     * Number of recent/fav dirs to show
     *
     * @return  int     Nb of dirs
     */
    public function showLast(): int
    {
        return abs((int) dotclear()->user()->preference()->interface->media_nb_last_dirs);
    }

    /**
     * Return list of last dirs
     *
     * @return  array   Last dirs
     */
    public function getLast(): array
    {
        if ($this->media_last === null) {
            $m = dotclear()->user()->preference()->interface->media_last_dirs;
            if (!is_array($m)) {
                $m = [];
            }
            $this->media_last = $m;
        }

        return $this->media_last;
    }

    /**
     * Update user last dirs
     *
     * @param   string  $dir        The directory
     * @param   bool    $remove     Remove
     *
     * @return  bool                The change
     */
    public function updateLast(string $dir, bool $remove = false): bool
    {
        if ($this->filter->q) {
            return false;
        }

        $nb_last_dirs = $this->showLast();
        if (!$nb_last_dirs) {
            return false;
        }

        $done      = false;
        $last_dirs = $this->getLast();

        if ($remove) {
            if (in_array($dir, $last_dirs)) {
                unset($last_dirs[array_search($dir, $last_dirs)]);
                $done = true;
            }
        } else {
            if (!in_array($dir, $last_dirs)) {
                # Add new dir at the top of the list
                array_unshift($last_dirs, $dir);
                # Remove oldest dir(s)
                while (count($last_dirs) > $nb_last_dirs) {
                    array_pop($last_dirs);
                }
                $done = true;
            } else {
                # Move current dir at the top of list
                unset($last_dirs[array_search($dir, $last_dirs)]);
                array_unshift($last_dirs, $dir);
                $done = true;
            }
        }

        if ($done) {
            $this->media_last = $last_dirs;
            dotclear()->user()->preference()->interface->put('media_last_dirs', $last_dirs, 'array');
        }

        return $done;
    }

    /**
     * Return list of fav dirs
     *
     * @return  array   Fav dirs
     */
    public function getFav(): array
    {
        if ($this->media_fav === null) {
            $m = dotclear()->user()->preference()->interface->media_fav_dirs;
            if (!is_array($m)) {
                $m = [];
            }
            $this->media_fav = $m;
        }

        return $this->media_fav;
    }

    /**
     * Update user fav dirs
     *
     * @param   string  $dir        The directory
     * @param   bool    $remove     Remove
     *
     * @return  bool                The change
     */
    public function updateFav(string $dir, bool $remove = false): bool
    {
        if ($this->filter->q) {
            return false;
        }

        $nb_last_dirs = $this->showLast();
        if (!$nb_last_dirs) {
            return false;
        }

        $done     = false;
        $fav_dirs = $this->getFav();
        if (!in_array($dir, $fav_dirs) && !$remove) {
            array_unshift($fav_dirs, $dir);
            $done = true;
        } elseif (in_array($dir, $fav_dirs) && $remove) {
            unset($fav_dirs[array_search($dir, $fav_dirs)]);
            $done = true;
        }

        if ($done) {
            $this->media_fav = $fav_dirs;
            dotclear()->user()->preference()->interface->put('media_fav_dirs', $fav_dirs, 'array');
        }

        return $done;
    }
}
