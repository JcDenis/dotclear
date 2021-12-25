<?php
/**
 * @class Dotclear\Admin\Page\Home
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
use Dotclear\Exception\AdminException;

use Dotclear\Core\Core;

use Dotclear\Admin\Page;
use Dotclear\Admin\Media as AdminMedia;
use Dotclear\Admin\Catalog\MediaCatalog;
use Dotclear\Admin\Filter\DefaultFilter;

use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\File\Files;
use Dotclear\File\Zip\Zip;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Media extends Page
{
    public function __construct(Core $core)
    {
        parent::__construct($core);
        parent::check('media,media_admin');

$page = new AdminMedia($core, $this);
$page->add('handler', 'admin.media');

/* Actions
-------------------------------------------------------- */

# Zip download
if (!empty($_GET['zipdl']) && $core->auth->check('media_admin', $core->blog->id)) {
    try {
        if (strpos(realpath($core->media->root . '/' . $page->d), realpath($core->media->root)) === 0) {
            // Media folder or one of it's sub-folder(s)
            @set_time_limit(300);
            $fp  = fopen('php://output', 'wb');
            $zip = new Zip($fp);
            $zip->addExclusion('#(^|/).(.*?)_(m|s|sq|t).jpg$#');
            $zip->addDirectory($core->media->root . '/' . $page->d, '', true);

            header('Content-Disposition: attachment;filename=' . date('Y-m-d') . '-' . $core->blog->id . '-' . ($page->d ?: 'media') . '.zip');
            header('Content-Type: application/x-zip');
            $zip->write();
            unset($zip);
            exit;
        }
        $page->d = null;
        $core->media->chdir($page->d);

        throw new Exception(__('Not a valid directory'));
    } catch (Exception $e) {
        $core->error->add($e->getMessage());
    }
}

# User last and fav dirs
if ($page->showLast()) {
    if (!empty($_GET['fav'])) {
        if ($page->updateFav(rtrim($page->d, '/'), $_GET['fav'] == 'n')) {
            $core->adminurl->redirect('admin.media', $page->values());
        }
    }
    $page->updateLast(rtrim($page->d, '/'));
}

# New directory
if ($page->getDirs() && !empty($_POST['newdir'])) {
    $nd = files::tidyFileName($_POST['newdir']);
    if (array_filter($page->getDirs('files'), function ($i) use ($nd) {return ($i->basename === $nd);})
        || array_filter($page->getDirs('dirs'), function ($i) use ($nd) {return ($i->basename === $nd);})
    ) {
        static::addWarningNotice(sprintf(
            __('Directory or file "%s" already exists.'),
            html::escapeHTML($nd)
        ));
    } else {
        try {
            $core->media->makeDir($_POST['newdir']);
            static::addSuccessNotice(sprintf(
                __('Directory "%s" has been successfully created.'),
                html::escapeHTML($nd)
            ));
            $core->adminurl->redirect('admin.media', $page->values());
        } catch (Exception $e) {
            $core->error->add($e->getMessage());
        }
    }
}

# Adding a file
if ($page->getDirs() && !empty($_FILES['upfile'])) {
    // only one file per request : @see option singleFileUploads in admin/js/jsUpload/jquery.fileupload
    $upfile = [
        'name'     => $_FILES['upfile']['name'][0],
        'type'     => $_FILES['upfile']['type'][0],
        'tmp_name' => $_FILES['upfile']['tmp_name'][0],
        'error'    => $_FILES['upfile']['error'][0],
        'size'     => $_FILES['upfile']['size'][0],
        'title'    => html::escapeHTML($_FILES['upfile']['name'][0])
    ];

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-type: application/json');
        $message = [];

        try {
            files::uploadStatus($upfile);
            $new_file_id = $core->media->uploadFile($upfile['tmp_name'], $upfile['name'], $upfile['title']);

            $message['files'][] = [
                'name' => $upfile['name'],
                'size' => $upfile['size'],
                'html' => $page->mediaLine($new_file_id)
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
        files::uploadStatus($upfile);

        $f_title   = (isset($_POST['upfiletitle']) ? html::escapeHTML($_POST['upfiletitle']) : '');
        $f_private = ($_POST['upfilepriv'] ?? false);

        $core->media->uploadFile($upfile['tmp_name'], $upfile['name'], $f_title, $f_private);

        static::addSuccessNotice(__('Files have been successfully uploaded.'));
        $core->adminurl->redirect('admin.media', $page->values());
    } catch (Exception $e) {
        $core->error->add($e->getMessage());
    }
}

# Removing items
if ($page->getDirs() && !empty($_POST['medias']) && !empty($_POST['delete_medias'])) {
    try {
        foreach ($_POST['medias'] as $media) {
            $core->media->removeItem(rawurldecode($media));
        }
        static::addSuccessNotice(
            sprintf(__('Successfully delete one media.',
                'Successfully delete %d medias.',
                count($_POST['medias'])
            ),
                count($_POST['medias'])
            )
        );
        $core->adminurl->redirect('admin.media', $page->values());
    } catch (Exception $e) {
        $core->error->add($e->getMessage());
    }
}

# Removing item from popup only
if ($page->getDirs() && !empty($_POST['rmyes']) && !empty($_POST['remove'])) {
    $_POST['remove'] = rawurldecode($_POST['remove']);
    $forget          = false;

    try {
        if (is_dir(path::real($core->media->getPwd() . '/' . path::clean($_POST['remove'])))) {
            $msg = __('Directory has been successfully removed.');
            # Remove dir from recents/favs if necessary
            $forget = true;
        } else {
            $msg = __('File has been successfully removed.');
        }
        $core->media->removeItem($_POST['remove']);
        if ($forget) {
            $page->updateLast($page->d . '/' . path::clean($_POST['remove']), true);
            $page->updateFav($page->d . '/' . path::clean($_POST['remove']), true);
        }
        static::addSuccessNotice($msg);
        $core->adminurl->redirect('admin.media', $page->values());
    } catch (Exception $e) {
        $core->error->add($e->getMessage());
    }
}

# Rebuild directory
if ($page->getDirs() && $core->auth->isSuperAdmin() && !empty($_POST['rebuild'])) {
    try {
        $core->media->rebuild($page->d);

        static::success(sprintf(
            __('Directory "%s" has been successfully rebuilt.'),
            html::escapeHTML($page->d))
        );
        $core->adminurl->redirect('admin.media', $page->values());
    } catch (Exception $e) {
        $core->error->add($e->getMessage());
    }
}

# DISPLAY confirm page for rmdir & rmfile
if ($page->getDirs() && !empty($_GET['remove']) && empty($_GET['noconfirm'])) {
    $page->openPage($page->breadcrumb([__('confirm removal') => '']));

    echo
    '<form action="' . html::escapeURL($core->adminurl->get('admin.media')) . '" method="post">' .
    '<p>' . sprintf(__('Are you sure you want to remove %s?'),
        html::escapeHTML($_GET['remove'])) . '</p>' .
    '<p><input type="submit" value="' . __('Cancel') . '" /> ' .
    ' &nbsp; <input type="submit" name="rmyes" value="' . __('Yes') . '" />' .
    $core->adminurl->getHiddenFormFields('admin.media', $page->values()) .
    $core->formNonce() .
    form::hidden('remove', html::escapeHTML($_GET['remove'])) . '</p>' .
        '</form>';

    $page->closePage();
    exit;
}

/* DISPLAY Main page
-------------------------------------------------------- */

// Recent media folders
$last_folders = '';
if ($page->showLast()) {
    $last_folders_item = '';
    $fav_url           = '';
    $fav_img           = '';
    $fav_alt           = '';
    // Favorites directories
    $fav_dirs = $page->getFav();
    foreach ($fav_dirs as $ld) {
        // Add favorites dirs on top of combo
        $ld_params      = $page->values();
        $ld_params['d'] = $ld;
        $ld_params['q'] = ''; // Reset search
        $last_folders_item .= '<option value="' . urldecode($core->adminurl->get('admin.media', $ld_params)) . '"' .
            ($ld == rtrim($page->d, '/') ? ' selected="selected"' : '') . '>' .
            '/' . $ld . '</option>' . "\n";
        if ($ld == rtrim($page->d, '/')) {
            // Current directory is a favorite → button will un-fav
            $ld_params['fav'] = 'n';
            $fav_url          = urldecode($core->adminurl->get('admin.media', $ld_params));
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
    $last_dirs = $page->getlast();
    foreach ($last_dirs as $ld) {
        if (!in_array($ld, $fav_dirs)) {
            $ld_params      = $page->values();
            $ld_params['d'] = $ld;
            $ld_params['q'] = ''; // Reset search
            $last_folders_item .= '<option value="' . urldecode($core->adminurl->get('admin.media', $ld_params)) . '"' .
                ($ld == rtrim($page->d, '/') ? ' selected="selected"' : '') . '>' .
                '/' . $ld . '</option>' . "\n";
            if ($ld == rtrim($page->d, '/')) {
                // Current directory is not a favorite → button will fav
                $ld_params['fav'] = 'y';
                $fav_url          = urldecode($core->adminurl->get('admin.media', $ld_params));
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

$page->openPage($page->breadcrumb(),
    static::jsModal() .
    $page->js($core->adminurl->get('admin.media', array_diff_key($page->values(), $page->values(false, true)), '&')) .
    static::jsLoad('js/_media.js') .
    ($page->mediaWritable() ? static::jsUpload(['d=' . $page->d]) : '')
);

if ($page->popup) {
    echo static::notices();
}

if (!$page->mediaWritable() && !$core->error->flag()) {
    static::warning(__('You do not have sufficient permissions to write to this folder.'));
}

if (!$page->getDirs()) {
    $page->closePage();
    exit;
}

if ($page->select) {
    // Select mode (popup or not)
    echo '<div class="' . ($page->popup ? 'form-note ' : '') . 'info"><p>';
    if ($page->select == 1) {
        echo sprintf(__('Select a file by clicking on %s'), '<img src="?df=images/plus.png" alt="' . __('Select this file') . '" />');
    } else {
        echo sprintf(__('Select files and click on <strong>%s</strong> button'), __('Choose selected medias'));
    }
    if ($page->mediaWritable()) {
        echo ' ' . __('or') . ' ' . sprintf('<a href="#fileupload">%s</a>', __('upload a new file'));
    }
    echo '</p></div>';
} else {
    if ($page->post_id) {
        echo '<div class="form-note info"><p>' . sprintf(__('Choose a file to attach to entry %s by clicking on %s'),
            '<a href="' . $core->getPostAdminURL($page->getPostType(), $page->post_id) . '">' . html::escapeHTML($page->getPostTitle()) . '</a>',
            '<img src="?df=images/plus.png" alt="' . __('Attach this file to entry') . '" />');
        if ($page->mediaWritable()) {
            echo ' ' . __('or') . ' ' . sprintf('<a href="#fileupload">%s</a>', __('upload a new file'));
        }
        echo '</p></div>';
    }
    if ($page->popup) {
        echo '<div class="info"><p>' . sprintf(__('Choose a file to insert into entry by clicking on %s'),
            '<img src="?df=images/plus.png" alt="' . __('Attach this file to entry') . '" />');
        if ($page->mediaWritable()) {
            echo ' ' . __('or') . ' ' . sprintf('<a href="#fileupload">%s</a>', __('upload a new file'));
        }
        echo '</p></div>';
    }
}

$rs         = $page->getDirsRecord();
$media_list = new MediaCatalog($core, $rs, $rs->count());

// add file mode into the filter box
$page->add((new DefaultFilter('file_mode'))->value($page->file_mode)->html(
    '<p><span class="media-file-mode">' .
    '<a href="' . $core->adminurl->get('admin.media', array_merge($page->values(), ['file_mode' => 'grid'])) . '" title="' . __('Grid display mode') . '">' .
    '<img src="?df=images/grid-' . ($page->file_mode == 'grid' ? 'on' : 'off') . '.png" alt="' . __('Grid display mode') . '" />' .
    '</a>' .
    '<a href="' . $core->adminurl->get('admin.media', array_merge($page->values(), ['file_mode' => 'list'])) . '" title="' . __('List display mode') . '">' .
    '<img src="?df=images/list-' . ($page->file_mode == 'list' ? 'on' : 'off') . '.png" alt="' . __('List display mode') . '" />' .
    '</a>' .
    '</span></p>', false
));

$fmt_form_media = '<form action="' . $core->adminurl->get('admin.media') . '" method="post" id="form-medias">' .
'<div class="files-group">%s</div>' .
'<p class="hidden">' .
$core->formNonce() .
$core->adminurl->getHiddenFormFields('admin.media', $page->values()) .
'</p>';

if (!$page->popup || $page->select > 1) {
    // Checkboxes and action
    $fmt_form_media .= '<div class="' . (!$page->popup ? 'medias-delete' : '') . ' ' . ($page->select > 1 ? 'medias-select' : '') . '">' .
        '<p class="checkboxes-helpers"></p>' .
        '<p>';
    if ($page->select > 1) {
        $fmt_form_media .= '<input type="submit" class="select" id="select_medias" name="select_medias" value="' . __('Choose selected medias') . '"/> ';
    }
    if (!$page->popup) {
        $fmt_form_media .= '<input type="submit" class="delete" id="delete_medias" name="delete_medias" value="' . __('Remove selected medias') . '"/>';
    }
    $fmt_form_media .= '</p>' .
        '</div>';
}
$fmt_form_media .= '</form>';

echo '<div class="media-list">';
echo $last_folders;

// remove form filters from hidden fields
$form_filters_hidden_fields = array_diff_key($page->values(), ['nb' => '', 'order' => '', 'sortby' => '', 'q' => '']);

// display filter
$page->display('admin.media', $core->adminurl->getHiddenFormFields('admin.media', $form_filters_hidden_fields));

// display list
$media_list->display($page, $fmt_form_media, $page->hasQuery());

echo '</div>';

if ((!$page->hasQuery()) && ($page->mediaWritable() || $page->mediaArchivable())) {
    echo
    '<div class="vertical-separator">' .
    '<h3 class="out-of-screen-if-js">' . sprintf(__('In %s:'), ($page->d == '' ? '“' . __('Media manager') . '”' : '“' . $page->d . '”')) . '</h3>';
}

if ((!$page->hasQuery()) && ($page->mediaWritable() || $page->mediaArchivable())) {
    echo
        '<div class="two-boxes odd">';

    # Create directory
    if ($page->mediaWritable()) {
        echo
        '<form action="' . html::escapeURL($core->adminurl->get('admin.media', $page->values(), '&')) . '" method="post" class="fieldset">' .
        '<div id="new-dir-f">' .
        '<h4 class="pretty-title">' . __('Create new directory') . '</h4>' .
        $core->formNonce() .
        '<p><label for="newdir">' . __('Directory Name:') . '</label>' .
        form::field('newdir', 35, 255) . '</p>' .
        '<p><input type="submit" value="' . __('Create') . '" />' .
        $core->adminurl->getHiddenFormFields('admin.media', $page->values()) .
            '</p>' .
            '</div>' .
            '</form>';
    }

    # Get zip directory
    if ($page->mediaArchivable() && !$page->popup) {
        echo
        '<div class="fieldset">' .
        '<h4 class="pretty-title">' . sprintf(__('Backup content of %s'), ($page->d == '' ? '“' . __('Media manager') . '”' : '“' . $page->d . '”')) . '</h4>' .
        '<p><a class="button submit" href="' . $core->adminurl->get('admin.media',
            array_merge($page->values(), ['zipdl' => 1])) . '">' . __('Download zip file') . '</a></p>' .
            '</div>';
    }

    echo
        '</div>';
}

if (!$page->hasQuery() && $page->mediaWritable()) {
    echo
        '<div class="two-boxes fieldset even">';
    if ($page->showUploader()) {
        echo
            '<div class="enhanced_uploader">';
    } else {
        echo
            '<div>';
    }

    echo
    '<h4>' . __('Add files') . '</h4>' .
    '<p class="more-info">' . __('Please take care to publish media that you own and that are not protected by copyright.') . '</p>' .
    '<form id="fileupload" action="' . html::escapeURL($core->adminurl->get('admin.media', $page->values(), '&')) . '" method="post" enctype="multipart/form-data" aria-disabled="false">' .
    '<p>' . form::hidden(['MAX_FILE_SIZE'], DOTCLEAR_MAX_UPLOAD_SIZE) .
    $core->formNonce() . '</p>' .
        '<div class="fileupload-ctrl"><p class="queue-message"></p><ul class="files"></ul></div>';

    echo
        '<div class="fileupload-buttonbar clear">';

    echo
    '<p><label for="upfile">' . '<span class="add-label one-file">' . __('Choose file') . '</span>' . '</label>' .
    '<button class="button choose_files">' . __('Choose files') . '</button>' .
    '<input type="file" id="upfile" name="upfile[]"' . ($page->showUploader() ? ' multiple="mutiple"' : '') . ' data-url="' . html::escapeURL($core->adminurl->get('admin.media', $page->values(), '&')) . '" /></p>';

    echo
    '<p class="max-sizer form-note">&nbsp;' . __('Maximum file size allowed:') . ' ' . files::size((int) DOTCLEAR_MAX_UPLOAD_SIZE) . '</p>';

    echo
    '<p class="one-file"><label for="upfiletitle">' . __('Title:') . '</label>' . form::field('upfiletitle', 35, 255) . '</p>' .
    '<p class="one-file"><label for="upfilepriv" class="classic">' . __('Private') . '</label> ' .
    form::checkbox('upfilepriv', 1) . '</p>';

    if (!$page->showUploader()) {
        echo
        '<p class="one-file form-help info">' . __('To send several files at the same time, you can activate the enhanced uploader in') .
        ' <a href="' . $core->adminurl->get('admin.user.pref', ['tab' => 'user-options']) . '">' . __('My preferences') . '</a></p>';
    }

    echo
    '<p class="clear"><button class="button clean">' . __('Refresh') . '</button>' .
    '<input class="button cancel one-file" type="reset" value="' . __('Clear all') . '"/>' .
    '<input class="button start" type="submit" value="' . __('Upload') . '"/></p>' .
        '</div>';

    echo
    '<p style="clear:both;">' .
    $core->adminurl->getHiddenFormFields('admin.media', $page->values()) .
        '</p>' .
        '</form>' .
        '</div>' .
        '</div>';
}

# Empty remove form (for javascript actions)
echo
'<form id="media-remove-hide" action="' . html::escapeURL($core->adminurl->get('admin.media', $page->values())) . '" method="post" class="hidden">' .
'<div>' .
form::hidden('rmyes', 1) .
$core->adminurl->getHiddenFormFields('admin.media', $page->values()) .
form::hidden('remove', '') .
$core->formNonce() .
    '</div>' .
    '</form>';

if ((!$page->hasQuery()) && ($page->mediaWritable() || $page->mediaArchivable())) {
    echo
        '</div>';
}

if (!$page->popup) {
    echo '<div class="info"><p>' . sprintf(__('Current settings for medias and images are defined in %s'),
        '<a href="' . $core->adminurl->get('admin.blog.pref') . '#medias-settings">' . __('Blog parameters') . '</a>') . '</p></div>';

    # Go back button
    echo '<p><input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" /></p>';
}

$page->closePage();
    }
}
