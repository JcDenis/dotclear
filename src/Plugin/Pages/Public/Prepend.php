<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pages\Public;

// Dotclear\Plugin\Pages\Public\Prepend
use Dotclear\App;
use Dotclear\Database\Param;
use Dotclear\Modules\ModulePrepend;
use Dotclear\Plugin\Pages\Common\PagesUrl;
use Dotclear\Plugin\Pages\Common\PagesWidgets;

/**
 * Public prepend for plugin Pages.
 *
 * @ingroup  Plugin Pages
 */
class Prepend extends ModulePrepend
{
    public function loadModule(): void
    {
        // Localized string we find in template
        __('Published on');
        __('This page\'s comments feed');

        // Add post type to queries
        App::core()->behavior('coreBeforeCountPosts')->add([$this, 'behaviorCoreBeforeXxxPosts']);
        App::core()->behavior('coreBeforeGetPosts')->add([$this, 'behaviorCoreBeforeXxxPosts']);

        $this->addTemplatePath();
        new PagesUrl();
        new PagesWidgets();
    }

    public function behaviorCoreBeforeXxxPosts(Param $param, $sql): void
    {
        if ('search' == App::core()->url()->getCurrentType()) {
            // Add page post type for searching (don't use default Param post_type() as it is 'post')
            if (null !== $param->get('post_type')) {
                if (!is_array($param->get('post_type'))) {
                    // Convert it in array
                    $param->set('post_type', [$param->get('post_type')]);
                }
                if (!in_array('page', $param->get('post_type'))) {
                    // Add page post type
                    $param->push('post_type', 'page');
                }
            } else {
                // Dont miss default post type (aka post)
                $param->set('post_type', ['post', 'page']);
            }
        }
    }
}
