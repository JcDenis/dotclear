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
    private $post_type  = '';
    private $post_title = '';

    public function __construct(string $id = 'media')
    {
        parent::__construct(id: $id, filters: new FilterStack(
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

        return new Filter(id: 'post_id', value: $post_id);
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

        return new Filter(
            id: 'd',
            value: $get
        );
    }

    protected function getFileModeFilter(): Filter
    {
        if (!GPC::get()->empty('file_mode')) {
            $_SESSION['media_file_mode'] = 'grid' == GPC::get()->string('file_mode') ? 'grid' : 'list';
        }

        return new Filter(
            id: 'file_mode',
            value: !empty($_SESSION['media_file_mode']) ? $_SESSION['media_file_mode'] : 'grid'
        );
    }

    protected function getPluginIdFilter(): Filter
    {
        return new Filter(
            id: 'plugin_id',
            value: Html::sanitizeURL(GPC::request()->string('plugin_id'))
        );
    }

    protected function getLinkTypeFilter(): Filter
    {
        return new Filter(
            id: 'link_type',
            value: !GPC::request()->empty('link_type') ? Html::escapeHTML(GPC::request()->string('link_type')) : null
        );
    }

    protected function getPopupFilter(): Filter
    {
        return new Filter(
            id: 'popup',
            value: (int) !GPC::request()->empty('popup')
        );
    }

    // 0 : none, 1 : single media, >1 : multiple media
    protected function getMediaSelectFilter(): Filter
    {
        return new Filter(
            id: 'select',
            value: GPC::request()->int('select')
        );
    }
}
