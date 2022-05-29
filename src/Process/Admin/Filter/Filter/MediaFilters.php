<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Filter\Filter;

// Dotclear\Process\Admin\Filter\Filter\MediaFilters
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\Html;
use Dotclear\Process\Admin\Filter\Filter;
use Dotclear\Process\Admin\Filter\Filters;
use Dotclear\Process\Admin\Filter\FilterStack;

/**
 * Admin media list filters form.
 *
 * @ingroup  Admin Media Filter
 *
 * @since 2.20
 */
class MediaFilters extends Filters
{
    protected $post_type  = '';
    protected $post_title = '';

    public function __construct(string $type = 'media')
    {
        parent::__construct(type: $type, filters: new FilterStack(
            $this->getPageFilter(),
            $this->getSearchFilter(),
            $this->getPostIdFilter(),
            $this->getDirFilter(),
            $this->getFileModeFilter(),
            $this->getPluginIdFilter(),
            $this->getLinkTypeFilter(),
            $this->getPopupFilter(),
            $this->getMediaSelectFilter()
        ));
    }

    protected function getPostIdFilter(): Filter
    {
        $post_id = GPC::request()->int('post_id', null);
        if ($post_id) {
            $param = new Param();
            $param->set('post_id', $post_id);
            $param->set('post_type', '');

            $post = App::core()->blog()->posts()->getPosts(param: $param);
            if ($post->isEmpty()) {
                $post_id = null;
            } else {
                // keep track of post_title_ and post_type without using filters
                $this->post_title = $post->f('post_title');
                $this->post_type  = $post->f('post_type');
            }
        }

        return new Filter('post_id', $post_id);
    }

    public function getPostTitle(): string
    {
        return $this->post_title;
    }

    public function getPostType(): string
    {
        return $this->post_type;
    }

    protected function getDirFilter(): Filter
    {
        $get = GPC::request()->string('d', null);
        if (null === $get && isset($_SESSION['media_manager_dir'])) {
            // We get session information
            $get = $_SESSION['media_manager_dir'];
        }
        if ($get) {
            $_SESSION['media_manager_dir'] = $get;
        } else {
            unset($_SESSION['media_manager_dir']);
        }

        return new Filter('d', $get);
    }

    protected function getFileModeFilter(): Filter
    {
        if (!GPC::get()->empty('file_mode')) {
            $_SESSION['media_file_mode'] = 'grid' == GPC::get()->string('file_mode') ? 'grid' : 'list';
        }
        $get = !empty($_SESSION['media_file_mode']) ? $_SESSION['media_file_mode'] : 'grid';

        return new Filter('file_mode', $get);
    }

    protected function getPluginIdFilter(): Filter
    {
        $get = Html::sanitizeURL(GPC::request()->string('plugin_id'));

        return new Filter('plugin_id', $get);
    }

    protected function getLinkTypeFilter(): Filter
    {
        $get = !GPC::request()->empty('link_type') ? Html::escapeHTML(GPC::request()->string('link_type')) : null;

        return new Filter('link_type', $get);
    }

    protected function getPopupFilter(): Filter
    {
        $get = (int) !GPC::request()->empty('popup');

        return new Filter('popup', $get);
    }

    protected function getMediaSelectFilter(): Filter
    {
        // 0 : none, 1 : single media, >1 : multiple media
        $get = GPC::request()->int('select');

        return new Filter('select', $get);
    }
}
