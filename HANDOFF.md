# Pretty PHP Info — Handoff Document

This document captures the full state of the Pretty PHP Info project as of March 19, 2026. It covers three related repositories and all outstanding work.

## Repositories

### 1. Package: `stechstudio/phpinfo`
- **Local path:** `~/dev/phpinfo`
- **Remote:** `git@github.com:stechstudio/phpinfo.git`
- **Branch:** `main`
- **Latest tag:** `0.6` (most recent changes are untagged on main — see "What needs a release" below)
- **Tests:** 76 tests, 181 assertions, all passing (`./vendor/bin/phpunit`)
- **CI:** GitHub Actions runs PHPUnit on PHP 8.3 and 8.5 (`.github/workflows/tests.yml`)

### 2. Landing page: `stechstudio/PrettyPHPInfo`
- **Local path:** `~/dev/prettyphpinfo`
- **Remote:** `git@github.com:stechstudio/PrettyPHPInfo.git`
- **Branch:** `main`
- **Hosting:** GitHub Pages with CNAME `prettyphpinfo.com`
- **Stack:** Single `index.html` using Tailwind CDN + Alpine.js, no build step

### 3. Local test site
- **Local path:** `~/dev/info`
- **URL:** `http://info.test` (via Laravel Herd)
- **Purpose:** Consumer of the package via Composer path repo pointing to `../phpinfo`
- **Note:** `composer.json` uses `"stechstudio/phpinfo": "dev-main"` with a local path repository. After tagging a new release, this should be switched back to a version constraint from Packagist.

---

## Architecture Overview

### Package structure

```
src/
  Info.php              # Static factory: capture(), fromHtml(), fromText(), detect(), __callStatic
  PhpInfo.php           # Value object: version(), modules(), module(), config(), hasModule(), hasConfig(), os(), hostname(), render()
  Models/
    Module.php          # name(), groups(), configs(), hasConfig(), config()
    Group.php           # name(), note(), configs(), headings(), hasHeadings()
    Config.php          # name(), localValue(), masterValue(), hasMasterValue(), value()
  Parsers/
    Parser.php          # Interface: canParse(), parse()
    HtmlParser.php      # Parses phpinfo() HTML output (DOMDocument/DOMXPath)
    TextParser.php      # Thin wrapper: delegates to parse_text.php, wraps arrays in models
    parse_text.php      # Shared plain-PHP text parser (used by package and standalone script)
  Support/
    Items.php           # Lightweight iterable collection (zero dependencies)
    Str.php             # Static slug() utility
helpers.php             # Global prettyphpinfo() and items() functions, autoloaded via composer files
dist/
  default.php           # Pretty UI template (includes styles.css and app.js via PHP include)
  styles.css            # Tailwind v4 compiled CSS (built via npm run build)
  app.js                # Alpine.js (vendored)
  go.php                # Standalone script template (uses include for CSS/JS — NOT self-contained)
  go-standalone.php     # Fully self-contained version (CSS/JS baked in, built by build-go.php)
build-go.php            # Build script: reads go.php + styles.css + app.js → go-standalone.php
```

### Key design decisions

1. **Parser produces Result, not IS Result.** `HtmlParser` and `TextParser` implement a `Parser` interface with `parse(): PhpInfo`. The `PhpInfo` value object is what users interact with.

2. **No traits, no magic.** The old `Slugifies` and `ConfigAliases` traits were removed. `Str::slug()` is a static utility. `os()` and `hostname()` are explicit methods on `PhpInfo`.

3. **`Config::$hasMasterValue` is a proper bool.** The old code used `false` as a sentinel value for "no master value exists" vs `null` for "master value is empty." Now it's a clean `bool $hasMasterValue` parameter.

4. **Zero runtime dependencies.** `illuminate/collections` was replaced with a lightweight `Items` class. The text parser is plain PHP shared between the package and standalone script.

5. **Lookups compare slugified names, not prefixed keys.** `module()`, `config()`, `hasModule()`, `hasConfig()` all slugify the input and compare against slugified model names. The `key()` methods (with `module_`, `config_`, `group_` prefixes) are only for frontend DOM IDs and JSON serialization.

6. **`Info::capture()` accepts `INFO_*` constants.** Same bitmask as native `phpinfo()`. The text parser handles partial output (no version line, no dividers, missing sections).

7. **Self-contained UI.** `dist/default.php` includes `styles.css` and `app.js` via PHP `include()` so they're inlined into the HTML response. Zero external requests.

8. **Sticky header layout.** The header is `position: sticky; top: 0` inside the scroll container. This gives frosted glass (content scrolls behind it), doesn't overlap the scrollbar, and `scroll-padding-top` handles anchor offsets. This was iterated on several times — see commit history.

### Frontend (dist/default.php)

Built with Alpine.js and Tailwind CSS v4. Features:
- Sidebar navigation with scroll tracking (x-intersect)
- Instant search filtering (matches module names AND config names/values)
- Dark mode toggle (persists to localStorage, defaults to system preference)
- Click-to-copy on config values (uses execCommand fallback for HTTP)
- Long value truncation with show more/less toggle
- Deep linking via URL hash for every module, group, and config
- Mobile responsive with slide-out nav drawer
- Frosted glass sticky header with backdrop-blur

Tailwind config: `src/resources/input.css` with `@custom-variant dark (&:where(.dark, .dark *))` for class-based dark mode. Build with `npm run build`.

### Standalone script (dist/go.php → dist/go-standalone.php)

A zero-dependency PHP script that captures `phpinfo()`, parses it with a lightweight built-in text parser (plain arrays, no Collections), and outputs a complete HTML page with all CSS/JS inlined.

**Build process:**
1. `dist/go.php` is the source template — uses `<?php include() ?>` for CSS/JS
2. `build-go.php` reads `go.php`, `styles.css`, and `app.js`, replaces the includes with file contents
3. Output: `dist/go-standalone.php` (101KB, fully self-contained)
4. This file is copied to `~/dev/prettyphpinfo/go` for the landing page `/go` endpoint

**Usage:** `curl -sSL prettyphpinfo.com/go | php`

**Important:** The standalone parser in `go.php` is a SEPARATE, simplified text parser written in plain PHP (functions `pp_parse`, `pp_parseModule`, `pp_parseGroup`, `pp_slug`). It does NOT use the package's classes or Collections. If the main parser logic changes, the standalone parser may need to be updated separately.

---

## Landing page (prettyphpinfo.com)

Single `index.html` — dark-first design with light/dark toggle:
- Tailwind CDN + Alpine.js (loaded from CDN)
- `darkMode: 'class'` on Tailwind config
- `x-data="{ dark: window.matchMedia('(prefers-color-scheme: dark)').matches }"` on `<html>`
- Every element has explicit light + `dark:` class pairs
- Screenshots swap between `screenshot-dark.png` and `screenshot-light.png` based on toggle
- Code blocks always stay dark (syntax highlighting on dark background)
- `/go` endpoint serves the standalone PHP script as a plain file

### Sections:
1. Nav (logo, GitHub, Packagist, theme toggle, Get Started)
2. Hero (badge, h1, description, composer install command)
3. Screenshot (swaps with theme toggle)
4. Two ways to use it (Browser UI card + Programmatic API card)
5. Clean, expressive API (two code example panels)
6. Feature grid (6 cards: search, dark mode, copy, deep linking, self-contained, dual parser)
7. CTA (get started in seconds — install + use)
8. Try without installing (curl one-liner + explanation)
9. Footer

---

## What's been done (this session)

### Package
- Full architecture redesign (Parser separated from Result, PhpInfo value object, TextCursor, etc.)
- PHP 8.3+ minimum, zero runtime dependencies, Tailwind CSS v4, PHPUnit 12
- Fixed 8+ bugs (broken lookups, SVG parsing, JS errors, PHP 8.3 deprecations, clipboard on HTTP)
- Modern UI overhaul (thin borders, frosted glass header, dark mode toggle, click-to-copy, search matching module names, long value truncation)
- `prettyphpinfo()` global helper with `INFO_*` constant support
- Standalone zero-dependency script for curl one-liner
- 76 tests, all passing
- CI on PHP 8.3 and 8.5
- Anonymized test fixtures (original fixtures leaked credentials to a public repo which was deleted and recreated)
- README rewritten

### Landing page
- Built from scratch — dark design with proper light/dark toggle
- Syntax-highlighted code examples
- Mobile responsive (tested down to 320px)
- `/go` endpoint for curl one-liner
- Deployed to GitHub Pages with CNAME

---

## What still needs doing

### Must do before release
- **Tag a new release.** `0.6` was tagged before the `prettyphpinfo()` helper, UI modernization, standalone script, frosted glass header, and other changes. Everything on `main` since `0.6` needs a new tag (`0.7` or `1.0`).
- **Switch `~/dev/info` back to Packagist** after tagging. Currently uses a path repo.

### Standalone script improvements
- **The standalone parser (`pp_parse` in `go.php`) is fragile.** It's a quick reimplementation of `TextParser` using plain arrays. It hasn't been tested against diverse phpinfo outputs (different PHP versions, different extensions, different OSes). Needs proper testing.
- **Credits and License sections are NOT parsed** by the standalone parser. The main package's `TextParser` has `parseCredits()` and `parseLicense()` methods — the standalone parser stops at the module loop.
- **The standalone script's UI is slightly different** from `default.php`. It doesn't have deep-link anchors on config names, copy-to-clipboard buttons, or the "show more" truncation button styling that perfectly matches. Should be kept in sync.
- **Build workflow:** When CSS or JS changes in the package, someone needs to run `npm run build` then `php build-go.php` then copy `dist/go-standalone.php` to `~/dev/prettyphpinfo/go`. This should ideally be automated or at least documented.
- **`go.php` comment says "excluding environment variables"** but `phpinfo()` is now called with no arguments (full output). The comment is stale.

### Landing page
- **Hosting/deployment details** for `prettyphpinfo.com` need to be finalized. The CNAME is set but the actual DNS and GitHub Pages config should be verified.
- **The `/go` endpoint needs to serve as `text/plain`** (or at least not be interpreted as HTML by the server). With GitHub Pages, the file `go` (no extension) should serve as-is, but this should be tested with an actual `curl` from outside.
- **SEO and meta tags** could be improved (Open Graph, Twitter cards, etc.)

### Package improvements
- **More test fixtures.** Currently only has PHP 8.3 CLI and HTML fixtures. Would benefit from PHP 8.1, 8.2, 8.4, 8.5 fixtures, and fixtures from different OSes (Linux, Windows).
- **The `HtmlParser` makes positional DOM assumptions** (`//body//table[2]` for General, `//body//h1[2]` for Credits). These could break if PHP changes the phpinfo HTML structure.
- **`PhpInfo::render()` uses `echo` via `include`.** Works fine everywhere including Laravel, but doesn't return a Response object. A `toHtml()` method that returns a string could be useful.
- **No `phpstan` or code style checking.** Could add PHPStan and Pint to CI.

### Nice to have
- **Packagist description** should probably be updated to "Pretty PHP Info — A beautiful, searchable replacement for phpinfo()" or similar.
- **GitHub repo description** and topics should be set.
- **The package README** and landing page both reference `prettyphpinfo()` but the Packagist package name is still `stechstudio/phpinfo`. This is fine but worth being aware of.

---

## Key file locations

| What | Where |
|------|-------|
| Package source | `~/dev/phpinfo/src/` |
| Tests | `~/dev/phpinfo/tests/` |
| Test fixtures | `~/dev/phpinfo/tests/fixtures/` |
| Pretty UI template | `~/dev/phpinfo/dist/default.php` |
| Tailwind input CSS | `~/dev/phpinfo/src/resources/input.css` |
| Built CSS | `~/dev/phpinfo/dist/styles.css` |
| Alpine.js | `~/dev/phpinfo/dist/app.js` |
| Standalone script (template) | `~/dev/phpinfo/dist/go.php` |
| Standalone script (built) | `~/dev/phpinfo/dist/go-standalone.php` |
| Build script for standalone | `~/dev/phpinfo/build-go.php` |
| Global helper | `~/dev/phpinfo/helpers.php` |
| Landing page | `~/dev/prettyphpinfo/index.html` |
| Landing page /go endpoint | `~/dev/prettyphpinfo/go` |
| Local test site | `~/dev/info/index.php` |

## Commands

```bash
# Package
cd ~/dev/phpinfo
./vendor/bin/phpunit              # Run tests
npm run build                     # Rebuild Tailwind CSS
php build-go.php                  # Rebuild standalone script

# After CSS/JS changes, update standalone and landing page:
npm run build && php build-go.php && cp dist/go-standalone.php ~/dev/prettyphpinfo/go

# Landing page
cd ~/dev/prettyphpinfo
# Static site — no build step, just commit and push to deploy via GitHub Pages
```
