<?php
/**
 * @ingroup  Themes
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
if (!function_exists('dotclear')) {
    return;
}
dotclear()->help()->context('ductile', __DIR__ . '/help/help.html');
