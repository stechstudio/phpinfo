<?php
/**
 * Build script that compiles build/go.php into a fully self-contained file
 * with CSS, JS, and the shared text parser baked in.
 *
 * Run: php build/build.php
 */

$root = dirname(__DIR__);

$source = file_get_contents(__DIR__ . '/go.php');
$css = file_get_contents($root . '/dist/styles.css');
$js = file_get_contents($root . '/dist/app.js');

// Read the shared parser, stripping its opening <?php tag
$parser = file_get_contents($root . '/src/Parsers/parse_text.php');
$parser = preg_replace('/^<\?php\s*/s', '', $parser);

// Replace the include() calls with the actual file contents
$source = str_replace(
    "require_once __DIR__ . '/../src/Parsers/parse_text.php';",
    $parser,
    $source
);

$source = str_replace(
    '<?php include(__DIR__ . "/../dist/styles.css"); ?>',
    $css,
    $source
);

$source = str_replace(
    '<?php include(__DIR__ . "/../dist/app.js"); ?>',
    $js,
    $source
);

$out = $root . '/dist/go-standalone.php';
file_put_contents($out, $source);

echo "Built dist/go-standalone.php (" . number_format(strlen($source)) . " bytes)\n";
