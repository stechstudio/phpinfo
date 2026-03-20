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
 * @see https://prettyphpinfo.com
 */

// ── Capture phpinfo ───────────────────────────────────────────────────

ob_start();
phpinfo();
$raw = ob_get_clean();

// ── Parse ────────────────────────────────────────────────────────────
require_once __DIR__ . '/../vendor/autoload.php';

$info = (new \STS\Phpinfo\Parsers\TextParser($raw))->parse();

// ── Output complete HTML page ─────────────────────────────────────────
ob_start();
include __DIR__ . '/../resources/template.php';
$html = ob_get_clean();

if (!stream_isatty(STDOUT)) {
    echo $html;
    exit;
}

$file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'phpinfo.html';
file_put_contents($file, $html);

$opened = match (PHP_OS_FAMILY) {
    'Darwin' => !exec('open ' . escapeshellarg($file)),
    'Linux' => !exec('xdg-open ' . escapeshellarg($file) . ' 2>/dev/null &'),
    'Windows' => !exec('start "" ' . escapeshellarg($file)),
    default => false,
};

fwrite(STDERR, $opened
    ? "Opened in your browser.\n"
    : "Saved to $file\n"
);
