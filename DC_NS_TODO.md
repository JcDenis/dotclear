### ToDo List

- [ ] update all Form:: to Forms:: etc...
- [ ] add methods type int
- [ ] move all admin action to Action (see post|blog action)
- [ ] repare and test Xmlrpc
- [ ] remove all hard coded Antispam feature from core (by adding behavior and somenthing else)
- [ ] move all modules (themes,plugins) features to Modules
- [ ] check plural from class name, except double class (Blog|Blogs)
- [ ] fix use of path::real to use strict mode only when it is needed (php strict error on type)
- [ ] convert amdin page action into Action
- [ ] remove or include to core a maximum of clearbricks class/methods
- [ ] change signature of Combos behaviors (to ArrayObject)
- [ ] update all scss files
- [ ] rework process (avoid abstract extends abstract extends abstract...)

### Idea

- Merge Distrib into Install
- Autoloader for plugins and themes
- Abstract class for admin page
- use Trait for instead of Utils::
- use "dotclear()" instead of "dcCore()"
