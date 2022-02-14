<?php
/**
 * @class Dotclear\Admin\Page\Xmlrpc
 * @brief Dotclear admin xmlrpc page
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

use Dotclear\Network\Http;

if (!defined('DOTCLEAR_PROCESS') || DOTCLEAR_PROCESS != 'Admin') {
    return;
}

class Xmlrpc extends Page
{
    use \Dotclear\Core\Instance\TraitXmlrpc;

    protected function getPermissions(): string|null|false
    {
        return false;
    }

    protected function getPagePrepend(): ?bool
    {
        if (isset($_SERVER['PATH_INFO'])) {
            $blog_id = trim($_SERVER['PATH_INFO']);
            $blog_id = preg_replace('#^/#', '', $blog_id);
        } elseif (!empty($_GET['b'])) {
            $blog_id = $_GET['b'];
        }

        if (empty($blog_id)) {
            header('Content-Type: text/plain');
            Http::head(412);
            echo 'No blog ID given';

            return null;
        }

        # Avoid plugins warnings, set a default blog
        dotclear()->setBlog($blog_id);

        # Start XML-RPC server
        $this->xmlrpc($blog_id)->serve();

        return null;
    }
}
