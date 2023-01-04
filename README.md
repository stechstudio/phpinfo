# PHP info

This package will get the output from `phpinfo()` and provide you with:

1. Lookup methods for inspecting specific modules and configs
2. Collection-based data structure for iterating over and building your own custom output
3. A pretty, opinionated output page that replaces the default `phpinfo()` output

## Installation

```bash
composer require stechstudio/phpinfo
```

## Quickstart

If you want to display a pretty, mobile-friendly `phpinfo()` page, just call `render()` on the `Info` factory class:

```php
<?php
// Make sure this points to your composer autoload file, if you are using plain PHP.
// If you are in a framework context, you can probably remove this line as your
// framework likely handles it for you.
require __DIR__ . '/../vendor/autoload.php';

// This will capture your current phpinfo() and display a prettier page.
STS\Phpinfo\Info::render();
?>
```

### Interact with `phpinfo()` configuration

If you're looking to directly inspect and interact with the configuration, you need to first capture it:

```php
use STS\Phpinfo\Info;

$info = Info::capture();
```

If you have `phpinfo()` output that you've saved previously and want to load and parse:
```php
use STS\Phpinfo\Info;

// If you've saved the HTML output from phpinfo()
$info = Info::fromHtml($yourSavedHtmlOutput);

// If you've saved the CLI output from phpinfo()
$info = Info::fromText($yourSavedHtmlOutput);
```

From here you can query some base info, modules, and configs:
```php
// Your PHP version
$info->version(); // 8.2.0

// Check for the presence of a specific module. Name is case-insensitive.
$info->hasModule('redis'); // true

// Check to see if a specific configuration key is present. Name is case-insensitive.
$info->hasConfig('ICU version'); // true

// Retrieve the value for a specific configuration key. Name is case-insensitive. If there is both a local and master value, the local is returned as default.
$info->config('max_file_uploads'); // 5

// Pass in 'master' as a second parameter to retrieve the master value instead. Note that this will return null if there is no master value;
$info->config('max_file_uploads', 'master'); // 20
$info->config('BCMath support', 'master'); // null
```

## Iterating over data structure

You can access a data structure of [collections](https://laravel.com/docs/master/collections) to easily loop over your `phpinfo()` configuration. 

```php
// Loop over defined modules
foreach($info->modules() AS $module) {
    $module->name(); // session
    
    // Configs are grouped the same way phpinfo() groups them by table
    // Different groups have different table headers, different number of values
    foreach($modules->groups AS $group) {
        $group->headings(); // [Directive, Local Value, Master Value]
        
        foreach($group->configs() AS $config) {
            $config->name(); // session.gc_maxlifetime
            $config->localValue(); // 1440
            
            $config->hasMasterValue(); // True (will be false if there is only one value)
            $config->masterValue(); // 28800
        }
    }
}
```

You see that we have four levels to the data structure:

1. Base `info` containing `modules()`
2. Modules with `name()` method, and containing `groups()`
3. Groups containing `configs()` and optionally with `headings()`
4. Configs with `name()`, `value()/localValue()`, and optionally `masterValue()`

You can _also_ access configs directly from the Module and base Info levels:

```php
// This flattens the grouped 'session' configs down to a single collection
$info->module('session')->configs();

// This flattens ALL configs across all modules down to a single collection
$info->configs();
```

### Modules and Groups

We've already seen how to iterate over modules and groups. Sometimes you may want to look up a specific module and inspect it directly.

```php
// This lookup is case-insensitive. Will return null if no matching module is found.
$module = $info->module('zend opcache');

// Retrieve the name of the module as displayed in phpinfo(), which might have a different case.
$module->name(); // Zend OPcache

// Flatten all configs into one collection. You can then use any Laravel collection method.
$module->configs()->count(); // 59

// Retrieve a specific configuration from this module. This works exactly the same as the main `config()` method shown in the previous section.
$module->config('Max keys'); // 16229
$module->config('opcache.enable_file_override', 'master'); // Off

// Retrieve just the first group of configs, which is often the list of single-value configs
$group = $info->module('session')->groups()->first(); // Collection of Configs
```


Here is a super simple example to display modules and configuration:

```php
foreach ($info->modules() AS $module) {
    echo '<h2>' . $module->name() . '</h2>';

    echo '<ul>';
    foreach($module->configs() AS $config) {
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