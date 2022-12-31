# PHP info

This package will get the output from `phpinfo()` and provide it to you as a useful data structure. You can query for configuration settings, or loop over to generate your own custom output.

## Installation

```bash
composer require stechstudio/phpinfo
```

## Usage

To capture your current `phpinfo()` information and get started:

```php
use STS\Phpinfo\Info;

$info = Info::capture();
```

### Iterating

You can easily loop over your `phpinfo()` configuration. 

```php
// Loop over defined modules
foreach($info->modules() AS $module) {
    $module->name(); // session
    
    // Now loop over module configs
    foreach($module->configs() AS $config) {
        $config->name(); // session.auto_start
        $config->localValue(); // Off
        $config->masterValue(); // Off
    }
}

// Want a full list of all configs from all modules?
foreach($info->configs() AS $config) { ... }
```

### Looking up specific information

There are several methods to help with finding specific information such as modules and configuration values.

```php
// Your PHP version
$info->version(); // 8.1.1

// Check if a module is present. Name is case-insensitive.
$info->hasModule('phar'); // true

// Check if a specific configuration key is present. Name is case-insensitive.
$info->hasConfig('ICU version'); // true

// Retrieve the value for a specific configuration key. Name is case-insensitive. If there is both a local and master value, the local is returned as default.
$info->config('max_file_uploads'); // 5

// Pass in 'master' as a second parameter to retrieve the master value instead. Note that this will return null if there is no master value;
$info->config('max_file_uploads', 'master'); // 20
$info->config('BCMath support', 'master'); // null
```

### Modules

You can look up a specific module if you want to interact with it directly.

The general information at the top of `phpinfo()` is stored as the "General" module.

```php
// This lookup is case-insensitive. Will return null if no matching module is found.
$module = $info->module('zend opcache');

// Retrieve the name of the module as displayed in phpinfo(), which might have a different case.
$module->name(); // Zend OPcache

// Retrieve a specific configuration from this module. This works exactly the same as the main `config()` method shown in the previous section.
$module->config('Max keys'); // 16229
$module->config('opcache.enable_file_override', 'master'); // Off
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