### ToDo List

- [ ] add methods type int
- [ ] move all admin action to Action (see post|blog action)
- [ ] repare and test Xmlrpc
- [ ] move all modules (themes,plugins) features to Modules
- [ ] check plural from class name, except double class (Blog|Blogs)
- [ ] fix use of path::real to use strict mode only when it is needed (php strict error on type)
- [ ] convert amdin page action into Action
- [ ] remove or include to core a maximum of clearbricks class/methods
- [ ] change signature of Combos behaviors (to ArrayObject)
- [ ] update all scss files
- [ ] rework process (avoid abstract extends abstract extends abstract...)
- [ ] use array instead of constant for all DOTLCEAR_xxx stuff
- [ ] check extends class vs public method, ex: dotclear()->url->getPageNumber()
- [ ] use class for html form elements and static method for simple cases
- [ ] remove maximum php magic methods and/or add equivalent methods
- [ ] rework Dt class to use php DateTime class
- [ ] fix public context and template
- [ ] use sql statement everywhere it is possible
- [ ] move root functions into dotclear namespace
- [ ] move config outside src path

### Idea

- Merge Distrib into Install
- Use container instead of array and ArrayObject (ie for getBLogs())

### Done

- Require PHP 8.1
- Convert to full PHP namespace
- Composer compliant (not tested)
- PSR4, PSR12 compliant
- Include used Clearbricks libraries
- Use configuration array instead of DC_xx constants
- Remove cursor and record magic method (and some other magics)
- Convert lots of static class into dynamic
- Split Core into sub parts (blogs, users, ...)
- Convert plugins and themes into modules (also store and iconset)
- Allow multiple path for themes (as for plugins)
- Allow per blog path for plugins and themes
- Use XML file for modules definition
- Use virtual URLs for all resources
- Remove GLOBALS (no more global $core;)
- Use singleton dotclear() instance accessible from every where
- Use doxygen for code documentation (not complete)
- ...
