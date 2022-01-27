<?php
/**
 * @class Dotclear\Admin\Page\Services
 * @brief Dotclear admin rest service page
 *
 * @package Dotclear
 * @subpackage Admin
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Admin\Page;

use Dotclear\Admin\Page;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Services extends Page
{
    private $rest_default_class   = 'Dotclear\\Admin\\RestMethods';
    private $rest_default_methods = [
        'getPostsCount',
        'getCommentsCount',
        'checkNewsUpdate',
#        'checkCoreUpdate',
#        'checkStoreUpdate',
        'getPostById',
        'getCommentById',
        'quickPost',
        'validatePostMarkup',
        'getZipMediaContent',
        'getMeta',
        'delMeta',
        'setPostMeta',
        'searchMeta',
        'setSectionFold',
        'getModuleById',
        'setDashboardPositions',
        'setListsOptions',
    ];

    protected function getPermissions(): string|null|false
    {
        return false;
    }

    protected function getPagePrepend(): ?bool
    {
        foreach($this->rest_default_methods as $method) {
            $this->core->rest->addFunction($method, [$this->rest_default_class, $method]);
        }
        $this->core->rest->serve();

        return null;
    }
}
