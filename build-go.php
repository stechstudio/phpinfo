<?php
/**
 * Build script that compiles dist/go.php into a fully self-contained file
 * with CSS and JS baked in. Output goes to dist/go-standalone.php.
 *
 * Run: php build-go.php
 */

$source = file_get_contents(__DIR__ . '/dist/go.php');
$css = file_get_contents(__DIR__ . '/dist/styles.css');
$js = file_get_contents(__DIR__ . '/dist/app.js');

// Replace the include() calls with the actual file contents
$source = str_replace(
    '<?php include(__DIR__ . "/styles.css"); ?>',
    $css,
    $source
);

$source = str_replace(
    '<?php include(__DIR__ . "/app.js"); ?>',
    $js,
    $source
);

file_put_contents(__DIR__ . '/dist/go-standalone.php', $source);

echo "Built dist/go-standalone.php (" . number_format(strlen($source)) . " bytes)\n";
