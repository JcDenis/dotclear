<?php
/**
 * @class Dotclear\Plugin\Pages\Public\Prepend
 * @brief Dotclear Plugins class
 *
 * @package Dotclear
 * @subpackage PluginPages
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Plugin\Pages\Public;

use Dotclear\Module\AbstractPrepend;
use Dotclear\Module\TraitPrependPublic;
use Dotclear\Plugin\Pages\Common\PagesUrl;
use Dotclear\Plugin\Pages\Common\PagesWidgets;

class Prepend extends AbstractPrepend
{
    use TraitPrependPublic;

    public function loadModule(): void
    {
        # Localized string we find in template
        __('Published on');
        __('This page\'s comments feed');

        # Add post type to queries
        dotclear()->behavior()->add('coreBlogBeforeGetPosts', function ($params) {
            if (dotclear()->url()->type == 'search') {
                // Add page post type for searching
                if (isset($params['post_type'])) {
                    if (!is_array($params['post_type'])) {
                        // Convert it in array
                        $params['post_type'] = [$params['post_type']];
                    }
                    if (!in_array('page', $params['post_type'])) {
                        // Add page post type
                        $params['post_type'][] = 'page';
                    }
                } else {
                    // Dont miss default post type (aka post)
                    $params['post_type'] = ['post', 'page'];
                }
            }
        });

        $this->addTemplatePath();
        new PagesUrl();
        new PagesWidgets();
    }
}
