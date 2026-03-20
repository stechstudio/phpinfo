<?php
/**
 * Plain-PHP text parser for phpinfo() CLI output.
 *
 * This file is the single source of truth for text parsing. It is used by:
 *   - The package's TextParser class (via require)
 *   - The standalone script dist/go-standalone.php (inlined by build-go.php)
 *
 * No classes, no namespaces, no dependencies — just functions returning arrays.
 */

function pp_slug(string $text): string {
    return strtolower(trim(preg_replace('/\W+/', '_', $text), '_'));
}

function pp_parse(string $raw): array {
    $lines = explode("\n", str_replace("\r\n", "\n", $raw));
    $i = 0;
    $len = count($lines);

    // Helpers
    $current = function() use (&$lines, &$i, $len): ?string {
        return $i < $len ? $lines[$i] : null;
    };
    $advance = function() use (&$i, $len, &$lines): void {
        do { $i++; } while ($i < $len && ($lines[$i] === '' || $lines[$i] === 'Configuration'));
    };
    $isDivider = function() use ($current): bool {
        return $current() !== null && str_contains($current(), '_______________________________________________________________________');
    };
    $hasItems = function() use ($current): bool {
        return $current() !== null && str_contains($current(), ' => ');
    };
    $items = function() use ($current): array {
        return explode(' => ', $current() ?? '');
    };

    // Skip "phpinfo()"
    $advance();

    // PHP Version
    $version = '';
    if ($hasItems() && ($items()[0] ?? '') === 'PHP Version') {
        $parts = $items();
        $version = $parts[1] ?? '';
        $advance();
    }

    $modules = [];

    // General module
    $general = pp_parseModule('General', $lines, $i, $len);
    if (!empty($general['groups'])) {
        $modules[] = $general;
    }

    // Skip divider
    if ($i < $len && $isDivider()) {
        $advance();
    }

    // Module loop
    while ($i < $len) {
        $line = $current();
        if ($line === null) break;
        if ($isDivider()) { $advance(); continue; }
        if ($line === 'PHP Credits' || $line === 'PHP License') break;

        // Module name detection: no " => ", next line is blank, short text
        if (!$hasItems() && strlen($line) < 80 && ($i + 1 >= $len || $lines[$i + 1] === '')) {
            $moduleName = $line;
            $advance();
            $modules[] = pp_parseModule($moduleName, $lines, $i, $len);
        } else {
            break;
        }
    }

    // Credits and License
    pp_parseCredits($modules, $lines, $i, $len);
    pp_parseLicense($modules, $lines, $i, $len);

    // Filter out empty modules (e.g. "Module Name" under Additional Modules)
    $modules = array_values(array_filter($modules, fn($m) => !empty($m['groups'])));

    return ['version' => $version, 'modules' => $modules];
}

function pp_parseModule(string $name, array &$lines, int &$i, int $len): array {
    $groups = [];

    while ($i < $len) {
        $group = pp_parseGroup($lines, $i, $len);
        if ($group === null) break;
        $groups[] = $group;
    }

    return [
        'key' => 'module_' . pp_slug($name),
        'name' => $name,
        'groups' => $groups,
    ];
}

function pp_parseGroup(array &$lines, int &$i, int $len): ?array {
    if ($i >= $len) return null;
    $line = $lines[$i] ?? null;
    if ($line === null) return null;

    // Stop at dividers or blank-followed-by-short (module names)
    if (str_contains($line, '_______________________________________________________________________')) return null;
    if ($line === '') { $i++; return pp_parseGroup($lines, $i, $len); }

    // Check if this looks like a module name (stop parsing groups)
    if (!str_contains($line, ' => ') && strlen($line) < 80 && ($i + 1 >= $len || $lines[$i + 1] === '')) {
        return null;
    }

    $groupName = null;
    $headings = [];
    $shortHeadings = [];
    $configs = [];
    $note = null;

    // Group title: no " => ", next line is NOT blank, short text, not a heading keyword
    if (!str_contains($line, ' => ') && strlen($line) < 80
        && ($i + 1 < $len && $lines[$i + 1] !== '')
        && !in_array($line, ['Directive', 'Variable', 'Contribution', 'Module'])) {

        // But also check it's not a module name
        if (!str_contains($line, '                     ') && ($i + 1 < $len && str_contains($lines[$i + 1], ' => ') || in_array($lines[$i + 1] ?? '', ['Directive', 'Variable']))) {
            $groupName = $line;
            do { $i++; } while ($i < $len && $lines[$i] === '');
            $line = $lines[$i] ?? null;
            if ($line === null) return null;
        }
    }

    // Table heading
    $parts = explode(' => ', $line);
    if (in_array($parts[0], ['Directive', 'Variable', 'Contribution', 'Module'])) {
        $headings = $parts;
        $shortHeadings = array_map(fn($h) => trim(str_replace('Value', '', $h)), $parts);
        do { $i++; } while ($i < $len && $lines[$i] === '');
        $line = $lines[$i] ?? null;
    }

    // Determine expected column count
    $expectedCols = count($headings) ?: null;

    // Parse config rows
    while ($i < $len) {
        $line = $lines[$i];
        if ($line === '' || str_contains($line, '_______________________________________________________________________')) break;

        if (!str_contains($line, ' => ')) {
            // Could be a note or end of group
            if (strlen($line) > 50) {
                // Note
                $noteLines = [];
                while ($i < $len && $lines[$i] !== '' && !str_contains($lines[$i], '_____')) {
                    $noteLines[] = $lines[$i];
                    $i++;
                }
                $note = trim(implode("\n", $noteLines));
                break;
            }
            break;
        }

        $parts = explode(' => ', $line);
        $configName = $parts[0];
        $localValue = $parts[1] ?? null;
        $masterValue = null;
        $hasMaster = false;

        if ($expectedCols === 3 && count($parts) >= 3) {
            $masterValue = $parts[2];
            $hasMaster = true;
        } elseif ($expectedCols === null && count($parts) >= 3) {
            $masterValue = $parts[2];
            $hasMaster = true;
        }

        // Multi-line values (comma-continued)
        while ($localValue !== null && str_ends_with($localValue, ',') && $i + 1 < $len) {
            $i++;
            $localValue .= "\n" . $lines[$i];
        }

        // Multi-line Array dumps (e.g. $_SERVER['argv'] => Array\n(\n...\n))
        if ($localValue !== null && $localValue === 'Array') {
            $i++;
            while ($i < $len && trim($lines[$i]) !== ')') {
                $localValue .= "\n" . $lines[$i];
                $i++;
            }
            if ($i < $len) {
                $localValue .= "\n" . $lines[$i];
            }
        }

        $configs[] = [
            'key' => $configName === 'Names'
                ? 'config_names_' . md5($localValue ?? '')
                : 'config_' . pp_slug($configName),
            'name' => $configName,
            'hasMasterValue' => $hasMaster,
            'localValue' => ($localValue === 'no value') ? null : $localValue,
            'masterValue' => $hasMaster ? (($masterValue === 'no value') ? null : $masterValue) : null,
        ];

        $i++;
    }

    if (empty($configs) && $note === null && $groupName === null) return null;

    return [
        'key' => $groupName ? 'group_' . pp_slug($groupName) : 'group_' . md5(implode(',', array_column($configs, 'name'))),
        'name' => $groupName,
        'headings' => $headings,
        'shortHeadings' => $shortHeadings,
        'configs' => $configs,
        'note' => $note,
    ];
}

function pp_parseCredits(array &$modules, array &$lines, int &$i, int $len): void {
    if ($i >= $len || $lines[$i] !== 'PHP Credits') return;

    $i++; // skip "PHP Credits"
    while ($i < $len && $lines[$i] === '') $i++;

    $groups = [];

    while ($i < $len) {
        $line = $lines[$i];
        if ($line === '' && ($i + 1 >= $len || $lines[$i + 1] === 'PHP License' || str_contains($lines[$i + 1] ?? '', '____'))) break;
        if ($line === 'PHP License' || str_contains($line, '_______________________________________________________________________')) break;

        // Centered title (padded with spaces)
        if (str_contains($line, '                     ')) {
            $groupName = trim($line);
            $i++;
            while ($i < $len && $lines[$i] === '') $i++;

            // Table heading?
            $headings = [];
            $shortHeadings = [];
            $firstWord = explode(' => ', $lines[$i] ?? '')[0] ?? '';
            if (in_array($firstWord, ['Contribution', 'Module', 'Authors'])) {
                $headings = explode(' => ', $lines[$i]);
                $shortHeadings = array_map(fn($h) => trim(str_replace('Value', '', $h)), $headings);
                $i++;
                while ($i < $len && $lines[$i] === '') $i++;
            }

            // Config rows
            $configs = [];
            while ($i < $len && $lines[$i] !== '' && !str_contains($lines[$i], '______')) {
                if (str_contains($lines[$i], ' => ')) {
                    $parts = explode(' => ', $lines[$i]);
                    $configs[] = [
                        'key' => 'config_' . pp_slug($parts[0]),
                        'name' => $parts[0],
                        'hasMasterValue' => false,
                        'localValue' => $parts[1] ?? null,
                        'masterValue' => null,
                    ];
                } else {
                    break;
                }
                $i++;
            }
            while ($i < $len && $lines[$i] === '') $i++;

            if (!empty($configs)) {
                $groups[] = [
                    'key' => 'group_' . pp_slug($groupName),
                    'name' => $groupName,
                    'headings' => $headings,
                    'shortHeadings' => $shortHeadings,
                    'configs' => $configs,
                    'note' => null,
                ];
            }
        }
        // Simple title + value pair (e.g. "PHP Group" followed by names)
        elseif (!str_contains($line, ' => ') && strlen($line) < 80) {
            $groupName = $line;
            $i++;
            while ($i < $len && $lines[$i] === '') $i++;
            $value = ($i < $len && $lines[$i] !== '' && !str_contains($lines[$i], '______')) ? $lines[$i] : null;
            if ($value !== null) $i++;
            while ($i < $len && $lines[$i] === '') $i++;

            $groups[] = [
                'key' => 'group_' . pp_slug($groupName),
                'name' => $groupName,
                'headings' => [],
                'shortHeadings' => [],
                'configs' => $value ? [[
                    'key' => 'config_names_' . md5($value),
                    'name' => 'Names',
                    'hasMasterValue' => false,
                    'localValue' => $value,
                    'masterValue' => null,
                ]] : [],
                'note' => null,
            ];
        } else {
            break;
        }
    }

    if (!empty($groups)) {
        $modules[] = [
            'key' => 'module_' . pp_slug('PHP Credits'),
            'name' => 'PHP Credits',
            'groups' => $groups,
        ];
    }
}

function pp_parseLicense(array &$modules, array &$lines, int &$i, int $len): void {
    if ($i >= $len || $lines[$i] !== 'PHP License') return;

    $i++; // skip "PHP License"
    while ($i < $len && $lines[$i] === '') $i++;

    $text = [];
    while ($i < $len) {
        $text[] = $lines[$i];
        $i++;
    }

    $note = trim(implode("\n", $text));
    if ($note !== '') {
        $modules[] = [
            'key' => 'module_' . pp_slug('PHP License'),
            'name' => 'PHP License',
            'groups' => [[
                'key' => 'group_license',
                'name' => null,
                'headings' => [],
                'shortHeadings' => [],
                'configs' => [],
                'note' => $note,
            ]],
        ];
    }
}
