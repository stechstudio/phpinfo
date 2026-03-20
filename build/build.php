<?php
/**
 * Build script that compiles build/go.php into a fully self-contained file
 * with CSS, JS, and source classes baked in.
 *
 * Run: php build/build.php
 */

$root = dirname(__DIR__);

$source = file_get_contents(__DIR__ . '/go.php');
$template = file_get_contents($root . '/resources/template.php');
$css = file_get_contents($root . '/dist/styles.css');
$js = file_get_contents($root . '/dist/app.js');

// Source files to inline (in dependency order)
$classFiles = [
    'src/Support/Str.php',
    'src/Support/Items.php',
    'src/Models/Config.php',
    'src/Models/Group.php',
    'src/Models/Module.php',
    'src/PhpInfo.php',
    'src/Parsers/Parser.php',
    'src/Parsers/TextParser.php',
];

// Convert each source file to a namespace block and concatenate
$inlined = '';
foreach ($classFiles as $file) {
    $inlined .= toNamespaceBlock(file_get_contents($root . '/' . $file)) . "\n";
}

// Add the items() helper in the global namespace
$inlined .= "namespace {\n";
$inlined .= "    if (!function_exists('items')) {\n";
$inlined .= "        function items(iterable \$items = []): \\STS\\Phpinfo\\Support\\Items {\n";
$inlined .= "            return new \\STS\\Phpinfo\\Support\\Items(\$items);\n";
$inlined .= "        }\n";
$inlined .= "    }\n";
$inlined .= "}\n\n";

// Strip opening <?php and the autoload require from go.php,
// then wrap the main script in a global namespace block.
$source = preg_replace('/^<\?php\s*/s', '', $source);
$source = str_replace(
    "require_once __DIR__ . '/../vendor/autoload.php';\n",
    '',
    $source
);

// Inline the template (exit PHP, paste template content, re-enter PHP)
$source = str_replace(
    "include __DIR__ . '/../resources/template.php';",
    "?>\n" . $template . "\n<?php",
    $source
);

// Build the final output: <?php + class blocks + main script in namespace {}
$output = "<?php\n" . $inlined . "namespace {\n\n" . $source . "\n}\n";

// Replace the include() calls with the actual file contents
$output = str_replace(
    '<?php include(__DIR__ . "/../dist/styles.css"); ?>',
    $css,
    $output
);

$output = str_replace(
    '<?php include(__DIR__ . "/../dist/app.js"); ?>',
    $js,
    $output
);

$out = $root . '/dist/go-standalone.php';
file_put_contents($out, $output);

echo "Built dist/go-standalone.php (" . number_format(strlen($output)) . " bytes)\n";

/**
 * Convert a PHP source file from "namespace X;" declaration syntax
 * to "namespace X { ... }" block syntax.
 */
function toNamespaceBlock(string $content): string
{
    // Strip opening <?php tag
    $content = preg_replace('/^<\?php\s*/s', '', $content);

    // Extract the namespace declaration
    if (preg_match('/^namespace\s+([^;]+);\s*/m', $content, $matches)) {
        $namespace = $matches[1];
        $content = preg_replace('/^namespace\s+[^;]+;\s*/m', '', $content);
        return "namespace {$namespace} {\n{$content}}\n";
    }

    // No namespace — wrap in global namespace block
    return "namespace {\n{$content}}\n";
}
