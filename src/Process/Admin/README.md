[![Dotclear](https://img.shields.io/badge/dotclear-namespace-blue.svg)](https://github.com/JcDenis/dotclear/tree/namespace)
[![License](https://img.shields.io/github/license/dotclear/dotclear)](https://github.com/JcDenis/dotclear/blob/namespace/LICENSE)

# Dotclear \ Admin \ Prepend

This is the starting place of the blog's administration Process.

## Usage

To access administration pages of your blog's plateform create a `index.php` file accessible for the web 
containing something like that :

```php
<?php
require __DIR__ '/../src/functions.php';
dotclear_run('admin');

```

The required path must point to the `dotclear/src/functions.php` file.
Then point your browser to the Url of your `index.php` file.

You must also set directive `admin_url` with the right value in the Dotclear's configuration file. 
(located on dotclear/src/config.php, see Dotclear\Core\Core help)
