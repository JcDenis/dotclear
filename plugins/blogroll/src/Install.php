<?php
/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   AGPL-3.0
 */
declare(strict_types=1);

namespace Dotclear\Plugin\blogroll;

use Dotclear\App;
use Dotclear\Core\Process;
use Dotclear\Database\Structure;

/**
 * @brief   The module install process.
 * @ingroup blogroll
 */
class Install extends Process
{
    public static function init(): bool
    {
        return self::status(My::checkContext(My::INSTALL));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        $schema = new Structure(App::con(), App::con()->prefix());

        $schema->{Blogroll::LINK_TABLE_NAME}
            ->link_id('bigint', 0, false)
            ->blog_id('varchar', 32, false)
            ->link_href('varchar', 255, false)
            ->link_title('varchar', 255, false)
            ->link_desc('varchar', 255, true)
            ->link_lang('varchar', 5, true)
            ->link_xfn('varchar', 255, true)
            ->link_position('integer', 0, false, 0)

            ->primary('pk_link', 'link_id')
            ->index('idx_link_blog_id', 'btree', 'blog_id')
            ->reference('fk_link_blog', 'blog_id', 'blog', 'blog_id', 'cascade', 'cascade')
        ;

        (new Structure(App::con(), App::con()->prefix()))->synchronize($schema);

        return true;
    }
}
