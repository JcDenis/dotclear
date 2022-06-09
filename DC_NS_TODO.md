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
- [ ] rework Record::extend method and calls
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
- [ ] enhance GPC class (protection)
- [ ] fix Inventory display() method
- [ ] used named arguments on behaviors to be more strict
- [ ] unset unused variables
- [ ] enabled choice of response format for REST server. (JSON vs XML)

### Idea

- Merge Distrib into Install
- Move build-tools features into Plugin BuildTools

### Rules
Should be followed...one day...
- Document all class, class properties, class methods.
- Use UpperCamelCase for class names (and their folders)
- Use final keyword on class every time it's possible
- Use lowerCamelCase for methods names
- Use action verb for first part of mehtods names (ie: setName, parsePlop)
- Use single explicit word for named arguments.
- Use method named arguments when you call it.
- Use underscore_words for variables names
- Use word(s) for variables names, not initials.
- Use lowerCamelCase for behaviors names with :
- Use lowercase process name as first word for behaviors (admin|public|core|...)
- Use first upper case prefix Before|After as second word for behaviors names
- Use first upper case action as third word of behaviors names
- Use UpperCamelCase object of the behaviors as thier fourth (and so on) word
- Use named arguments for behaviors calls
- Use custom descriptor object instead of multidimensional array.
- No magic methods. Use real methods.
- No chaining methods. Recall class each time.
- No additionnal logic switch methods parameters. Split into multiple methods.
- No mixed type return. Split into multiple methods or use custom object
- No ArrayObject. Use tiny custom class.

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

### WIP

- New (posts,comments,blogs) Status handling
- New Permissions handling
