<?php
/**
 * @package Dotclear
 *
 * @copyright Olivier Meunier & Association Dotclear
 * @copyright GPL-2.0-only
 * 
 * 
 * @defgroup Action List actions
 *
 * Classes related to list actions.
 *
 * 
 * @defgroup Admin Admin
 * 
 * Classes related to Admin process. (backend)
 * 
 * 
 * @defgroup Blog Blogs
 *
 * Classes related to blogs.
 * 
 * 
 * @defgroup Category categories
 *
 * Classes related to categories.
 * 
 * 
 * @defgroup Comment Comments
 *
 * Classes related to comments.
 * 
 * 
 * @defgroup Container Container
 *
 * Container used to serve fields
 * from a single record with the right type.
 * 
 * 
 * @defgroup Core Core
 *
 * Core classes that can be used by any process.
 * A process (Admin, Public, Install, Distrib, ...)
 * extends class Core and can be access
 * from \em dotclear()->
 * 
 * 
 * @defgroup Database Database
 *
 * Databases management classes.
 * 
 * 
 * @defgroup Deprecated Deprecated
 *
 * Mark and manage deprecated classes and methods.
 *
 * 
 * @defgroup Distrib Distrib
 * 
 * Classes related to Distrib process.
 * 
 * 
 * @defgroup Dotclear Dotclear
 * 
 * Dotclear is an open-source web publishing software 
 * published in 2003 by Olivier Meunier.
 * 
 * 
 * @defgroup Exception Exception
 *
 * Exception classes.
 * 
 * 
 * @defgroup File File
 *
 * Classes related to file and path manipulation.
 * 
 * 
 * @defgroup Filter List filters
 *
 * Classes related to list filters.
 *
 *
 * @defgroup  Function Functions
 *
 * Out-of-namespaces functions
 * to simplify calls from everywhere.
 * 
 * 
 * @defgroup Handler Admin page handler
 *
 * Classes related to admin page handling.
 * 
 * 
 * @defgroup Helper Helper
 *
 * Usefull reusable classes.
 * 
 * 
 * @defgroup Help Help
 *
 * Classes related to help.
 * 
 * 
 * @defgroup Html Html
 *
 * Casses related to HTML manipulation.
 *
 * 
 * @defgroup Install Install
 * 
 * Classes related to Install process.
 *
 * 
 * @defgroup Inventory Inventory
 * 
 * Classes that manage inventory list.
 * 
 * 
 * @defgroup Localisation Localisation
 *
 * Classes related to locales.
 * 
 * 
 * @defgroup Media Media
 *
 * Classes related to media.
 *
 * 
 * @defgroup Module Modules
 *
 * Extensions to Dotclear, like Themes or Plugins.
 * This set of Module classes provides usefull methods 
 * to interface extensions to Dotclear.
 * 
 * 
 * @defgroup Network Network
 *
 * Classes related to network feartures.
 *
 *
 * @defgroup Plugin Plugins
 *
 * Plugins specific type of Dotclear Modules.
 * 
 * 
 * @defgroup Post Posts
 *
 * Classes related to posts.
 * 
 * 
 * @defgroup Preference Preference
 *
 * Classes related to user preference.
 *
 * 
 * @defgroup Public Public
 * 
 * Classes related to Public process. (frontend)
 *
 * 
 * @defgroup Rest REST
 * 
 * Classes related to REST service/methods.
 * 
 * 
 * @defgroup Settings Blog settings
 *
 * Classes related to blogs settings.
 * 
 * 
 * @defgroup Stack Stack
 *
 * Classes using stack properties.
 * 
 * 
 * @defgroup Template Template
 *
 * Classes related to Template manipulation.
 *
 *
 * @defgroup Theme Themes
 *
 * Themes specific type of Dotclear Modules.
 * 
 * 
 * @defgroup Url Public URL
 *
 * Classes related to public URLs.
 * 
 * 
 * @defgroup User User
 *
 * Classes related to users.
 * 
 * 
 * @defgroup Xmlrpc XML-RPC
 *
 * Classes related to XML-RPC protocol.
 * 
 * 
 * @defgroup  AboutConfig Plugin "about:config"
 *
 * Manage every blog configuration directive.
 * 
 * 
 * @defgroup  Akismet Plugin "Akismet"
 *
 * Akismet interface for Dotclear.
 * 
 * 
 * @defgroup  Antispam Plugin "Antispam"
 *
 * Generic antispam plugin for Dotclear.
 * 
 * 
 * @defgroup  Attachments Plugin "Attachments"
 *
 * Manage post attachments.
 * 
 * 
 * @defgroup  Blogroll Plugin "Blogroll"
 *
 * Manage your blogroll.
 * 
 * 
 * @defgroup  Breadcrumb Plugin "Breadcrumb"
 * 
 * Breadcrumb for Dotclear.
 * 
 * @copyright Franck Paul
 * @copyright GPL-2.0-only
 * 
 * 
 * @defgroup  Buildtools Plugin "Buildtools"
 *
 * Internal build tools for dotclear team.
 * 
 * 
 * @defgroup  CKEditor Plugin "CKEditor"
 *
 * dotclear CKEditor integration.
 * 
 * 
 * @defgroup  FairTrackbacks Plugin "Fair Trackbacks"
 *
 * Trackback validity check.
 * 
 * 
 * @defgroup  Pages Plugin "Pages"
 *
 * Serve entries as simple web pages.
 * 
 * 
 * @defgroup  Pings Plugin "Pings"
 *
 * Ping services.
 * 
 * 
 * @defgroup  SimpleMenu Plugin "Simple menu"
 *
 * Simple menu for Dotclear.
 * 
 * 
 * @defgroup  Tags Plugin "Tags"
 *
 * Tags for posts.
 * 
 * 
 * @defgroup  ThemeEditor Plugin "themeEditor"
 *
 * Theme Editor.
 * 
 * 
 * @defgroup  UserPref Plugin "user:preferences"
 *
 * Manage every user preference directive.
 * 
 * 
 * @defgroup  Widgets Plugin "Widgets"
 *
 * Widgets for your blog sidebars.
 * 
 * 
 * @defgroup  Berlin Theme "Berlin"
 *
 * Dotclear 2.7+ default theme
 * 
 * 
 * @defgroup  Blowup Theme "Blowup"
 * 
 * Fully customizable theme.
 * 
 * 
 * @defgroup  BlueSilence Theme "BlueSilence"
 * 
 * Theme from old Dotclear version.
 * 
 * @copyright Marco / marcarea.com
 * @copyright GPL-2.0-only
 * 
 * 
 * @defgroup  CustomCSS Theme "Custom CSS"
 *
 * A CSS customizable theme.
 * 
 * 
 * @defgroup  Ductile Theme "Ductile"
 *
 * Mediaqueries compliant elegant theme.
 */