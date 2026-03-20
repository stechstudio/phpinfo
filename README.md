# Pretty PHP Info

[![Latest Version on Packagist](https://img.shields.io/packagist/v/stechstudio/phpinfo.svg?style=flat-square)](https://packagist.org/packages/stechstudio/phpinfo)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Tests](https://img.shields.io/github/actions/workflow/status/stechstudio/phpinfo/tests.yml?branch=main&style=flat-square&label=tests)](https://github.com/stechstudio/phpinfo/actions/workflows/tests.yml)

A beautiful, searchable replacement for `phpinfo()`. Query PHP configuration programmatically or browse it in a modern UI with dark mode, instant search, and click-to-copy.

## Requirements

- PHP 8.3+
- `ext-dom`

## Installation

```bash
composer require stechstudio/phpinfo
```

## Quickstart

The simplest way to use this package is the global `prettyphpinfo()` function — a drop-in replacement for `phpinfo()`:

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

prettyphpinfo();
```

That's it. You'll get a pretty, searchable, dark-mode-ready page instead of the default `phpinfo()` output.

<img width="1440" height="922" alt="phpinfo-screenshot" src="https://github.com/user-attachments/assets/c49c866a-4b20-4a49-a3ea-0185365a161e" />


Just like the native `phpinfo()`, you can pass `INFO_*` constants to control which sections are displayed:

```php
// Only show modules (excludes environment variables — useful for security)
prettyphpinfo(INFO_MODULES);

// Only general information
prettyphpinfo(INFO_GENERAL);

// Combine flags
prettyphpinfo(INFO_GENERAL | INFO_MODULES);
```

You can also use the class-based API directly:

```php
STS\Phpinfo\Info::render();
```

### Interact with `phpinfo()` configuration

If you're looking to directly inspect and interact with the configuration, capture it first:

```php
use STS\Phpinfo\Info;

$info = Info::capture();

// Or capture a subset
$info = Info::capture(INFO_MODULES);
```

If you have `phpinfo()` output that you've saved previously and want to load and parse:
```php
use STS\Phpinfo\Info;

// If you've saved the HTML output from phpinfo()
$info = Info::fromHtml($yourSavedHtmlOutput);

// If you've saved the CLI output from phpinfo()
$info = Info::fromText($yourSavedTextOutput);

// Or if you don't know the format, let the package detect it
$info = Info::detect($yourSavedOutput);
```

From here you can query base info, modules, and configs:
```php
// Your PHP version
$info->version(); // "8.5.4"

// Check for the presence of a specific module. Name is case-insensitive.
$info->hasModule('redis'); // true

// Check to see if a specific configuration key is present.
$info->hasConfig('ICU version'); // true

// Retrieve the value for a specific configuration key. If there is both
// a local and master value, the local is returned by default.
$info->config('max_file_uploads'); // "20"

// Pass 'master' to get the php.ini default instead of the effective local value.
$info->config('max_file_uploads', 'master'); // "100"
$info->config('BCMath support', 'master'); // null

// Convenience methods for common lookups
$info->os(); // "Linux"
$info->hostname(); // "my-server"
```

## Iterating over data structure

You can iterate over the full data structure to loop over your `phpinfo()` configuration. All lists (`modules()`, `groups()`, `configs()`) return iterable `Items` objects with `filter()`, `map()`, `first()`, `each()`, `count()`, and more.

```php
// Loop over defined modules
foreach($info->modules() as $module) {
    $module->name(); // "session"
    
    // Configs are grouped the same way phpinfo() groups them by table
    foreach($module->groups() as $group) {
        $group->headings(); // ["Directive", "Local Value", "Master Value"]
        
        foreach($group->configs() as $config) {
            $config->name(); // "session.gc_maxlifetime"
            $config->localValue(); // "1440"
            
            $config->hasMasterValue(); // true
            $config->masterValue(); // "28800"
        }
    }
}
```

The data structure has four levels:

1. `PhpInfo` containing `modules()`
2. Modules with `name()`, containing `groups()`
3. Groups containing `configs()` and optionally `headings()`
4. Configs with `name()`, `value()`/`localValue()`, and optionally `masterValue()`

You can also access configs directly from the Module and PhpInfo levels:

```php
// Flatten grouped configs for a single module
$info->module('session')->configs();

// Flatten ALL configs across all modules
$info->configs();
```

### Modules and Groups

Look up a specific module and inspect it directly:

```php
// Case-insensitive lookup. Returns null if not found.
$module = $info->module('zend opcache');

// Retrieve the name as displayed in phpinfo()
$module->name(); // "Zend OPcache"

// Flatten all configs into one collection
$module->configs()->count(); // 59

// Query a specific config from this module
$module->config('Max keys'); // "16229"
$module->config('opcache.enable_file_override', 'master'); // "Off"

// Access individual groups
$group = $info->module('session')->groups()->first();
```

### Simple example

```php
foreach ($info->modules() as $module) {
    echo '<h2>' . $module->name() . '</h2>';

    echo '<ul>';
    foreach($module->configs() as $config) {
        echo '<li>';
        echo $config->name() . ': ' . $config->value();
        if($config->hasMasterValue()) {
            echo ' (master: ' . $config->masterValue() . ')';
        }
        echo '</li>';
    }
    echo '</ul>';
}
```
