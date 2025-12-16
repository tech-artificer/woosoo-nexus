#!/usr/bin/env node
const { spawn } = require('child_process');
const path = require('path');

const scriptDir = path.join(__dirname);
const isWin = process.platform === 'win32';

if (isWin) {
  const psPath = path.join(scriptDir, 'dev-monitor.ps1');
  const child = spawn('powershell.exe', ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', psPath], { stdio: 'inherit' });
  child.on('exit', code => process.exit(code));
  child.on('error', err => {
    console.error('Failed to spawn PowerShell monitor:', err);
    process.exit(1);
  });
} else {
  const shPath = path.join(scriptDir, 'dev-monitor.sh');
  const child = spawn('bash', [shPath], { stdio: 'inherit' });
  child.on('exit', code => process.exit(code));
  child.on('error', err => {
    console.error('Failed to spawn bash monitor:', err);
    process.exit(1);
  });
}
