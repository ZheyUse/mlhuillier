#!/usr/bin/env node
const fs = require('fs');
const path = require('path');

function usage() {
  console.error('Usage: node scripts/add-scenario.js --title "Title" --step "Step 1" --step "Step 2" ...');
  process.exit(1);
}

const args = process.argv.slice(2);
let title = null;
const steps = [];
let fileOverride = null;

for (let i = 0; i < args.length; i++) {
  const a = args[i];
  if (a === '--title' || a === '-t') {
    title = args[i + 1];
    i++;
    continue;
  }
  if (a === '--step' || a === '-s') {
    steps.push(args[i + 1]);
    i++;
    continue;
  }
  if (a === '--file' || a === '-f') {
    fileOverride = args[i + 1];
    i++;
    continue;
  }
  if (a === '--help' || a === '-h') {
    usage();
  }
  console.error('Unknown arg:', a);
  usage();
}

if (!title || steps.length === 0) {
  console.error('Error: --title and at least one --step are required.');
  usage();
}

const parseFile = fileOverride ? path.resolve(fileOverride) : path.join(__dirname, 'parse-ml.js');
if (!fs.existsSync(parseFile)) {
  console.error('Could not find parse-ml.js at', parseFile);
  process.exit(2);
}

let content = fs.readFileSync(parseFile, 'utf8');
const marker = 'scenarios: [';
const idx = content.indexOf(marker);
if (idx === -1) {
  console.error('Could not find "scenarios: [" in parse-ml.js');
  process.exit(3);
}

const openIdx = content.indexOf('[', idx);
// find matching closing bracket for the scenarios array
let pos = openIdx;
let depth = 0;
let endIdx = -1;
for (; pos < content.length; pos++) {
  const ch = content[pos];
  if (ch === '[') depth++;
  else if (ch === ']') {
    depth--;
    if (depth === 0) {
      endIdx = pos;
      break;
    }
  }
}
if (endIdx === -1) {
  console.error('Could not locate end of scenarios array.');
  process.exit(4);
}

// compute indentation for insertion
const newlineAfterOpen = content.indexOf('\n', openIdx);
const nextLineIndentMatch = content.slice(newlineAfterOpen + 1).match(/^(\s*)/);
const indent = nextLineIndentMatch ? nextLineIndentMatch[1] : '    ';

function toSingleQuoted(s) {
  return "'" + s.replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
}

const stepsBlock = steps.map(s => `${indent}    ${toSingleQuoted(s)}`).join(',\n');
const block = `\n${indent}{\n${indent}  title: ${toSingleQuoted(title)},\n${indent}  steps: [\n${stepsBlock}\n${indent}  ]\n${indent}},`;

const newContent = content.slice(0, endIdx) + block + content.slice(endIdx);
fs.writeFileSync(parseFile, newContent, 'utf8');
console.log('Inserted scenario "' + title + '" into', parseFile);
console.log('Run `npm run docs:build` to regenerate documentation output.');
