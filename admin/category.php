<?php
/**
 * @deprecated since 2.27 Use name "admin.category" on dcCore::app()->adminurl methods
 *
 * @package Dotclear
 * @subpackage Backend
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
require_once implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'src', 'App.php']);

Dotclear\App::bootstrap('Backend', 'Category');
