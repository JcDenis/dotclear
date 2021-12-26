<?php
/**
 * @class Dotclear\Core\RestServer
 * @brief Dotclear core rest server class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core;

use Dotclear\Exception;

use Dotclear\Core\Core;

use Dotclear\Html\XmlTag;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class RestServer
{
    /** @var Core       Core instance */
    public $core;

    /**
     * Constructs a new instance.
     *
     * @param Core  $core   The core
     */
    public function __construct(Core $core)
    {
        $this->rsp = new XmlTag('rsp');
        $this->core = $core;
    }

    /**
     * Add Function
     *
     * This adds a new function to the server. <var>$callback</var> should be
     * a valid PHP callback. Callback function takes two arguments: GET and
     * POST values.
     *
     * @param string    $name        Function name
     * @param callable  $callback        Callback function
     */
    public function addFunction(string $name, $callback): void
    {
        if (is_callable($callback)) {
            $this->functions[$name] = $callback;
        }
    }

    /**
     * Call Function
     *
     * This method calls callback named <var>$name</var>.
     *
     * @param string    $name        Function name
     * @param array        $get            GET values
     * @param array        $post        POST values
     * @return mixed
     */
    protected function callFunction(string $name, array $get, array $post)
    {
        if (isset($this->functions[$name])) {
            return call_user_func($this->functions[$name], $this->core, $get, $post);
        }
    }

    /**
     * Main server
     *
     * This method creates the main server.
     *
     * @param string    $encoding        Server charset
     *
     * @return bool
     */
    public function serve(string $encoding = 'UTF-8'): bool
    {
        $get  = $_GET ?: [];
        $post = $_POST ?: [];

        if (!isset($_REQUEST['f'])) {
            $this->rsp->status = 'failed';
            $this->rsp->message('No function given');
            $this->getXML($encoding);

            return false;
        }

        if (!isset($this->functions[$_REQUEST['f']])) {
            $this->rsp->status = 'failed';
            $this->rsp->message('Function does not exist' . $_REQUEST['f']);
            $this->getXML($encoding);

            return false;
        }

        try {
            $res = $this->callFunction($_REQUEST['f'], $get, $post);
        } catch (Exception $e) {
            $this->rsp->status = 'failed';
            $this->rsp->message($e->getMessage());
            $this->getXML($encoding);

            return false;
        }

        $this->rsp->status = 'ok';

        $this->rsp->insertNode($res);

        $this->getXML($encoding);

        return true;
    }

    /**
     * Get XML
     *
     * This method send to ouput the xml response
     *
     * @param string    $encoding        Server charset
     */
    private function getXML(string $encoding = 'UTF-8'): void
    {
        header('Content-Type: text/xml; charset=' . $encoding);
        echo $this->rsp->toXML(1, $encoding);
    }
}
