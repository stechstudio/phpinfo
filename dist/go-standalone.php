<?php
/**
 * Pretty PHP Info — Standalone Script
 *
 * Usage:
 *   curl -sSL prettyphpinfo.com/go | php
 *
 * This script captures phpinfo() locally, parses it into a structured format,
 * and outputs a complete self-contained HTML page. Nothing leaves your machine.
 *
 * @see https://github.com/stechstudio/phpinfo
 */

// ── Capture phpinfo (safe subset only) ────────────────────────────────

ob_start();
phpinfo();
$raw = ob_get_clean();

// ── Text parser (shared with package) ─────────────────────────────────
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


$info = pp_parse($raw);

// ── Output complete HTML page ─────────────────────────────────────────
ob_start();
?>
<!doctype html>
<html :class="darkMode && 'dark'" x-data="{ darkMode: localStorage.getItem('phpinfo-dark') === 'true' || (!localStorage.getItem('phpinfo-dark') && window.matchMedia('(prefers-color-scheme: dark)').matches) }" class="dark:[color-scheme:dark]">

<head>
    <meta charset="utf-8">
    <title>Pretty PHP Info</title>
    <link rel="shortcut icon" type="image/svg" href="data:image/svg+xml,%0A%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 -1 100 50'%3E%3Cpath d='m7.579 10.123 14.204 0c4.169 0.035 7.19 1.237 9.063 3.604 1.873 2.367 2.491 5.6 1.855 9.699-0.247 1.873-0.795 3.71-1.643 5.512-0.813 1.802-1.943 3.427-3.392 4.876-1.767 1.837-3.657 3.003-5.671 3.498-2.014 0.495-4.099 0.742-6.254 0.742l-6.36 0-2.014 10.07-7.367 0 7.579-38.001 0 0m6.201 6.042-3.18 15.9c0.212 0.035 0.424 0.053 0.636 0.053 0.247 0 0.495 0 0.742 0 3.392 0.035 6.219-0.3 8.48-1.007 2.261-0.742 3.781-3.321 4.558-7.738 0.636-3.71 0-5.848-1.908-6.413-1.873-0.565-4.222-0.83-7.049-0.795-0.424 0.035-0.83 0.053-1.219 0.053-0.353 0-0.724 0-1.113 0l0.053-0.053'/%3E%3Cpath d='m41.093 0 7.314 0-2.067 10.123 6.572 0c3.604 0.071 6.289 0.813 8.056 2.226 1.802 1.413 2.332 4.099 1.59 8.056l-3.551 17.649-7.42 0 3.392-16.854c0.353-1.767 0.247-3.021-0.318-3.763-0.565-0.742-1.784-1.113-3.657-1.113l-5.883-0.053-4.346 21.783-7.314 0 7.632-38.054 0 0'/%3E%3Cpath d='m70.412 10.123 14.204 0c4.169 0.035 7.19 1.237 9.063 3.604 1.873 2.367 2.491 5.6 1.855 9.699-0.247 1.873-0.795 3.71-1.643 5.512-0.813 1.802-1.943 3.427-3.392 4.876-1.767 1.837-3.657 3.003-5.671 3.498-2.014 0.495-4.099 0.742-6.254 0.742l-6.36 0-2.014 10.07-7.367 0 7.579-38.001 0 0m6.201 6.042-3.18 15.9c0.212 0.035 0.424 0.053 0.636 0.053 0.247 0 0.495 0 0.742 0 3.392 0.035 6.219-0.3 8.48-1.007 2.261-0.742 3.781-3.321 4.558-7.738 0.636-3.71 0-5.848-1.908-6.413-1.873-0.565-4.222-0.83-7.049-0.795-0.424 0.035-0.83 0.053-1.219 0.053-0.353 0-0.724 0-1.113 0l0.053-0.053'/%3E%3C/svg%3E%0A">
    <meta name="description" content="View your phpinfo() output in a pretty, responsive, searchable interface">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        /*! tailwindcss v4.2.2 | MIT License | https://tailwindcss.com */
@layer properties{@supports (((-webkit-hyphens:none)) and (not (margin-trim:inline))) or ((-moz-orient:inline) and (not (color:rgb(from red r g b)))){*,:before,:after,::backdrop{--tw-rotate-x:initial;--tw-rotate-y:initial;--tw-rotate-z:initial;--tw-skew-x:initial;--tw-skew-y:initial;--tw-space-y-reverse:0;--tw-border-style:solid;--tw-gradient-position:initial;--tw-gradient-from:#0000;--tw-gradient-via:#0000;--tw-gradient-to:#0000;--tw-gradient-stops:initial;--tw-gradient-via-stops:initial;--tw-gradient-from-position:0%;--tw-gradient-via-position:50%;--tw-gradient-to-position:100%;--tw-font-weight:initial;--tw-tracking:initial;--tw-blur:initial;--tw-brightness:initial;--tw-contrast:initial;--tw-grayscale:initial;--tw-hue-rotate:initial;--tw-invert:initial;--tw-opacity:initial;--tw-saturate:initial;--tw-sepia:initial;--tw-drop-shadow:initial;--tw-drop-shadow-color:initial;--tw-drop-shadow-alpha:100%;--tw-drop-shadow-size:initial;--tw-backdrop-blur:initial;--tw-backdrop-brightness:initial;--tw-backdrop-contrast:initial;--tw-backdrop-grayscale:initial;--tw-backdrop-hue-rotate:initial;--tw-backdrop-invert:initial;--tw-backdrop-opacity:initial;--tw-backdrop-saturate:initial;--tw-backdrop-sepia:initial;--tw-shadow:0 0 #0000;--tw-shadow-color:initial;--tw-shadow-alpha:100%;--tw-inset-shadow:0 0 #0000;--tw-inset-shadow-color:initial;--tw-inset-shadow-alpha:100%;--tw-ring-color:initial;--tw-ring-shadow:0 0 #0000;--tw-inset-ring-color:initial;--tw-inset-ring-shadow:0 0 #0000;--tw-ring-inset:initial;--tw-ring-offset-width:0px;--tw-ring-offset-color:#fff;--tw-ring-offset-shadow:0 0 #0000;--tw-outline-style:solid}}}@layer theme{:root,:host{--font-sans:ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";--font-mono:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;--color-blue-50:oklch(97% .014 254.604);--color-blue-400:oklch(70.7% .165 254.624);--color-blue-600:oklch(54.6% .245 262.881);--color-blue-950:oklch(28.2% .091 267.935);--color-slate-50:oklch(98.4% .003 247.858);--color-slate-100:oklch(96.8% .007 247.896);--color-slate-200:oklch(92.9% .013 255.508);--color-slate-300:oklch(86.9% .022 252.894);--color-slate-400:oklch(70.4% .04 256.788);--color-slate-500:oklch(55.4% .046 257.417);--color-slate-600:oklch(44.6% .043 257.281);--color-slate-700:oklch(37.2% .044 257.287);--color-slate-800:oklch(27.9% .041 260.031);--color-slate-900:oklch(20.8% .042 265.755);--color-slate-950:oklch(12.9% .042 264.695);--color-white:#fff;--spacing:.25rem;--container-3xl:48rem;--text-xs:.75rem;--text-xs--line-height:calc(1 / .75);--text-sm:.875rem;--text-sm--line-height:calc(1.25 / .875);--text-lg:1.125rem;--text-lg--line-height:calc(1.75 / 1.125);--text-xl:1.25rem;--text-xl--line-height:calc(1.75 / 1.25);--font-weight-medium:500;--font-weight-semibold:600;--tracking-tight:-.025em;--tracking-wider:.05em;--radius-md:.375rem;--radius-lg:.5rem;--blur-sm:8px;--default-transition-duration:.15s;--default-transition-timing-function:cubic-bezier(.4, 0, .2, 1);--default-font-family:var(--font-sans);--default-mono-font-family:var(--font-mono)}}@layer base{*,:after,:before,::backdrop{box-sizing:border-box;border:0 solid;margin:0;padding:0}::file-selector-button{box-sizing:border-box;border:0 solid;margin:0;padding:0}html,:host{-webkit-text-size-adjust:100%;tab-size:4;line-height:1.5;font-family:var(--default-font-family,ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji");font-feature-settings:var(--default-font-feature-settings,normal);font-variation-settings:var(--default-font-variation-settings,normal);-webkit-tap-highlight-color:transparent}hr{height:0;color:inherit;border-top-width:1px}abbr:where([title]){-webkit-text-decoration:underline dotted;text-decoration:underline dotted}h1,h2,h3,h4,h5,h6{font-size:inherit;font-weight:inherit}a{color:inherit;-webkit-text-decoration:inherit;-webkit-text-decoration:inherit;-webkit-text-decoration:inherit;text-decoration:inherit}b,strong{font-weight:bolder}code,kbd,samp,pre{font-family:var(--default-mono-font-family,ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace);font-feature-settings:var(--default-mono-font-feature-settings,normal);font-variation-settings:var(--default-mono-font-variation-settings,normal);font-size:1em}small{font-size:80%}sub,sup{vertical-align:baseline;font-size:75%;line-height:0;position:relative}sub{bottom:-.25em}sup{top:-.5em}table{text-indent:0;border-color:inherit;border-collapse:collapse}:-moz-focusring{outline:auto}progress{vertical-align:baseline}summary{display:list-item}ol,ul,menu{list-style:none}img,svg,video,canvas,audio,iframe,embed,object{vertical-align:middle;display:block}img,video{max-width:100%;height:auto}button,input,select,optgroup,textarea{font:inherit;font-feature-settings:inherit;font-variation-settings:inherit;letter-spacing:inherit;color:inherit;opacity:1;background-color:#0000;border-radius:0}::file-selector-button{font:inherit;font-feature-settings:inherit;font-variation-settings:inherit;letter-spacing:inherit;color:inherit;opacity:1;background-color:#0000;border-radius:0}:where(select:is([multiple],[size])) optgroup{font-weight:bolder}:where(select:is([multiple],[size])) optgroup option{padding-inline-start:20px}::file-selector-button{margin-inline-end:4px}::placeholder{opacity:1}@supports (not ((-webkit-appearance:-apple-pay-button))) or (contain-intrinsic-size:1px){::placeholder{color:currentColor}@supports (color:color-mix(in lab, red, red)){::placeholder{color:color-mix(in oklab, currentcolor 50%, transparent)}}}textarea{resize:vertical}::-webkit-search-decoration{-webkit-appearance:none}::-webkit-date-and-time-value{min-height:1lh;text-align:inherit}::-webkit-datetime-edit{display:inline-flex}::-webkit-datetime-edit-fields-wrapper{padding:0}::-webkit-datetime-edit{padding-block:0}::-webkit-datetime-edit-year-field{padding-block:0}::-webkit-datetime-edit-month-field{padding-block:0}::-webkit-datetime-edit-day-field{padding-block:0}::-webkit-datetime-edit-hour-field{padding-block:0}::-webkit-datetime-edit-minute-field{padding-block:0}::-webkit-datetime-edit-second-field{padding-block:0}::-webkit-datetime-edit-millisecond-field{padding-block:0}::-webkit-datetime-edit-meridiem-field{padding-block:0}::-webkit-calendar-picker-indicator{line-height:1}:-moz-ui-invalid{box-shadow:none}button,input:where([type=button],[type=reset],[type=submit]){appearance:button}::file-selector-button{appearance:button}::-webkit-inner-spin-button{height:auto}::-webkit-outer-spin-button{height:auto}[hidden]:where(:not([hidden=until-found])){display:none!important}}@layer components;@layer utilities{.pointer-events-none{pointer-events:none}.collapse{visibility:collapse}.visible{visibility:visible}.absolute{position:absolute}.fixed{position:fixed}.relative{position:relative}.static{position:static}.sticky{position:sticky}.inset-0{inset:calc(var(--spacing) * 0)}.start{inset-inline-start:var(--spacing)}.end{inset-inline-end:var(--spacing)}.top-0{top:calc(var(--spacing) * 0)}.top-2{top:calc(var(--spacing) * 2)}.top-14{top:calc(var(--spacing) * 14)}.right-0{right:calc(var(--spacing) * 0)}.right-2{right:calc(var(--spacing) * 2)}.right-6{right:calc(var(--spacing) * 6)}.bottom-0{bottom:calc(var(--spacing) * 0)}.bottom-6{bottom:calc(var(--spacing) * 6)}.left-0{left:calc(var(--spacing) * 0)}.z-10{z-index:10}.z-20{z-index:20}.z-30{z-index:30}.z-40{z-index:40}.z-50{z-index:50}.mx-auto{margin-inline:auto}.my-3{margin-block:calc(var(--spacing) * 3)}.mt-1\.5{margin-top:calc(var(--spacing) * 1.5)}.mt-3{margin-top:calc(var(--spacing) * 3)}.mt-20{margin-top:calc(var(--spacing) * 20)}.-mr-2{margin-right:calc(var(--spacing) * -2)}.mr-1{margin-right:calc(var(--spacing) * 1)}.mr-3{margin-right:calc(var(--spacing) * 3)}.mr-4{margin-right:calc(var(--spacing) * 4)}.ml-1{margin-left:calc(var(--spacing) * 1)}.block{display:block}.flex{display:flex}.hidden{display:none}.inline{display:inline}.inline-block{display:inline-block}.inline-flex{display:inline-flex}.table{display:table}.h-3{height:calc(var(--spacing) * 3)}.h-3\.5{height:calc(var(--spacing) * 3.5)}.h-4{height:calc(var(--spacing) * 4)}.h-5{height:calc(var(--spacing) * 5)}.h-6{height:calc(var(--spacing) * 6)}.h-8{height:calc(var(--spacing) * 8)}.h-12{height:calc(var(--spacing) * 12)}.h-14{height:calc(var(--spacing) * 14)}.w-3{width:calc(var(--spacing) * 3)}.w-3\.5{width:calc(var(--spacing) * 3.5)}.w-4{width:calc(var(--spacing) * 4)}.w-5{width:calc(var(--spacing) * 5)}.w-6{width:calc(var(--spacing) * 6)}.w-8{width:calc(var(--spacing) * 8)}.w-14{width:calc(var(--spacing) * 14)}.w-48{width:calc(var(--spacing) * 48)}.w-80{width:calc(var(--spacing) * 80)}.w-full{width:100%}.max-w-3xl{max-width:var(--container-3xl)}.max-w-\[96rem\]{max-width:96rem}.flex-1{flex:1}.flex-shrink-0{flex-shrink:0}.border-collapse{border-collapse:collapse}.transform{transform:var(--tw-rotate-x,) var(--tw-rotate-y,) var(--tw-rotate-z,) var(--tw-skew-x,) var(--tw-skew-y,)}.scroll-mt-14{scroll-margin-top:calc(var(--spacing) * 14)}.scroll-py-6{scroll-padding-block:calc(var(--spacing) * 6)}.scroll-pt-14{scroll-padding-top:calc(var(--spacing) * 14)}.flex-col{flex-direction:column}.items-center{align-items:center}.justify-between{justify-content:space-between}.justify-center{justify-content:center}.justify-end{justify-content:flex-end}.gap-2{gap:calc(var(--spacing) * 2)}.gap-3{gap:calc(var(--spacing) * 3)}.gap-4{gap:calc(var(--spacing) * 4)}:where(.space-y-0\.5>:not(:last-child)){--tw-space-y-reverse:0;margin-block-start:calc(calc(var(--spacing) * .5) * var(--tw-space-y-reverse));margin-block-end:calc(calc(var(--spacing) * .5) * calc(1 - var(--tw-space-y-reverse)))}:where(.space-y-px>:not(:last-child)){--tw-space-y-reverse:0;margin-block-start:calc(1px * var(--tw-space-y-reverse));margin-block-end:calc(1px * calc(1 - var(--tw-space-y-reverse)))}.overflow-hidden{overflow:hidden}.overflow-y-auto{overflow-y:auto}.rounded{border-radius:.25rem}.rounded-lg{border-radius:var(--radius-lg)}.rounded-md{border-radius:var(--radius-md)}.border{border-style:var(--tw-border-style);border-width:1px}.border-r{border-right-style:var(--tw-border-style);border-right-width:1px}.border-b{border-bottom-style:var(--tw-border-style);border-bottom-width:1px}.border-slate-100{border-color:var(--color-slate-100)}.border-slate-200{border-color:var(--color-slate-200)}.border-slate-300{border-color:var(--color-slate-300)}.border-slate-600{border-color:var(--color-slate-600)}.bg-blue-50{background-color:var(--color-blue-50)}.bg-slate-50\/50{background-color:#f8fafc80}@supports (color:color-mix(in lab, red, red)){.bg-slate-50\/50{background-color:color-mix(in oklab, var(--color-slate-50) 50%, transparent)}}.bg-slate-100{background-color:var(--color-slate-100)}.bg-slate-600{background-color:var(--color-slate-600)}.bg-slate-800{background-color:var(--color-slate-800)}.bg-slate-900{background-color:var(--color-slate-900)}.bg-slate-900\/50{background-color:#0f172b80}@supports (color:color-mix(in lab, red, red)){.bg-slate-900\/50{background-color:color-mix(in oklab, var(--color-slate-900) 50%, transparent)}}.bg-transparent{background-color:#0000}.bg-white{background-color:var(--color-white)}.bg-white\/80{background-color:#fffc}@supports (color:color-mix(in lab, red, red)){.bg-white\/80{background-color:color-mix(in oklab, var(--color-white) 80%, transparent)}}.bg-gradient-to-b{--tw-gradient-position:to bottom in oklab;background-image:linear-gradient(var(--tw-gradient-stops))}.bg-gradient-to-t{--tw-gradient-position:to top in oklab;background-image:linear-gradient(var(--tw-gradient-stops))}.from-slate-800{--tw-gradient-from:var(--color-slate-800);--tw-gradient-stops:var(--tw-gradient-via-stops,var(--tw-gradient-position), var(--tw-gradient-from) var(--tw-gradient-from-position), var(--tw-gradient-to) var(--tw-gradient-to-position))}.from-white{--tw-gradient-from:var(--color-white);--tw-gradient-stops:var(--tw-gradient-via-stops,var(--tw-gradient-position), var(--tw-gradient-from) var(--tw-gradient-from-position), var(--tw-gradient-to) var(--tw-gradient-to-position))}.via-slate-800\/75{--tw-gradient-via:#1d293dbf}@supports (color:color-mix(in lab, red, red)){.via-slate-800\/75{--tw-gradient-via:color-mix(in oklab, var(--color-slate-800) 75%, transparent)}}.via-slate-800\/75{--tw-gradient-via-stops:var(--tw-gradient-position), var(--tw-gradient-from) var(--tw-gradient-from-position), var(--tw-gradient-via) var(--tw-gradient-via-position), var(--tw-gradient-to) var(--tw-gradient-to-position);--tw-gradient-stops:var(--tw-gradient-via-stops)}.to-transparent{--tw-gradient-to:transparent;--tw-gradient-stops:var(--tw-gradient-via-stops,var(--tw-gradient-position), var(--tw-gradient-from) var(--tw-gradient-from-position), var(--tw-gradient-to) var(--tw-gradient-to-position))}.p-1{padding:calc(var(--spacing) * 1)}.p-1\.5{padding:calc(var(--spacing) * 1.5)}.p-2{padding:calc(var(--spacing) * 2)}.p-4{padding:calc(var(--spacing) * 4)}.p-6{padding:calc(var(--spacing) * 6)}.px-1\.5{padding-inline:calc(var(--spacing) * 1.5)}.px-3{padding-inline:calc(var(--spacing) * 3)}.px-4{padding-inline:calc(var(--spacing) * 4)}.px-6{padding-inline:calc(var(--spacing) * 6)}.py-0\.5{padding-block:calc(var(--spacing) * .5)}.py-1{padding-block:calc(var(--spacing) * 1)}.py-1\.5{padding-block:calc(var(--spacing) * 1.5)}.py-2{padding-block:calc(var(--spacing) * 2)}.py-2\.5{padding-block:calc(var(--spacing) * 2.5)}.py-3{padding-block:calc(var(--spacing) * 3)}.py-6{padding-block:calc(var(--spacing) * 6)}.py-8{padding-block:calc(var(--spacing) * 8)}.pt-14{padding-top:calc(var(--spacing) * 14)}.pt-16{padding-top:calc(var(--spacing) * 16)}.pl-6{padding-left:calc(var(--spacing) * 6)}.text-center{text-align:center}.text-left{text-align:left}.align-top{vertical-align:top}.font-mono{font-family:var(--font-mono)}.font-sans{font-family:var(--font-sans)}.text-lg{font-size:var(--text-lg);line-height:var(--tw-leading,var(--text-lg--line-height))}.text-sm{font-size:var(--text-sm);line-height:var(--tw-leading,var(--text-sm--line-height))}.text-xl{font-size:var(--text-xl);line-height:var(--tw-leading,var(--text-xl--line-height))}.text-xs{font-size:var(--text-xs);line-height:var(--tw-leading,var(--text-xs--line-height))}.text-\[13px\]{font-size:13px}.font-medium{--tw-font-weight:var(--font-weight-medium);font-weight:var(--font-weight-medium)}.font-semibold{--tw-font-weight:var(--font-weight-semibold);font-weight:var(--font-weight-semibold)}.tracking-tight{--tw-tracking:var(--tracking-tight);letter-spacing:var(--tracking-tight)}.tracking-wider{--tw-tracking:var(--tracking-wider);letter-spacing:var(--tracking-wider)}.text-blue-600{color:var(--color-blue-600)}.text-slate-300{color:var(--color-slate-300)}.text-slate-400{color:var(--color-slate-400)}.text-slate-500{color:var(--color-slate-500)}.text-slate-600{color:var(--color-slate-600)}.text-slate-700{color:var(--color-slate-700)}.text-slate-900{color:var(--color-slate-900)}.text-white{color:var(--color-white)}.uppercase{text-transform:uppercase}.italic{font-style:italic}.antialiased{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}.opacity-50{opacity:.5}.filter{filter:var(--tw-blur,) var(--tw-brightness,) var(--tw-contrast,) var(--tw-grayscale,) var(--tw-hue-rotate,) var(--tw-invert,) var(--tw-saturate,) var(--tw-sepia,) var(--tw-drop-shadow,)}.backdrop-blur-sm{--tw-backdrop-blur:blur(var(--blur-sm));-webkit-backdrop-filter:var(--tw-backdrop-blur,) var(--tw-backdrop-brightness,) var(--tw-backdrop-contrast,) var(--tw-backdrop-grayscale,) var(--tw-backdrop-hue-rotate,) var(--tw-backdrop-invert,) var(--tw-backdrop-opacity,) var(--tw-backdrop-saturate,) var(--tw-backdrop-sepia,);backdrop-filter:var(--tw-backdrop-blur,) var(--tw-backdrop-brightness,) var(--tw-backdrop-contrast,) var(--tw-backdrop-grayscale,) var(--tw-backdrop-hue-rotate,) var(--tw-backdrop-invert,) var(--tw-backdrop-opacity,) var(--tw-backdrop-saturate,) var(--tw-backdrop-sepia,)}.transition{transition-property:color,background-color,border-color,outline-color,text-decoration-color,fill,stroke,--tw-gradient-from,--tw-gradient-via,--tw-gradient-to,opacity,box-shadow,transform,translate,scale,rotate,filter,-webkit-backdrop-filter,backdrop-filter,display,content-visibility,overlay,pointer-events;transition-timing-function:var(--tw-ease,var(--default-transition-timing-function));transition-duration:var(--tw-duration,var(--default-transition-duration))}.transition-colors{transition-property:color,background-color,border-color,outline-color,text-decoration-color,fill,stroke,--tw-gradient-from,--tw-gradient-via,--tw-gradient-to;transition-timing-function:var(--tw-ease,var(--default-transition-timing-function));transition-duration:var(--tw-duration,var(--default-transition-duration))}@media (hover:hover){.group-hover\:inline:is(:where(.group):hover *){display:inline}}.placeholder\:text-slate-400::placeholder{color:var(--color-slate-400)}.last\:border-0:last-child{border-style:var(--tw-border-style);border-width:0}.empty\:hidden:empty{display:none}@media (hover:hover){.hover\:bg-slate-50:hover{background-color:var(--color-slate-50)}.hover\:bg-slate-100:hover{background-color:var(--color-slate-100)}.hover\:text-slate-600:hover{color:var(--color-slate-600)}.hover\:text-slate-900:hover{color:var(--color-slate-900)}.hover\:underline:hover{text-decoration-line:underline}}.focus\:ring-2:focus{--tw-ring-shadow:var(--tw-ring-inset,) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color,currentcolor);box-shadow:var(--tw-inset-shadow), var(--tw-inset-ring-shadow), var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow)}.focus\:ring-slate-900\/10:focus{--tw-ring-color:#0f172b1a}@supports (color:color-mix(in lab, red, red)){.focus\:ring-slate-900\/10:focus{--tw-ring-color:color-mix(in oklab, var(--color-slate-900) 10%, transparent)}}.focus\:outline-0:focus{outline-style:var(--tw-outline-style);outline-width:0}@media (min-width:48rem){.md\:relative{position:relative}.md\:mb-6{margin-bottom:calc(var(--spacing) * 6)}.md\:ml-52{margin-left:calc(var(--spacing) * 52)}.md\:block{display:block}.md\:flex{display:flex}.md\:hidden{display:none}.md\:inline-block{display:inline-block}.md\:h-10{height:calc(var(--spacing) * 10)}.md\:w-72{width:calc(var(--spacing) * 72)}.md\:scroll-mt-6{scroll-margin-top:calc(var(--spacing) * 6)}.md\:scroll-mt-8{scroll-margin-top:calc(var(--spacing) * 8)}:where(.md\:space-y-3>:not(:last-child)){--tw-space-y-reverse:0;margin-block-start:calc(calc(var(--spacing) * 3) * var(--tw-space-y-reverse));margin-block-end:calc(calc(var(--spacing) * 3) * calc(1 - var(--tw-space-y-reverse)))}.md\:rounded-lg{border-radius:var(--radius-lg)}.md\:border{border-style:var(--tw-border-style);border-width:1px}.md\:border-0{border-style:var(--tw-border-style);border-width:0}.md\:px-4{padding-inline:calc(var(--spacing) * 4)}.md\:py-0{padding-block:calc(var(--spacing) * 0)}.md\:pl-0{padding-left:calc(var(--spacing) * 0)}.md\:text-sm{font-size:var(--text-sm);line-height:var(--tw-leading,var(--text-sm--line-height))}}@media (min-width:64rem){.lg\:top-16{top:calc(var(--spacing) * 16)}.lg\:mb-10{margin-bottom:calc(var(--spacing) * 10)}.lg\:ml-60{margin-left:calc(var(--spacing) * 60)}.lg\:hidden{display:none}.lg\:table-row{display:table-row}.lg\:h-16{height:calc(var(--spacing) * 16)}.lg\:w-1\/4{width:25%}.lg\:w-56{width:calc(var(--spacing) * 56)}.lg\:w-96{width:calc(var(--spacing) * 96)}.lg\:scroll-pt-16{scroll-padding-top:calc(var(--spacing) * 16)}:where(.lg\:space-y-5>:not(:last-child)){--tw-space-y-reverse:0;margin-block-start:calc(calc(var(--spacing) * 5) * var(--tw-space-y-reverse));margin-block-end:calc(calc(var(--spacing) * 5) * calc(1 - var(--tw-space-y-reverse)))}.lg\:px-4{padding-inline:calc(var(--spacing) * 4)}.lg\:py-0{padding-block:calc(var(--spacing) * 0)}.lg\:py-2\.5{padding-block:calc(var(--spacing) * 2.5)}.lg\:pt-16{padding-top:calc(var(--spacing) * 16)}.lg\:pl-4{padding-left:calc(var(--spacing) * 4)}@media (hover:hover){.lg\:group-hover\/cell\:inline-flex:is(:where(.group\/cell):hover *){display:inline-flex}.lg\:hover\:bg-blue-50:hover{background-color:var(--color-blue-50)}.lg\:hover\:bg-slate-50\/75:hover{background-color:#f8fafcbf}@supports (color:color-mix(in lab, red, red)){.lg\:hover\:bg-slate-50\/75:hover{background-color:color-mix(in oklab, var(--color-slate-50) 75%, transparent)}}}}@media (min-width:80rem){.xl\:ml-72{margin-left:calc(var(--spacing) * 72)}.xl\:w-64{width:calc(var(--spacing) * 64)}.xl\:px-4{padding-inline:calc(var(--spacing) * 4)}.xl\:px-8{padding-inline:calc(var(--spacing) * 8)}.xl\:pr-8{padding-right:calc(var(--spacing) * 8)}}.dark\:border-slate-600:where(.dark,.dark *){border-color:var(--color-slate-600)}.dark\:border-slate-700:where(.dark,.dark *){border-color:var(--color-slate-700)}.dark\:border-slate-800:where(.dark,.dark *){border-color:var(--color-slate-800)}.dark\:border-slate-800\/75:where(.dark,.dark *){border-color:#1d293dbf}@supports (color:color-mix(in lab, red, red)){.dark\:border-slate-800\/75:where(.dark,.dark *){border-color:color-mix(in oklab, var(--color-slate-800) 75%, transparent)}}.dark\:bg-blue-950\/30:where(.dark,.dark *){background-color:#1624564d}@supports (color:color-mix(in lab, red, red)){.dark\:bg-blue-950\/30:where(.dark,.dark *){background-color:color-mix(in oklab, var(--color-blue-950) 30%, transparent)}}.dark\:bg-slate-100:where(.dark,.dark *){background-color:var(--color-slate-100)}.dark\:bg-slate-700:where(.dark,.dark *){background-color:var(--color-slate-700)}.dark\:bg-slate-800:where(.dark,.dark *){background-color:var(--color-slate-800)}.dark\:bg-slate-900\/50:where(.dark,.dark *){background-color:#0f172b80}@supports (color:color-mix(in lab, red, red)){.dark\:bg-slate-900\/50:where(.dark,.dark *){background-color:color-mix(in oklab, var(--color-slate-900) 50%, transparent)}}.dark\:bg-slate-950:where(.dark,.dark *){background-color:var(--color-slate-950)}.dark\:bg-slate-950\/80:where(.dark,.dark *){background-color:#020618cc}@supports (color:color-mix(in lab, red, red)){.dark\:bg-slate-950\/80:where(.dark,.dark *){background-color:color-mix(in oklab, var(--color-slate-950) 80%, transparent)}}.dark\:bg-transparent:where(.dark,.dark *){background-color:#0000}.dark\:from-slate-700:where(.dark,.dark *){--tw-gradient-from:var(--color-slate-700);--tw-gradient-stops:var(--tw-gradient-via-stops,var(--tw-gradient-position), var(--tw-gradient-from) var(--tw-gradient-from-position), var(--tw-gradient-to) var(--tw-gradient-to-position))}.dark\:from-slate-950:where(.dark,.dark *){--tw-gradient-from:var(--color-slate-950);--tw-gradient-stops:var(--tw-gradient-via-stops,var(--tw-gradient-position), var(--tw-gradient-from) var(--tw-gradient-from-position), var(--tw-gradient-to) var(--tw-gradient-to-position))}.dark\:via-slate-700\/75:where(.dark,.dark *){--tw-gradient-via:#314158bf}@supports (color:color-mix(in lab, red, red)){.dark\:via-slate-700\/75:where(.dark,.dark *){--tw-gradient-via:color-mix(in oklab, var(--color-slate-700) 75%, transparent)}}.dark\:via-slate-700\/75:where(.dark,.dark *){--tw-gradient-via-stops:var(--tw-gradient-position), var(--tw-gradient-from) var(--tw-gradient-from-position), var(--tw-gradient-via) var(--tw-gradient-via-position), var(--tw-gradient-to) var(--tw-gradient-to-position);--tw-gradient-stops:var(--tw-gradient-via-stops)}.dark\:text-blue-400:where(.dark,.dark *){color:var(--color-blue-400)}.dark\:text-slate-100:where(.dark,.dark *){color:var(--color-slate-100)}.dark\:text-slate-200:where(.dark,.dark *){color:var(--color-slate-200)}.dark\:text-slate-300:where(.dark,.dark *){color:var(--color-slate-300)}.dark\:text-slate-400:where(.dark,.dark *){color:var(--color-slate-400)}.dark\:text-slate-500:where(.dark,.dark *){color:var(--color-slate-500)}.dark\:text-slate-600:where(.dark,.dark *){color:var(--color-slate-600)}.dark\:text-slate-900:where(.dark,.dark *){color:var(--color-slate-900)}.dark\:\[color-scheme\:dark\]:where(.dark,.dark *){color-scheme:dark}.dark\:invert:where(.dark,.dark *){--tw-invert:invert(100%);filter:var(--tw-blur,) var(--tw-brightness,) var(--tw-contrast,) var(--tw-grayscale,) var(--tw-hue-rotate,) var(--tw-invert,) var(--tw-saturate,) var(--tw-sepia,) var(--tw-drop-shadow,)}@media (hover:hover){.dark\:hover\:bg-slate-800:where(.dark,.dark *):hover{background-color:var(--color-slate-800)}.dark\:hover\:bg-slate-800\/50:where(.dark,.dark *):hover{background-color:#1d293d80}@supports (color:color-mix(in lab, red, red)){.dark\:hover\:bg-slate-800\/50:where(.dark,.dark *):hover{background-color:color-mix(in oklab, var(--color-slate-800) 50%, transparent)}}.dark\:hover\:text-slate-200:where(.dark,.dark *):hover{color:var(--color-slate-200)}.dark\:hover\:text-slate-300:where(.dark,.dark *):hover{color:var(--color-slate-300)}}.dark\:focus\:text-slate-100:where(.dark,.dark *):focus{color:var(--color-slate-100)}.dark\:focus\:ring-slate-300\/20:where(.dark,.dark *):focus{--tw-ring-color:#cad5e233}@supports (color:color-mix(in lab, red, red)){.dark\:focus\:ring-slate-300\/20:where(.dark,.dark *):focus{--tw-ring-color:color-mix(in oklab, var(--color-slate-300) 20%, transparent)}}@media (min-width:64rem){@media (hover:hover){.lg\:dark\:hover\:bg-blue-950\/30:where(.dark,.dark *):hover{background-color:#1624564d}@supports (color:color-mix(in lab, red, red)){.lg\:dark\:hover\:bg-blue-950\/30:where(.dark,.dark *):hover{background-color:color-mix(in oklab, var(--color-blue-950) 30%, transparent)}}.lg\:dark\:hover\:bg-slate-800\/40:where(.dark,.dark *):hover{background-color:#1d293d66}@supports (color:color-mix(in lab, red, red)){.lg\:dark\:hover\:bg-slate-800\/40:where(.dark,.dark *):hover{background-color:color-mix(in oklab, var(--color-slate-800) 40%, transparent)}}}}}[x-cloak]{display:none!important}@property --tw-rotate-x{syntax:"*";inherits:false}@property --tw-rotate-y{syntax:"*";inherits:false}@property --tw-rotate-z{syntax:"*";inherits:false}@property --tw-skew-x{syntax:"*";inherits:false}@property --tw-skew-y{syntax:"*";inherits:false}@property --tw-space-y-reverse{syntax:"*";inherits:false;initial-value:0}@property --tw-border-style{syntax:"*";inherits:false;initial-value:solid}@property --tw-gradient-position{syntax:"*";inherits:false}@property --tw-gradient-from{syntax:"<color>";inherits:false;initial-value:#0000}@property --tw-gradient-via{syntax:"<color>";inherits:false;initial-value:#0000}@property --tw-gradient-to{syntax:"<color>";inherits:false;initial-value:#0000}@property --tw-gradient-stops{syntax:"*";inherits:false}@property --tw-gradient-via-stops{syntax:"*";inherits:false}@property --tw-gradient-from-position{syntax:"<length-percentage>";inherits:false;initial-value:0%}@property --tw-gradient-via-position{syntax:"<length-percentage>";inherits:false;initial-value:50%}@property --tw-gradient-to-position{syntax:"<length-percentage>";inherits:false;initial-value:100%}@property --tw-font-weight{syntax:"*";inherits:false}@property --tw-tracking{syntax:"*";inherits:false}@property --tw-blur{syntax:"*";inherits:false}@property --tw-brightness{syntax:"*";inherits:false}@property --tw-contrast{syntax:"*";inherits:false}@property --tw-grayscale{syntax:"*";inherits:false}@property --tw-hue-rotate{syntax:"*";inherits:false}@property --tw-invert{syntax:"*";inherits:false}@property --tw-opacity{syntax:"*";inherits:false}@property --tw-saturate{syntax:"*";inherits:false}@property --tw-sepia{syntax:"*";inherits:false}@property --tw-drop-shadow{syntax:"*";inherits:false}@property --tw-drop-shadow-color{syntax:"*";inherits:false}@property --tw-drop-shadow-alpha{syntax:"<percentage>";inherits:false;initial-value:100%}@property --tw-drop-shadow-size{syntax:"*";inherits:false}@property --tw-backdrop-blur{syntax:"*";inherits:false}@property --tw-backdrop-brightness{syntax:"*";inherits:false}@property --tw-backdrop-contrast{syntax:"*";inherits:false}@property --tw-backdrop-grayscale{syntax:"*";inherits:false}@property --tw-backdrop-hue-rotate{syntax:"*";inherits:false}@property --tw-backdrop-invert{syntax:"*";inherits:false}@property --tw-backdrop-opacity{syntax:"*";inherits:false}@property --tw-backdrop-saturate{syntax:"*";inherits:false}@property --tw-backdrop-sepia{syntax:"*";inherits:false}@property --tw-shadow{syntax:"*";inherits:false;initial-value:0 0 #0000}@property --tw-shadow-color{syntax:"*";inherits:false}@property --tw-shadow-alpha{syntax:"<percentage>";inherits:false;initial-value:100%}@property --tw-inset-shadow{syntax:"*";inherits:false;initial-value:0 0 #0000}@property --tw-inset-shadow-color{syntax:"*";inherits:false}@property --tw-inset-shadow-alpha{syntax:"<percentage>";inherits:false;initial-value:100%}@property --tw-ring-color{syntax:"*";inherits:false}@property --tw-ring-shadow{syntax:"*";inherits:false;initial-value:0 0 #0000}@property --tw-inset-ring-color{syntax:"*";inherits:false}@property --tw-inset-ring-shadow{syntax:"*";inherits:false;initial-value:0 0 #0000}@property --tw-ring-inset{syntax:"*";inherits:false}@property --tw-ring-offset-width{syntax:"<length>";inherits:false;initial-value:0}@property --tw-ring-offset-color{syntax:"*";inherits:false;initial-value:#fff}@property --tw-ring-offset-shadow{syntax:"*";inherits:false;initial-value:0 0 #0000}@property --tw-outline-style{syntax:"*";inherits:false;initial-value:solid}
    </style>
</head>

<body>

<div class="page-wrapper antialiased font-sans text-slate-700 dark:text-slate-400 fixed inset-0" x-data='Navigation' @keydown.window.slash.prevent="$refs.search.focus();">
    <header class="absolute top-0 h-14 lg:h-16 w-full flex items-center justify-between py-3 px-6 xl:px-8 z-30 bg-white/80 dark:bg-slate-950/80 backdrop-blur-sm border-b border-slate-200 dark:border-slate-800">
        <div class="flex-1 md:flex items-center gap-3">
            <img class="h-6 md:h-10 dark:invert" src="data:image/svg+xml,%0A%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 -1 100 50'%3E%3Cpath d='m7.579 10.123 14.204 0c4.169 0.035 7.19 1.237 9.063 3.604 1.873 2.367 2.491 5.6 1.855 9.699-0.247 1.873-0.795 3.71-1.643 5.512-0.813 1.802-1.943 3.427-3.392 4.876-1.767 1.837-3.657 3.003-5.671 3.498-2.014 0.495-4.099 0.742-6.254 0.742l-6.36 0-2.014 10.07-7.367 0 7.579-38.001 0 0m6.201 6.042-3.18 15.9c0.212 0.035 0.424 0.053 0.636 0.053 0.247 0 0.495 0 0.742 0 3.392 0.035 6.219-0.3 8.48-1.007 2.261-0.742 3.781-3.321 4.558-7.738 0.636-3.71 0-5.848-1.908-6.413-1.873-0.565-4.222-0.83-7.049-0.795-0.424 0.035-0.83 0.053-1.219 0.053-0.353 0-0.724 0-1.113 0l0.053-0.053'/%3E%3Cpath d='m41.093 0 7.314 0-2.067 10.123 6.572 0c3.604 0.071 6.289 0.813 8.056 2.226 1.802 1.413 2.332 4.099 1.59 8.056l-3.551 17.649-7.42 0 3.392-16.854c0.353-1.767 0.247-3.021-0.318-3.763-0.565-0.742-1.784-1.113-3.657-1.113l-5.883-0.053-4.346 21.783-7.314 0 7.632-38.054 0 0'/%3E%3Cpath d='m70.412 10.123 14.204 0c4.169 0.035 7.19 1.237 9.063 3.604 1.873 2.367 2.491 5.6 1.855 9.699-0.247 1.873-0.795 3.71-1.643 5.512-0.813 1.802-1.943 3.427-3.392 4.876-1.767 1.837-3.657 3.003-5.671 3.498-2.014 0.495-4.099 0.742-6.254 0.742l-6.36 0-2.014 10.07-7.367 0 7.579-38.001 0 0m6.201 6.042-3.18 15.9c0.212 0.035 0.424 0.053 0.636 0.053 0.247 0 0.495 0 0.742 0 3.392 0.035 6.219-0.3 8.48-1.007 2.261-0.742 3.781-3.321 4.558-7.738 0.636-3.71 0-5.848-1.908-6.413-1.873-0.565-4.222-0.83-7.049-0.795-0.424 0.035-0.83 0.053-1.219 0.053-0.353 0-0.724 0-1.113 0l0.053-0.053'/%3E%3C/svg%3E%0A"/>
            <span class="hidden md:inline-block text-sm font-mono text-slate-400 dark:text-slate-500"><?php echo $info['version'] ?></span>
            <span class="md:hidden text-xs font-mono text-slate-400"><?php echo $info['version'] ?></span>
        </div>
        <div class="flex-1 flex justify-center">
            <div class="relative group">
                <input type="search" class="w-48 md:w-72 lg:w-96 rounded-lg px-3 py-1.5 text-sm border border-slate-200 dark:border-slate-700 focus:outline-0 focus:ring-2 focus:ring-slate-900/10 dark:focus:ring-slate-300/20 dark:focus:text-slate-100 bg-transparent dark:bg-transparent placeholder:text-slate-400"
                       placeholder="Type to search..." x-model.debounce="search" x-ref="search" @keydown.stop="" x-on:focus="searchFocused = true" x-on:blur="searchFocused = false"/>
                <div x-show="!searchFocused && isUnfiltered()" class="absolute top-0 right-0 mt-1.5 mr-3 border border-slate-300 dark:border-slate-600 rounded px-1.5 py-0.5 text-xs text-slate-400 dark:text-slate-500 font-mono">/</div>
            </div>
        </div>
        <div class="flex-1 flex justify-end items-center gap-3">
            <button @click="darkMode = !darkMode; localStorage.setItem('phpinfo-dark', darkMode)" class="hidden md:flex items-center justify-center p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800" title="Toggle dark mode">
                <svg x-show="!darkMode" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                </svg>
                <svg x-show="darkMode" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                </svg>
            </button>
            <button @click="showMobileNav()" class="md:hidden p-1.5 -mr-2 text-slate-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
        </div>
    </header>

    <div class="absolute w-full inset-0 overflow-y-auto pt-14 lg:pt-16 bg-white dark:bg-slate-950">
        <div x-cloak x-show="emptyState" class="max-w-3xl mx-auto mt-20 flex gap-4 justify-center text-slate-500 text-xl">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <div>No search results found</div>
        </div>

        <div class="flex-1 flex max-w-[96rem] mx-auto">
            <div class="fixed top-14 lg:top-16 bottom-0 hidden md:block flex-shrink-0 w-48 lg:w-56 xl:w-64 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950">
                <aside class="absolute inset-0 overflow-y-auto py-6 px-3 xl:px-4 space-y-0.5 scroll-py-6">
                    <template x-for="module in info.modules" :key="module.key">
                        <a x-show="module.shouldShow"
                           :id="'nav_' + module.key"
                           :href="'#' + module.key" @click=jump(module.key)
                           class="px-3 py-1 rounded-md block text-[13px]" :class="selected == module.key ? 'bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-slate-200 font-medium' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/50'">
                            <span x-text="module.name"></span>
                        </a>
                    </template>
                </aside>
                <div class="pointer-events-none absolute bottom-0 left-0 right-0 h-12 bg-gradient-to-t from-white dark:from-slate-950 to-transparent"></div>
                <div class="pointer-events-none absolute top-0 left-0 right-0 h-6 bg-gradient-to-b from-white dark:from-slate-950 to-transparent"></div>
            </div>

            <article class="flex-1 md:ml-52 lg:ml-60 xl:ml-72 py-8">
                <div class="md:px-4 md:pl-0 xl:pr-8">
                    <template x-for="module in info.modules" :key="module.key">
                        <section x-intersect:enter.margin.-100px="enter(module.key)"
                                 x-intersect:leave.margin.-100px="leave(module.key)"
                                 x-show="module.shouldShow"
                                 class="md:space-y-3 lg:space-y-5 md:mb-6 lg:mb-10 md:scroll-mt-6" :id="module.key">
                            <h2 class="block text-lg font-semibold tracking-tight pl-6 md:pl-0 py-2 md:py-0 sticky md:relative top-0 border-b border-slate-100 dark:border-slate-800 md:border-0 z-20 bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100">
                                <span x-text="module.name"></span>
                            </h2>

                            <template x-for="group in module.groups" :key="group.key">
                                <div x-show="group.shouldShow" :id="group.key" class="md:scroll-mt-8">
                                    <h3 x-show="group.name" class="pl-6 md:pl-0 text-sm font-medium text-slate-500 dark:text-slate-400 my-3">
                                        <span x-text="group.name"></span>
                                    </h3>
                                    <div x-show="group.configs.length > 0"
                                         class="table-wrapper md:border border-slate-200 dark:border-slate-800 md:rounded-lg overflow-hidden">
                                        <table class="w-full text-sm">
                                            <thead>
                                                <tr x-show="group && group.headings.length > 0" class="hidden lg:table-row border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
                                                    <th class="text-left text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500 py-2.5 px-4"><span x-text="group.headings[0]"></span></th>
                                                    <th class="text-left text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500 py-2.5 px-4"><span x-text="group.headings[1]"></span></th>
                                                    <th x-show="group.headings.length == 3" class="text-left text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500 py-2.5 px-4"><span x-text="group.headings[2]"></span></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="config in group.configs" :key="config.key">
                                                    <tr class="flex flex-col py-1.5 lg:py-0 lg:table-row border-b border-slate-100 dark:border-slate-800/75 last:border-0 lg:hover:bg-slate-50/75 lg:dark:hover:bg-slate-800/40 transition-colors"
                                                        x-show="config.shouldShow">
                                                        <td class="lg:w-1/4 flex-shrink-0 align-top py-1 lg:py-2.5 pl-6 lg:pl-4 font-medium text-slate-500 dark:text-slate-400">
                                                            <span x-html="formatted(config.name)"></span>
                                                        </td>
                                                        <td class="py-1 lg:py-2.5 px-6 lg:px-4 text-slate-600 dark:text-slate-300" style="overflow-wrap: anywhere"
                                                            :class="isNoValue(config.localValue) && 'text-slate-300 dark:text-slate-600 italic'">
                                                            <span x-show="group.headings.length > 0" class="empty:hidden inline-block w-14 text-center lg:hidden py-0.5 mr-1 text-xs font-medium rounded border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400" x-text="group.shortHeadings[1]"></span>
                                                            <span x-html="isLongValue(config.localValue) && !config.expanded ? truncateValue(formatted(config.localValue)) : formatted(config.localValue)"></span>
                                                            <button x-show="isLongValue(config.localValue)" @click="config.expanded = !config.expanded" class="ml-1 text-xs text-blue-600 dark:text-blue-400 hover:underline" x-text="config.expanded ? 'show less' : 'show more'"></button>
                                                        </td>
                                                        <td x-show="config.hasMasterValue" class="py-1 lg:py-2.5 px-6 lg:px-4 text-slate-600 dark:text-slate-300" style="overflow-wrap: anywhere"
                                                            :class="isNoValue(config.masterValue) && 'text-slate-300 dark:text-slate-600 italic'">
                                                            <span x-show="group.headings.length > 0" class="empty:hidden inline-block w-14 text-center lg:hidden py-0.5 mr-1 text-xs font-medium rounded border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400" x-text="group.shortHeadings[2]"></span>
                                                            <span x-html="formatted(config.masterValue)"></span>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div x-show="group.note" x-html='group.note ? group.note.replaceAll("\n","<br>") : ""'
                                         class="my-3 text-xs md:text-sm text-slate-500 dark:text-slate-400 md:border border-slate-200 dark:border-slate-800 p-4 md:rounded-lg"></div>
                                </div>
                            </template>
                        </section>
                    </template>
                </div>
            </article>
        </div>
    </div>

    <div x-cloak x-transition.opacity x-show="mobileNav" class="absolute inset-0 overflow-hidden bg-slate-900/50 backdrop-blur-sm z-40">
        <div x-show="mobileNav" @click.away="hideMobileNav()" class="fixed top-0 bottom-0 right-0 w-80 bg-slate-800 dark:bg-slate-700 z-50">
            <nav class="absolute inset-0 overflow-y-auto p-6 pt-16 space-y-px text-white">
                <template x-for="module in info.modules" :key="module.key">
                    <a x-show="module.shouldShow"
                       :id="'mobile_nav_' + module.key"
                       :href="'#' + module.key" @click="hideMobileNav()"
                       class="px-4 py-1 rounded block"
                       :class="selected == module.key ? 'bg-slate-600' : ''"
                       @click="selectModule(module.key)"
                       x-text="module.name"></a>
                </template>
            </nav>
            <div class="absolute top-0 left-0 right-0 flex justify-end bg-gradient-to-b from-slate-800 dark:from-slate-700 via-slate-800/75 dark:via-slate-700/75 to-transparent">
                <button @click="hideMobileNav()" class="mt-3 mr-4 p-2 bg-slate-800 dark:bg-slate-700 text-slate-400 border border-slate-600 rounded">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('Navigation', () => ({
            hash: null,
            mobileNav: false,
            info: <?php echo json_encode($info) ?>,
            sections: <?php echo json_encode(array_column($info['modules'], 'key')) ?>,
            selected: null,
            selectedIndex: null,
            initialized: false,
            search: null,
            searchFocused: false,
            emptyState: false,
            init() {
                if(window.location.hash != '') this.hash = window.location.hash.replace("#","");
                window.addEventListener('hashchange', () => this.hash = window.location.hash.replace("#",""), false);

                document.addEventListener('alpine:initialized', () => {
                    if(this.hash) {
                        document.querySelector(`#${this.hash}`) && document.querySelector(`#${this.hash}`).scrollIntoView();
                        this.selectModule(this.isModule(this.hash) ? this.hash : this.firstSectionVisible());
                    } else {
                        this.selectModule(this.firstSectionVisible());
                    }

                    let nav = document.querySelector(`#nav_${this.selected}`);
                    if(nav) nav.scrollIntoView({block: "center"});
                    this.initialized = true;
                });

                this.allVisible();
                this.$watch('search', () => this.isFiltered() ? this.applyVisibleFlags() : this.allVisible());
            },
            allVisible() {
                this.info.modules.forEach((module) => {
                    module.groups.forEach((group) => {
                        group.configs.forEach((config) => config.shouldShow = true);
                        group.shouldShow = true;
                    });
                    module.shouldShow = true;
                });
                this.emptyState = false;
            },
            applyVisibleFlags() {
                let term = this.search.toLowerCase();
                this.info.modules.forEach((module) => {
                    let moduleNameMatch = module.name.toLowerCase().includes(term);
                    module.groups.forEach((group) => {
                        group.configs.forEach((config) => {
                            config.shouldShow = moduleNameMatch
                                || config.name.toLowerCase().includes(term)
                                || (config.localValue && config.localValue.toLowerCase().includes(term))
                                || (config.hasMasterValue && config.masterValue && config.masterValue.toLowerCase().includes(term));
                        });
                        group.shouldShow = moduleNameMatch
                            ? group.configs.length > 0
                            : group.configs.filter((config) => config.shouldShow).length > 0;
                    });
                    module.shouldShow = moduleNameMatch || module.groups.filter((group) => group.shouldShow).length > 0;
                });
                this.emptyState = this.info.modules.filter((module) => module.shouldShow).length === 0;
            },
            isFiltered() { return !this.isUnfiltered(); },
            isUnfiltered() { return this.search == null || this.search == ''; },
            isNoValue(val) { return val == null || val === 'no value'; },
            isLongValue(val) { return val && val.length > 200; },
            truncateValue(html) {
                if(!html) return html;
                let text = html.replace(/<[^>]+>/g, '');
                if(text.length <= 200) return html;
                return html.substring(0, 200) + '...';
            },
            firstSectionVisible() {
                let first = Array.from(document.querySelectorAll('section')).filter((s) =>
                    s && s.getBoundingClientRect().bottom > 100
                )[0];
                return first ? first.id : null;
            },
            enter(key) {
                let index = this.sections.indexOf(key);
                if (this.initialized && (this.selectedIndex == null || index < this.selectedIndex || this.selectedNoLongerVisible())) {
                    this.select(index);
                }
            },
            leave(key) {
                if (this.initialized && (this.selectedIndex == null || this.selectedIndex == this.sections.indexOf(key) || this.selectedNoLongerVisible())) {
                    this.selectNextIndex();
                }
            },
            jump(key) { this.selectModule(key); },
            isModule(key) { return this.sections.indexOf(key) > -1; },
            select(index) {
                if(this.sections[index] === undefined) return;
                this.selectedIndex = index;
                this.selected = this.sections[index];
                this.scrollIntoView();
            },
            selectNextIndex() {
                if(this.isUnfiltered()) return this.select(this.selectedIndex + 1);
                this.selectModule(this.firstSectionVisible());
            },
            selectModule(key) {
                if(this.isModule(key)) this.select(this.sections.indexOf(key));
            },
            selectedNoLongerVisible() {
                let el = document.querySelector("#" + this.selected);
                return el == null || el.getBoundingClientRect().bottom < 100;
            },
            scrollIntoView() {
                let el = document.querySelector(`#nav_${this.selected}`);
                if(el) el.scrollIntoView({block: "nearest"});
            },
            showMobileNav() {
                document.body.style = "overflow-y: hidden";
                this.mobileNav = true;
                this.$nextTick(() => { let el = document.querySelector(`#mobile_nav_${this.selected}`); if(el) el.scrollIntoView({block: "center"}); });
            },
            hideMobileNav() {
                document.body.style = "";
                this.mobileNav = false;
            },
            escapeRegex(str) { return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); },
            formatted(text) {
                if(text) text = text.replaceAll("\n", "<br>");
                if(this.isUnfiltered() || text == null) return text;
                try { return text.replace(new RegExp(this.escapeRegex(this.search),"gi"), "<mark>$&</mark>"); }
                catch(e) { return text; }
            }
        }))
    });
</script>
<script type="module">
    // Alpine's Intersect plugin v3.10.5
(()=>{function c(e){e.directive("intersect",(t,{value:i,expression:l,modifiers:n},{evaluateLater:r,cleanup:o})=>{let s=r(l),d={rootMargin:p(n),threshold:f(n)},u=new IntersectionObserver(h=>{h.forEach(a=>{a.isIntersecting!==(i==="leave")&&(s(),n.includes("once")&&u.disconnect())})},d);u.observe(t),o(()=>{u.disconnect()})})}function f(e){if(e.includes("full"))return .99;if(e.includes("half"))return .5;if(!e.includes("threshold"))return 0;let t=e[e.indexOf("threshold")+1];return t==="100"?1:t==="0"?0:Number(`.${t}`)}function x(e){let t=e.match(/^(-?[0-9]+)(px|%)?$/);return t?t[1]+(t[2]||"px"):void 0}function p(e){let t="margin",i="0px 0px 0px 0px",l=e.indexOf(t);if(l===-1)return i;let n=[];for(let r=1;r<5;r++)n.push(x(e[l+r]||""));return n=n.filter(r=>r!==void 0),n.length?n.join(" ").trim():i}document.addEventListener("alpine:init",()=>{window.Alpine.plugin(c)});})();

// Alpine v3.10.5
(()=>{var We=!1,Ge=!1,B=[];function $t(e){an(e)}function an(e){B.includes(e)||B.push(e),cn()}function he(e){let t=B.indexOf(e);t!==-1&&B.splice(t,1)}function cn(){!Ge&&!We&&(We=!0,queueMicrotask(ln))}function ln(){We=!1,Ge=!0;for(let e=0;e<B.length;e++)B[e]();B.length=0,Ge=!1}var A,K,Y,Ye,Je=!0;function Lt(e){Je=!1,e(),Je=!0}function jt(e){A=e.reactive,Y=e.release,K=t=>e.effect(t,{scheduler:r=>{Je?$t(r):r()}}),Ye=e.raw}function Ze(e){K=e}function Ft(e){let t=()=>{};return[n=>{let i=K(n);return e._x_effects||(e._x_effects=new Set,e._x_runEffects=()=>{e._x_effects.forEach(o=>o())}),e._x_effects.add(i),t=()=>{i!==void 0&&(e._x_effects.delete(i),Y(i))},i},()=>{t()}]}var Bt=[],Kt=[],zt=[];function Vt(e){zt.push(e)}function _e(e,t){typeof t=="function"?(e._x_cleanups||(e._x_cleanups=[]),e._x_cleanups.push(t)):(t=e,Kt.push(t))}function Ht(e){Bt.push(e)}function qt(e,t,r){e._x_attributeCleanups||(e._x_attributeCleanups={}),e._x_attributeCleanups[t]||(e._x_attributeCleanups[t]=[]),e._x_attributeCleanups[t].push(r)}function Qe(e,t){!e._x_attributeCleanups||Object.entries(e._x_attributeCleanups).forEach(([r,n])=>{(t===void 0||t.includes(r))&&(n.forEach(i=>i()),delete e._x_attributeCleanups[r])})}var et=new MutationObserver(Xe),tt=!1;function rt(){et.observe(document,{subtree:!0,childList:!0,attributes:!0,attributeOldValue:!0}),tt=!0}function fn(){un(),et.disconnect(),tt=!1}var te=[],nt=!1;function un(){te=te.concat(et.takeRecords()),te.length&&!nt&&(nt=!0,queueMicrotask(()=>{dn(),nt=!1}))}function dn(){Xe(te),te.length=0}function m(e){if(!tt)return e();fn();let t=e();return rt(),t}var it=!1,ge=[];function Ut(){it=!0}function Wt(){it=!1,Xe(ge),ge=[]}function Xe(e){if(it){ge=ge.concat(e);return}let t=[],r=[],n=new Map,i=new Map;for(let o=0;o<e.length;o++)if(!e[o].target._x_ignoreMutationObserver&&(e[o].type==="childList"&&(e[o].addedNodes.forEach(s=>s.nodeType===1&&t.push(s)),e[o].removedNodes.forEach(s=>s.nodeType===1&&r.push(s))),e[o].type==="attributes")){let s=e[o].target,a=e[o].attributeName,c=e[o].oldValue,l=()=>{n.has(s)||n.set(s,[]),n.get(s).push({name:a,value:s.getAttribute(a)})},u=()=>{i.has(s)||i.set(s,[]),i.get(s).push(a)};s.hasAttribute(a)&&c===null?l():s.hasAttribute(a)?(u(),l()):u()}i.forEach((o,s)=>{Qe(s,o)}),n.forEach((o,s)=>{Bt.forEach(a=>a(s,o))});for(let o of r)if(!t.includes(o)&&(Kt.forEach(s=>s(o)),o._x_cleanups))for(;o._x_cleanups.length;)o._x_cleanups.pop()();t.forEach(o=>{o._x_ignoreSelf=!0,o._x_ignore=!0});for(let o of t)r.includes(o)||!o.isConnected||(delete o._x_ignoreSelf,delete o._x_ignore,zt.forEach(s=>s(o)),o._x_ignore=!0,o._x_ignoreSelf=!0);t.forEach(o=>{delete o._x_ignoreSelf,delete o._x_ignore}),t=null,r=null,n=null,i=null}function xe(e){return D(k(e))}function C(e,t,r){return e._x_dataStack=[t,...k(r||e)],()=>{e._x_dataStack=e._x_dataStack.filter(n=>n!==t)}}function ot(e,t){let r=e._x_dataStack[0];Object.entries(t).forEach(([n,i])=>{r[n]=i})}function k(e){return e._x_dataStack?e._x_dataStack:typeof ShadowRoot=="function"&&e instanceof ShadowRoot?k(e.host):e.parentNode?k(e.parentNode):[]}function D(e){let t=new Proxy({},{ownKeys:()=>Array.from(new Set(e.flatMap(r=>Object.keys(r)))),has:(r,n)=>e.some(i=>i.hasOwnProperty(n)),get:(r,n)=>(e.find(i=>{if(i.hasOwnProperty(n)){let o=Object.getOwnPropertyDescriptor(i,n);if(o.get&&o.get._x_alreadyBound||o.set&&o.set._x_alreadyBound)return!0;if((o.get||o.set)&&o.enumerable){let s=o.get,a=o.set,c=o;s=s&&s.bind(t),a=a&&a.bind(t),s&&(s._x_alreadyBound=!0),a&&(a._x_alreadyBound=!0),Object.defineProperty(i,n,{...c,get:s,set:a})}return!0}return!1})||{})[n],set:(r,n,i)=>{let o=e.find(s=>s.hasOwnProperty(n));return o?o[n]=i:e[e.length-1][n]=i,!0}});return t}function ye(e){let t=n=>typeof n=="object"&&!Array.isArray(n)&&n!==null,r=(n,i="")=>{Object.entries(Object.getOwnPropertyDescriptors(n)).forEach(([o,{value:s,enumerable:a}])=>{if(a===!1||s===void 0)return;let c=i===""?o:`${i}.${o}`;typeof s=="object"&&s!==null&&s._x_interceptor?n[o]=s.initialize(e,c,o):t(s)&&s!==n&&!(s instanceof Element)&&r(s,c)})};return r(e)}function be(e,t=()=>{}){let r={initialValue:void 0,_x_interceptor:!0,initialize(n,i,o){return e(this.initialValue,()=>pn(n,i),s=>st(n,i,s),i,o)}};return t(r),n=>{if(typeof n=="object"&&n!==null&&n._x_interceptor){let i=r.initialize.bind(r);r.initialize=(o,s,a)=>{let c=n.initialize(o,s,a);return r.initialValue=c,i(o,s,a)}}else r.initialValue=n;return r}}function pn(e,t){return t.split(".").reduce((r,n)=>r[n],e)}function st(e,t,r){if(typeof t=="string"&&(t=t.split(".")),t.length===1)e[t[0]]=r;else{if(t.length===0)throw error;return e[t[0]]||(e[t[0]]={}),st(e[t[0]],t.slice(1),r)}}var Gt={};function x(e,t){Gt[e]=t}function re(e,t){return Object.entries(Gt).forEach(([r,n])=>{Object.defineProperty(e,`$${r}`,{get(){let[i,o]=at(t);return i={interceptor:be,...i},_e(t,o),n(t,i)},enumerable:!1})}),e}function Yt(e,t,r,...n){try{return r(...n)}catch(i){J(i,e,t)}}function J(e,t,r=void 0){Object.assign(e,{el:t,expression:r}),console.warn(`Alpine Expression Error: ${e.message}

${r?'Expression: "'+r+`"

`:""}`,t),setTimeout(()=>{throw e},0)}var ve=!0;function Jt(e){let t=ve;ve=!1,e(),ve=t}function P(e,t,r={}){let n;return g(e,t)(i=>n=i,r),n}function g(...e){return Zt(...e)}var Zt=ct;function Qt(e){Zt=e}function ct(e,t){let r={};re(r,e);let n=[r,...k(e)];if(typeof t=="function")return mn(n,t);let i=hn(n,t,e);return Yt.bind(null,e,t,i)}function mn(e,t){return(r=()=>{},{scope:n={},params:i=[]}={})=>{let o=t.apply(D([n,...e]),i);we(r,o)}}var lt={};function _n(e,t){if(lt[e])return lt[e];let r=Object.getPrototypeOf(async function(){}).constructor,n=/^[\n\s]*if.*\(.*\)/.test(e)||/^(let|const)\s/.test(e)?`(() => { ${e} })()`:e,o=(()=>{try{return new r(["__self","scope"],`with (scope) { __self.result = ${n} }; __self.finished = true; return __self.result;`)}catch(s){return J(s,t,e),Promise.resolve()}})();return lt[e]=o,o}function hn(e,t,r){let n=_n(t,r);return(i=()=>{},{scope:o={},params:s=[]}={})=>{n.result=void 0,n.finished=!1;let a=D([o,...e]);if(typeof n=="function"){let c=n(n,a).catch(l=>J(l,r,t));n.finished?(we(i,n.result,a,s,r),n.result=void 0):c.then(l=>{we(i,l,a,s,r)}).catch(l=>J(l,r,t)).finally(()=>n.result=void 0)}}}function we(e,t,r,n,i){if(ve&&typeof t=="function"){let o=t.apply(r,n);o instanceof Promise?o.then(s=>we(e,s,r,n)).catch(s=>J(s,i,t)):e(o)}else e(t)}var ut="x-";function E(e=""){return ut+e}function Xt(e){ut=e}var er={};function d(e,t){er[e]=t}function ne(e,t,r){if(t=Array.from(t),e._x_virtualDirectives){let o=Object.entries(e._x_virtualDirectives).map(([a,c])=>({name:a,value:c})),s=ft(o);o=o.map(a=>s.find(c=>c.name===a.name)?{name:`x-bind:${a.name}`,value:`"${a.value}"`}:a),t=t.concat(o)}let n={};return t.map(tr((o,s)=>n[o]=s)).filter(rr).map(xn(n,r)).sort(yn).map(o=>gn(e,o))}function ft(e){return Array.from(e).map(tr()).filter(t=>!rr(t))}var dt=!1,ie=new Map,nr=Symbol();function ir(e){dt=!0;let t=Symbol();nr=t,ie.set(t,[]);let r=()=>{for(;ie.get(t).length;)ie.get(t).shift()();ie.delete(t)},n=()=>{dt=!1,r()};e(r),n()}function at(e){let t=[],r=a=>t.push(a),[n,i]=Ft(e);return t.push(i),[{Alpine:I,effect:n,cleanup:r,evaluateLater:g.bind(g,e),evaluate:P.bind(P,e)},()=>t.forEach(a=>a())]}function gn(e,t){let r=()=>{},n=er[t.type]||r,[i,o]=at(e);qt(e,t.original,o);let s=()=>{e._x_ignore||e._x_ignoreSelf||(n.inline&&n.inline(e,t,i),n=n.bind(n,e,t,i),dt?ie.get(nr).push(n):n())};return s.runCleanups=o,s}var Ee=(e,t)=>({name:r,value:n})=>(r.startsWith(e)&&(r=r.replace(e,t)),{name:r,value:n}),Se=e=>e;function tr(e=()=>{}){return({name:t,value:r})=>{let{name:n,value:i}=or.reduce((o,s)=>s(o),{name:t,value:r});return n!==t&&e(n,t),{name:n,value:i}}}var or=[];function Z(e){or.push(e)}function rr({name:e}){return sr().test(e)}var sr=()=>new RegExp(`^${ut}([^:^.]+)\\b`);function xn(e,t){return({name:r,value:n})=>{let i=r.match(sr()),o=r.match(/:([a-zA-Z0-9\-:]+)/),s=r.match(/\.[^.\]]+(?=[^\]]*$)/g)||[],a=t||e[r]||r;return{type:i?i[1]:null,value:o?o[1]:null,modifiers:s.map(c=>c.replace(".","")),expression:n,original:a}}}var pt="DEFAULT",Ae=["ignore","ref","data","id","radio","tabs","switch","disclosure","menu","listbox","list","item","combobox","bind","init","for","mask","model","modelable","transition","show","if",pt,"teleport"];function yn(e,t){let r=Ae.indexOf(e.type)===-1?pt:e.type,n=Ae.indexOf(t.type)===-1?pt:t.type;return Ae.indexOf(r)-Ae.indexOf(n)}function z(e,t,r={}){e.dispatchEvent(new CustomEvent(t,{detail:r,bubbles:!0,composed:!0,cancelable:!0}))}var mt=[],ht=!1;function Te(e=()=>{}){return queueMicrotask(()=>{ht||setTimeout(()=>{Oe()})}),new Promise(t=>{mt.push(()=>{e(),t()})})}function Oe(){for(ht=!1;mt.length;)mt.shift()()}function ar(){ht=!0}function R(e,t){if(typeof ShadowRoot=="function"&&e instanceof ShadowRoot){Array.from(e.children).forEach(i=>R(i,t));return}let r=!1;if(t(e,()=>r=!0),r)return;let n=e.firstElementChild;for(;n;)R(n,t,!1),n=n.nextElementSibling}function O(e,...t){console.warn(`Alpine Warning: ${e}`,...t)}function lr(){document.body||O("Unable to initialize. Trying to load Alpine before `<body>` is available. Did you forget to add `defer` in Alpine's `<script>` tag?"),z(document,"alpine:init"),z(document,"alpine:initializing"),rt(),Vt(t=>w(t,R)),_e(t=>bn(t)),Ht((t,r)=>{ne(t,r).forEach(n=>n())});let e=t=>!V(t.parentElement,!0);Array.from(document.querySelectorAll(cr())).filter(e).forEach(t=>{w(t)}),z(document,"alpine:initialized")}var _t=[],ur=[];function fr(){return _t.map(e=>e())}function cr(){return _t.concat(ur).map(e=>e())}function Ce(e){_t.push(e)}function Re(e){ur.push(e)}function V(e,t=!1){return Q(e,r=>{if((t?cr():fr()).some(i=>r.matches(i)))return!0})}function Q(e,t){if(!!e){if(t(e))return e;if(e._x_teleportBack&&(e=e._x_teleportBack),!!e.parentElement)return Q(e.parentElement,t)}}function dr(e){return fr().some(t=>e.matches(t))}function w(e,t=R){ir(()=>{t(e,(r,n)=>{ne(r,r.attributes).forEach(i=>i()),r._x_ignore&&n()})})}function bn(e){R(e,t=>Qe(t))}function oe(e,t){return Array.isArray(t)?pr(e,t.join(" ")):typeof t=="object"&&t!==null?vn(e,t):typeof t=="function"?oe(e,t()):pr(e,t)}function pr(e,t){let r=o=>o.split(" ").filter(Boolean),n=o=>o.split(" ").filter(s=>!e.classList.contains(s)).filter(Boolean),i=o=>(e.classList.add(...o),()=>{e.classList.remove(...o)});return t=t===!0?t="":t||"",i(n(t))}function vn(e,t){let r=a=>a.split(" ").filter(Boolean),n=Object.entries(t).flatMap(([a,c])=>c?r(a):!1).filter(Boolean),i=Object.entries(t).flatMap(([a,c])=>c?!1:r(a)).filter(Boolean),o=[],s=[];return i.forEach(a=>{e.classList.contains(a)&&(e.classList.remove(a),s.push(a))}),n.forEach(a=>{e.classList.contains(a)||(e.classList.add(a),o.push(a))}),()=>{s.forEach(a=>e.classList.add(a)),o.forEach(a=>e.classList.remove(a))}}function H(e,t){return typeof t=="object"&&t!==null?wn(e,t):En(e,t)}function wn(e,t){let r={};return Object.entries(t).forEach(([n,i])=>{r[n]=e.style[n],n.startsWith("--")||(n=Sn(n)),e.style.setProperty(n,i)}),setTimeout(()=>{e.style.length===0&&e.removeAttribute("style")}),()=>{H(e,r)}}function En(e,t){let r=e.getAttribute("style",t);return e.setAttribute("style",t),()=>{e.setAttribute("style",r||"")}}function Sn(e){return e.replace(/([a-z])([A-Z])/g,"$1-$2").toLowerCase()}function se(e,t=()=>{}){let r=!1;return function(){r?t.apply(this,arguments):(r=!0,e.apply(this,arguments))}}d("transition",(e,{value:t,modifiers:r,expression:n},{evaluate:i})=>{typeof n=="function"&&(n=i(n)),n?An(e,n,t):On(e,r,t)});function An(e,t,r){mr(e,oe,""),{enter:i=>{e._x_transition.enter.during=i},"enter-start":i=>{e._x_transition.enter.start=i},"enter-end":i=>{e._x_transition.enter.end=i},leave:i=>{e._x_transition.leave.during=i},"leave-start":i=>{e._x_transition.leave.start=i},"leave-end":i=>{e._x_transition.leave.end=i}}[r](t)}function On(e,t,r){mr(e,H);let n=!t.includes("in")&&!t.includes("out")&&!r,i=n||t.includes("in")||["enter"].includes(r),o=n||t.includes("out")||["leave"].includes(r);t.includes("in")&&!n&&(t=t.filter((h,b)=>b<t.indexOf("out"))),t.includes("out")&&!n&&(t=t.filter((h,b)=>b>t.indexOf("out")));let s=!t.includes("opacity")&&!t.includes("scale"),a=s||t.includes("opacity"),c=s||t.includes("scale"),l=a?0:1,u=c?ae(t,"scale",95)/100:1,p=ae(t,"delay",0),y=ae(t,"origin","center"),N="opacity, transform",W=ae(t,"duration",150)/1e3,pe=ae(t,"duration",75)/1e3,f="cubic-bezier(0.4, 0.0, 0.2, 1)";i&&(e._x_transition.enter.during={transformOrigin:y,transitionDelay:p,transitionProperty:N,transitionDuration:`${W}s`,transitionTimingFunction:f},e._x_transition.enter.start={opacity:l,transform:`scale(${u})`},e._x_transition.enter.end={opacity:1,transform:"scale(1)"}),o&&(e._x_transition.leave.during={transformOrigin:y,transitionDelay:p,transitionProperty:N,transitionDuration:`${pe}s`,transitionTimingFunction:f},e._x_transition.leave.start={opacity:1,transform:"scale(1)"},e._x_transition.leave.end={opacity:l,transform:`scale(${u})`})}function mr(e,t,r={}){e._x_transition||(e._x_transition={enter:{during:r,start:r,end:r},leave:{during:r,start:r,end:r},in(n=()=>{},i=()=>{}){Me(e,t,{during:this.enter.during,start:this.enter.start,end:this.enter.end},n,i)},out(n=()=>{},i=()=>{}){Me(e,t,{during:this.leave.during,start:this.leave.start,end:this.leave.end},n,i)}})}window.Element.prototype._x_toggleAndCascadeWithTransitions=function(e,t,r,n){let i=document.visibilityState==="visible"?requestAnimationFrame:setTimeout,o=()=>i(r);if(t){e._x_transition&&(e._x_transition.enter||e._x_transition.leave)?e._x_transition.enter&&(Object.entries(e._x_transition.enter.during).length||Object.entries(e._x_transition.enter.start).length||Object.entries(e._x_transition.enter.end).length)?e._x_transition.in(r):o():e._x_transition?e._x_transition.in(r):o();return}e._x_hidePromise=e._x_transition?new Promise((s,a)=>{e._x_transition.out(()=>{},()=>s(n)),e._x_transitioning.beforeCancel(()=>a({isFromCancelledTransition:!0}))}):Promise.resolve(n),queueMicrotask(()=>{let s=hr(e);s?(s._x_hideChildren||(s._x_hideChildren=[]),s._x_hideChildren.push(e)):i(()=>{let a=c=>{let l=Promise.all([c._x_hidePromise,...(c._x_hideChildren||[]).map(a)]).then(([u])=>u());return delete c._x_hidePromise,delete c._x_hideChildren,l};a(e).catch(c=>{if(!c.isFromCancelledTransition)throw c})})})};function hr(e){let t=e.parentNode;if(!!t)return t._x_hidePromise?t:hr(t)}function Me(e,t,{during:r,start:n,end:i}={},o=()=>{},s=()=>{}){if(e._x_transitioning&&e._x_transitioning.cancel(),Object.keys(r).length===0&&Object.keys(n).length===0&&Object.keys(i).length===0){o(),s();return}let a,c,l;Tn(e,{start(){a=t(e,n)},during(){c=t(e,r)},before:o,end(){a(),l=t(e,i)},after:s,cleanup(){c(),l()}})}function Tn(e,t){let r,n,i,o=se(()=>{m(()=>{r=!0,n||t.before(),i||(t.end(),Oe()),t.after(),e.isConnected&&t.cleanup(),delete e._x_transitioning})});e._x_transitioning={beforeCancels:[],beforeCancel(s){this.beforeCancels.push(s)},cancel:se(function(){for(;this.beforeCancels.length;)this.beforeCancels.shift()();o()}),finish:o},m(()=>{t.start(),t.during()}),ar(),requestAnimationFrame(()=>{if(r)return;let s=Number(getComputedStyle(e).transitionDuration.replace(/,.*/,"").replace("s",""))*1e3,a=Number(getComputedStyle(e).transitionDelay.replace(/,.*/,"").replace("s",""))*1e3;s===0&&(s=Number(getComputedStyle(e).animationDuration.replace("s",""))*1e3),m(()=>{t.before()}),n=!0,requestAnimationFrame(()=>{r||(m(()=>{t.end()}),Oe(),setTimeout(e._x_transitioning.finish,s+a),i=!0)})})}function ae(e,t,r){if(e.indexOf(t)===-1)return r;let n=e[e.indexOf(t)+1];if(!n||t==="scale"&&isNaN(n))return r;if(t==="duration"){let i=n.match(/([0-9]+)ms/);if(i)return i[1]}return t==="origin"&&["top","right","left","center","bottom"].includes(e[e.indexOf(t)+2])?[n,e[e.indexOf(t)+2]].join(" "):n}var gt=!1;function $(e,t=()=>{}){return(...r)=>gt?t(...r):e(...r)}function _r(e,t){t._x_dataStack||(t._x_dataStack=e._x_dataStack),gt=!0,Rn(()=>{Cn(t)}),gt=!1}function Cn(e){let t=!1;w(e,(n,i)=>{R(n,(o,s)=>{if(t&&dr(o))return s();t=!0,i(o,s)})})}function Rn(e){let t=K;Ze((r,n)=>{let i=t(r);return Y(i),()=>{}}),e(),Ze(t)}function ce(e,t,r,n=[]){switch(e._x_bindings||(e._x_bindings=A({})),e._x_bindings[t]=r,t=n.includes("camel")?Dn(t):t,t){case"value":Mn(e,r);break;case"style":Pn(e,r);break;case"class":Nn(e,r);break;default:kn(e,t,r);break}}function Mn(e,t){if(e.type==="radio")e.attributes.value===void 0&&(e.value=t),window.fromModel&&(e.checked=gr(e.value,t));else if(e.type==="checkbox")Number.isInteger(t)?e.value=t:!Number.isInteger(t)&&!Array.isArray(t)&&typeof t!="boolean"&&![null,void 0].includes(t)?e.value=String(t):Array.isArray(t)?e.checked=t.some(r=>gr(r,e.value)):e.checked=!!t;else if(e.tagName==="SELECT")In(e,t);else{if(e.value===t)return;e.value=t}}function Nn(e,t){e._x_undoAddedClasses&&e._x_undoAddedClasses(),e._x_undoAddedClasses=oe(e,t)}function Pn(e,t){e._x_undoAddedStyles&&e._x_undoAddedStyles(),e._x_undoAddedStyles=H(e,t)}function kn(e,t,r){[null,void 0,!1].includes(r)&&Ln(t)?e.removeAttribute(t):(xr(t)&&(r=t),$n(e,t,r))}function $n(e,t,r){e.getAttribute(t)!=r&&e.setAttribute(t,r)}function In(e,t){let r=[].concat(t).map(n=>n+"");Array.from(e.options).forEach(n=>{n.selected=r.includes(n.value)})}function Dn(e){return e.toLowerCase().replace(/-(\w)/g,(t,r)=>r.toUpperCase())}function gr(e,t){return e==t}function xr(e){return["disabled","checked","required","readonly","hidden","open","selected","autofocus","itemscope","multiple","novalidate","allowfullscreen","allowpaymentrequest","formnovalidate","autoplay","controls","loop","muted","playsinline","default","ismap","reversed","async","defer","nomodule"].includes(e)}function Ln(e){return!["aria-pressed","aria-checked","aria-expanded","aria-selected"].includes(e)}function yr(e,t,r){if(e._x_bindings&&e._x_bindings[t]!==void 0)return e._x_bindings[t];let n=e.getAttribute(t);return n===null?typeof r=="function"?r():r:n===""?!0:xr(t)?!![t,"true"].includes(n):n}function Ne(e,t){var r;return function(){var n=this,i=arguments,o=function(){r=null,e.apply(n,i)};clearTimeout(r),r=setTimeout(o,t)}}function Pe(e,t){let r;return function(){let n=this,i=arguments;r||(e.apply(n,i),r=!0,setTimeout(()=>r=!1,t))}}function br(e){e(I)}var q={},vr=!1;function wr(e,t){if(vr||(q=A(q),vr=!0),t===void 0)return q[e];q[e]=t,typeof t=="object"&&t!==null&&t.hasOwnProperty("init")&&typeof t.init=="function"&&q[e].init(),ye(q[e])}function Er(){return q}var Sr={};function Ar(e,t){let r=typeof t!="function"?()=>t:t;e instanceof Element?xt(e,r()):Sr[e]=r}function Or(e){return Object.entries(Sr).forEach(([t,r])=>{Object.defineProperty(e,t,{get(){return(...n)=>r(...n)}})}),e}function xt(e,t,r){let n=[];for(;n.length;)n.pop()();let i=Object.entries(t).map(([s,a])=>({name:s,value:a})),o=ft(i);i=i.map(s=>o.find(a=>a.name===s.name)?{name:`x-bind:${s.name}`,value:`"${s.value}"`}:s),ne(e,i,r).map(s=>{n.push(s.runCleanups),s()})}var Tr={};function Cr(e,t){Tr[e]=t}function Rr(e,t){return Object.entries(Tr).forEach(([r,n])=>{Object.defineProperty(e,r,{get(){return(...i)=>n.bind(t)(...i)},enumerable:!1})}),e}var jn={get reactive(){return A},get release(){return Y},get effect(){return K},get raw(){return Ye},version:"3.10.5",flushAndStopDeferringMutations:Wt,dontAutoEvaluateFunctions:Jt,disableEffectScheduling:Lt,setReactivityEngine:jt,closestDataStack:k,skipDuringClone:$,addRootSelector:Ce,addInitSelector:Re,addScopeToNode:C,deferMutations:Ut,mapAttributes:Z,evaluateLater:g,setEvaluator:Qt,mergeProxies:D,findClosest:Q,closestRoot:V,interceptor:be,transition:Me,setStyles:H,mutateDom:m,directive:d,throttle:Pe,debounce:Ne,evaluate:P,initTree:w,nextTick:Te,prefixed:E,prefix:Xt,plugin:br,magic:x,store:wr,start:lr,clone:_r,bound:yr,$data:xe,data:Cr,bind:Ar},I=jn;function yt(e,t){let r=Object.create(null),n=e.split(",");for(let i=0;i<n.length;i++)r[n[i]]=!0;return t?i=>!!r[i.toLowerCase()]:i=>!!r[i]}var ns={[1]:"TEXT",[2]:"CLASS",[4]:"STYLE",[8]:"PROPS",[16]:"FULL_PROPS",[32]:"HYDRATE_EVENTS",[64]:"STABLE_FRAGMENT",[128]:"KEYED_FRAGMENT",[256]:"UNKEYED_FRAGMENT",[512]:"NEED_PATCH",[1024]:"DYNAMIC_SLOTS",[2048]:"DEV_ROOT_FRAGMENT",[-1]:"HOISTED",[-2]:"BAIL"},is={[1]:"STABLE",[2]:"DYNAMIC",[3]:"FORWARDED"};var Fn="itemscope,allowfullscreen,formnovalidate,ismap,nomodule,novalidate,readonly";var os=yt(Fn+",async,autofocus,autoplay,controls,default,defer,disabled,hidden,loop,open,required,reversed,scoped,seamless,checked,muted,multiple,selected");var Mr=Object.freeze({}),ss=Object.freeze([]);var bt=Object.assign;var Bn=Object.prototype.hasOwnProperty,le=(e,t)=>Bn.call(e,t),L=Array.isArray,X=e=>Nr(e)==="[object Map]";var Kn=e=>typeof e=="string",ke=e=>typeof e=="symbol",ue=e=>e!==null&&typeof e=="object";var zn=Object.prototype.toString,Nr=e=>zn.call(e),vt=e=>Nr(e).slice(8,-1);var De=e=>Kn(e)&&e!=="NaN"&&e[0]!=="-"&&""+parseInt(e,10)===e;var Ie=e=>{let t=Object.create(null);return r=>t[r]||(t[r]=e(r))},Vn=/-(\w)/g,as=Ie(e=>e.replace(Vn,(t,r)=>r?r.toUpperCase():"")),Hn=/\B([A-Z])/g,cs=Ie(e=>e.replace(Hn,"-$1").toLowerCase()),wt=Ie(e=>e.charAt(0).toUpperCase()+e.slice(1)),ls=Ie(e=>e?`on${wt(e)}`:""),Et=(e,t)=>e!==t&&(e===e||t===t);var St=new WeakMap,fe=[],M,U=Symbol("iterate"),At=Symbol("Map key iterate");function qn(e){return e&&e._isEffect===!0}function Pr(e,t=Mr){qn(e)&&(e=e.raw);let r=Un(e,t);return t.lazy||r(),r}function Dr(e){e.active&&(kr(e),e.options.onStop&&e.options.onStop(),e.active=!1)}var Wn=0;function Un(e,t){let r=function(){if(!r.active)return e();if(!fe.includes(r)){kr(r);try{return Gn(),fe.push(r),M=r,e()}finally{fe.pop(),Ir(),M=fe[fe.length-1]}}};return r.id=Wn++,r.allowRecurse=!!t.allowRecurse,r._isEffect=!0,r.active=!0,r.raw=e,r.deps=[],r.options=t,r}function kr(e){let{deps:t}=e;if(t.length){for(let r=0;r<t.length;r++)t[r].delete(e);t.length=0}}var ee=!0,Ot=[];function Yn(){Ot.push(ee),ee=!1}function Gn(){Ot.push(ee),ee=!0}function Ir(){let e=Ot.pop();ee=e===void 0?!0:e}function T(e,t,r){if(!ee||M===void 0)return;let n=St.get(e);n||St.set(e,n=new Map);let i=n.get(r);i||n.set(r,i=new Set),i.has(M)||(i.add(M),M.deps.push(i),M.options.onTrack&&M.options.onTrack({effect:M,target:e,type:t,key:r}))}function j(e,t,r,n,i,o){let s=St.get(e);if(!s)return;let a=new Set,c=u=>{u&&u.forEach(p=>{(p!==M||p.allowRecurse)&&a.add(p)})};if(t==="clear")s.forEach(c);else if(r==="length"&&L(e))s.forEach((u,p)=>{(p==="length"||p>=n)&&c(u)});else switch(r!==void 0&&c(s.get(r)),t){case"add":L(e)?De(r)&&c(s.get("length")):(c(s.get(U)),X(e)&&c(s.get(At)));break;case"delete":L(e)||(c(s.get(U)),X(e)&&c(s.get(At)));break;case"set":X(e)&&c(s.get(U));break}let l=u=>{u.options.onTrigger&&u.options.onTrigger({effect:u,target:e,key:r,type:t,newValue:n,oldValue:i,oldTarget:o}),u.options.scheduler?u.options.scheduler(u):u()};a.forEach(l)}var Jn=yt("__proto__,__v_isRef,__isVue"),$r=new Set(Object.getOwnPropertyNames(Symbol).map(e=>Symbol[e]).filter(ke)),Zn=$e(),Qn=$e(!1,!0),Xn=$e(!0),ei=$e(!0,!0),Le={};["includes","indexOf","lastIndexOf"].forEach(e=>{let t=Array.prototype[e];Le[e]=function(...r){let n=_(this);for(let o=0,s=this.length;o<s;o++)T(n,"get",o+"");let i=t.apply(n,r);return i===-1||i===!1?t.apply(n,r.map(_)):i}});["push","pop","shift","unshift","splice"].forEach(e=>{let t=Array.prototype[e];Le[e]=function(...r){Yn();let n=t.apply(this,r);return Ir(),n}});function $e(e=!1,t=!1){return function(n,i,o){if(i==="__v_isReactive")return!e;if(i==="__v_isReadonly")return e;if(i==="__v_raw"&&o===(e?t?ri:jr:t?ti:Lr).get(n))return n;let s=L(n);if(!e&&s&&le(Le,i))return Reflect.get(Le,i,o);let a=Reflect.get(n,i,o);return(ke(i)?$r.has(i):Jn(i))||(e||T(n,"get",i),t)?a:Tt(a)?!s||!De(i)?a.value:a:ue(a)?e?Fr(a):je(a):a}}var ni=Br(),ii=Br(!0);function Br(e=!1){return function(r,n,i,o){let s=r[n];if(!e&&(i=_(i),s=_(s),!L(r)&&Tt(s)&&!Tt(i)))return s.value=i,!0;let a=L(r)&&De(n)?Number(n)<r.length:le(r,n),c=Reflect.set(r,n,i,o);return r===_(o)&&(a?Et(i,s)&&j(r,"set",n,i,s):j(r,"add",n,i)),c}}function oi(e,t){let r=le(e,t),n=e[t],i=Reflect.deleteProperty(e,t);return i&&r&&j(e,"delete",t,void 0,n),i}function si(e,t){let r=Reflect.has(e,t);return(!ke(t)||!$r.has(t))&&T(e,"has",t),r}function ai(e){return T(e,"iterate",L(e)?"length":U),Reflect.ownKeys(e)}var Kr={get:Zn,set:ni,deleteProperty:oi,has:si,ownKeys:ai},zr={get:Xn,set(e,t){return console.warn(`Set operation on key "${String(t)}" failed: target is readonly.`,e),!0},deleteProperty(e,t){return console.warn(`Delete operation on key "${String(t)}" failed: target is readonly.`,e),!0}},hs=bt({},Kr,{get:Qn,set:ii}),_s=bt({},zr,{get:ei}),Ct=e=>ue(e)?je(e):e,Rt=e=>ue(e)?Fr(e):e,Mt=e=>e,Fe=e=>Reflect.getPrototypeOf(e);function Be(e,t,r=!1,n=!1){e=e.__v_raw;let i=_(e),o=_(t);t!==o&&!r&&T(i,"get",t),!r&&T(i,"get",o);let{has:s}=Fe(i),a=n?Mt:r?Rt:Ct;if(s.call(i,t))return a(e.get(t));if(s.call(i,o))return a(e.get(o));e!==i&&e.get(t)}function Ke(e,t=!1){let r=this.__v_raw,n=_(r),i=_(e);return e!==i&&!t&&T(n,"has",e),!t&&T(n,"has",i),e===i?r.has(e):r.has(e)||r.has(i)}function ze(e,t=!1){return e=e.__v_raw,!t&&T(_(e),"iterate",U),Reflect.get(e,"size",e)}function Vr(e){e=_(e);let t=_(this);return Fe(t).has.call(t,e)||(t.add(e),j(t,"add",e,e)),this}function qr(e,t){t=_(t);let r=_(this),{has:n,get:i}=Fe(r),o=n.call(r,e);o?Hr(r,n,e):(e=_(e),o=n.call(r,e));let s=i.call(r,e);return r.set(e,t),o?Et(t,s)&&j(r,"set",e,t,s):j(r,"add",e,t),this}function Ur(e){let t=_(this),{has:r,get:n}=Fe(t),i=r.call(t,e);i?Hr(t,r,e):(e=_(e),i=r.call(t,e));let o=n?n.call(t,e):void 0,s=t.delete(e);return i&&j(t,"delete",e,void 0,o),s}function Wr(){let e=_(this),t=e.size!==0,r=X(e)?new Map(e):new Set(e),n=e.clear();return t&&j(e,"clear",void 0,void 0,r),n}function Ve(e,t){return function(n,i){let o=this,s=o.__v_raw,a=_(s),c=t?Mt:e?Rt:Ct;return!e&&T(a,"iterate",U),s.forEach((l,u)=>n.call(i,c(l),c(u),o))}}function He(e,t,r){return function(...n){let i=this.__v_raw,o=_(i),s=X(o),a=e==="entries"||e===Symbol.iterator&&s,c=e==="keys"&&s,l=i[e](...n),u=r?Mt:t?Rt:Ct;return!t&&T(o,"iterate",c?At:U),{next(){let{value:p,done:y}=l.next();return y?{value:p,done:y}:{value:a?[u(p[0]),u(p[1])]:u(p),done:y}},[Symbol.iterator](){return this}}}}function F(e){return function(...t){{let r=t[0]?`on key "${t[0]}" `:"";console.warn(`${wt(e)} operation ${r}failed: target is readonly.`,_(this))}return e==="delete"?!1:this}}var Gr={get(e){return Be(this,e)},get size(){return ze(this)},has:Ke,add:Vr,set:qr,delete:Ur,clear:Wr,forEach:Ve(!1,!1)},Yr={get(e){return Be(this,e,!1,!0)},get size(){return ze(this)},has:Ke,add:Vr,set:qr,delete:Ur,clear:Wr,forEach:Ve(!1,!0)},Jr={get(e){return Be(this,e,!0)},get size(){return ze(this,!0)},has(e){return Ke.call(this,e,!0)},add:F("add"),set:F("set"),delete:F("delete"),clear:F("clear"),forEach:Ve(!0,!1)},Zr={get(e){return Be(this,e,!0,!0)},get size(){return ze(this,!0)},has(e){return Ke.call(this,e,!0)},add:F("add"),set:F("set"),delete:F("delete"),clear:F("clear"),forEach:Ve(!0,!0)},ci=["keys","values","entries",Symbol.iterator];ci.forEach(e=>{Gr[e]=He(e,!1,!1),Jr[e]=He(e,!0,!1),Yr[e]=He(e,!1,!0),Zr[e]=He(e,!0,!0)});function qe(e,t){let r=t?e?Zr:Yr:e?Jr:Gr;return(n,i,o)=>i==="__v_isReactive"?!e:i==="__v_isReadonly"?e:i==="__v_raw"?n:Reflect.get(le(r,i)&&i in n?r:n,i,o)}var li={get:qe(!1,!1)},gs={get:qe(!1,!0)},ui={get:qe(!0,!1)},xs={get:qe(!0,!0)};function Hr(e,t,r){let n=_(r);if(n!==r&&t.call(e,n)){let i=vt(e);console.warn(`Reactive ${i} contains both the raw and reactive versions of the same object${i==="Map"?" as keys":""}, which can lead to inconsistencies. Avoid differentiating between the raw and reactive versions of an object and only use the reactive version if possible.`)}}var Lr=new WeakMap,ti=new WeakMap,jr=new WeakMap,ri=new WeakMap;function fi(e){switch(e){case"Object":case"Array":return 1;case"Map":case"Set":case"WeakMap":case"WeakSet":return 2;default:return 0}}function di(e){return e.__v_skip||!Object.isExtensible(e)?0:fi(vt(e))}function je(e){return e&&e.__v_isReadonly?e:Qr(e,!1,Kr,li,Lr)}function Fr(e){return Qr(e,!0,zr,ui,jr)}function Qr(e,t,r,n,i){if(!ue(e))return console.warn(`value cannot be made reactive: ${String(e)}`),e;if(e.__v_raw&&!(t&&e.__v_isReactive))return e;let o=i.get(e);if(o)return o;let s=di(e);if(s===0)return e;let a=new Proxy(e,s===2?n:r);return i.set(e,a),a}function _(e){return e&&_(e.__v_raw)||e}function Tt(e){return Boolean(e&&e.__v_isRef===!0)}x("nextTick",()=>Te);x("dispatch",e=>z.bind(z,e));x("watch",(e,{evaluateLater:t,effect:r})=>(n,i)=>{let o=t(n),s=!0,a,c=r(()=>o(l=>{JSON.stringify(l),s?a=l:queueMicrotask(()=>{i(l,a),a=l}),s=!1}));e._x_effects.delete(c)});x("store",Er);x("data",e=>xe(e));x("root",e=>V(e));x("refs",e=>(e._x_refs_proxy||(e._x_refs_proxy=D(pi(e))),e._x_refs_proxy));function pi(e){let t=[],r=e;for(;r;)r._x_refs&&t.push(r._x_refs),r=r.parentNode;return t}var Nt={};function Pt(e){return Nt[e]||(Nt[e]=0),++Nt[e]}function Xr(e,t){return Q(e,r=>{if(r._x_ids&&r._x_ids[t])return!0})}function en(e,t){e._x_ids||(e._x_ids={}),e._x_ids[t]||(e._x_ids[t]=Pt(t))}x("id",e=>(t,r=null)=>{let n=Xr(e,t),i=n?n._x_ids[t]:Pt(t);return r?`${t}-${i}-${r}`:`${t}-${i}`});x("el",e=>e);tn("Focus","focus","focus");tn("Persist","persist","persist");function tn(e,t,r){x(t,n=>O(`You can't use [$${directiveName}] without first installing the "${e}" plugin here: https://alpinejs.dev/plugins/${r}`,n))}d("modelable",(e,{expression:t},{effect:r,evaluateLater:n})=>{let i=n(t),o=()=>{let l;return i(u=>l=u),l},s=n(`${t} = __placeholder`),a=l=>s(()=>{},{scope:{__placeholder:l}}),c=o();a(c),queueMicrotask(()=>{if(!e._x_model)return;e._x_removeModelListeners.default();let l=e._x_model.get,u=e._x_model.set;r(()=>a(l())),r(()=>u(o()))})});d("teleport",(e,{expression:t},{cleanup:r})=>{e.tagName.toLowerCase()!=="template"&&O("x-teleport can only be used on a <template> tag",e);let n=document.querySelector(t);n||O(`Cannot find x-teleport element for selector: "${t}"`);let i=e.content.cloneNode(!0).firstElementChild;e._x_teleport=i,i._x_teleportBack=e,e._x_forwardEvents&&e._x_forwardEvents.forEach(o=>{i.addEventListener(o,s=>{s.stopPropagation(),e.dispatchEvent(new s.constructor(s.type,s))})}),C(i,{},e),m(()=>{n.appendChild(i),w(i),i._x_ignore=!0}),r(()=>i.remove())});var rn=()=>{};rn.inline=(e,{modifiers:t},{cleanup:r})=>{t.includes("self")?e._x_ignoreSelf=!0:e._x_ignore=!0,r(()=>{t.includes("self")?delete e._x_ignoreSelf:delete e._x_ignore})};d("ignore",rn);d("effect",(e,{expression:t},{effect:r})=>r(g(e,t)));function de(e,t,r,n){let i=e,o=c=>n(c),s={},a=(c,l)=>u=>l(c,u);if(r.includes("dot")&&(t=mi(t)),r.includes("camel")&&(t=hi(t)),r.includes("passive")&&(s.passive=!0),r.includes("capture")&&(s.capture=!0),r.includes("window")&&(i=window),r.includes("document")&&(i=document),r.includes("prevent")&&(o=a(o,(c,l)=>{l.preventDefault(),c(l)})),r.includes("stop")&&(o=a(o,(c,l)=>{l.stopPropagation(),c(l)})),r.includes("self")&&(o=a(o,(c,l)=>{l.target===e&&c(l)})),(r.includes("away")||r.includes("outside"))&&(i=document,o=a(o,(c,l)=>{e.contains(l.target)||l.target.isConnected!==!1&&(e.offsetWidth<1&&e.offsetHeight<1||e._x_isShown!==!1&&c(l))})),r.includes("once")&&(o=a(o,(c,l)=>{c(l),i.removeEventListener(t,o,s)})),o=a(o,(c,l)=>{_i(t)&&gi(l,r)||c(l)}),r.includes("debounce")){let c=r[r.indexOf("debounce")+1]||"invalid-wait",l=kt(c.split("ms")[0])?Number(c.split("ms")[0]):250;o=Ne(o,l)}if(r.includes("throttle")){let c=r[r.indexOf("throttle")+1]||"invalid-wait",l=kt(c.split("ms")[0])?Number(c.split("ms")[0]):250;o=Pe(o,l)}return i.addEventListener(t,o,s),()=>{i.removeEventListener(t,o,s)}}function mi(e){return e.replace(/-/g,".")}function hi(e){return e.toLowerCase().replace(/-(\w)/g,(t,r)=>r.toUpperCase())}function kt(e){return!Array.isArray(e)&&!isNaN(e)}function xi(e){return e.replace(/([a-z])([A-Z])/g,"$1-$2").replace(/[_\s]/,"-").toLowerCase()}function _i(e){return["keydown","keyup"].includes(e)}function gi(e,t){let r=t.filter(o=>!["window","document","prevent","stop","once"].includes(o));if(r.includes("debounce")){let o=r.indexOf("debounce");r.splice(o,kt((r[o+1]||"invalid-wait").split("ms")[0])?2:1)}if(r.length===0||r.length===1&&nn(e.key).includes(r[0]))return!1;let i=["ctrl","shift","alt","meta","cmd","super"].filter(o=>r.includes(o));return r=r.filter(o=>!i.includes(o)),!(i.length>0&&i.filter(s=>((s==="cmd"||s==="super")&&(s="meta"),e[`${s}Key`])).length===i.length&&nn(e.key).includes(r[0]))}function nn(e){if(!e)return[];e=xi(e);let t={ctrl:"control",slash:"/",space:"-",spacebar:"-",cmd:"meta",esc:"escape",up:"arrow-up",down:"arrow-down",left:"arrow-left",right:"arrow-right",period:".",equal:"="};return t[e]=e,Object.keys(t).map(r=>{if(t[r]===e)return r}).filter(r=>r)}d("model",(e,{modifiers:t,expression:r},{effect:n,cleanup:i})=>{let o=g(e,r),s=`${r} = rightSideOfExpression($event, ${r})`,a=g(e,s);var c=e.tagName.toLowerCase()==="select"||["checkbox","radio"].includes(e.type)||t.includes("lazy")?"change":"input";let l=yi(e,t,r),u=de(e,c,t,y=>{a(()=>{},{scope:{$event:y,rightSideOfExpression:l}})});e._x_removeModelListeners||(e._x_removeModelListeners={}),e._x_removeModelListeners.default=u,i(()=>e._x_removeModelListeners.default());let p=g(e,`${r} = __placeholder`);e._x_model={get(){let y;return o(N=>y=N),y},set(y){p(()=>{},{scope:{__placeholder:y}})}},e._x_forceModelUpdate=()=>{o(y=>{y===void 0&&r.match(/\./)&&(y=""),window.fromModel=!0,m(()=>ce(e,"value",y)),delete window.fromModel})},n(()=>{t.includes("unintrusive")&&document.activeElement.isSameNode(e)||e._x_forceModelUpdate()})});function yi(e,t,r){return e.type==="radio"&&m(()=>{e.hasAttribute("name")||e.setAttribute("name",r)}),(n,i)=>m(()=>{if(n instanceof CustomEvent&&n.detail!==void 0)return n.detail||n.target.value;if(e.type==="checkbox")if(Array.isArray(i)){let o=t.includes("number")?Dt(n.target.value):n.target.value;return n.target.checked?i.concat([o]):i.filter(s=>!bi(s,o))}else return n.target.checked;else{if(e.tagName.toLowerCase()==="select"&&e.multiple)return t.includes("number")?Array.from(n.target.selectedOptions).map(o=>{let s=o.value||o.text;return Dt(s)}):Array.from(n.target.selectedOptions).map(o=>o.value||o.text);{let o=n.target.value;return t.includes("number")?Dt(o):t.includes("trim")?o.trim():o}}})}function Dt(e){let t=e?parseFloat(e):null;return vi(t)?t:e}function bi(e,t){return e==t}function vi(e){return!Array.isArray(e)&&!isNaN(e)}d("cloak",e=>queueMicrotask(()=>m(()=>e.removeAttribute(E("cloak")))));Re(()=>`[${E("init")}]`);d("init",$((e,{expression:t},{evaluate:r})=>typeof t=="string"?!!t.trim()&&r(t,{},!1):r(t,{},!1)));d("text",(e,{expression:t},{effect:r,evaluateLater:n})=>{let i=n(t);r(()=>{i(o=>{m(()=>{e.textContent=o})})})});d("html",(e,{expression:t},{effect:r,evaluateLater:n})=>{let i=n(t);r(()=>{i(o=>{m(()=>{e.innerHTML=o,e._x_ignoreSelf=!0,w(e),delete e._x_ignoreSelf})})})});Z(Ee(":",Se(E("bind:"))));d("bind",(e,{value:t,modifiers:r,expression:n,original:i},{effect:o})=>{if(!t){let a={};Or(a),g(e,n)(l=>{xt(e,l,i)},{scope:a});return}if(t==="key")return wi(e,n);let s=g(e,n);o(()=>s(a=>{a===void 0&&typeof n=="string"&&n.match(/\./)&&(a=""),m(()=>ce(e,t,a,r))}))});function wi(e,t){e._x_keyExpression=t}Ce(()=>`[${E("data")}]`);d("data",$((e,{expression:t},{cleanup:r})=>{t=t===""?"{}":t;let n={};re(n,e);let i={};Rr(i,n);let o=P(e,t,{scope:i});o===void 0&&(o={}),re(o,e);let s=A(o);ye(s);let a=C(e,s);s.init&&P(e,s.init),r(()=>{s.destroy&&P(e,s.destroy),a()})}));d("show",(e,{modifiers:t,expression:r},{effect:n})=>{let i=g(e,r);e._x_doHide||(e._x_doHide=()=>{m(()=>{e.style.setProperty("display","none",t.includes("important")?"important":void 0)})}),e._x_doShow||(e._x_doShow=()=>{m(()=>{e.style.length===1&&e.style.display==="none"?e.removeAttribute("style"):e.style.removeProperty("display")})});let o=()=>{e._x_doHide(),e._x_isShown=!1},s=()=>{e._x_doShow(),e._x_isShown=!0},a=()=>setTimeout(s),c=se(p=>p?s():o(),p=>{typeof e._x_toggleAndCascadeWithTransitions=="function"?e._x_toggleAndCascadeWithTransitions(e,p,s,o):p?a():o()}),l,u=!0;n(()=>i(p=>{!u&&p===l||(t.includes("immediate")&&(p?a():o()),c(p),l=p,u=!1)}))});d("for",(e,{expression:t},{effect:r,cleanup:n})=>{let i=Si(t),o=g(e,i.items),s=g(e,e._x_keyExpression||"index");e._x_prevKeys=[],e._x_lookup={},r(()=>Ei(e,i,o,s)),n(()=>{Object.values(e._x_lookup).forEach(a=>a.remove()),delete e._x_prevKeys,delete e._x_lookup})});function Ei(e,t,r,n){let i=s=>typeof s=="object"&&!Array.isArray(s),o=e;r(s=>{Ai(s)&&s>=0&&(s=Array.from(Array(s).keys(),f=>f+1)),s===void 0&&(s=[]);let a=e._x_lookup,c=e._x_prevKeys,l=[],u=[];if(i(s))s=Object.entries(s).map(([f,h])=>{let b=on(t,h,f,s);n(v=>u.push(v),{scope:{index:f,...b}}),l.push(b)});else for(let f=0;f<s.length;f++){let h=on(t,s[f],f,s);n(b=>u.push(b),{scope:{index:f,...h}}),l.push(h)}let p=[],y=[],N=[],W=[];for(let f=0;f<c.length;f++){let h=c[f];u.indexOf(h)===-1&&N.push(h)}c=c.filter(f=>!N.includes(f));let pe="template";for(let f=0;f<u.length;f++){let h=u[f],b=c.indexOf(h);if(b===-1)c.splice(f,0,h),p.push([pe,f]);else if(b!==f){let v=c.splice(f,1)[0],S=c.splice(b-1,1)[0];c.splice(f,0,S),c.splice(b,0,v),y.push([v,S])}else W.push(h);pe=h}for(let f=0;f<N.length;f++){let h=N[f];a[h]._x_effects&&a[h]._x_effects.forEach(he),a[h].remove(),a[h]=null,delete a[h]}for(let f=0;f<y.length;f++){let[h,b]=y[f],v=a[h],S=a[b],G=document.createElement("div");m(()=>{S.after(G),v.after(S),S._x_currentIfEl&&S.after(S._x_currentIfEl),G.before(v),v._x_currentIfEl&&v.after(v._x_currentIfEl),G.remove()}),ot(S,l[u.indexOf(b)])}for(let f=0;f<p.length;f++){let[h,b]=p[f],v=h==="template"?o:a[h];v._x_currentIfEl&&(v=v._x_currentIfEl);let S=l[b],G=u[b],me=document.importNode(o.content,!0).firstElementChild;C(me,A(S),o),m(()=>{v.after(me),w(me)}),typeof G=="object"&&O("x-for key cannot be an object, it must be a string or an integer",o),a[G]=me}for(let f=0;f<W.length;f++)ot(a[W[f]],l[u.indexOf(W[f])]);o._x_prevKeys=u})}function Si(e){let t=/,([^,\}\]]*)(?:,([^,\}\]]*))?$/,r=/^\s*\(|\)\s*$/g,n=/([\s\S]*?)\s+(?:in|of)\s+([\s\S]*)/,i=e.match(n);if(!i)return;let o={};o.items=i[2].trim();let s=i[1].replace(r,"").trim(),a=s.match(t);return a?(o.item=s.replace(t,"").trim(),o.index=a[1].trim(),a[2]&&(o.collection=a[2].trim())):o.item=s,o}function on(e,t,r,n){let i={};return/^\[.*\]$/.test(e.item)&&Array.isArray(t)?e.item.replace("[","").replace("]","").split(",").map(s=>s.trim()).forEach((s,a)=>{i[s]=t[a]}):/^\{.*\}$/.test(e.item)&&!Array.isArray(t)&&typeof t=="object"?e.item.replace("{","").replace("}","").split(",").map(s=>s.trim()).forEach(s=>{i[s]=t[s]}):i[e.item]=t,e.index&&(i[e.index]=r),e.collection&&(i[e.collection]=n),i}function Ai(e){return!Array.isArray(e)&&!isNaN(e)}function sn(){}sn.inline=(e,{expression:t},{cleanup:r})=>{let n=V(e);n._x_refs||(n._x_refs={}),n._x_refs[t]=e,r(()=>delete n._x_refs[t])};d("ref",sn);d("if",(e,{expression:t},{effect:r,cleanup:n})=>{let i=g(e,t),o=()=>{if(e._x_currentIfEl)return e._x_currentIfEl;let a=e.content.cloneNode(!0).firstElementChild;return C(a,{},e),m(()=>{e.after(a),w(a)}),e._x_currentIfEl=a,e._x_undoIf=()=>{R(a,c=>{c._x_effects&&c._x_effects.forEach(he)}),a.remove(),delete e._x_currentIfEl},a},s=()=>{!e._x_undoIf||(e._x_undoIf(),delete e._x_undoIf)};r(()=>i(a=>{a?o():s()})),n(()=>e._x_undoIf&&e._x_undoIf())});d("id",(e,{expression:t},{evaluate:r})=>{r(t).forEach(i=>en(e,i))});Z(Ee("@",Se(E("on:"))));d("on",$((e,{value:t,modifiers:r,expression:n},{cleanup:i})=>{let o=n?g(e,n):()=>{};e.tagName.toLowerCase()==="template"&&(e._x_forwardEvents||(e._x_forwardEvents=[]),e._x_forwardEvents.includes(t)||e._x_forwardEvents.push(t));let s=de(e,t,r,a=>{o(()=>{},{scope:{$event:a},params:[a]})});i(()=>s())}));Ue("Collapse","collapse","collapse");Ue("Intersect","intersect","intersect");Ue("Focus","trap","focus");Ue("Mask","mask","mask");function Ue(e,t,r){d(t,n=>O(`You can't use [x-${t}] without first installing the "${e}" plugin here: https://alpinejs.dev/plugins/${r}`,n))}I.setEvaluator(ct);I.setReactivityEngine({reactive:je,effect:Pr,release:Dr,raw:_});var It=I;window.Alpine=It;queueMicrotask(()=>{It.start()});})();
</script>
</body>
</html>
<?php
$html = ob_get_clean();

if (!stream_isatty(STDOUT)) {
    echo $html;
    exit;
}

$file = getcwd() . DIRECTORY_SEPARATOR . 'phpinfo.html';
file_put_contents($file, $html);

$opened = match (PHP_OS_FAMILY) {
    'Darwin' => !exec('open ' . escapeshellarg($file)),
    'Linux' => !exec('xdg-open ' . escapeshellarg($file) . ' 2>/dev/null &'),
    'Windows' => !exec('start "" "' . addcslashes($file, '"') . '"'),
    default => false,
};

fwrite(STDERR, $opened
    ? "Saved to phpinfo.html and opened in your browser.\n"
    : "Saved to phpinfo.html\n"
);
