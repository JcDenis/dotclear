<?php

/**
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   AGPL-3.0
 */

use Dotclear\Core\Backend\Listing\Listing;
use Dotclear\Database\MetaRecord;

/**
 * @brief   The module backend listing aliases handler.
 * @ingroup dcProxyV2
 */
class adminGenericList extends Listing
{
    /**
     * Constructs a new instance.
     *
     * @param   dcCore                          $core       The deprecated core instance
     * @param   Dotclear\Database\MetaRecord    $rs         The record
     * @param   mixed                           $rs_count   The rs count
     */
    // @phpstan-ignore constructor.unusedParameter
    public function __construct(dcCore $core, MetaRecord $rs, $rs_count)
    {
        parent::__construct($rs, $rs_count);
    }
}
