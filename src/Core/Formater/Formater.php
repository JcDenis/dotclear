<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

// Dotclear\Core\Formater\Formater

namespace Dotclear\Core\Formater;

use Closure;

/**
 * Text formater methods.
 *
 * @ingroup  Core Text
 */
class Formater
{
    /**
     * @var array<string,mixed> $formaters
     *                          formaters container
     */
    private $formaters = [];

    /**
     * Add editor formater.
     *
     * Adds a new text formater which will call the function <var>$callback</var> to
     * transform text. The function must be a valid callback and takes one
     * argument: the string to transform. It returns the transformed string.
     *
     * @param string               $editor   The editor identifier (LegacyEditor, dcCKEditor, ...)
     * @param string               $formater The formater name
     * @param array|Closure|string $callback The function to use, must be a valid and callable callback
     */
    public function addEditorFormater(string $editor, string $formater, string|array|Closure $callback): void
    {
        // Silently failed non callable function
        if (is_callable($callback)) {
            $this->formaters[$editor][$formater] = $callback;
        }
    }

    /**
     * Get the editors list.
     *
     * @return array<string, string> The editors
     */
    public function getEditors(): array
    {
        $editors = [];

        foreach (array_keys($this->formaters) as $editor) {
            if (null !== ($module = dotclear()->plugins()?->getModule($editor))) {
                $editors[$editor] = $module->name();
            }
        }

        return $editors;
    }

    /**
     * Get the formaters.
     *
     * if $editor is empty:
     * return all formaters sorted by actives editors
     *
     * if $editor is not empty
     * return formaters for an editor if editor is active
     * return empty() array if editor is not active.
     * It can happens when a user choose an editor and admin deactivate that editor later
     *
     * @param string $editor The editor identifier (LegacyEditor, dcCKEditor, ...)
     *
     * @return array<string, array> The formaters
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
     * Call editor formater. (format a string).
     *
     * If <b>$formater</b> is a valid formater, it returns <b>$str</b>
     * transformed using that formater.
     *
     * @param string $editor   The editor identifier (LegacyEditor, dcCKEditor, ...)
     * @param string $formater The formater name
     * @param string $str      The string to transform
     *
     * @return string The formated string
     */
    public function callEditorFormater(string $editor, string $formater, string $str): string
    {
        if (isset($this->formaters[$editor], $this->formaters[$editor][$formater])) {
            return call_user_func($this->formaters[$editor][$formater], $str);
        }

        // Fallback with another editor if possible
        foreach ($this->formaters as $editor => $formaters) {
            if (array_key_exists($formater, $formaters)) {
                return call_user_func($this->formaters[$editor][$formater], $str);
            }
        }

        return $str;
    }
}
