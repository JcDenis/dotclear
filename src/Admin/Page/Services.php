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

use Dotclear\Core\Core;

use Dotclear\Admin\Page;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Services extends Page
{
    public function __construct(Core $core)
    {
        $n = 'Dotclear\\Admin\\RestMethods';
        $f = [
            'getPostsCount',
            'getCommentsCount',
            'checkNewsUpdate',
#            'checkCoreUpdate',
#            'checkStoreUpdate',
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

        foreach($f as $m) {
            $core->rest->addFunction($m, [$n, $m]);
        }

        $core->rest->serve();
        exit(1);
    }
}
