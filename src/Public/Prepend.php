<?php
/**
 * @brief Dotclear public core prepend class
 *
 * @package Dotclear
 * @subpackage Public
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 */
declare(strict_types=1);

namespace Dotclear\Public;

use Dotclear\Core\Prepend as BasePrepend;

if (!defined('DOTCLEAR_ROOT_DIR')) {
    return;
}

class Prepend extends BasePrepend
{
    protected $process = 'Public';

    public function __construct()
    {
        parent::__construct();

        echo 'public: public/prepend.php : structure only ';
    }
}
