<?php
/**
 * @class Dotclear\Admin\Filter\MediaFilter
 * @brief class for admin media list filters form
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @since 2.20
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Filter;

use Dotclear\Core\Core;

use Dotclear\Admin\Filter;
use Dotclear\Admin\Filters;
use Dotclear\Admin\Filter\DefaultFilter;

use Dotclear\Html\Html;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class MediaFilter extends Filter
{
    protected $post_type  = '';
    protected $post_title = '';

    public function __construct(Core $core, string $type = 'media')
    {
        parent::__construct($core, $type);

        $filters = new \arrayObject([
            Filters::getPageFilter(),
            Filters::getSearchFilter(),

            $this->getPostIdFilter(),
            $this->getDirFilter(),
            $this->getFileModeFilter(),
            $this->getPluginIdFilter(),
            $this->getLinkTypeFilter(),
            $this->getPopupFilter(),
            $this->getSelectFilter()
        ]);

        # --BEHAVIOR-- adminBlogFilter
        $core->callBehavior('adminMediaFilter', $filters);

        $filters = $filters->getArrayCopy();

        $this->add($filters);

        $this->legacyBehavior();
    }

    /**
     * Cope with old behavior
     */
    protected function legacyBehavior()
    {
        $values = new \ArrayObject($this->values());

        $this->core->callBehavior('adminMediaURLParams', $values);

        foreach ($values->getArrayCopy() as $filter => $new_value) {
            if (isset($this->filters[$filter])) {
                $this->filters[$filter]->value($new_value);
            } else {
                $this->add($filter, $new_value);
            }
        }
    }

    protected function getPostIdFilter()
    {
        $post_id = !empty($_REQUEST['post_id']) ? (integer) $_REQUEST['post_id'] : null;
        if ($post_id) {
            $post = $this->core->blog->getPosts(['post_id' => $post_id, 'post_type' => '']);
            if ($post->isEmpty()) {
                $post_id = null;
            }
            // keep track of post_title_ and post_type without using filters
            $this->post_title = $post->post_title;
            $this->post_type  = $post->post_type;
        }

        return new DefaultFilter('post_id', $post_id);
    }

    public function getPostTitle()
    {
        return $this->post_title;
    }

    public function getPostType()
    {
        return $this->post_type;
    }

    protected function getDirFilter()
    {
        $get = $_REQUEST['d'] ?? null;
        if ($get === null && isset($_SESSION['media_manager_dir'])) {
            // We get session information
            $get = $_SESSION['media_manager_dir'];
        }
        if ($get) {
            $_SESSION['media_manager_dir'] = $get;
        } else {
            unset($_SESSION['media_manager_dir']);
        }

        return new DefaultFilter('d', $get);
    }

    protected function getFileModeFilter()
    {
        if (!empty($_GET['file_mode'])) {
            $_SESSION['media_file_mode'] = $_GET['file_mode'] == 'grid' ? 'grid' : 'list';
        }
        $get = !empty($_SESSION['media_file_mode']) ? $_SESSION['media_file_mode'] : 'grid';

        return new DefaultFilter('file_mode', $get);
    }

    protected function getPluginIdFilter()
    {
        $get = isset($_REQUEST['plugin_id']) ? Html::sanitizeURL($_REQUEST['plugin_id']) : '';

        return new DefaultFilter('plugin_id', $get);
    }

    protected function getLinkTypeFilter()
    {
        $get = !empty($_REQUEST['link_type']) ? Html::escapeHTML($_REQUEST['link_type']) : null;

        return new DefaultFilter('link_type', $get);
    }

    protected function getPopupFilter()
    {
        $get = (integer) !empty($_REQUEST['popup']);

        return new DefaultFilter('popup', $get);
    }

    protected function getSelectFilter()
    {
        // 0 : none, 1 : single media, >1 : multiple media
        $get = !empty($_REQUEST['select']) ? (integer) $_REQUEST['select'] : 0;

        return new DefaultFilter('select', $get);
    }
}
