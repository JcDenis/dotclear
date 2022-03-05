<?php
/**
 * @class Dotclear\Plugin\ImportExport\Admin\Lib\Module\ImportFlat
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginImportExport
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\ImportExport\Admin\Lib\Module;

use Dotclear\Exception\ModuleException;
use Dotclear\File\Files;
use Dotclear\File\Path;
use Dotclear\File\Zip\Unzip;
use Dotclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Network\Http;
use Dotclear\Plugin\ImportExport\Admin\Lib\Module;
use Dotclear\Plugin\ImportExport\Admin\Lib\Module\Flat\FlatImport;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class ImportFlat extends Module
{
    protected $status = false;

    public function setInfo()
    {
        $this->type        = 'import';
        $this->name        = __('Flat file import');
        $this->description = __('Imports a blog or a full Dotclear installation from flat file.');
    }

    public function process($do)
    {
        if ($do == 'single' || $do == 'full') {
            $this->status = $do;

            return;
        }

        $to_unlink = false;

        # Single blog import
        $files      = $this->getPublicFiles();
        $single_upl = null;
        if (!empty($_POST['public_single_file']) && in_array($_POST['public_single_file'], $files)) {
            $single_upl = false;
        } elseif (!empty($_FILES['up_single_file'])) {
            $single_upl = true;
        }

        if ($single_upl !== null) {
            if ($single_upl) {
                Files::uploadStatus($_FILES['up_single_file']);
                $file = dotclear()->config()->cache_dir . '/' . md5(uniqid());
                if (!move_uploaded_file($_FILES['up_single_file']['tmp_name'], $file)) {
                    throw new Exception(__('Unable to move uploaded file.'));
                }
                $to_unlink = true;
            } else {
                $file = $_POST['public_single_file'];
            }

            $unzip_file = '';

            try {
                # Try to unzip file
                $unzip_file = $this->unzip($file);
                if (false !== $unzip_file) {
                    $bk = new FlatImport($unzip_file);
                }
                # Else this is a normal file
                else {
                    $bk = new FlatImport($file);
                }

                $bk->importSingle();
            } catch (\Exception $e) {
                @unlink($unzip_file);
                if ($to_unlink) {
                    @unlink($file);
                }

                throw $e;
            }
            if ($unzip_file) {
                @unlink($unzip_file);
            }
            if ($to_unlink) {
                @unlink($file);
            }
            Http::redirect($this->getURL() . '&do=single');
        }

        # Full import
        $full_upl = null;
        if (!empty($_POST['public_full_file']) && in_array($_POST['public_full_file'], $files)) {
            $full_upl = false;
        } elseif (!empty($_FILES['up_full_file'])) {
            $full_upl = true;
        }

        if ($full_upl !== null && dotclear()->user()->isSuperAdmin()) {
            if (empty($_POST['your_pwd']) || !dotclear()->user()->checkPassword($_POST['your_pwd'])) {
                throw new Exception(__('Password verification failed'));
            }

            if ($full_upl) {
                Files::uploadStatus($_FILES['up_full_file']);
                $file = dotclear()->config()->cache_dir . '/' . md5(uniqid());
                if (!move_uploaded_file($_FILES['up_full_file']['tmp_name'], $file)) {
                    throw new Exception(__('Unable to move uploaded file.'));
                }
                $to_unlink = true;
            } else {
                $file = $_POST['public_full_file'];
            }

            $unzip_file = '';

            try {
                # Try to unzip file
                $unzip_file = $this->unzip($file);
                if (false !== $unzip_file) {
                    $bk = new FlatImport($unzip_file);
                }
                # Else this is a normal file
                else {
                    $bk = new FlatImport($file);
                }

                $bk->importFull();
            } catch (\Exception $e) {
                @unlink($unzip_file);
                if ($to_unlink) {
                    @unlink($file);
                }

                throw $e;
            }
            @unlink($unzip_file);
            if ($to_unlink) {
                @unlink($file);
            }
            Http::redirect($this->getURL() . '&do=full');
        }

        header('content-type:text/plain');
        var_dump($_POST);
        exit;
    }

    public function gui()
    {
        if ($this->status == 'single') {
            dotclear()->notice()->success(__('Single blog successfully imported.'));

            return;
        }
        if ($this->status == 'full') {
            dotclear()->notice()->success(__('Content successfully imported.'));

            return;
        }

        $public_files = array_merge(['-' => ''], $this->getPublicFiles());
        $has_files    = (bool) (count($public_files) - 1);

        echo
        dotclear()->resource()->json(
            'ie_import_flat_msg',
            ['confirm_full_import' => __('Are you sure you want to import a full backup file?')]
        ) .
        dotclear()->resource()->load('import_flat.js', 'Plugin', 'ImportExport') .
        '<form action="' . $this->getURL(true) . '" method="post" enctype="multipart/form-data" class="fieldset">' .
        '<h3>' . __('Single blog') . '</h3>' .
        '<p>' . sprintf(__('This will import a single blog backup as new content in the current blog: <strong>%s</strong>.'), Html::escapeHTML(dotclear()->blog()->name)) . '</p>' .

        '<p><label for="up_single_file">' . __('Upload a backup file') .
        ' (' . sprintf(__('maximum size %s'), Files::size((int) dotclear()->config()->media_upload_maxsize)) . ')' . ' </label>' .
            ' <input type="file" id="up_single_file" name="up_single_file" size="20" />' .
            '</p>';

        if ($has_files) {
            echo
            '<p><label for="public_single_file" class="">' . __('or pick up a local file in your public directory') . ' </label> ' .
            Form::combo('public_single_file', $public_files) .
                '</p>';
        }

        echo
        '<p>' .
        dotclear()->nonce()->form() .
        Form::hidden(['handler'], 'admin.plugin.ImportExport') .
        Form::hidden(['do'], 1) .
        Form::hidden(['MAX_FILE_SIZE'], (int) dotclear()->config()->media_upload_maxsize) .
        '<input type="submit" value="' . __('Import') . '" /></p>' .

            '</form>';

        if (dotclear()->user()->isSuperAdmin()) {
            echo
            '<form action="' . $this->getURL(true) . '" method="post" enctype="multipart/form-data" id="formfull" class="fieldset">' .
            '<h3>' . __('Multiple blogs') . '</h3>' .
            '<p class="warning">' . __('This will reset all the content of your database, except users.') . '</p>' .

            '<p><label for="up_full_file">' . __('Upload a backup file') . ' ' .
            ' (' . sprintf(__('maximum size %s'), Files::size((int) dotclear()->config()->media_upload_maxsize)) . ')' . ' </label>' .
                '<input type="file" id="up_full_file" name="up_full_file" size="20" />' .
                '</p>';

            if ($has_files) {
                echo
                '<p><label for="public_full_file">' . __('or pick up a local file in your public directory') . ' </label>' .
                Form::combo('public_full_file', $public_files) .
                    '</p>';
            }

            echo
            '<p><label for="your_pwd" class="required"><abbr title="' . __('Required field') . '">*</abbr> ' . __('Your password:') . '</label>' .
            Form::password(
                'your_pwd',
                20,
                255,
                [
                    'extra_html'   => 'required placeholder="' . __('Password') . '"',
                    'autocomplete' => 'current-password',
                ]
            ) . '</p>' .

            '<p>' .
            dotclear()->nonce()->form() .
            Form::hidden(['handler'], 'admin.plugin.ImportExport') .
            Form::hidden(['do'], 1) .
            Form::hidden(['MAX_FILE_SIZE'], dotclear()->config()->media_upload_maxsize) .
            '<input type="submit" value="' . __('Import') . '" /></p>' .

                '</form>';
        }
    }

    protected function getPublicFiles()
    {
        $public_files = [];
        $dir          = @dir(dotclear()->blog()->public_path);
        if ($dir) {
            while (($entry = $dir->read()) !== false) {
                $entry_path = $dir->path . '/' . $entry;

                if (is_file($entry_path) && is_readable($entry_path)) {
                    # Do not test each zip file content here, its too long
                    if (substr($entry_path, -4) == '.zip') {
                        $public_files[$entry] = $entry_path;
                    } elseif (self::checkFileContent($entry_path)) {
                        $public_files[$entry] = $entry_path;
                    }
                }
            }
        }

        return $public_files;
    }

    protected static function checkFileContent($entry_path)
    {
        $ret = false;

        $fp  = fopen($entry_path, 'rb');
        if (false !== ($line = fgets($fp))) {
            $ret = strpos($line, '///DOTCLEAR|') === 0;
        }
        fclose($fp);

        return $ret;
    }

    private function unzip($file)
    {
        $zip = new Unzip($file);

        if ($zip->isEmpty()) {
            $zip->close();

            return false; //throw new Exception(__('File is empty or not a compressed file.'));
        }

        foreach ($zip->getFilesList() as $zip_file) {
            # Check zipped file name
            if (substr($zip_file, -4) != '.txt') {
                continue;
            }

            # Check zipped file contents
            $content = $zip->unzip($zip_file);
            if (strpos($content, '///DOTCLEAR|') !== 0) {
                unset($content);

                continue;
            }

            $target = Path::fullFromRoot($zip_file, dirname($file));

            # Check existing files with same name
            if (file_exists($target)) {
                $zip->close();
                unset($content);

                throw new ModuleException(__('Another file with same name exists.'));
            }

            # Extract backup content
            if (file_put_contents($target, $content) === false) {
                $zip->close();
                unset($content);

                throw new ModuleException(__('Failed to extract backup file.'));
            }

            $zip->close();
            unset($content);

            # Return extracted file name
            return $target;
        }

        $zip->close();

        throw new ModuleException(__('No backup in compressed file.'));
    }
}
