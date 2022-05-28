<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Process\Admin\Handler;

// Dotclear\Process\Admin\Handler\Xmlrpc
use Dotclear\App;
use Dotclear\Core\Xmlrpc\Xmlrpc as CoreXmlrpc;
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Network\Http;
use Dotclear\Process\Admin\Page\AbstractPage;

/**
 * Admin xmlrpc page.
 *
 * @ingroup  Admin Xmlrpc Handler
 */
class Xmlrpc extends AbstractPage
{
    protected function getPermissions(): string|bool
    {
        return true;
    }

    protected function getPagePrepend(): ?bool
    {
        $blog_id = GPC::get()->string('b');

        if (isset($_SERVER['PATH_INFO'])) {
            $blog_id = preg_replace('#^/#', '', trim($_SERVER['PATH_INFO']));
        }

        if (empty($blog_id)) {
            header('Content-Type: text/plain');
            Http::head(412);
            echo 'No blog ID given';

            return null;
        }

        // Avoid plugins warnings, set a default blog
        App::core()->setBlog($blog_id);

        // Start XML-RPC server
        $xmlrpc = new CoreXmlrpc($blog_id);
        $xmlrpc->serve();

        return null;
    }
}
