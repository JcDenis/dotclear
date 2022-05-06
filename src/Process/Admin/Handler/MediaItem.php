<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\MediaItem
use Dotclear\App;
use Dotclear\Core\Media\Media;
use Dotclear\Core\Media\Manager\Item;
use Dotclear\Exception\AdminException;
use Dotclear\Helper\File\Files;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Html\Form;
use Dotclear\Helper\Html\Html;
use Dotclear\Helper\Dt;
use Dotclear\Process\Admin\Page\AbstractPage;
use SimpleXMLElement;
use Exception;

/**
 * Admin media item page.
 *
 * @ingroup  Admin Media Handler
 */
class MediaItem extends AbstractPage
{
    private $item_popup;
    private $item_select;
    private $item_page_url_params;
    private $media_page_url_params;
    private $item_id;

    /**
     * @var null|Item $item_file
     *                File info
     */
    private $item_file;
    private $item_dirs_combo;
    private $media_writable;

    protected function getPermissions(): string|bool
    {
        return 'media,media_admin';
    }

    protected function getPagePrepend(): ?bool
    {
        try {
            App::core()->media(true, true);
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());

            return true;
        }

        $tab = empty($_REQUEST['tab']) ? '' : $_REQUEST['tab'];

        $post_id = !empty($_REQUEST['post_id']) ? (int) $_REQUEST['post_id'] : null;
        if ($post_id) {
            $post = App::core()->blog()->posts()->getPosts(['post_id' => $post_id]);
            if ($post->isEmpty()) {
                $post_id = null;
            }
            unset($post);
        }

        $this->item_file                  = null;
        $this->item_popup                 = (int) !empty($_REQUEST['popup']);
        $this->item_select                = !empty($_REQUEST['select']) ? (int) $_REQUEST['select'] : 0; // 0 : none, 1 : single media, >1 : multiple medias
        $plugin_id                        = isset($_REQUEST['plugin_id']) ? Html::sanitizeURL($_REQUEST['plugin_id']) : '';
        $this->item_page_url_params       = ['popup' => $this->item_popup, 'select' => $this->item_select, 'post_id' => $post_id];
        $this->media_page_url_params      = ['popup' => $this->item_popup, 'select' => $this->item_select, 'post_id' => $post_id, 'link_type' => !empty($_REQUEST['link_type']) ? $_REQUEST['link_type'] : null];

        if ('' != $plugin_id) {
            $this->item_page_url_params['plugin_id']       = $plugin_id;
            $this->media_page_url_params['plugin_id']      = $plugin_id;
        }

        $this->item_id = !empty($_REQUEST['id']) ? (int) $_REQUEST['id'] : '';

        if ('' != $this->item_id) {
            $this->item_page_url_params['id'] = $this->item_id;
        }

        $this->media_writable = false;

        $this->item_dirs_combo = [];

        try {
            if ($this->item_id) {
                $this->item_file = App::core()->media()->getFile($this->item_id);
            }

            if (null === $this->item_file) {
                throw new AdminException(__('Not a valid file'));
            }

            App::core()->media()->chdir(dirname($this->item_file->relname));
            $this->media_writable = App::core()->media()->writable();

            // Prepare directories combo box
            foreach (App::core()->media()->getDBDirs() as $v) {
                $this->item_dirs_combo['/' . $v] = $v;
            }
            // Add parent and direct childs directories if any
            App::core()->media()->getFSDir();
            foreach (App::core()->media()->dir['dirs'] as $k => $v) {
                $this->item_dirs_combo['/' . $v->relname] = $v->relname;
            }
            ksort($this->item_dirs_combo);
        } catch (Exception $e) {
            App::core()->error()->add($e->getMessage());
        }

        // Upload a new file
        if ($this->item_file && !empty($_FILES['upfile']) && $this->item_file->editable && $this->media_writable) {
            try {
                Files::uploadStatus($_FILES['upfile']);
                App::core()->media()->uploadMediaFile($_FILES['upfile']['tmp_name'], $this->item_file->basename, null, false, true);

                App::core()->notice()->addSuccessNotice(__('File has been successfully updated.'));
                App::core()->adminurl()->redirect('admin.media.item', $this->item_page_url_params);
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Update file
        if ($this->item_file && !empty($_POST['media_file']) && $this->item_file->editable && $this->media_writable) {
            $newFile = clone $this->item_file;

            $newFile->basename = $_POST['media_file'];

            if ($_POST['media_path']) {
                $newFile->dir     = $_POST['media_path'];
                $newFile->relname = $_POST['media_path'] . '/' . $newFile->basename;
            } else {
                $newFile->dir     = '';
                $newFile->relname = $newFile->basename;
            }
            $newFile->media_title = Html::escapeHTML($_POST['media_title']);
            $newFile->media_dt    = (int) strtotime($_POST['media_dt']);
            $newFile->media_dtstr = $_POST['media_dt'];
            $newFile->media_priv  = !empty($_POST['media_private']);

            $desc = isset($_POST['media_desc']) ? Html::escapeHTML($_POST['media_desc']) : '';

            if ($this->item_file->media_meta instanceof SimpleXMLElement) {
                if (0 < count($this->item_file->media_meta)) {
                    foreach ($this->item_file->media_meta as $k => $v) {
                        if ('Description' == $k) {
                            // Update value
                            // $v[0] = $desc;
                            $this->item_file->media_meta->Description = $desc;

                            break;
                        }
                    }
                } else {
                    if ($desc) {
                        // Add value
                        $this->item_file->media_meta->addChild('Description', $desc);
                    }
                }
            } else {
                if ($desc) {
                    // Create meta and add value
                    $this->item_file->media_meta = simplexml_load_string('<meta></meta>');
                    $this->item_file->media_meta->addChild('Description', $desc);
                }
            }

            try {
                App::core()->media()->updateFile($this->item_file, $newFile);

                App::core()->notice()->addSuccessNotice(__('File has been successfully updated.'));
                $this->item_page_url_params['tab'] = 'media-details-tab';
                App::core()->adminurl()->redirect('admin.media.item', $this->item_page_url_params);
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Update thumbnails
        if (!empty($_POST['thumbs']) && 'image' == $this->item_file->media_type && $this->item_file->editable && $this->media_writable) {
            try {
                $foo = null;
                App::core()->media()->mediaFireRecreateEvent($this->item_file);

                App::core()->notice()->addSuccessNotice(__('Thumbnails have been successfully updated.'));
                $this->item_page_url_params['tab'] = 'media-details-tab';
                App::core()->adminurl()->redirect('admin.media.item', $this->item_page_url_params);
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Unzip file
        if (!empty($_POST['unzip']) && 'application/zip' == $this->item_file->type && $this->item_file->editable && $this->media_writable) {
            try {
                $unzip_dir = App::core()->media()->inflateZipFile($this->item_file, 'new' == $_POST['inflate_mode']);

                App::core()->notice()->addSuccessNotice(__('Zip file has been successfully extracted.'));
                $this->media_page_url_params['d'] = $unzip_dir;
                App::core()->adminurl()->redirect('admin.media', $this->media_page_url_params);
            } catch (Exception $e) {
                App::core()->error()->add($e->getMessage());
            }
        }

        // Save media insertion settings for the blog
        if (!empty($_POST['save_blog_prefs'])) {
            if (!empty($_POST['pref_src'])) {
                if (!($s = array_search($_POST['pref_src'], $this->item_file->media_thumb))) {
                    $s = 'o';
                }
                App::core()->blog()->settings()->get('system')->put('media_img_default_size', $s);
            }
            if (!empty($_POST['pref_alignment'])) {
                App::core()->blog()->settings()->get('system')->put('media_img_default_alignment', $_POST['pref_alignment']);
            }
            if (!empty($_POST['pref_insertion'])) {
                App::core()->blog()->settings()->get('system')->put('media_img_default_link', ('link' == $_POST['pref_insertion']));
            }
            if (!empty($_POST['pref_legend'])) {
                App::core()->blog()->settings()->get('system')->put('media_img_default_legend', $_POST['pref_legend']);
            }

            App::core()->notice()->addSuccessNotice(__('Default media insertion settings have been successfully updated.'));
            App::core()->adminurl()->redirect('admin.media.item', $this->item_page_url_params);
        }

        // Save media insertion settings for the folder
        if (!empty($_POST['save_folder_prefs'])) {
            $prefs = [];
            if (!empty($_POST['pref_src'])) {
                if (!($s = array_search($_POST['pref_src'], $this->item_file->media_thumb))) {
                    $s = 'o';
                }
                $prefs['size'] = $s;
            }
            if (!empty($_POST['pref_alignment'])) {
                $prefs['alignment'] = $_POST['pref_alignment'];
            }
            if (!empty($_POST['pref_insertion'])) {
                $prefs['link'] = ('link' == $_POST['pref_insertion']);
            }
            if (!empty($_POST['pref_legend'])) {
                $prefs['legend'] = $_POST['pref_legend'];
            }

            $local = App::core()->media()->root . '/' . dirname($this->item_file->relname) . '/' . '.mediadef.json';
            if (file_put_contents($local, json_encode($prefs, JSON_PRETTY_PRINT))) {
                App::core()->notice()->addSuccessNotice(__('Media insertion settings have been successfully registered for this folder.'));
            }
            App::core()->adminurl()->redirect('admin.media.item', $this->item_page_url_params);
        }

        // Delete media insertion settings for the folder (.mediadef and .mediadef.json)
        if (!empty($_POST['remove_folder_prefs'])) {
            $local      = App::core()->media()->root . '/' . dirname($this->item_file->relname) . '/' . '.mediadef';
            $local_json = $local . '.json';
            if ((file_exists($local) && unlink($local)) || (file_exists($local_json) && unlink($local_json))) {
                App::core()->notice()->addSuccessNotice(__('Media insertion settings have been successfully removed for this folder.'));
            }
            App::core()->adminurl()->redirect('admin.media.item', $this->item_page_url_params);
        }

        // Page setup
        $this->setPageHead(
            App::core()->resource()->modal() .
            App::core()->resource()->load('_media_item.js')
        );
        if ($this->item_popup && !empty($plugin_id)) {
            $this->setPageHead(App::core()->behavior()->call('adminPopupMedia', $plugin_id));
        }

        $temp_params      = $this->media_page_url_params;
        $temp_params['d'] = '%s';
        $breadcrumb       = App::core()->media()->breadCrumb(App::core()->adminurl()->get('admin.media', $temp_params, '&amp;', true)) .
            (null === $this->item_file ? '' : '<span class="page-title">' . $this->item_file->basename . '</span>');
        $temp_params['d'] = '';
        $home_url         = App::core()->adminurl()->get('admin.media', $temp_params);

        $this->setPageTitle(__('Media manager'));
        $this->setPageBreadcrumb(
            [
                Html::escapeHTML(App::core()->blog()->name) => '',
                __('Media manager')                         => $home_url,
                $breadcrumb                                 => '',
            ],
            [
                'home_link' => !$this->item_popup,
                'hl'        => false,
            ]
        );

        if ($this->item_popup) {
            $this->setPageType('popup');
            $this->setPageHead(App::core()->resource()->pageTabs($tab));
        } else {
            $this->setPageHelp('core_media');
        }

        return true;
    }

    protected function getPageContent(): void
    {
        if (!App::core()->media()) {
            return;
        }

        if (!empty($_GET['fupd']) || !empty($_GET['fupl'])) {
            App::core()->notice()->success(__('File has been successfully updated.'));
        }
        if (!empty($_GET['thumbupd'])) {
            App::core()->notice()->success(__('Thumbnails have been successfully updated.'));
        }
        if (!empty($_GET['blogprefupd'])) {
            App::core()->notice()->success(__('Default media insertion settings have been successfully updated.'));
        }

        // Get major file type (first part of mime type)
        $file_type = explode('/', $this->item_file->type);

        // Selection mode
        if ($this->item_select) {
            // Let user choose thumbnail size if image
            $media_title = $this->item_file->media_title;
            if ($media_title == $this->item_file->basename || Files::tidyFileName($media_title) == $this->item_file->basename) {
                $media_title = '';
            }

            $media_desc = $this->getImageDescription($this->item_file, $media_title);
            $defaults   = $this->getImageDefinition($this->item_file);

            echo '<div id="media-select" class="multi-part" title="' . __('Select media item') . '">' .
            '<h3>' . __('Select media item') . '</h3>' .
                '<form id="media-select-form" action="" method="get">';

            if ('image' == $this->item_file->media_type) {
                $media_type  = 'image';
                $media_title = $this->getImageTitle(
                    $this->item_file,
                    App::core()->blog()->settings()->get('system')->get('media_img_title_pattern'),
                    App::core()->blog()->settings()->get('system')->get('media_img_use_dto_first'),
                    App::core()->blog()->settings()->get('system')->get('media_img_no_date_alone')
                );
                if ($media_title == $this->item_file->basename || Files::tidyFileName($media_title) == $this->item_file->basename) {
                    $media_title = '';
                }

                echo '<h3>' . __('Image size:') . '</h3> ';

                $s_checked = false;
                echo '<p>';
                foreach (array_reverse($this->item_file->media_thumb) as $s => $v) {
                    $s_checked = ($s == $defaults['size']);
                    echo '<label class="classic">' .
                    Form::radio(['src'], Html::escapeHTML($v), $s_checked) . ' ' .
                    App::core()->media()->thumbsize()->getName($s) . '</label><br /> ';
                }
                $s_checked = (!isset($this->item_file->media_thumb[$defaults['size']]));
                echo '<label class="classic">' .
                Form::radio(['src'], $this->item_file->file_url, $s_checked) . ' ' . __('original') . '</label><br /> ';
                echo '</p>';
            } elseif ('audio' == $file_type[0]) {
                $media_type = 'mp3';
            } elseif ('video' == $file_type[0]) {
                $media_type = 'flv';
            } else {
                $media_type = 'default';
            }

            echo '<p>' .
            '<button type="button" id="media-select-ok" class="submit">' . __('Select') . '</button> ' .
            '<button type="button" id="media-select-cancel">' . __('Cancel') . '</button>' .
            Form::hidden(['type'], Html::escapeHTML($media_type)) .
            Form::hidden(['title'], Html::escapeHTML($media_title)) .
            Form::hidden(['description'], Html::escapeHTML($media_desc)) .
            Form::hidden(['url'], $this->item_file->file_url) .
                '</p>';

            echo '</form>';
            echo '</div>';
        }

        // Insertion popup
        if ($this->item_popup && !$this->item_select) {
            $media_title = $this->item_file->media_title;
            if ($media_title == $this->item_file->basename || Files::tidyFileName($media_title) == $this->item_file->basename) {
                $media_title = '';
            }

            $media_desc = $this->getImageDescription($this->item_file, $media_title);
            $defaults   = $this->getImageDefinition($this->item_file);

            echo '<div id="media-insert" class="multi-part" title="' . __('Insert media item') . '">' .
            '<h3>' . __('Insert media item') . '</h3>' .
                '<form id="media-insert-form" action="" method="get">';

            if ('image' == $this->item_file->media_type) {
                $media_type  = 'image';
                $media_title = $this->getImageTitle(
                    $this->item_file,
                    App::core()->blog()->settings()->get('system')->get('media_img_title_pattern'),
                    App::core()->blog()->settings()->get('system')->get('media_img_use_dto_first'),
                    App::core()->blog()->settings()->get('system')->get('media_img_no_date_alone')
                );
                if ($media_title == $this->item_file->basename || Files::tidyFileName($media_title) == $this->item_file->basename) {
                    $media_title = '';
                }

                echo '<div class="two-boxes">' .
                '<h3>' . __('Image size:') . '</h3> ';
                $s_checked = false;
                echo '<p>';
                foreach (array_reverse($this->item_file->media_thumb) as $s => $v) {
                    $s_checked = ($s == $defaults['size']);
                    echo '<label class="classic">' .
                    Form::radio(['src'], Html::escapeHTML($v), $s_checked) . ' ' .
                    App::core()->media()->thumbsize()->getName($s) . '</label><br /> ';
                }
                $s_checked = (!isset($this->item_file->media_thumb[$defaults['size']]));
                echo '<label class="classic">' .
                Form::radio(['src'], $this->item_file->file_url, $s_checked) . ' ' . __('original') . '</label><br /> ';
                echo '</p>';
                echo '</div>';

                echo '<div class="two-boxes">' .
                '<h3>' . __('Image legend and title') . '</h3>' .
                '<p>' .
                '<label for="legend1" class="classic">' . Form::radio(
                    ['legend', 'legend1'],
                    'legend',
                    ('legend' == $defaults['legend'])
                ) .
                __('Legend and title') . '</label><br />' .
                '<label for="legend2" class="classic">' . Form::radio(
                    ['legend', 'legend2'],
                    'title',
                    ('title' == $defaults['legend'])
                ) .
                __('Title') . '</label><br />' .
                '<label for="legend3" class="classic">' . Form::radio(
                    ['legend', 'legend3'],
                    'none',
                    ('none' == $defaults['legend'])
                ) .
                __('None') . '</label>' .
                '</p>' .
                '<p id="media-attribute">' .
                __('Title: ') . ('' != $media_title ? '<span class="media-title">' . $media_title . '</span>' : __('(none)')) .
                '<br />' .
                __('Legend: ') . ('' != $media_desc ? ' <span class="media-desc">' . $media_desc . '</span>' : __('(none)')) .
                    '</p>' .
                    '</div>';

                echo '<div class="two-boxes">' .
                '<h3>' . __('Image alignment') . '</h3>';
                $i_align = [
                    'none'   => [__('None'), ('none' == $defaults['alignment'] ? 1 : 0)],
                    'left'   => [__('Left'), ('left' == $defaults['alignment'] ? 1 : 0)],
                    'right'  => [__('Right'), ('right' == $defaults['alignment'] ? 1 : 0)],
                    'center' => [__('Center'), ('center' == $defaults['alignment'] ? 1 : 0)],
                ];

                echo '<p>';
                foreach ($i_align as $k => $v) {
                    echo '<label class="classic">' .
                    Form::radio(['alignment'], $k, $v[1]) . ' ' . $v[0] . '</label><br /> ';
                }
                echo '</p>';
                echo '</div>';

                echo '<div class="two-boxes">' .
                '<h3>' . __('Image insertion') . '</h3>' .
                '<p>' .
                '<label for="insert1" class="classic">' . Form::radio(['insertion', 'insert1'], 'simple', !$defaults['link']) .
                __('As a single image') . '</label><br />' .
                '<label for="insert2" class="classic">' . Form::radio(['insertion', 'insert2'], 'link', $defaults['link']) .
                __('As a link to the original image') . '</label>' .
                    '</p>' .
                    '</div>';
            } elseif ('audio' == $file_type[0]) {
                $media_type = 'mp3';

                echo '<div class="two-boxes">' .
                '<h3>' . __('MP3 disposition') . '</h3>';
                App::core()->notice()->message(__('Please note that you cannot insert mp3 files with visual editor.'), false);

                $i_align = [
                    'none'   => [__('None'), ('none' == $defaults['alignment'] ? 1 : 0)],
                    'left'   => [__('Left'), ('left' == $defaults['alignment'] ? 1 : 0)],
                    'right'  => [__('Right'), ('right' == $defaults['alignment'] ? 1 : 0)],
                    'center' => [__('Center'), ('center' == $defaults['alignment'] ? 1 : 0)],
                ];

                echo '<p>';
                foreach ($i_align as $k => $v) {
                    echo '<label class="classic">' .
                    Form::radio(['alignment'], $k, $v[1]) . ' ' . $v[0] . '</label><br /> ';
                }

                $url = $this->item_file->file_url;
                if (App::core()->blog()->host === substr($url, 0, strlen(App::core()->blog()->host))) {
                    $url = substr($url, strlen(App::core()->blog()->host));
                }
                echo Form::hidden('blog_host', Html::escapeHTML(App::core()->blog()->host));
                echo Form::hidden('public_player', Html::escapeHTML(Media::audioPlayer($this->item_file->type, $url)));
                echo '</p>';
                echo '</div>';
            } elseif ('video' == $file_type[0]) {
                $media_type = 'flv';

                App::core()->notice()->message(__('Please note that you cannot insert video files with visual editor.'), false);

                echo '<div class="two-boxes">' .
                '<h3>' . __('Video size') . '</h3>' .
                '<p><label for="video_w" class="classic">' . __('Width:') . '</label> ' .
                Form::number('video_w', 0, 9999, App::core()->blog()->settings()->get('system')->get('media_video_width')) . '  ' .
                '<label for="video_h" class="classic">' . __('Height:') . '</label> ' .
                Form::number('video_h', 0, 9999, App::core()->blog()->settings()->get('system')->get('media_video_height')) .
                    '</p>' .
                    '</div>';

                echo '<div class="two-boxes">' .
                '<h3>' . __('Video disposition') . '</h3>';

                $i_align = [
                    'none'   => [__('None'), ('none' == $defaults['alignment'] ? 1 : 0)],
                    'left'   => [__('Left'), ('left' == $defaults['alignment'] ? 1 : 0)],
                    'right'  => [__('Right'), ('right' == $defaults['alignment'] ? 1 : 0)],
                    'center' => [__('Center'), ('center' == $defaults['alignment'] ? 1 : 0)],
                ];

                echo '<p>';
                foreach ($i_align as $k => $v) {
                    echo '<label class="classic">' .
                    Form::radio(['alignment'], $k, $v[1]) . ' ' . $v[0] . '</label><br /> ';
                }

                $url = $this->item_file->file_url;
                if (App::core()->blog()->host === substr($url, 0, strlen(App::core()->blog()->host))) {
                    $url = substr($url, strlen(App::core()->blog()->host));
                }
                echo Form::hidden('blog_host', Html::escapeHTML(App::core()->blog()->host));
                echo Form::hidden('public_player', Html::escapeHTML(Media::videoPlayer($this->item_file->type, $url)));
                echo '</p>';
                echo '</div>';
            } else {
                $media_type  = 'default';
                $media_title = $this->item_file->media_title;
                echo '<p>' . __('Media item will be inserted as a link.') . '</p>';
            }

            echo '<p>' .
            '<button type="button" id="media-insert-ok" class="submit">' . __('Insert') . '</button> ' .
            '<button type="button" id="media-insert-cancel">' . __('Cancel') . '</button>' .
            Form::hidden(['type'], Html::escapeHTML($media_type)) .
            Form::hidden(['title'], Html::escapeHTML($media_title)) .
            Form::hidden(['description'], Html::escapeHTML($media_desc)) .
            Form::hidden(['url'], $this->item_file->file_url) .
                '</p>';

            echo '</form>';

            if ('default' != $media_type) {
                echo '<div class="border-top">' .
                '<form id="save_settings" action="' . App::core()->adminurl()->root() . '" method="post">' .
                '<p>' . __('Make current settings as default') . ' ' .
                '<input class="reset" type="submit" name="save_blog_prefs" value="' . __('For the blog') . '" /> ' . __('or') . ' ' .
                '<input class="reset" type="submit" name="save_folder_prefs" value="' . __('For this folder only') . '" />';

                $local = App::core()->media()->root . '/' . dirname($this->item_file->relname) . '/' . '.mediadef';
                if (!file_exists($local)) {
                    $local .= '.json';
                }
                if (file_exists($local)) {
                    echo '</p>' .
                    '<p>' . __('Settings exist for this folder:') . ' ' .
                    '<input class="delete" type="submit" name="remove_folder_prefs" value="' . __('Remove them') . '" /> ';
                }

                echo Form::hidden(['pref_src'], '') .
                Form::hidden(['pref_alignment'], '') .
                Form::hidden(['pref_insertion'], '') .
                Form::hidden(['pref_legend'], '') .
                App::core()->adminurl()->getHiddenFormFields('admin.media.item', $this->item_page_url_params, true) . '</p>' .
                    '</form>' . '</div>';
            }

            echo '</div>';
        }

        if ($this->item_popup || $this->item_select) {
            echo '<div class="multi-part" title="' . __('Media details') . '" id="media-details-tab">';
        } else {
            echo '<h3 class="out-of-screen-if-js">' . __('Media details') . '</h3>';
        }
        echo '<p id="media-icon"><img class="media-icon-square" src="' . $this->item_file->media_icon . '&' . time() * rand() . '" alt="" /></p>';

        echo '<div id="media-details">' .
            '<div class="near-icon">';

        if ($this->item_file->media_image) {
            $thumb_size = !empty($_GET['size']) ? $_GET['size'] : 's';

            if (!App::core()->media()->thumbsize()->exists($thumb_size) && 'o' != $thumb_size) {
                $thumb_size = 's';
            }

            if (isset($this->item_file->media_thumb[$thumb_size])) {
                echo '<p><a class="modal-image" href="' . $this->item_file->file_url . '">' .
                '<img src="' . $this->item_file->media_thumb[$thumb_size] . '&' . time() * rand() . '" alt="" />' .
                    '</a></p>';
            } elseif ('o' == $thumb_size) {
                $S     = getimagesize($this->item_file->file);
                $class = !$S || (500 < $S[1]) ? ' class="overheight"' : '';
                unset($S);
                echo '<p id="media-original-image"' . $class . '><a class="modal-image" href="' . $this->item_file->file_url . '">' .
                '<img src="' . $this->item_file->file_url . '&' . time() * rand() . '" alt="" />' .
                    '</a></p>';
            }

            echo '<p>' . __('Available sizes:') . ' ';
            foreach (array_reverse($this->item_file->media_thumb) as $s => $v) {
                $strong_link = ($s == $thumb_size) ? '<strong>%s</strong>' : '%s';
                printf($strong_link, '<a href="' . App::core()->adminurl()->get('admin.media.item', array_merge(
                    $this->item_page_url_params,
                    ['size' => $s, 'tab' => 'media-details-tab']
                )) . '">' . App::core()->media()->thumbsize()->getName($s) . '</a> | ');
            }
            echo '<a href="' . App::core()->adminurl()->get('admin.media.item', array_merge($this->item_page_url_params, ['size' => 'o', 'tab' => 'media-details-tab'])) . '">' . __('original') . '</a>';
            echo '</p>';

            if ('o' != $thumb_size && isset($this->item_file->media_thumb[$thumb_size])) {
                $p          = Path::info($this->item_file->file);
                $alpha      = ('png' == $p['extension']) || ('PNG' == $p['extension']);
                $alpha      = strtolower($p['extension']) === 'png';
                $webp       = strtolower($p['extension']) === 'webp';
                $thumb_tp   = ($alpha ? App::core()->media()->thumb_tp_alpha : ($webp ? App::core()->media()->thumb_tp_webp : App::core()->media()->thumb_tp));
                $thumb      = sprintf($thumb_tp, $p['dirname'], $p['base'], '%s');
                $thumb_file = sprintf($thumb, $thumb_size);
                $T          = getimagesize($thumb_file);
                $stats      = stat($thumb_file);
                echo '<h3>' . __('Thumbnail details') . '</h3>' .
                '<ul>';
                if (is_array($T)) {
                    echo '<li><strong>' . __('Image width:') . '</strong> ' . $T[0] . ' px</li>' .
                    '<li><strong>' . __('Image height:') . '</strong> ' . $T[1] . ' px</li>';
                }
                echo '<li><strong>' . __('File size:') . '</strong> ' . Files::size($stats[7]) . '</li>' .
                '<li><strong>' . __('File URL:') . '</strong> <a href="' . $this->item_file->media_thumb[$thumb_size] . '">' .
                $this->item_file->media_thumb[$thumb_size] . '</a></li>' .
                    '</ul>';
            }
        }

        // Show player if relevant
        if ('audio' == $file_type[0]) {
            echo Media::audioPlayer($this->item_file->type, $this->item_file->file_url);
        }
        if ('video' == $file_type[0]) {
            echo Media::videoPlayer($this->item_file->type, $this->item_file->file_url);
        }

        echo '<h3>' . __('Media details') . '</h3>' .
        '<ul>' .
        '<li><strong>' . __('File owner:') . '</strong> ' . $this->item_file->media_user . '</li>' .
        '<li><strong>' . __('File type:') . '</strong> ' . $this->item_file->type . '</li>';
        if ($this->item_file->media_image) {
            $S = getimagesize($this->item_file->file);
            if (is_array($S)) {
                echo '<li><strong>' . __('Image width:') . '</strong> ' . $S[0] . ' px</li>' .
                '<li><strong>' . __('Image height:') . '</strong> ' . $S[1] . ' px</li>';
                unset($S);
            }
        }
        echo '<li><strong>' . __('File size:') . '</strong> ' . Files::size($this->item_file->size) . '</li>' .
        '<li><strong>' . __('File URL:') . '</strong> <a href="' . $this->item_file->file_url . '">' . $this->item_file->file_url . '</a></li>' .
            '</ul>';

        if (empty($_GET['find_posts'])) {
            echo '<p><a class="button" href="' . App::core()->adminurl()->get('admin.media.item', array_merge($this->item_page_url_params, ['find_posts' => 1, 'tab' => 'media-details-tab'])) . '">' .
            __('Show entries containing this media') . '</a></p>';
        } else {
            echo '<h3>' . __('Entries containing this media') . '</h3>';
            $params = [
                'post_type' => '',
                'join'      => 'LEFT OUTER JOIN ' . App::core()->prefix . 'post_media PM ON P.post_id = PM.post_id ',
                'sql'       => 'AND (' .
                'PM.media_id = ' . (int) $this->item_id . ' ' .
                "OR post_content_xhtml LIKE '%" . App::core()->con()->escape($this->item_file->relname) . "%' " .
                "OR post_excerpt_xhtml LIKE '%" . App::core()->con()->escape($this->item_file->relname) . "%' ",
            ];

            if ($this->item_file->media_image) {
                // We look for thumbnails too
                $media_root = App::core()->blog()->public_url . '/';

                foreach ($this->item_file->media_thumb as $v) {
                    $v = preg_replace('/^' . preg_quote($media_root, '/') . '/', '', $v);
                    $params['sql'] .= "OR post_content_xhtml LIKE '%" . App::core()->con()->escape($v) . "%' ";
                    $params['sql'] .= "OR post_excerpt_xhtml LIKE '%" . App::core()->con()->escape($v) . "%' ";
                }
            }

            $params['sql'] .= ') ';

            $rs = App::core()->blog()->posts()->getPosts($params);

            if ($rs->isEmpty()) {
                echo '<p>' . __('No entry seems contain this media.') . '</p>';
            } else {
                echo '<ul>';
                while ($rs->fetch()) {
                    $img        = '<img alt="%1$s" title="%1$s" src="?df=images/%2$s" />';
                    $img_status = match ($rs->fInt('post_status')) {
                        1       => sprintf($img, __('published'), 'check-on.png'),
                        0       => sprintf($img, __('unpublished'), 'check-off.png'),
                        -1      => sprintf($img, __('scheduled'), 'scheduled.png'),
                        -2      => sprintf($img, __('pending'), 'check-wrn.png'),
                        default => '',
                    };
                    echo '<li>' . $img_status . ' ' . '<a href="' . App::core()->posttype()->getPostAdminURL($rs->f('post_type'), $rs->f('post_id')) . '">' .
                    $rs->f('post_title') . '</a>' .
                    ('post' != $rs->f('post_type') ? ' (' . Html::escapeHTML($rs->f('post_type')) . ')' : '') .
                    ' - ' . Dt::dt2str(__('%Y-%m-%d %H:%M'), $rs->f('post_dt')) . '</li>';
                }
                echo '</ul>';
            }
        }

        if ('image/jpeg' == $this->item_file->type || 'image/webp' == $this->item_file->type) {
            echo '<h3>' . __('Image details') . '</h3>';

            $details = '';
            if (count($this->item_file->media_meta) > 0) {
                foreach ($this->item_file->media_meta as $k => $v) {
                    if ((string) $v) {
                        $details .= '<li><strong>' . $k . ':</strong> ' . Html::escapeHTML((string) $v) . '</li>';
                    }
                }
            }
            if ($details) {
                echo '<ul>' . $details . '</ul>';
            } else {
                echo '<p>' . __('No detail') . '</p>';
            }
        }

        echo '</div>';

        echo '<h3>' . __('Updates and modifications') . '</h3>';

        if ($this->item_file->editable && $this->media_writable) {
            if ('image' == $this->item_file->media_type) {
                echo '<form class="clear fieldset" action="' . App::core()->adminurl()->root() . '" method="post">' .
                '<h4>' . __('Update thumbnails') . '</h4>' .
                '<p class="more-info">' . __('This will create or update thumbnails for this image.') . '</p>' .
                '<p><input type="submit" name="thumbs" value="' . __('Update thumbnails') . '" />' .
                App::core()->adminurl()->getHiddenFormFields('admin.media.item', $this->item_page_url_params, true) .
                '</p>' .
                    '</form>';
            }

            if ('application/zip' == $this->item_file->type) {
                $inflate_combo = [
                    __('Extract in a new directory')   => 'new',
                    __('Extract in current directory') => 'current',
                ];

                echo '<form class="clear fieldset" id="file-unzip" action="' . App::core()->adminurl()->root() . '" method="post">' .
                '<h4>' . __('Extract archive') . '</h4>' .
                '<ul>' .
                '<li><strong>' . __('Extract in a new directory') . '</strong> : ' .
                __('This will extract archive in a new directory that should not exist yet.') . '</li>' .
                '<li><strong>' . __('Extract in current directory') . '</strong> : ' .
                __('This will extract archive in current directory and will overwrite existing files or directory.') . '</li>' .
                '</ul>' .
                '<p><label for="inflate_mode" class="classic">' . __('Extract mode:') . '</label> ' .
                Form::combo('inflate_mode', $inflate_combo, 'new') .
                '<input type="submit" name="unzip" value="' . __('Extract') . '" />' .
                App::core()->adminurl()->getHiddenFormFields('admin.media.item', $this->item_page_url_params, true) .
                '</p>' .
                    '</form>';
            }

            echo '<form class="clear fieldset" action="' . App::core()->adminurl()->root() . '" method="post">' .
            '<h4>' . __('Change media properties') . '</h4>' .
            '<p><label for="media_file">' . __('File name:') . '</label>' .
            Form::field('media_file', 30, 255, Html::escapeHTML($this->item_file->basename)) . '</p>' .
            '<p><label for="media_title">' . __('File title:') . '</label>' .
            Form::field(
                'media_title',
                30,
                255,
                [
                    'default'    => Html::escapeHTML($this->item_file->media_title),
                    'extra_html' => 'lang="' . App::core()->user()->getInfo('user_lang') . '" spellcheck="true"',
                ]
            ) . '</p>';
            if ('image/jpeg' == $this->item_file->type || 'image/webp' == $this->item_file->type) {
                echo '<p><label for="media_desc">' . __('File description:') . '</label>' .
                Form::field(
                    'media_desc',
                    60,
                    255,
                    [
                        'default'    => Html::escapeHTML((string) $this->getImageDescription($this->item_file, '')),
                        'extra_html' => 'lang="' . App::core()->user()->getInfo('user_lang') . '" spellcheck="true"',
                    ]
                ) . '</p>' .
                '<p><label for="media_dt">' . __('File date:') . '</label>';
            }
            echo Form::datetime('media_dt', ['default' => Html::escapeHTML(Dt::str('%Y-%m-%d\T%H:%M', $this->item_file->media_dt))]) .
            '</p>' .
            '<p><label for="media_private" class="classic">' . Form::checkbox('media_private', 1, $this->item_file->media_priv) . ' ' .
            __('Private') . '</label></p>' .
            '<p><label for="media_path">' . __('New directory:') . '</label>' .
            Form::combo('media_path', $this->item_dirs_combo, dirname($this->item_file->relname)) . '</p>' .
            '<p><input type="submit" accesskey="s" value="' . __('Save') . '" />' .
            App::core()->adminurl()->getHiddenFormFields('admin.media.item', $this->item_page_url_params, true) .
            '</p>' .
                '</form>';

            echo '<form class="clear fieldset" action="' . App::core()->adminurl()->root() . '" method="post" enctype="multipart/form-data">' .
            '<h4>' . __('Change file') . '</h4>' .
            '<div>' . Form::hidden(['MAX_FILE_SIZE'], (string) App::core()->config()->get('media_upload_maxsize')) . '</div>' .
            '<p><label for="upfile">' . __('Choose a file:') .
            ' (' . sprintf(__('Maximum size %s'), Files::size((int) App::core()->config()->get('media_upload_maxsize'))) . ') ' .
            '<input type="file" id="upfile" name="upfile" size="35" />' .
            '</label></p>' .
            '<p><input type="submit" value="' . __('Send') . '" />' .
            App::core()->adminurl()->getHiddenFormFields('admin.media.item', $this->item_page_url_params, true) .
            '</p>' .
                '</form>';

            if ($this->item_file->del) {
                echo '<form id="delete-form" method="post" action="' . App::core()->adminurl()->root() . '">' .
                '<p><input name="delete" type="submit" class="delete" value="' . __('Delete this media') . '" />' .
                Form::hidden('remove', rawurlencode($this->item_file->basename)) .
                Form::hidden('rmyes', 1) .
                App::core()->adminurl()->getHiddenFormFields('admin.media', $this->media_page_url_params, true) .
                '</p>' .
                    '</form>';
            }

            // --BEHAVIOR-- adminMediaItemForm
            App::core()->behavior()->call('adminMediaItemForm', $this->item_file);
        }

        echo '</div>';
        if ($this->item_popup || $this->item_select) {
            echo '</div>';
        } else {
            // Go back button
            echo '<p><input type="button" value="' . __('Cancel') . '" class="go-back reset hidden-if-no-js" /></p>';
        }
    }

    protected function getImageTitle($file, $pattern, $dto_first = false, $no_date_alone = false)
    {
        $res     = [];
        $pattern = preg_split('/\s*;;\s*/', $pattern);
        $sep     = ', ';
        $dates   = 0;
        $items   = 0;

        foreach ($pattern as $v) {
            if ('Title' == $v) {
                if ('' != $file->media_title) {
                    $res[] = $file->media_title;
                }
                ++$items;
            } elseif ($file->media_meta->{$v}) {
                if ((string) $file->media_meta->{$v} != '') {
                    $res[] = (string) $file->media_meta->{$v};
                }
                ++$items;
            } elseif (preg_match('/^Date\((.+?)\)$/u', $v, $m)) {
                if ($dto_first && (0 != $file->media_meta->DateTimeOriginal)) {
                    $res[] = Dt::dt2str($m[1], (string) $file->media_meta->DateTimeOriginal);
                } else {
                    $res[] = Dt::str($m[1], $file->media_dt);
                }
                ++$items;
                ++$dates;
            } elseif (preg_match('/^DateTimeOriginal\((.+?)\)$/u', $v, $m) && $file->media_meta->DateTimeOriginal) {
                $res[] = Dt::dt2str($m[1], (string) $file->media_meta->DateTimeOriginal);
                ++$items;
                ++$dates;
            } elseif (preg_match('/^separator\((.*?)\)$/u', $v, $m)) {
                $sep = $m[1];
            }
        }
        if ($no_date_alone && count($res) == $dates && $dates < $items) {
            // On ne laisse pas les dates seules, sauf si ce sont les seuls items du pattern (hors séparateur)
            return '';
        }

        return implode($sep, $res);
    }

    protected function getImageDescription($file, $default = '')
    {
        if (count($file->media_meta) > 0) {
            foreach ($file->media_meta as $k => $v) {
                if ((string) $v && ('Description' == $k)) {
                    return (string) $v;
                }
            }
        }

        return (string) $default;
    }

    protected function getImageDefinition($file)
    {
        $defaults = [
            'size'      => (string) App::core()->blog()->settings()->get('system')->get('media_img_default_size') ?: 'm',
            'alignment' => (string) App::core()->blog()->settings()->get('system')->get('media_img_default_alignment') ?: 'none',
            'link'      => (bool) App::core()->blog()->settings()->get('system')->get('media_img_default_link'),
            'legend'    => (string) App::core()->blog()->settings()->get('system')->get('media_img_default_legend') ?: 'legend',
            'mediadef'  => false,
        ];

        try {
            $local = App::core()->media()->root . '/' . dirname($file->relname) . '/' . '.mediadef';
            if (!file_exists($local)) {
                $local .= '.json';
            }
            if (file_exists($local)) {
                if ($specifics = json_decode(file_get_contents($local) ?: '', true)) {
                    foreach ($defaults as $key => $value) {
                        $defaults[$key]       = $specifics[$key] ?? $defaults[$key];
                        $defaults['mediadef'] = true;
                    }
                }
            }
        } catch (\Exception) {
        }

        return $defaults;
    }
}
