<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Filter\Filter;

// Dotclear\Process\Admin\Filter\Filter\MediaFilter
use ArrayObject;
use Dotclear\App;
use Dotclear\Process\Admin\Filter\Filter;
use Dotclear\Helper\Html\Html;

/**
 * Admin media list filters form.
 *
 * @ingroup  Admin Media Filter
 *
 * @since 2.20
 */
class MediaFilter extends Filter
{
    protected $post_type  = '';
    protected $post_title = '';

    public function __construct(string $type = 'media')
    {
        parent::__construct($type);

        $filters = new ArrayObject([
            $this->getPageFilter(),
            $this->getSearchFilter(),

            $this->getPostIdFilter(),
            $this->getDirFilter(),
            $this->getFileModeFilter(),
            $this->getPluginIdFilter(),
            $this->getLinkTypeFilter(),
            $this->getPopupFilter(),
            $this->getMediaSelectFilter(),
        ]);

        // --BEHAVIOR-- adminBlogFilter
        App::core()->behavior()->call('adminMediaFilter', $filters);

        $filters = $filters->getArrayCopy();

        $this->add($filters);

        $this->legacyBehavior();
    }

    /**
     * Cope with old behavior.
     */
    protected function legacyBehavior(): void
    {
        $values = new ArrayObject($this->values());

        App::core()->behavior()->call('adminMediaURLParams', $values);

        foreach ($values->getArrayCopy() as $filter => $new_value) {
            if (isset($this->filters[$filter])) {
                $this->filters[$filter]->value($new_value);
            } else {
                $this->add($filter, $new_value);
            }
        }
    }

    protected function getPostIdFilter(): DefaultFilter
    {
        $post_id = !empty($_REQUEST['post_id']) ? (int) $_REQUEST['post_id'] : null;
        if ($post_id) {
            $post = App::core()->blog()->posts()->getPosts(['post_id' => $post_id, 'post_type' => '']);
            if ($post->isEmpty()) {
                $post_id = null;
            }
            // keep track of post_title_ and post_type without using filters
            $this->post_title = $post->f('post_title');
            $this->post_type  = $post->f('post_type');
        }

        return new DefaultFilter('post_id', $post_id);
    }

    public function getPostTitle(): string
    {
        return $this->post_title;
    }

    public function getPostType(): string
    {
        return $this->post_type;
    }

    protected function getDirFilter(): DefaultFilter
    {
        $get = $_REQUEST['d'] ?? null;
        if (null === $get && isset($_SESSION['media_manager_dir'])) {
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

    protected function getFileModeFilter(): DefaultFilter
    {
        if (!empty($_GET['file_mode'])) {
            $_SESSION['media_file_mode'] = 'grid' == $_GET['file_mode'] ? 'grid' : 'list';
        }
        $get = !empty($_SESSION['media_file_mode']) ? $_SESSION['media_file_mode'] : 'grid';

        return new DefaultFilter('file_mode', $get);
    }

    protected function getPluginIdFilter(): DefaultFilter
    {
        $get = isset($_REQUEST['plugin_id']) ? Html::sanitizeURL($_REQUEST['plugin_id']) : '';

        return new DefaultFilter('plugin_id', $get);
    }

    protected function getLinkTypeFilter(): DefaultFilter
    {
        $get = !empty($_REQUEST['link_type']) ? Html::escapeHTML($_REQUEST['link_type']) : null;

        return new DefaultFilter('link_type', $get);
    }

    protected function getPopupFilter(): DefaultFilter
    {
        $get = (int) !empty($_REQUEST['popup']);

        return new DefaultFilter('popup', $get);
    }

    protected function getMediaSelectFilter(): DefaultFilter
    {
        // 0 : none, 1 : single media, >1 : multiple media
        $get = !empty($_REQUEST['select']) ? (int) $_REQUEST['select'] : 0;

        return new DefaultFilter('select', $get);
    }
}
