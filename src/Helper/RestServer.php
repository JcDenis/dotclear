<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Helper;

// Dotclear\Helper\RestServer
use Dotclear\Helper\GPC\GPC;
use Dotclear\Helper\Html\XmlTag;
use Exception;

/**
 * Rest server.
 *
 * @ingroup  Helper Rest
 */
class RestServer
{
    /**
     * @var XmlTag $rsp
     *             XML response
     */
    protected $rsp;

    /**
     * @var array<string,callable> $functions
     *                             Registered functions
     */
    protected $functions = [];

    /**
     * Constructs a new instance.
     */
    public function __construct()
    {
        $this->rsp = new XmlTag('rsp');
    }

    /**
     * Add Function.
     *
     * This adds a new function to the server. <var>$callback</var> should be
     * a valid PHP callback. Callback function takes two arguments: GET and
     * POST values.
     *
     * @param string   $name     Function name
     * @param callable $callback Callback function
     */
    public function addFunction(string $name, callable $callback): void
    {
        $this->functions[$name] = $callback;
    }

    /**
     * Call Function.
     *
     * This method calls callback named <var>$name</var>.
     *
     * @param string $name Function name
     * @param array  $get  GET values
     * @param array  $post POST values
     *
     * @return mixed
     */
    protected function callFunction(string $name, array $get, array $post)
    {
        if (isset($this->functions[$name])) {
            return call_user_func($this->functions[$name], $get, $post);
        }
    }

    /**
     * Main server.
     *
     * This method creates the main server.
     *
     * @param string $encoding Server charset
     */
    public function serve(string $encoding = 'UTF-8'): bool
    {
        if (!GPC::request()->isset('f')) {
            $this->rsp->insertAttr('status', 'failed');
            $this->rsp->insertNode(new XmlTag('message', 'No function given'));
            $this->getXML($encoding);

            return false;
        }

        if (!isset($this->functions[GPC::request()->string('f')])) {
            $this->rsp->insertAttr('status', 'failed');
            $this->rsp->insertNode(new XmlTag('message', 'Function does not exist' . GPC::request()->string('f')));
            $this->getXML($encoding);

            return false;
        }

        try {
            $res = $this->callFunction(GPC::request()->string('f'), GPC::get()->dump(), GPC::post()->dump());
        } catch (Exception $e) {
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
     * Get XML.
     *
     * This method send to ouput the xml response
     *
     * @param string $encoding Server charset
     */
    private function getXML(string $encoding = 'UTF-8'): void
    {
        header('Content-Type: text/xml; charset=' . $encoding);
        echo $this->rsp->toXML(true, $encoding);
    }

    /**
     * Dump functions stack.
     *
     * @return array<string,callable> The registered functions
     */
    public function dump(): array
    {
        return $this->functions;
    }
}
