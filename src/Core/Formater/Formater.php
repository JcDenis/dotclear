<?php
/**
 * @class Dotclear\Core\Formater\Formater
 * @brief Dotclear core Formater class
 *
 * @package Dotclear
 * @subpackage Instance
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Core\Formater;

use Closure;

if (!defined('DOTCLEAR_PROCESS')) {
    return;
}

class Formater
{
    /** @var array              formaters container */
    private $formaters  = [];

    /**
     * Add editor formater
     *
     * Adds a new text formater which will call the function <var>$callback</var> to
     * transform text. The function must be a valid callback and takes one
     * argument: the string to transform. It returns the transformed string.
     *
     * @param   string                  $editor     The editor identifier (LegacyEditor, dcCKEditor, ...)
     * @param   string                  $formater   The formater name
     * @param   string|array|Closure    $callback   The function to use, must be a valid and callable callback
     */
    public function addEditorFormater(string $editor, string $formater, string|array|Closure $callback): void
    {
        # Silently failed non callable function
        if (is_callable($callback)) {
            $this->formaters[$editor][$formater] = $callback;
        }
    }

    /**
     * Gets the editors list.
     *
     * @return  array   The editors.
     */
    public function getEditors(): array
    {
        $editors = [];

        foreach (array_keys($this->formaters) as $editor) {
            if (null !== ($module = dotclear()->plugins->getModule($editor))) {
                $editors[$editor] = $module->name();
            }
        }

        return $editors;
    }

    /**
     * Gets the formaters.
     *
     * if @param editor is empty:
     * return all formaters sorted by actives editors
     *
     * if @param editor is not empty
     * return formaters for an editor if editor is active
     * return empty() array if editor is not active.
     * It can happens when a user choose an editor and admin deactivate that editor later
     *
     * @param   string  $editor     The editor identifier (LegacyEditor, dcCKEditor, ...)
     *
     * @return  array   The formaters.
     */
    public function getFormaters(string $editor = ''): array
    {
        $formaters_list = [];

        if (!empty($editor)) {
            if (isset($this->formaters[$editor])) {
                $formaters_list = array_keys($this->formaters[$editor]);
            }
        } else {
            foreach ($this->formaters as $editor => $formaters) {
                $formaters_list[$editor] = array_keys($formaters);
            }
        }

        return $formaters_list;
    }

    /**
     * Call editor formater. (format a string)
     *
     * If <var>$formater</var> is a valid formater, it returns <var>$str</var>
     * transformed using that formater.
     *
     * @param   string  $editor     The editor identifier (LegacyEditor, dcCKEditor, ...)
     * @param   string  $formater   The formater name
     * @param   string  $str        The string to transform
     *
     * @return  string  The formated string
     */
    public function callEditorFormater(string $editor, string $formater, string $str): string
    {
        if (isset($this->formaters[$editor]) && isset($this->formaters[$editor][$formater])) {
            return call_user_func($this->formaters[$editor][$formater], $str);
        }

        // Fallback with another editor if possible
        foreach ($this->formaters as $editor => $formaters) {
            if (array_key_exists($name, $formaters)) {
                return call_user_func($this->formaters[$editor][$name], $str);
            }
        }

        return $str;
    }
}
