<?php
/**
 * @class Dotclear\Plugin\ImportExport\Admin\Lib\Module\ExportFlat
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

use Dotclear\Database\Record;
use Dotclear\Database\Schema;
use Dotclear\Exception\ModuleException;
use Dotclear\File\Zip\Zip;
use DOtclear\Html\Form;
use Dotclear\Html\Html;
use Dotclear\Network\Http;
use Dotclear\Plugin\ImportExport\Admin\Lib\Module;
use Dotclear\Plugin\ImportExport\Admin\Lib\Module\Flat\FlatExport;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class ExportFlat extends Module
{
    public function setInfo()
    {
        $this->type        = 'export';
        $this->name        = __('Flat file export');
        $this->description = __('Exports a blog or a full Dotclear installation to flat file.');
    }

    public function process($do)
    {
        # Export a blog
        if ($do == 'export_blog' && dotclear()->user()->check('admin', dotclear()->blog()->id)) {
            $fullname = dotclear()->blog()->public_path . '/.backup_' . sha1(uniqid());
            $blog_id  = dotclear()->con()->escape(dotclear()->blog()->id);

            try {
                $exp = new FlatExport($fullname);
                fwrite($exp->fp, '///DOTCLEAR|' . dotclear()->config()->version . "|single\n");

                $exp->export(
                    'category',
                    'SELECT * FROM ' . dotclear()->prefix . 'category ' .
                    "WHERE blog_id = '" . $blog_id . "'"
                );
                $exp->export(
                    'link',
                    'SELECT * FROM ' . dotclear()->prefix . 'link ' .
                    "WHERE blog_id = '" . $blog_id . "'"
                );
                $exp->export(
                    'setting',
                    'SELECT * FROM ' . dotclear()->prefix . 'setting ' .
                    "WHERE blog_id = '" . $blog_id . "'"
                );
                $exp->export(
                    'post',
                    'SELECT * FROM ' . dotclear()->prefix . 'post ' .
                    "WHERE blog_id = '" . $blog_id . "'"
                );
                $exp->export(
                    'meta',
                    'SELECT meta_id, meta_type, M.post_id ' .
                    'FROM ' . dotclear()->prefix . 'meta M, ' . dotclear()->prefix . 'post P ' .
                    'WHERE P.post_id = M.post_id ' .
                    "AND P.blog_id = '" . $blog_id . "'"
                );
                $exp->export(
                    'media',
                    'SELECT * FROM ' . dotclear()->prefix . "media WHERE media_path = '" .
                    dotclear()->con()->escape(dotclear()->blog()->settings()->system->public_path) . "'"
                );
                $exp->export(
                    'post_media',
                    'SELECT media_id, M.post_id ' .
                    'FROM ' . dotclear()->prefix . 'post_media M, ' . dotclear()->prefix . 'post P ' .
                    'WHERE P.post_id = M.post_id ' .
                    "AND P.blog_id = '" . $blog_id . "'"
                );
                $exp->export(
                    'ping',
                    'SELECT ping.post_id, ping_url, ping_dt ' .
                    'FROM ' . dotclear()->prefix . 'ping ping, ' . dotclear()->prefix . 'post P ' .
                    'WHERE P.post_id = ping.post_id ' .
                    "AND P.blog_id = '" . $blog_id . "'"
                );
                $exp->export(
                    'comment',
                    'SELECT C.* ' .
                    'FROM ' . dotclear()->prefix . 'comment C, ' . dotclear()->prefix . 'post P ' .
                    'WHERE P.post_id = C.post_id ' .
                    "AND P.blog_id = '" . $blog_id . "'"
                );

                # --BEHAVIOR-- exportSingle
                dotclear()->behavior()->call('exportSingle', $exp, $blog_id);

                $_SESSION['export_file']     = $fullname;
                $_SESSION['export_filename'] = $_POST['file_name'];
                $_SESSION['export_filezip']  = !empty($_POST['file_zip']);
                Http::redirect($this->getURL() . '&do=ok');
            } catch (\Exception $e) {
                @unlink($fullname);

                throw $e;
            }
        }

        # Export all content
        if ($do == 'export_all' && dotclear()->user()->isSuperAdmin()) {
            $fullname = dotclear()->blog()->public_path . '/.backup_' . sha1(uniqid());

            try {
                $exp = new FlatExport($fullname);
                fwrite($exp->fp, '///DOTCLEAR|' . dotclear()->config()->core_version . "|full\n");
                $exp->exportTable('blog');
                $exp->exportTable('category');
                $exp->exportTable('link');
                $exp->exportTable('setting');
                $exp->exportTable('user');
                $exp->exportTable('pref');
                $exp->exportTable('permissions');
                $exp->exportTable('post');
                $exp->exportTable('meta');
                $exp->exportTable('media');
                $exp->exportTable('post_media');
                $exp->exportTable('log');
                $exp->exportTable('ping');
                $exp->exportTable('comment');
                $exp->exportTable('spamrule');
                $exp->exportTable('version');

                # --BEHAVIOR-- exportFull
                dotclear()->behavior()->call('exportFull', $exp);

                $_SESSION['export_file']     = $fullname;
                $_SESSION['export_filename'] = $_POST['file_name'];
                $_SESSION['export_filezip']  = !empty($_POST['file_zip']);
                Http::redirect($this->getURL() . '&do=ok');
            } catch (\Exception $e) {
                @unlink($fullname);

                throw $e;
            }
        }

        # Send file content
        if ($do == 'ok') {
            if (!file_exists($_SESSION['export_file'])) {
                throw new ModuleException(__('Export file not found.'));
            }

            ob_end_clean();

            if (substr($_SESSION['export_filename'], -4) == '.zip') {
                $_SESSION['export_filename'] = substr($_SESSION['export_filename'], 0, -4); //.'.txt';
            }

            # Flat export
            if (empty($_SESSION['export_filezip'])) {
                header('Content-Disposition: attachment;filename=' . $_SESSION['export_filename']);
                header('Content-Type: text/plain; charset=UTF-8');
                readfile($_SESSION['export_file']);

                unlink($_SESSION['export_file']);
                unset($_SESSION['export_file'], $_SESSION['export_filename'], $_SESSION['export_filezip']);
                exit;
            }
            # Zip export

            try {
                $file_zipname = $_SESSION['export_filename'] . '.zip';

                $fp  = fopen('php://output', 'wb');
                $zip = new Zip($fp);
                $zip->addFile($_SESSION['export_file'], $_SESSION['export_filename']);

                header('Content-Disposition: attachment;filename=' . $file_zipname);
                header('Content-Type: application/x-zip');

                $zip->write();

                unlink($_SESSION['export_file']);
                unset($zip, $_SESSION['export_file'], $_SESSION['export_filename'], $file_zipname);
                exit;
            } catch (\Exception $e) {
                unset($zip, $_SESSION['export_file'], $_SESSION['export_filename'], $file_zipname);
                @unlink($_SESSION['export_file']);

                throw new ModuleException(__('Failed to compress export file.'));
            }
        }
    }

    public function gui()
    {
        echo
        '<form action="' . $this->getURL(true) . '" method="post" class="fieldset">' .
        '<h3>' . __('Single blog') . '</h3>' .
        '<p>' . sprintf(__('This will create an export of your current blog: %s'), '<strong>' . Html::escapeHTML(dotclear()->blog()->name)) . '</strong>.</p>' .

        '<p><label for="file_name">' . __('File name:') . '</label>' .
        Form::field('file_name', 50, 255, date('Y-m-d-H-i-') . Html::escapeHTML(dotclear()->blog()->id . '-backup.txt')) .
        '</p>' .

        '<p><label for="file_zip" class="classic">' .
        Form::checkbox(['file_zip', 'file_zip'], 1) . ' ' .
        __('Compress file') . '</label>' .
        '</p>' .

        '<p class="zip-dl"><a href="' . dotclear()->adminurl()->get('admin.media', ['d' => '', 'zipdl' => '1']) . '">' .
        __('You may also want to download your media directory as a zip file') . '</a></p>' .

        '<p><input type="submit" value="' . __('Export') . '" />' .
        Form::hidden(['do'], 'export_blog') .
        Form::hidden(['handler'], 'admin.plugin.ImportExport') .
        dotclear()->nonce()->form() . '</p>' .

            '</form>';

        if (dotclear()->user()->isSuperAdmin()) {
            echo
            '<form action="' . $this->getURL(true) . '" method="post" class="fieldset">' .
            '<h3>' . __('Multiple blogs') . '</h3>' .
            '<p>' . __('This will create an export of all the content of your database.') . '</p>' .

            '<p><label for="file_name2">' . __('File name:') . '</label>' .
            Form::field(['file_name', 'file_name2'], 50, 255, date('Y-m-d-H-i-') . 'dotclear-backup.txt') .
            '</p>' .

            '<p><label for="file_zip2" class="classic">' .
            Form::checkbox(['file_zip', 'file_zip2'], 1) . ' ' .
            __('Compress file') . '</label>' .
            '</p>' .

            '<p><input type="submit" value="' . __('Export') . '" />' .
            Form::hidden(['do'], 'export_all') .
            Form::hidden(['handler'], 'admin.plugin.ImportExport') .
            dotclear()->nonce()->form() . '</p>' .

                '</form>';
        }
    }
}
