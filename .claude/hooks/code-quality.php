<?php

/**
 * Claude Code "Stop" hook — code-quality gate.
 *
 * Runs when Claude finishes a turn. It:
 *   1. Skips immediately unless PHP files changed (no latency on non-code turns).
 *   2. Auto-formats the changed files with Pint (`--dirty`).
 *   3. Runs PHPStan/Larastan — the baseline grandfathers legacy issues, so only
 *      NEWLY introduced errors fail.
 *   4. Blocks (exit 2) on new static-analysis errors so Claude fixes them before
 *      declaring the task done. A loop-safety valve (`stop_hook_active`) makes sure
 *      the user is never trapped, and missing php/git never hard-blocks.
 *
 * Exit codes: 0 = allow stop, 2 = block stop and feed stderr back to Claude.
 */

$raw = stream_get_contents(STDIN) ?: '';
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw); // strip a leading UTF-8 BOM if present
$payload = json_decode($raw, true) ?: [];
$stopHookActive = (bool) ($payload['stop_hook_active'] ?? false);

$root = getcwd();

// Only act on a recognizable Composer/PHP checkout.
if (! is_file($root.'/vendor/bin/pint') && ! is_file($root.'/vendor/bin/phpstan')) {
    exit(0);
}

// Determine changed/untracked PHP files via git. If git is unavailable, never block.
$statusLines = [];
exec('git status --porcelain 2>&1', $statusLines, $gitCode);
if ($gitCode !== 0) {
    exit(0);
}

$phpChanged = false;
foreach ($statusLines as $line) {
    $path = trim(substr($line, 3));
    if (str_contains($path, ' -> ')) {                 // renamed: "old -> new"
        $path = trim(substr($path, strpos($path, ' -> ') + 4));
    }
    $path = trim($path, '"');
    if (str_ends_with(strtolower($path), '.php')) {
        $phpChanged = true;
        break;
    }
}

if (! $phpChanged) {
    exit(0);
}

$php = escapeshellarg(PHP_BINARY);

// 1) Auto-format changed files (safe; non-blocking).
$pintOut = [];
exec($php.' vendor/bin/pint --dirty 2>&1', $pintOut, $pintCode);

// 2) Static analysis — baseline means only new errors return a non-zero code.
// Clear the result cache first: PHPStan only watches its analysis paths (app/),
// so schema changes under database/migrations don't invalidate it and a stale
// cache can report a false "clean" (and hide resolved baseline entries).
$clearOut = [];
exec($php.' vendor/bin/phpstan clear-result-cache --no-interaction 2>&1', $clearOut);

$analyseOut = [];
exec($php.' vendor/bin/phpstan analyse --no-progress --no-interaction --memory-limit=512M 2>&1', $analyseOut, $analyseCode);

if ($analyseCode === 0) {
    exit(0);
}

$report = implode("\n", $analyseOut);

// Loop-safety valve: if we already blocked once and errors persist, warn but allow
// the stop so the session is never trapped.
if ($stopHookActive) {
    fwrite(STDOUT, "⚠ PHPStan still reports errors (allowing stop to avoid a loop):\n".$report."\n");
    exit(0);
}

fwrite(STDERR, "PHPStan found new errors introduced by recent changes. Fix them before finishing:\n\n".$report."\n");
exit(2);
