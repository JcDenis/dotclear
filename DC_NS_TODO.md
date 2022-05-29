### ToDo List

- [ ] add methods type int
- [ ] move all admin action to Action (see post|blog action)
- [ ] repare and test Xmlrpc
- [ ] move all modules (themes,plugins) features to Modules
- [ ] check plural from class name, except double class (Blog|Blogs)
- [ ] convert amdin page action into Action
- [ ] remove or include to core a maximum of clearbricks class/methods
- [ ] update all scss files
- [ ] rework process (avoid abstract extends abstract extends abstract...)
- [ ] check extends class vs public method, ex: dotclear()->url->getPageNumber()
- [ ] use class for html form elements and static method for simple cases
- [ ] remove maximum php magic methods and/or add equivalent methods
- [ ] fix date on media_dt 
- [ ] fix public context and template
- [ ] use sql statement everywhere it is possible
- [ ] update build-tools and makefile
- [ ] complete module clone (.po, .js)
- [ ] reduce number of possible methods returned types
- [ ] replace all ArrayObject by a custom Stack class (and avoid ArrayObject magic methods)
- [ ] change signature of Combos behaviors (to Stack)
- [ ] use Stack instead of array and ArrayObject (ie for getBLogs())
- [ ] check and use behavior on (de)activate modules (some modules should erase cache etc...)
- [ ] rework Exceptions by type of exception, not by class hierarchy
- [ ] avoid logic switch parameters
- [ ] move GET, POST, REQUEST to mapper

### Idea

- Merge Distrib into Install
- Move build-tools features into Plugin BuildTools
- Use array for permissions
- Open posts,comments,blogs status

### Rules
Should be followed...one day...
- No ArrayObject, use tiny custom class
- No magic methods, use real methods
- No chaining methods, recall class each time
- No additionnal logic switch methods parameters, or only if it's the best way
- No mixed type return, use multiple methods to split returned types or return object
- Use method named arguments, even if there's only one argument
- Use word(s) for variables names, not initials
- Use final keyword on class every time it's possible

### Done

- Require PHP 8.1
- Convert to full PHP namespace
- Composer autoload compliant
- Composer packages compliant (not yet available, see testing feature at <https://github.com/JcDenis/dotclear-blog>)
- PSR4, PSR12 compliant
- Include used Clearbricks libraries
- Use configuration array instead of DC_xx constants
- Remove cursor and record magic method (and some other magics)
- Convert lots of static class into dynamic
- Split Core into sub parts (blogs, users, ...)
- Convert plugins and themes into modules (also store and iconset)
- Allow multiple path for themes (as for plugins)
- Allow per blog path for plugins and themes
- Use virtual URLs for all resources
- Use unique class for all datetime handling
- Remove GLOBALS (no more global $core nor $core passed as argument)
- Use singleton dotclear() instance accessible from every where
- Use doxygen for code documentation (not complete)
- ...
