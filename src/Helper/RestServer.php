<?php
/**
 * @class Dotclear\Core\Helper\RestServer
 * @brief Dotclear core rest server class
 *
 * @package Dotclear
 * @subpackage Core
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper;

use Dotclear\Helper\Html\XmlTag;

class RestServer
{
    /** @var    XmlTag  $rsp    XML response */
    protected $rsp;

    /** @var    array   $functions  Registered fucntions */
    protected $functions = [];

    /**
     * Constructs a new instance.
     */
    public function __construct()
    {
        $this->rsp = new XmlTag('rsp');
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
            return call_user_func($this->functions[$name], $get, $post);
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
            $this->rsp->insertAttr('status', 'failed');
            $this->rsp->insertNode(new XmlTag('message', 'No function given'));
            $this->getXML($encoding);

            return false;
        }

        if (!isset($this->functions[$_REQUEST['f']])) {
            $this->rsp->insertAttr('status', 'failed');
            $this->rsp->insertNode(new XmlTag('message', 'Function does not exist' . $_REQUEST['f']));
            $this->getXML($encoding);

            return false;
        }

        try {
            $res = $this->callFunction($_REQUEST['f'], $get, $post);
        } catch (\Exception $e) {
            $this->rsp->insertAttr('status', 'failed');
            $this->rsp->insertNode(new XmlTag('message', $e->getMessage()));
            $this->getXML($encoding);

            return false;
        }

        $this->rsp->insertAttr('status', 'ok');

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
        echo $this->rsp->toXML(true, $encoding);
    }
}
