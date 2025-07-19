<?php

/**
 * @file
 * @brief       The plugin dcProxyV1 classes constants
 * @ingroup     dcProxyV1
 *
 * @package     Dotclear
 *
 * @copyright   Olivier Meunier & Association Dotclear
 * @copyright   AGPL-3.0
 */

/**
 * @deprecated  since 2.28, use Dotclear::Plugin::blogroll::Blogroll instead
 */
class initBlogroll
{
    /**
     * Blogroll permission.
     *
     * @deprecated  since 2.28, use Dotclear::Plugin::blogroll::Blogroll instead
     *
     * @var        string   PERMISSION_BLOGROLL
     */
    public const PERMISSION_BLOGROLL = 'blogroll';

    /**
     * Links (blogroll) table name.
     *
     * @deprecated  since 2.28, use Dotclear::Plugin::blogroll::Blogroll instead
     *
     * @var        string   LINK_TABLE_NAME
     */
    public const LINK_TABLE_NAME = 'link';
}

/**
 * @deprecated since 2.25, use Dotclear::Plugin::blogroll::Blogroll instead
 */
class dcLinks extends initBlogroll
{
}

/**
 * @deprecated  since 2.28, use Dotclear::Plugin::antispam::Antispam instead
 */
class initAntispam
{
    /**
     * Spam rules table name.
     *
     * @deprecated  since 2.28, use Dotclear::Plugin::antispam::Antispam instead
     *
     * @var     string  SPAMRULE_TABLE_NAME
     */
    public const SPAMRULE_TABLE_NAME = 'spamrule';
}

/**
 * @deprecated  since 2.28, use Dotclear::Plugin::pages::Pages instead
 */
class initPages
{
    /**
     * Pages permission.
     *
     * @deprecated  since 2.28, use Dotclear::Plugin::pages::Pages instead
     *
     * @var     string  PERMISSION_PAGES
     */
    public const PERMISSION_PAGES = 'pages';
}

/**
 * @deprecated since 2.25, use Dotclear::Plugin::pages::Pages instead
 */
class dcPages extends initPages
{
}
