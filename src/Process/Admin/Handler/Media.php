<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\Media
use Dotclear\App;
use Dotclear\Database\StaticRecord;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\Clock;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\File\Zip\Zip;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Filter\Filter;
use Dotclear\Process\Admin\Filter\Filter\MediaFilters;
use Dotclear\Process\Admin\Inventory\Inventory\MediaInventory;
use Dotclear\Process\Admin\Page\AbstractPage;
use Exception;

/**
 * Admin media list page.
 *
 * @ingroup  Admin Media Handler
 */
class Media extends AbstractPage
{
    /**
     * @var bool $media_has_query
     *           Page has a valid query
     */
    private $media_has_query = false;

    /**
     * @var bool $media_writable
     *           Media dir is writable
     */
    private $media_writable = false;

    /**
     * @var bool $media_archivable
     *           Media dir is archivable
     */
    private $media_archivable;

    /**
     * @var array<string,array> $media_dir
     *                          Dirs and files fileItem objects
     */
    private $media_dir;

    /**
     * @var array<int,array> $media_last
     *                       User media recents
     */
    private $media_last;

    /**
     * @var array<int,array> $media_fav
     *                       User media favorites
     */
    private $media_fav;

    /**
     * @var bool $media_uploader
     *           Uses enhance uploader
     */
    private $media_uploader;

    protected function getPermissions(): string|bool
    {
        return 'media,media_admin';
    }

    protected function getFilterInstance(): ?MediaFilters
    {
        // AdminMedia extends MediaFilter
        return new MediaFilters();
    }

    protected function getInventoryInstance(): ?MediaInventory
    {
        if (!App::core()->media()) {
            return null;
        }

        // try to load core media and themes
        try {
            App::core()->media()->setFileSort($this->filter->getValue(id: 'sortby') . '-' . $this->filter->getValue(id: 'order'));

            if ('' != $this->filter->getValue(id: 'q')) {
                $this->media_has_query = App::core()->media()->searchMedia($this->filter->getValue(id: 'q'));
            }
            if (!$this->media_has_query) {
                $try_d = $this->filter->getValue(id: 'd');
                // Reset current dir
                $this->filter->updateValue(id: 'd', value: null);
                // Change directory (may cause an exception if directory doesn't exist)
                App::core()->media()->chdir($try_d);
                // Restore current dir variable
                $this->filter->updateValue(id: 'd', value: $try_d);
                App::core()->media()->getDir();
            } else {
                $this->filter->updateValue(id: 'd', value: null);
                App::core()->media()->chdir('');
            }
            $this->media_writable = App::core()->media()->writable();
            $this->media_dir      = App::core()->media()->dir;

            $rs = $this->getDirsRecord();

            return new MediaInventory($rs, (int) $rs->count());
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());
        }

        return null;
    }

    protected function getPagePrepend(): ?bool
    {
        if (!$this->filter) {
            return true;
        }

        if ($this->filter->getValue(id: 'popup')) {
            $this->setPageType('popup');
        }

        $this->filter->addFilter(filter: new Filter(id: 'handler', value: 'admin.media'));

        $this->media_uploader = (bool) App::core()->user()->preferences('interface')->getPreference('enhanceduploader');

        // Zip download
        if (!GPC::get()->empty('zipdl') && App::core()->user()->check('media_admin', App::core()->blog()->id)) {
            try {
                if (str_starts_with(realpath(App::core()->media()->root . '/' . $this->filter->getValue(id: 'd')), realpath(App::core()->media()->root))) {
                    // Media folder or one of it's sub-folder(s)
                    @set_time_limit(300);
                    $fp  = fopen('php://output', 'wb');
                    $zip = new Zip($fp);
                    $zip->addExclusion('#(^|/).(.*?)_(m|s|sq|t).jpg$#');
                    $zip->addDirectory(App::core()->media()->root . '/' . $this->filter->getValue(id: 'd'), '', true);

                    header('Content-Disposition: attachment;filename=' . Clock::format(format: 'Y-m-d') . '-' . App::core()->blog()->id . '-' . ($this->filter->getValue(id: 'd') ?: 'media') . '.zip');
                    header('Content-Type: application/x-zip');
                    $zip->write();
                    unset($zip);

                    exit;
                }
                $this->filter->set(id: 'd', value: null);
                App::core()->media()->chdir($this->filter->getValue(id: 'd'));

                throw new AdminException(__('Not a valid directory'));
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // User last and fav dirs
        if ($this->showLast()) {
            if (!GPC::get()->empty('fav')) {
                if ($this->updateFav(rtrim((string) $this->filter->getValue(id: 'd'), '/'), 'n' == GPC::get()->string('fav'))) {
                    App::core()->adminurl()->redirect('admin.media', $this->filter->getValues());
                }
            }
            $this->updateLast(rtrim((string) $this->filter->getValue(id: 'd'), '/'));
        }

        // New directory
        if ($this->getDirs() && !GPC::post()->empty('newdir')) {
            $nd = Files::tidyFileName(GPC::post()->string('newdir'));
            if (array_filter($this->getDirs('files'), fn ($i) => $i->basename === $nd)
                || array_filter($this->getDirs('dirs'), fn ($i) => $i->basename === $nd)
            ) {
                App::core()->notice()->addWarningNotice(sprintf(
                    __('Directory or file "%s" already exists.'),
                    Html::escapeHTML($nd)
                ));
            } else {
                try {
                    App::core()->media()->makeDir(GPC::post()->string('newdir'));
                    App::core()->notice()->addSuccessNotice(sprintf(
                        __('Directory "%s" has been successfully created.'),
                        Html::escapeHTML($nd)
                    ));
                    App::core()->adminurl()->redirect('admin.media', $this->filter->getValues());
                } catch (Exception $e) {
                    App::core()->error()->add($e->getMessage());
                }
            }
        }

        // Adding a file
        if ($this->getDirs() && !empty($_FILES['upfile'])) {
            // only one file per request : @see option singleFileUploads in admin/js/jsUpload/jquery.fileupload
            $upfile = [
                'name'     => $_FILES['upfile']['name'][0],
                'type'     => $_FILES['upfile']['type'][0],
                'tmp_name' => $_FILES['upfile']['tmp_name'][0],
                'error'    => $_FILES['upfile']['error'][0],
                'size'     => $_FILES['upfile']['size'][0],
                'title'    => Html::escapeHTML($_FILES['upfile']['name'][0]),
            ];

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-type: application/json');
                $message = [];

                try {
                    Files::uploadStatus($upfile);
                    $new_file_id = (int) App::core()->media()->uploadMediaFile($upfile['tmp_name'], $upfile['name'], $upfile['title']);

                    $message['files'][] = [
                        'name' => $upfile['name'],
                        'size' => $upfile['size'],
                        'html' => $this->mediaLine($new_file_id),
                    ];
                } catch (Exception $e) {
                    $message['files'][] = [
                        'name'  => $upfile['name'],
                        'size'  => $upfile['size'],
                        'error' => $e->getMessage(),
                    ];
                }
                echo json_encode($message);

                exit();
            }

            try {
                Files::uploadStatus($upfile);

                App::core()->media()->uploadMediaFile(
                    $upfile['tmp_name'],
                    $upfile['name'],
                    Html::escapeHTML(GPC::post()->string('upfiletitle')),
                    !GPC::post()->empty('upfilepriv')
                );

                App::core()->notice()->addSuccessNotice(__('Files have been successfully uploaded.'));
                App::core()->adminurl()->redirect('admin.media', $this->filter->getValues());
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Removing items
        if ($this->getDirs() && !GPC::post()->empty('medias') && !GPC::post()->empty('delete_medias')) {
            try {
                foreach (GPC::post()->array('medias') as $media) {
                    App::core()->media()->removeItem(rawurldecode($media));
                }
                App::core()->notice()->addSuccessNotice(
                    sprintf(
                        __(
                            'Successfully delete one media.',
                            'Successfully delete %d medias.',
                            count(GPC::post()->array('medias'))
                        ),
                        count(GPC::post()->array('medias'))
                    )
                );
                App::core()->adminurl()->redirect('admin.media', $this->filter->getValues());
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Removing item from popup only
        if ($this->getDirs() && !GPC::post()->empty('rmyes') && !GPC::post()->empty('remove')) {
            $remove = rawurldecode(GPC::post()->string('remove'));
            $forget = false;

            try {
                if (is_dir(Path::real(App::core()->media()->getPwd() . '/' . Path::clean($remove), false))) {
                    $msg = __('Directory has been successfully removed.');
                    // Remove dir from recents/favs if necessary
                    $forget = true;
                } else {
                    $msg = __('File has been successfully removed.');
                }
                App::core()->media()->removeItem($remove);
                if ($forget) {
                    $this->updateLast($this->filter->getValue(id: 'd') . '/' . Path::clean($remove), true);
                    $this->updateFav($this->filter->getValue(id: 'd') . '/' . Path::clean($remove), true);
                }
                App::core()->notice()->addSuccessNotice($msg);
                App::core()->adminurl()->redirect('admin.media', $this->filter->getValues());
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Rebuild directory
        if ($this->getDirs() && App::core()->user()->isSuperAdmin() && !GPC::post()->empty('rebuild')) {
            try {
                App::core()->media()->rebuild($this->filter->getValue(id: 'd'));

                App::core()->notice()->success(
                    sprintf(
                        __('Directory "%s" has been successfully rebuilt.'),
                        Html::escapeHTML($this->filter->getValue(id: 'd'))
                    )
                );
                App::core()->adminurl()->redirect('admin.media', $this->filter->getValues());
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // DISPLAY confirm page for rmdir & rmfile
        if ($this->getDirs() && !GPC::get()->empty('remove') && GPC::get()->empty('noconfirm')) {
            $this->breadcrumb([__('confirm removal') => '']);
        } else {
            $this->breadcrumb();
            $this->setPageHead(
                App::core()->resource()->modal() .
                $this->filter->getFoldableJSCode(url: App::core()->adminurl()->get('admin.media', array_diff_key($this->filter->getValues(), $this->filter->getFormValues()), '&')) .
                App::core()->resource()->load('_media.js') .
                ($this->mediaWritable() ? App::core()->resource()->upload(['d=' . $this->filter->getValue(id: 'd')]) : '')
            );
        }

        if ($this->filter->getValue(id: 'popup')) {
            $this->setPageType('popup');
        }

        return true;
    }

    protected function getPageContent(): void
    {
        if ($this->getDirs() && !GPC::get()->empty('remove') && GPC::get()->empty('noconfirm')) {
            echo '<form action="' . App::core()->adminurl()->root() . '" method="post">' .
            '<p>' . sprintf(
                __('Are you sure you want to remove %s?'),
                Html::escapeHTML(GPC::get()->string('remove'))
            ) . '</p>' .
            '<p><input type="submit" value="' . __('Cancel') . '" /> ' .
            ' &nbsp; <input type="submit" name="rmyes" value="' . __('Yes') . '" />' .
            App::core()->adminurl()->getHiddenFormFields('admin.media', $this->filter->getValues(), true) .
            form::hidden('remove', Html::escapeHTML(GPC::get()->string('remove'))) . '</p>' .
            '</form>';

            return;
        }

        if (!$this->mediaWritable() && !App::core()->error()->flag()) {
            App::core()->notice()->warning(__('You do not have sufficient permissions to write to this folder.'));
        }

        if (!$this->getDirs()) {
            return;
        }

        // Recent media folders
        $last_folders = '';
        if ($this->showLast()) {
            $last_folders_item = '';
            $fav_url           = '';
            $fav_img           = '';
            $fav_alt           = '';
            // Favorites directories
            $fav_dirs = $this->getFav();
            foreach ($fav_dirs as $ld) {
                // Add favorites dirs on top of combo
                $ld_params      = $this->filter->getValues();
                $ld_params['d'] = $ld;
                $ld_params['q'] = ''; // Reset search
                $last_folders_item .= '<option value="' . urldecode(App::core()->adminurl()->get('admin.media', $ld_params)) . '"' .
                    (rtrim((string) $this->filter->getValue(id: 'd'), '/') == $ld ? ' selected="selected"' : '') . '>' .
                    '/' . $ld . '</option>' . "\n";
                if (rtrim((string) $this->filter->getValue(id: 'd'), '/') == $ld) {
                    // Current directory is a favorite → button will un-fav
                    $ld_params['fav'] = 'n';
                    $fav_url          = urldecode(App::core()->adminurl()->get('admin.media', $ld_params));
                    unset($ld_params['fav']);
                    $fav_img = 'images/fav-on.png';
                    $fav_alt = __('Remove this folder from your favorites');
                }
            }
            if ('' != $last_folders_item) {
                // add a separator between favorite dirs and recent dirs
                $last_folders_item .= '<option disabled>_________</option>';
            }
            // Recent directories
            $last_dirs = $this->getlast();
            foreach ($last_dirs as $ld) {
                if (!in_array($ld, $fav_dirs)) {
                    $ld_params      = $this->filter->getValues();
                    $ld_params['d'] = $ld;
                    $ld_params['q'] = ''; // Reset search
                    $last_folders_item .= '<option value="' . urldecode(App::core()->adminurl()->get('admin.media', $ld_params)) . '"' .
                        (rtrim((string) $this->filter->getValue(id: 'd'), '/') == $ld ? ' selected="selected"' : '') . '>' .
                        '/' . $ld . '</option>' . "\n";
                    if (rtrim((string) $this->filter->getValue(id: 'd'), '/') == $ld) {
                        // Current directory is not a favorite → button will fav
                        $ld_params['fav'] = 'y';
                        $fav_url          = urldecode(App::core()->adminurl()->get('admin.media', $ld_params));
                        unset($ld_params['fav']);
                        $fav_img = 'images/fav-off.png';
                        $fav_alt = __('Add this folder to your favorites');
                    }
                }
            }
            if ('' != $last_folders_item) {
                $last_folders = '<p class="media-recent hidden-if-no-js">' .
                '<label class="classic" for="switchfolder">' . __('Goto recent folder:') . '</label> ' .
                    '<select name="switchfolder" id="switchfolder">' .
                    $last_folders_item .
                    '</select>' .
                    ' <a id="media-fav-dir" href="' . $fav_url . '" title="' . $fav_alt . '"><img src="?df=' . $fav_img . '" alt="' . $fav_alt . '" /></a>' .
                    '</p>';
            }
        }

        if ($this->filter->getValue(id: 'select')) {
            // Select mode (popup or not)
            echo '<div class="' . ($this->filter->getValue(id: 'popup') ? 'form-note ' : '') . 'info"><p>';
            if (1 == $this->filter->getValue(id: 'select')) {
                echo sprintf(__('Select a file by clicking on %s'), '<img src="?df=images/plus.png" alt="' . __('Select this file') . '" />');
            } else {
                echo sprintf(__('Select files and click on <strong>%s</strong> button'), __('Choose selected medias'));
            }
            if ($this->mediaWritable()) {
                echo ' ' . __('or') . ' ' . sprintf('<a href="#fileupload">%s</a>', __('upload a new file'));
            }
            echo '</p></div>';
        } else {
            if ($this->filter->getValue(id: 'post_id')) {
                echo '<div class="form-note info"><p>' . sprintf(
                    __('Choose a file to attach to entry %s by clicking on %s'),
                    '<a href="' .
                    Html::escapeHTML(App::core()->posttype()->getPostAdminURL(type: $this->filter->getPostType(), id: $this->filter->getValue(id: 'post_id'))) . '">' .
                    Html::escapeHTML($this->filter->getPostTitle()) . '</a>',
                    '<img src="?df=images/plus.png" alt="' . __('Attach this file to entry') . '" />'
                );
                if ($this->mediaWritable()) {
                    echo ' ' . __('or') . ' ' . sprintf('<a href="#fileupload">%s</a>', __('upload a new file'));
                }
                echo '</p></div>';
            }
            if ($this->filter->getValue(id: 'popup')) {
                echo '<div class="info"><p>' . sprintf(
                    __('Choose a file to insert into entry by clicking on %s'),
                    '<img src="?df=images/plus.png" alt="' . __('Attach this file to entry') . '" />'
                );
                if ($this->mediaWritable()) {
                    echo ' ' . __('or') . ' ' . sprintf('<a href="#fileupload">%s</a>', __('upload a new file'));
                }
                echo '</p></div>';
            }
        }

        // add file mode into the filter box
        $this->filter->addFilter(new Filter(
            id: 'file_mode',
            value: $this->filter->getValue(id: 'file_mode'),
            type: 'none',
            contents: '<p><span class="media-file-mode">' .
                '<a href="' . App::core()->adminurl()->get('admin.media', array_merge($this->filter->getValues(), ['file_mode' => 'grid'])) . '" title="' . __('Grid display mode') . '">' .
                '<img src="?df=images/grid-' . ('grid' == $this->filter->getValue(id: 'file_mode') ? 'on' : 'off') . '.png" alt="' . __('Grid display mode') . '" />' .
                '</a>' .
                '<a href="' . App::core()->adminurl()->get('admin.media', array_merge($this->filter->getValues(), ['file_mode' => 'list'])) . '" title="' . __('List display mode') . '">' .
                '<img src="?df=images/list-' . ('list' == $this->filter->getValue(id: 'file_mode') ? 'on' : 'off') . '.png" alt="' . __('List display mode') . '" />' .
                '</a>' .
                '</span></p>'
        ));

        $fmt_form_media = '<form action="' . App::core()->adminurl()->root() . '" method="post" id="form-medias">' .
        '<div class="files-group">%s</div>' .
        '<p class="hidden">' .
        App::core()->adminurl()->getHiddenFormFields('admin.media', $this->filter->getValues(), true) .
        '</p>';

        if (!$this->filter->getValue(id: 'popup') || 1 < $this->filter->getValue(id: 'select')) {
            // Checkboxes and action
            $fmt_form_media .= '<div class="' . (!$this->filter->getValue(id: 'popup') ? 'medias-delete' : '') . ' ' . (1 < $this->filter->getValue(id: 'select') ? 'medias-select' : '') . '">' .
                '<p class="checkboxes-helpers"></p>' .
                '<p>';
            if (1 < $this->filter->getValue(id: 'select')) {
                $fmt_form_media .= '<input type="submit" class="select" id="select_medias" name="select_medias" value="' . __('Choose selected medias') . '"/> ';
            }
            if (!$this->filter->getValue(id: 'popup')) {
                $fmt_form_media .= '<input type="submit" class="delete" id="delete_medias" name="delete_medias" value="' . __('Remove selected medias') . '"/>';
            }
            $fmt_form_media .= '</p>' .
                '</div>';
        }
        $fmt_form_media .= '</form>';

        echo '<div class="media-list">';
        echo $last_folders;

        // remove form filters from hidden fields
        $form_filters_hidden_fields = array_diff_key($this->filter->getValues(), ['nb' => '', 'order' => '', 'sortby' => '', 'q' => '']);

        // display filter
        $this->filter->displayHTMLForm(adminurl: 'admin.media', append: App::core()->adminurl()->getHiddenFormFields('admin.media', $form_filters_hidden_fields));

        // display list
        if (null !== $this->inventory) {
            $this->inventory->display($this->filter, $fmt_form_media, $this->hasQuery());
        }

        echo '</div>';

        if ((!$this->hasQuery()) && ($this->mediaWritable() || $this->mediaArchivable())) {
            echo '<div class="vertical-separator">' .
            '<h3 class="out-of-screen-if-js">' . sprintf(__('In %s:'), ('' == $this->filter->getValue(id: 'd') ? '“' . __('Media manager') . '”' : '“' . $this->filter->getValue(id: 'd') . '”')) . '</h3>';
        }

        if ((!$this->hasQuery()) && ($this->mediaWritable() || $this->mediaArchivable())) {
            echo '<div class="two-boxes odd">';

            // Create directory
            if ($this->mediaWritable()) {
                echo '<form action="' . App::core()->adminurl()->root() . '" method="post" class="fieldset">' .
                '<div id="new-dir-f">' .
                '<h4 class="pretty-title">' . __('Create new directory') . '</h4>' .
                '<p><label for="newdir">' . __('Directory Name:') . '</label>' .
                form::field('newdir', 35, 255) . '</p>' .
                '<p><input type="submit" value="' . __('Create') . '" />' .
                App::core()->adminurl()->getHiddenFormFields('admin.media', $this->filter->getValues(), true) .
                    '</p>' .
                    '</div>' .
                    '</form>';
            }

            // Get zip directory
            if ($this->mediaArchivable() && !$this->filter->getValue(id: 'popup')) {
                echo '<div class="fieldset">' .
                '<h4 class="pretty-title">' . sprintf(__('Backup content of %s'), ('' == $this->filter->getValue(id: 'd') ? '“' . __('Media manager') . '”' : '“' . $this->filter->getValue(id: 'd') . '”')) . '</h4>' .
                '<p><a class="button submit" href="' . App::core()->adminurl()->get(
                    'admin.media',
                    array_merge($this->filter->getValues(), ['zipdl' => 1])
                ) . '">' . __('Download zip file') . '</a></p>' .
                    '</div>';
            }

            echo '</div>';
        }

        if (!$this->hasQuery() && $this->mediaWritable()) {
            echo '<div class="two-boxes fieldset even">';
            if ($this->showUploader()) {
                echo '<div class="enhanced_uploader">';
            } else {
                echo '<div>';
            }

            echo '<h4>' . __('Add files') . '</h4>' .
            '<p class="more-info">' . __('Please take care to publish media that you own and that are not protected by copyright.') . '</p>' .
            '<form id="fileupload" action="' . App::core()->adminurl()->root() . '" method="post" enctype="multipart/form-data" aria-disabled="false">' .
            '<p>' . form::hidden(['MAX_FILE_SIZE'], App::core()->config()->get('media_upload_maxsize')) .
            App::core()->nonce()->form() . '</p>' .
                '<div class="fileupload-ctrl"><p class="queue-message"></p><ul class="files"></ul></div>';

            echo '<div class="fileupload-buttonbar clear">';

            echo '<p><label for="upfile">' . '<span class="add-label one-file">' . __('Choose file') . '</span>' . '</label>' .
            '<button class="button choose_files">' . __('Choose files') . '</button>' .
            '<input type="file" id="upfile" name="upfile[]"' . ($this->showUploader() ? ' multiple="mutiple"' : '') . ' data-url="' . Html::escapeURL(App::core()->adminurl()->get('admin.media', $this->filter->getValues(), '&')) . '" /></p>';

            echo '<p class="max-sizer form-note">&nbsp;' . __('Maximum file size allowed:') . ' ' . Files::size((int) App::core()->config()->get('media_upload_maxsize')) . '</p>';

            echo '<p class="one-file"><label for="upfiletitle">' . __('Title:') . '</label>' . form::field('upfiletitle', 35, 255) . '</p>' .
            '<p class="one-file"><label for="upfilepriv" class="classic">' . __('Private') . '</label> ' .
            form::checkbox('upfilepriv', 1) . '</p>';

            if (!$this->showUploader()) {
                echo '<p class="one-file form-help info">' . __('To send several files at the same time, you can activate the enhanced uploader in') .
                ' <a href="' . App::core()->adminurl()->get('admin.user.pref', ['tab' => 'user-options']) . '">' . __('My preferences') . '</a></p>';
            }

            echo '<p class="clear"><button class="button clean">' . __('Refresh') . '</button>' .
            '<input class="button cancel one-file" type="reset" value="' . __('Clear all') . '"/>' .
            '<input class="button start" type="submit" value="' . __('Upload') . '"/></p>' .
                '</div>';

            echo '<p style="clear:both;">' .
            App::core()->adminurl()->getHiddenFormFields('admin.media', $this->filter->getValues(), true) .
                '</p>' .
                '</form>' .
                '</div>' .
                '</div>';
        }

        // Empty remove form (for javascript actions)
        echo '<form id="media-remove-hide" action="' . App::core()->adminurl()->root() . '" method="post" class="hidden">' .
        '<div>' .
        form::hidden('rmyes', 1) .
        App::core()->adminurl()->getHiddenFormFields('admin.media', $this->filter->getValues(), true) .
        form::hidden('remove', '') .
        App::core()->nonce()->form() .
            '</div>' .
            '</form>';

        if ((!$this->hasQuery()) && ($this->mediaWritable() || $this->mediaArchivable())) {
            echo '</div>';
        }

        if (!$this->filter->getValue(id: 'popup')) {
            echo '<div class="info"><p>' . sprintf(
                __('Current settings for medias and images are defined in %s'),
                '<a href="' . App::core()->adminurl()->get('admin.blog.pref') . '#medias-settings">' . __('Blog parameters') . '</a>'
            ) . '</p></div>';

            // Go back button
            echo '<p><input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" /></p>';
        }
    }

    /**
     * The breadcrumb of media page or popup.
     *
     * @param array $element The additionnal element
     */
    public function breadcrumb(array $element = []): void
    {
        $option = $param = [];

        if (empty($element)) {
            $param = [
                'd' => '',
                'q' => '',
            ];

            if ($this->media_has_query || $this->filter->getValue(id: 'q')) {
                $count = $this->media_has_query ? count($this->getDirs('files')) : 0;

                $element[__('Search:') . ' ' . $this->filter->getValue(id: 'q') . ' (' . sprintf(__('%s file found', '%s files found', $count), $count) . ')'] = '';
            } else {
                $bc_url   = App::core()->adminurl()->get('admin.media', array_merge($this->filter->getEscapeValues(), ['d' => '%s']), '&');
                $bc_media = App::core()->media()->breadCrumb($bc_url, '<span class="page-title">%s</span>');
                if ('' != $bc_media) {
                    $element[$bc_media] = '';
                    $option['hl']       = true;
                }
            }
        }

        $elements = [
            Html::escapeHTML(App::core()->blog()->name) => '',
            __('Media manager')                         => empty($param) ? '' :
                App::core()->adminurl()->get('admin.media', array_merge($this->filter->getValues(), array_merge($this->filter->getValues(), $param))),
        ];
        $options = [
            'home_link' => !$this->filter->getValue(id: 'popup'),
        ];

        $this->setPageBreadcrumb(array_merge($elements, $element), array_merge($options, $option));
    }

    /**
     * Check if page has a valid query.
     *
     * @return bool Has query
     */
    public function hasQuery(): bool
    {
        return $this->media_has_query;
    }

    /**
     * Check if media dir is writable.
     *
     * @return bool Is writable
     */
    public function mediaWritable(): bool
    {
        return $this->media_writable;
    }

    /**
     * Check if media dir is archivable.
     *
     * @return bool Is archivable
     */
    public function mediaArchivable(): bool
    {
        if (null === $this->media_archivable) {
            $rs = $this->getDirsRecord();

            $this->media_archivable = App::core()->user()->check('media_admin', App::core()->blog()->id)
                && !(count($rs) == 0 || (count($rs) == 1 && $rs->__data[0]->parent));
        }

        return $this->media_archivable;
    }

    /**
     * Return list of fileItem objects of current dir.
     *
     * @param string $type dir, file, all type
     *
     * @return null|array Dirs and/or files fileItem objects
     */
    public function getDirs(string $type = ''): ?array
    {
        if (!empty($type)) {
            return $this->media_dir[$type] ?? null;
        }

        return $this->media_dir;
    }

    /**
     * Return static record instance of fileItem objects.
     *
     * @return staticRecord Dirs and/or files fileItem objects
     */
    public function getDirsRecord(): staticRecord
    {
        $dir = $this->media_dir;
        // Remove hidden directories (unless 'media_dir_showhidden' is set to true)
        if (false === App::core()->config()->get('media_dir_showhidden')) {
            for ($i = count($dir['dirs']) - 1; 0 <= $i; --$i) {
                if ($dir['dirs'][$i]->d) {
                    if (str_starts_with($dir['dirs'][$i]->basename, '.')) {
                        unset($dir['dirs'][$i]);
                    }
                }
            }
        }
        $items = array_values(array_merge($dir['dirs'], $dir['files']));

        return staticRecord::newFromArray($items);
    }

    /**
     * Return html code of an element of list or grid items list.
     *
     * @param int $file_id The file id
     *
     * @return string The element
     */
    public function mediaLine(int $file_id): string
    {
        return null !== $this->inventory ? $this->inventory->mediaLine($this->filter, App::core()->media()->getFile($file_id), 1, $this->media_has_query) : '';
    }

    /**
     * Show enhance uploader.
     *
     * @return bool Show enhance uploader
     */
    public function showUploader(): bool
    {
        return $this->media_uploader;
    }

    /**
     * Number of recent/fav dirs to show.
     *
     * @return int Nb of dirs
     */
    public function showLast(): int
    {
        return abs((int) App::core()->user()->preferences('interface')->getPreference('media_nb_last_dirs'));
    }

    /**
     * Return list of last dirs.
     *
     * @return array Last dirs
     */
    public function getLast(): array
    {
        if (null === $this->media_last) {
            $m = App::core()->user()->preferences('interface')->getPreference('media_last_dirs');
            if (!is_array($m)) {
                $m = [];
            }
            $this->media_last = $m;
        }

        return $this->media_last;
    }

    /**
     * Update user last dirs.
     *
     * @param string $dir    The directory
     * @param bool   $remove Remove
     *
     * @return bool The change
     */
    public function updateLast(string $dir, bool $remove = false): bool
    {
        if ($this->filter->getValue(id: 'q')) {
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
                // Add new dir at the top of the list
                array_unshift($last_dirs, $dir);
                // Remove oldest dir(s)
                while (count($last_dirs) > $nb_last_dirs) {
                    array_pop($last_dirs);
                }
                $done = true;
            } else {
                // Move current dir at the top of list
                unset($last_dirs[array_search($dir, $last_dirs)]);
                array_unshift($last_dirs, $dir);
                $done = true;
            }
        }

        if ($done) {
            $this->media_last = $last_dirs;
            App::core()->user()->preferences('interface')->putPreference('media_last_dirs', $last_dirs, 'array');
        }

        return $done;
    }

    /**
     * Return list of fav dirs.
     *
     * @return array Fav dirs
     */
    public function getFav(): array
    {
        if (null === $this->media_fav) {
            $m = App::core()->user()->preferences('interface')->getPreference('media_fav_dirs');
            if (!is_array($m)) {
                $m = [];
            }
            $this->media_fav = $m;
        }

        return $this->media_fav;
    }

    /**
     * Update user fav dirs.
     *
     * @param string $dir    The directory
     * @param bool   $remove Remove
     *
     * @return bool The change
     */
    public function updateFav(string $dir, bool $remove = false): bool
    {
        if ($this->filter->getValue(id: 'q')) {
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
            App::core()->user()->preferences('interface')->putPreference('media_fav_dirs', $fav_dirs, 'array');
        }

        return $done;
    }
}
