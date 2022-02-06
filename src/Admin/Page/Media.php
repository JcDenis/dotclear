<?php
/**
 * @class Dotclear\Admin\Page\Media
 * @brief Dotclear class for admin media page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page;

use Dotclear\Exception;

use Dotclear\Admin\Page;
use Dotclear\Admin\Filter;
use Dotclear\Admin\Catalog;
use Dotclear\Admin\Filter\DefaultFilter;
use Dotclear\Admin\Filter\MediaFilter;
use Dotclear\Admin\Catalog\MediaCatalog;

use Dotclear\Database\StaticRecord;

use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\File\Files;
use Dotclear\File\Path;
use Dotclear\File\Zip\Zip;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Media extends Page
{
    /** @var boolean Page has a valid query */
    protected $media_has_query = false;

    /** @var boolean Media dir is writable */
    protected $media_writable = false;

    /** @var boolean Media dir is archivable */
    protected $media_archivable = null;

    /** @var array Dirs and files fileItem objects */
    protected $media_dir = null;

    /** @var array User media recents */
    protected $media_last = null;

    /** @var array User media favorites */
    protected $media_fav = null;

    /** @var boolean Uses enhance uploader */
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

    protected function getCatalogInstance(): ?Catalog
    {
        // try to load core media and themes
        try {
            dcCore()->mediaInstance();
            dcCore()->media->setFileSort($this->filter->sortby . '-' . $this->filter->order);

            if ($this->filter->q != '') {
                $this->media_has_query = dcCore()->media->searchMedia($this->filter->q);
            }
            if (!$this->media_has_query) {
                $try_d = $this->filter->d;
                // Reset current dir
                $this->filter->d = null;
                // Change directory (may cause an exception if directory doesn't exist)
                dcCore()->media->chdir($try_d);
                // Restore current dir variable
                $this->filter->d = $try_d;
                dcCore()->media->getDir();
            } else {
                $this->filter->d = null;
                dcCore()->media->chdir('');
            }
            $this->media_writable = dcCore()->media->writable();
            $this->media_dir      = &dcCore()->media->dir;

            $rs = $this->getDirsRecord();

            return new MediaCatalog($rs, $rs->count());
        } catch (Exception $e) {
            dcCore()->error($e->getMessage());
        }

        return null;
    }

    protected function getPagePrepend(): ?bool
    {
        $this->filter->add('handler', 'admin.media');

        $this->media_uploader = dcCore()->auth->user_prefs->interface->enhanceduploader;


        # Zip download
        if (!empty($_GET['zipdl']) && dcCore()->auth->check('media_admin', dcCore()->blog->id)) {
            try {
                if (strpos(realpath(dcCore()->media->root . '/' . $this->filter->d), realpath(dcCore()->media->root)) === 0) {
                    // Media folder or one of it's sub-folder(s)
                    @set_time_limit(300);
                    $fp  = fopen('php://output', 'wb');
                    $zip = new Zip($fp);
                    $zip->addExclusion('#(^|/).(.*?)_(m|s|sq|t).jpg$#');
                    $zip->addDirectory(dcCore()->media->root . '/' . $this->filter->d, '', true);

                    header('Content-Disposition: attachment;filename=' . date('Y-m-d') . '-' . dcCore()->blog->id . '-' . ($this->filter->d ?: 'media') . '.zip');
                    header('Content-Type: application/x-zip');
                    $zip->write();
                    unset($zip);
                    exit;
                }
                $this->filter->d = null;
                dcCore()->media->chdir($this->filter->d);

                throw new Exception(__('Not a valid directory'));
            } catch (Exception $e) {
                dcCore()->error($e->getMessage());
            }
        }

        # User last and fav dirs
        if ($this->showLast()) {
            if (!empty($_GET['fav'])) {
                if ($this->updateFav(rtrim((string) $this->filter->d, '/'), $_GET['fav'] == 'n')) {
                    dcCore()->adminurl->redirect('admin.media', $this->filter->values());
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
                dcCore()->notices->addWarningNotice(sprintf(
                    __('Directory or file "%s" already exists.'),
                    Html::escapeHTML($nd)
                ));
            } else {
                try {
                    dcCore()->media->makeDir($_POST['newdir']);
                    dcCore()->notices->addSuccessNotice(sprintf(
                        __('Directory "%s" has been successfully created.'),
                        Html::escapeHTML($nd)
                    ));
                    dcCore()->adminurl->redirect('admin.media', $this->filter->values());
                } catch (Exception $e) {
                    dcCore()->error($e->getMessage());
                }
            }
        }

        # Adding a file
        if ($this->getDirs() && !empty($_FILES['upfile'])) {
            // only one file per request : @see option singleFileUploads in admin/js/jsUpload/jquery.fileupload
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
                    $new_file_id = dcCore()->media->uploadFile($upfile['tmp_name'], $upfile['name'], $upfile['title']);

                    $message['files'][] = [
                        'name' => $upfile['name'],
                        'size' => $upfile['size'],
                        'html' => $this->mediaLine($new_file_id)
                    ];
                } catch (Exception $e) {
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

                dcCore()->media->uploadFile($upfile['tmp_name'], $upfile['name'], $f_title, $f_private);

                dcCore()->notices->addSuccessNotice(__('Files have been successfully uploaded.'));
                dcCore()->adminurl->redirect('admin.media', $this->filter->values());
            } catch (Exception $e) {
                dcCore()->error($e->getMessage());
            }
        }

        # Removing items
        if ($this->getDirs() && !empty($_POST['medias']) && !empty($_POST['delete_medias'])) {
            try {
                foreach ($_POST['medias'] as $media) {
                    dcCore()->media->removeItem(rawurldecode($media));
                }
                dcCore()->notices->addSuccessNotice(
                    sprintf(__('Successfully delete one media.',
                        'Successfully delete %d medias.',
                        count($_POST['medias'])
                    ),
                        count($_POST['medias'])
                    )
                );
                dcCore()->adminurl->redirect('admin.media', $this->filter->values());
            } catch (Exception $e) {
                dcCore()->error($e->getMessage());
            }
        }

        # Removing item from popup only
        if ($this->getDirs() && !empty($_POST['rmyes']) && !empty($_POST['remove'])) {
            $_POST['remove'] = rawurldecode($_POST['remove']);
            $forget          = false;

            try {
                if (is_dir(Path::real(dcCore()->media->getPwd() . '/' . Path::clean($_POST['remove'])))) {
                    $msg = __('Directory has been successfully removed.');
                    # Remove dir from recents/favs if necessary
                    $forget = true;
                } else {
                    $msg = __('File has been successfully removed.');
                }
                dcCore()->media->removeItem($_POST['remove']);
                if ($forget) {
                    $this->updateLast($this->filter->d . '/' . Path::clean($_POST['remove']), true);
                    $this->updateFav($this->filter->d . '/' . Path::clean($_POST['remove']), true);
                }
                dcCore()->notices->addSuccessNotice($msg);
                dcCore()->adminurl->redirect('admin.media', $this->filter->values());
            } catch (Exception $e) {
                dcCore()->error($e->getMessage());
            }
        }

        # Rebuild directory
        if ($this->getDirs() && dcCore()->auth->isSuperAdmin() && !empty($_POST['rebuild'])) {
            try {
                dcCore()->media->rebuild($this->filter->d);

                dcCore()->notices->success(sprintf(
                    __('Directory "%s" has been successfully rebuilt.'),
                    Html::escapeHTML($this->filter->d))
                );
                dcCore()->adminurl->redirect('admin.media', $this->filter->values());
            } catch (Exception $e) {
                dcCore()->error($e->getMessage());
            }
        }

        # DISPLAY confirm page for rmdir & rmfile
        if ($this->getDirs() && !empty($_GET['remove']) && empty($_GET['noconfirm'])) {
            $this->breadcrumb([__('confirm removal') => '']);
        } else {
            $this->breadcrumb();
            $this->setPageHead(
                static::jsModal() .
                $this->filter->js(dcCore()->adminurl->get('admin.media', array_diff_key($this->filter->values(), $this->filter->values(false, true)), '&')) .
                static::jsLoad('js/_media.js') .
                ($this->mediaWritable() ? static::jsUpload(['d=' . $this->filter->d]) : '')
            );
        }

        if ($this->filter->popup) {
            $this->setPageType('popup');
        }

        return true;
    }

    protected function getPageContent(): void
    {
        if ($this->getDirs() && !empty($_GET['remove']) && empty($_GET['noconfirm'])) {
            echo
            '<form action="' . Html::escapeURL(dcCore()->adminurl->get('admin.media')) . '" method="post">' .
            '<p>' . sprintf(__('Are you sure you want to remove %s?'),
                Html::escapeHTML($_GET['remove'])) . '</p>' .
            '<p><input type="submit" value="' . __('Cancel') . '" /> ' .
            ' &nbsp; <input type="submit" name="rmyes" value="' . __('Yes') . '" />' .
            dcCore()->adminurl->getHiddenFormFields('admin.media', $this->filter->values()) .
            dcCore()->formNonce() .
            form::hidden('remove', Html::escapeHTML($_GET['remove'])) . '</p>' .
            '</form>';

            return;
        }

        if (!$this->mediaWritable() && !dcCore()->error()->flag()) {
            dcCore()->notices->warning(__('You do not have sufficient permissions to write to this folder.'));
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
                $ld_params      = $this->filter->values();
                $ld_params['d'] = $ld;
                $ld_params['q'] = ''; // Reset search
                $last_folders_item .= '<option value="' . urldecode(dcCore()->adminurl->get('admin.media', $ld_params)) . '"' .
                    ($ld == rtrim((string) $this->filter->d, '/') ? ' selected="selected"' : '') . '>' .
                    '/' . $ld . '</option>' . "\n";
                if ($ld == rtrim((string) $this->filter->d, '/')) {
                    // Current directory is a favorite → button will un-fav
                    $ld_params['fav'] = 'n';
                    $fav_url          = urldecode(dcCore()->adminurl->get('admin.media', $ld_params));
                    unset($ld_params['fav']);
                    $fav_img = 'images/fav-on.png';
                    $fav_alt = __('Remove this folder from your favorites');
                }
            }
            if ($last_folders_item != '') {
                // add a separator between favorite dirs and recent dirs
                $last_folders_item .= '<option disabled>_________</option>';
            }
            // Recent directories
            $last_dirs = $this->getlast();
            foreach ($last_dirs as $ld) {
                if (!in_array($ld, $fav_dirs)) {
                    $ld_params      = $this->filter->values();
                    $ld_params['d'] = $ld;
                    $ld_params['q'] = ''; // Reset search
                    $last_folders_item .= '<option value="' . urldecode(dcCore()->adminurl->get('admin.media', $ld_params)) . '"' .
                        ($ld == rtrim((string) $this->filter->d, '/') ? ' selected="selected"' : '') . '>' .
                        '/' . $ld . '</option>' . "\n";
                    if ($ld == rtrim((string) $this->filter->d, '/')) {
                        // Current directory is not a favorite → button will fav
                        $ld_params['fav'] = 'y';
                        $fav_url          = urldecode(dcCore()->adminurl->get('admin.media', $ld_params));
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
            // Select mode (popup or not)
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
                    '<a href="' . dcCore()->getPostAdminURL($this->filter->getPostType(), $this->filter->post_id) . '">' . Html::escapeHTML($this->filter->getPostTitle()) . '</a>',
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


        // add file mode into the filter box
        $this->filter->add((new DefaultFilter('file_mode'))->value($this->filter->file_mode)->html(
            '<p><span class="media-file-mode">' .
            '<a href="' . dcCore()->adminurl->get('admin.media', array_merge($this->filter->values(), ['file_mode' => 'grid'])) . '" title="' . __('Grid display mode') . '">' .
            '<img src="?df=images/grid-' . ($this->filter->file_mode == 'grid' ? 'on' : 'off') . '.png" alt="' . __('Grid display mode') . '" />' .
            '</a>' .
            '<a href="' . dcCore()->adminurl->get('admin.media', array_merge($this->filter->values(), ['file_mode' => 'list'])) . '" title="' . __('List display mode') . '">' .
            '<img src="?df=images/list-' . ($this->filter->file_mode == 'list' ? 'on' : 'off') . '.png" alt="' . __('List display mode') . '" />' .
            '</a>' .
            '</span></p>', false
        ));

        $fmt_form_media = '<form action="' . dcCore()->adminurl->get('admin.media') . '" method="post" id="form-medias">' .
        '<div class="files-group">%s</div>' .
        '<p class="hidden">' .
        dcCore()->formNonce() .
        dcCore()->adminurl->getHiddenFormFields('admin.media', $this->filter->values()) .
        '</p>';

        if (!$this->filter->popup || $this->filter->select > 1) {
            // Checkboxes and action
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

        // remove form filters from hidden fields
        $form_filters_hidden_fields = array_diff_key($this->filter->values(), ['nb' => '', 'order' => '', 'sortby' => '', 'q' => '']);

        // display filter
        $this->filter->display('admin.media', dcCore()->adminurl->getHiddenFormFields('admin.media', $form_filters_hidden_fields));

        // display list
        $this->catalog->display($this->filter, $fmt_form_media, $this->hasQuery());

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
                '<form action="' . Html::escapeURL(dcCore()->adminurl->get('admin.media', $this->filter->values(), '&')) . '" method="post" class="fieldset">' .
                '<div id="new-dir-f">' .
                '<h4 class="pretty-title">' . __('Create new directory') . '</h4>' .
                dcCore()->formNonce() .
                '<p><label for="newdir">' . __('Directory Name:') . '</label>' .
                form::field('newdir', 35, 255) . '</p>' .
                '<p><input type="submit" value="' . __('Create') . '" />' .
                dcCore()->adminurl->getHiddenFormFields('admin.media', $this->filter->values()) .
                    '</p>' .
                    '</div>' .
                    '</form>';
            }

            # Get zip directory
            if ($this->mediaArchivable() && !$this->filter->popup) {
                echo
                '<div class="fieldset">' .
                '<h4 class="pretty-title">' . sprintf(__('Backup content of %s'), ($this->filter->d == '' ? '“' . __('Media manager') . '”' : '“' . $this->filter->d . '”')) . '</h4>' .
                '<p><a class="button submit" href="' . dcCore()->adminurl->get('admin.media',
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
            '<form id="fileupload" action="' . Html::escapeURL(dcCore()->adminurl->get('admin.media', $this->filter->values(), '&')) . '" method="post" enctype="multipart/form-data" aria-disabled="false">' .
            '<p>' . form::hidden(['MAX_FILE_SIZE'], DOTCLEAR_MAX_UPLOAD_SIZE) .
            dcCore()->formNonce() . '</p>' .
                '<div class="fileupload-ctrl"><p class="queue-message"></p><ul class="files"></ul></div>';

            echo
                '<div class="fileupload-buttonbar clear">';

            echo
            '<p><label for="upfile">' . '<span class="add-label one-file">' . __('Choose file') . '</span>' . '</label>' .
            '<button class="button choose_files">' . __('Choose files') . '</button>' .
            '<input type="file" id="upfile" name="upfile[]"' . ($this->showUploader() ? ' multiple="mutiple"' : '') . ' data-url="' . Html::escapeURL(dcCore()->adminurl->get('admin.media', $this->filter->values(), '&')) . '" /></p>';

            echo
            '<p class="max-sizer form-note">&nbsp;' . __('Maximum file size allowed:') . ' ' . Files::size((int) DOTCLEAR_MAX_UPLOAD_SIZE) . '</p>';

            echo
            '<p class="one-file"><label for="upfiletitle">' . __('Title:') . '</label>' . form::field('upfiletitle', 35, 255) . '</p>' .
            '<p class="one-file"><label for="upfilepriv" class="classic">' . __('Private') . '</label> ' .
            form::checkbox('upfilepriv', 1) . '</p>';

            if (!$this->showUploader()) {
                echo
                '<p class="one-file form-help info">' . __('To send several files at the same time, you can activate the enhanced uploader in') .
                ' <a href="' . dcCore()->adminurl->get('admin.user.pref', ['tab' => 'user-options']) . '">' . __('My preferences') . '</a></p>';
            }

            echo
            '<p class="clear"><button class="button clean">' . __('Refresh') . '</button>' .
            '<input class="button cancel one-file" type="reset" value="' . __('Clear all') . '"/>' .
            '<input class="button start" type="submit" value="' . __('Upload') . '"/></p>' .
                '</div>';

            echo
            '<p style="clear:both;">' .
            dcCore()->adminurl->getHiddenFormFields('admin.media', $this->filter->values()) .
                '</p>' .
                '</form>' .
                '</div>' .
                '</div>';
        }

        # Empty remove form (for javascript actions)
        echo
        '<form id="media-remove-hide" action="' . Html::escapeURL(dcCore()->adminurl->get('admin.media', $this->filter->values())) . '" method="post" class="hidden">' .
        '<div>' .
        form::hidden('rmyes', 1) .
        dcCore()->adminurl->getHiddenFormFields('admin.media', $this->filter->values()) .
        form::hidden('remove', '') .
        dcCore()->formNonce() .
            '</div>' .
            '</form>';

        if ((!$this->hasQuery()) && ($this->mediaWritable() || $this->mediaArchivable())) {
            echo
                '</div>';
        }

        if (!$this->filter->popup) {
            echo '<div class="info"><p>' . sprintf(__('Current settings for medias and images are defined in %s'),
                '<a href="' . dcCore()->adminurl->get('admin.blog.pref') . '#medias-settings">' . __('Blog parameters') . '</a>') . '</p></div>';

            # Go back button
            echo '<p><input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" /></p>';
        }
    }

    /**
     * The breadcrumb of media page or popup
     *
     * @param array $element  The additionnal element
     *
     * @return string The html code of breadcrumb
     */
    public function breadcrumb($element = [])
    {
        $option = $param = [];

        if (empty($element) && isset(dcCore()->media)) {
            $param = [
                'd' => '',
                'q' => ''
            ];

            if ($this->media_has_query || $this->filter->q) {
                $count = $this->media_has_query ? count($this->getDirs('files')) : 0;

                $element[__('Search:') . ' ' . $this->filter->q . ' (' . sprintf(__('%s file found', '%s files found', $count), $count) . ')'] = '';
            } else {
                $bc_url   = dcCore()->adminurl->get('admin.media', array_merge($this->filter->values(true), ['d' => '%s']), '&');
                $bc_media = dcCore()->media->breadCrumb($bc_url, '<span class="page-title">%s</span>');
                if ($bc_media != '') {
                    $element[$bc_media] = '';
                    $option['hl']       = true;
                }
            }
        }

        $elements = [
            Html::escapeHTML(dcCore()->blog->name) => '',
            __('Media manager')                       => empty($param) ? '' :
                dcCore()->adminurl->get('admin.media', array_merge($this->filter->values(), array_merge($this->filter->values(), $param)))
        ];
        $options = [
            'home_link' => !$this->filter->popup
        ];

        $this->setPageBreadcrumb(array_merge($elements, $element), array_merge($options, $option));
    }




    /**
     * Check if page has a valid query
     *
     * @return boolean Has query
     */
    public function hasQuery()
    {
        return $this->media_has_query;
    }

    /**
     * Check if media dir is writable
     *
     * @return boolean Is writable
     */
    public function mediaWritable()
    {
        return $this->media_writable;
    }

    /**
     * Check if media dir is archivable
     *
     * @return boolean Is archivable
     */
    public function mediaArchivable()
    {
        if ($this->media_archivable === null) {
            $rs = $this->getDirsRecord();

            $this->media_archivable = dcCore()->auth->check('media_admin', dcCore()->blog->id)
                && !(count($rs) == 0 || (count($rs) == 1 && $rs->__data[0]->parent));
        }

        return $this->media_archivable;
    }

    /**
     * Return list of fileItem objects of current dir
     *
     * @param string $type  dir, file, all type
     *
     * @return array Dirs and/or files fileItem objects
     */
    public function getDirs($type = '')
    {
        if (!empty($type)) {
            return $this->media_dir[$type] ?? null;
        }

        return $this->media_dir;
    }

    /**
     * Return static record instance of fileItem objects
     *
     * @return staticRecord Dirs and/or files fileItem objects
     */
    public function getDirsRecord()
    {
        $dir = $this->media_dir;
        // Remove hidden directories (unless DOTCLEAR_SHOW_HIDDEN_DIRS is set to true)
        if (!defined('DOTCLEAR_SHOW_HIDDEN_DIRS') || (DOTCLEAR_SHOW_HIDDEN_DIRS == false)) {
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
     * @param string $file_id  The file id
     *
     * @return string The element
     */
    public function mediaLine($file_id)
    {
        return MediaCatalog::mediaLine($this->filter, dcCore()->media->getFile($file_id), 1, $this->media_has_query);
    }

    /**
     * Show enhance uploader
     *
     * @return boolean Show enhance uploader
     */
    public function showUploader()
    {
        return $this->media_uploader;
    }

    /**
     * Number of recent/fav dirs to show
     *
     * @return integer Nb of dirs
     */
    public function showLast()
    {
        return abs((int) dcCore()->auth->user_prefs->interface->media_nb_last_dirs);
    }

    /**
     * Return list of last dirs
     *
     * @return array Last dirs
     */
    public function getLast()
    {
        if ($this->media_last === null) {
            $m = dcCore()->auth->user_prefs->interface->media_last_dirs;
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
     * @param string    $dir        The directory
     * @param boolean   $remove     Remove
     *
     * @return boolean The change
     */
    public function updateLast($dir, $remove = false)
    {
        if ($this->q) {
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
            dcCore()->auth->user_prefs->interface->put('media_last_dirs', $last_dirs, 'array');
        }

        return $done;
    }

    /**
     * Return list of fav dirs
     *
     * @return array Fav dirs
     */
    public function getFav()
    {
        if ($this->media_fav === null) {
            $m = dcCore()->auth->user_prefs->interface->media_fav_dirs;
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
     * @param string    $dir        The directory
     * @param boolean   $remove     Remove
     *
     * @return boolean The change
     */
    public function updateFav($dir, $remove = false)
    {
        if ($this->q) {
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
            dcCore()->auth->user_prefs->interface->put('media_fav_dirs', $fav_dirs, 'array');
        }

        return $done;
    }
}
