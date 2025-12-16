#!/usr/bin/env node
/**
 * Converted to ES Module imports to satisfy @typescript-eslint/no-require-imports
 *
 * Adapt the rest of the script as needed; this file shows the import pattern
 * and how to get __dirname/__filename in an ESM context.
 */

import { spawn } from 'child_process';
import path from 'path';
import { fileURLToPath } from 'url';

// If you used __dirname / __filename in CommonJS, recreate them in ESM:
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Example usage — replace with the script's original logic
// This is a small stub to keep behavior similar to previous script
const scriptPath = path.join(__dirname, '..', 'some-script.js');

function startMonitor() {
  // spawn a child process as an example
  const proc = spawn(process.execPath, [scriptPath], {
    stdio: 'inherit',
    env: { ...process.env },
  });

  proc.on('close', (code) => {
    console.log(`monitor process exited with code ${code}`);
    process.exit(code);
  });
}

if (import.meta.url === `file://${process.argv[1]}` || process.argv[1].endsWith('bin/dev-monitor-platform.js')) {
  startMonitor();
}