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
- [ ] fix admin popup
- [ ] auto create settings namespace and prefs workspace
- [ ] check extends class vs public method, ex: dotclear()->url->getPageNumber()
- [ ] fix install in user's lang

### Idea

- Merge Distrib into Install
- Abstract class for admin page
- use Trait for instead of Utils::
