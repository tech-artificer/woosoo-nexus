Bundled Windows PHP runtime (developer tooling)

This directory contains a prebuilt Windows PHP runtime used by the repository's
local developer scripts. It is a redistributable runtime (binaries and DLLs),
not the PHP source tree.

Usage
- Run CLI scripts with the bundled runtime:
  - `dependency/php/php.exe path\to\script.php`
- Use `php.ini-development` or `php.ini-production` as a starting configuration.

Notes
- The bundled runtime is provided for convenience on Windows x64 only.
- For official PHP releases, source code, and documentation, see https://www.php.net/
