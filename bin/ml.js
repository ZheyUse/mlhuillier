#!/usr/bin/env node
const cp = require('child_process');
const path = require('path');
const fs = require('fs');

const args = process.argv.slice(2);
const root = path.resolve(__dirname, '..');

function runCommand(cmd, cmdArgs) {
  const ps = cp.spawn(cmd, cmdArgs, { stdio: 'inherit' });
  ps.on('exit', code => process.exit(code));
}

// Prefer bundled Windows batch if present
const batPath = path.join(root, 'ml.bat');
if (process.platform === 'win32' && fs.existsSync(batPath)) {
  const cmd = 'cmd.exe';
  const cmdArgs = ['/c', batPath].concat(args);
  runCommand(cmd, cmdArgs);
  return;
}

// Fallback: use PHP generator script if available
const phpScript = path.join(root, 'generate-file-structure.php');
if (fs.existsSync(phpScript)) {
  const cmd = 'php';
  const cmdArgs = [phpScript].concat(args);
  runCommand(cmd, cmdArgs);
  return;
}

console.error('ml: packaged CLI not available on this platform.');
console.error('If you intended to install the full CLI, install via the Windows installer or publish the package with the bundled files.');
process.exit(2);
