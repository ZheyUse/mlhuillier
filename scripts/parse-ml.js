#!/usr/bin/env node
/* eslint-disable no-console */
const fs = require('node:fs');
const path = require('node:path');

const ROOT = path.resolve(__dirname, '..');
const ML_BAT_PATH = path.join(ROOT, 'ml.bat');
const OUTPUT_PATH = path.join(ROOT, 'documentation', 'assets', 'data', 'commands.json');

function readText(filePath) {
  return fs.readFileSync(filePath, 'utf8');
}

function ensureDir(filePath) {
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
}

function parseCliVersion(content) {
  const match = content.match(/set\s+"ML_VERSION=([^"]+)"/i);
  return match ? match[1].trim() : 'unknown';
}

function parseDispatchCommands(content) {
  const lines = content.split(/\r?\n/);
  const commands = [];

  const oneLevel = /if\s+\/I\s+"%~1"=="([^"]+)"\s+goto\s+:(cmd_[a-z0-9_]+|show_version|show_help|prepare_help_args)/i;
  const twoLevel = /if\s+\/I\s+"%~1"=="([^"]+)"\s+if\s+\/I\s+"%~2"=="([^"]+)"\s+goto\s+:(cmd_[a-z0-9_]+)/i;

  for (const line of lines) {
    let m = line.match(twoLevel);
    if (m) {
      commands.push({
        name: `ml ${m[1]} ${m[2]}`.replace(/\s+/g, ' ').trim(),
        top: m[1],
        sub: m[2],
        target: m[3],
      });
      continue;
    }

    m = line.match(oneLevel);
    if (m) {
      commands.push({
        name: `ml ${m[1]}`,
        top: m[1],
        sub: '',
        target: m[2],
      });
    }
  }

  const unique = new Map();
  for (const cmd of commands) {
    unique.set(cmd.name.toLowerCase(), cmd);
  }

  return [...unique.values()];
}

function parseHelpCommands(content) {
  const lines = content.split(/\r?\n/);
  const commands = [];
  let inBlock = false;
  let inCommandsSection = false;

  for (const line of lines) {
    if (/^:show_help\s*$/i.test(line.trim())) {
      inBlock = true;
      continue;
    }

    if (inBlock && /echo\s+To get help for a specific command:/i.test(line)) {
      break;
    }

    if (inBlock && /echo\s+Commands:/i.test(line)) {
      inCommandsSection = true;
      continue;
    }

    if (!inBlock || !inCommandsSection) {
      continue;
    }

    const m = line.match(/^echo\s{2,}([^\s][^\s]*?(?:\s+[^\s][^\s]*)?)\s{2,}(.+)$/i);
    if (!m) {
      continue;
    }

    const token = m[1].trim();
    const desc = m[2].trim();
    if (/^(--h|--v|--c|--d)$/i.test(token)) {
      commands.push({
        name: `ml ${token}`,
        top: token,
        sub: '',
        fromHelp: true,
        helpDescription: desc,
      });
      continue;
    }

    const parts = token.split(/\s+/);
    const top = parts[0];
    const sub = parts.slice(1).join(' ');
    commands.push({
      name: `ml ${token}`,
      top,
      sub,
      fromHelp: true,
      helpDescription: desc,
    });
  }

  return commands;
}

function parseHelpDetails(content) {
  const lines = content.split(/\r?\n/);
  const details = new Map();

  for (let i = 0; i < lines.length; i += 1) {
    const labelMatch = lines[i].match(/^:help_([a-z0-9_]+)$/i);
    if (!labelMatch) {
      continue;
    }

    const key = labelMatch[1].toLowerCase();
    let usage = '';
    const descriptionLines = [];

    for (let j = i + 1; j < lines.length; j += 1) {
      const line = lines[j].trim();
      if (/^:help_|^:cmd_|^:show_help|^:prepare_help_args/i.test(line)) {
        break;
      }
      const usageMatch = line.match(/^echo\s+Usage:\s*(.+)$/i);
      if (usageMatch) {
        usage = usageMatch[1].trim();
      }
      const descMatch = line.match(/^echo\s+Description:\s*(.*)$/i);
      if (descMatch) {
        descriptionLines.push(descMatch[1].trim());
      }
      const contMatch = line.match(/^echo\s{2,}(.+)$/i);
      if (contMatch && descriptionLines.length > 0) {
        descriptionLines.push(contMatch[1].trim());
      }
    }

    details.set(key, {
      usage,
      description: descriptionLines.join(' '),
    });
  }

  return details;
}

function inferCategory(name) {
  const lower = name.toLowerCase();
  if (lower.includes('--b') || lower.includes('userdb') || lower.includes('test') || lower.includes('add')) return 'database';
  if (lower.includes('create') || lower.includes('clone')) return 'project';
  if (lower.includes('serve') || lower.includes('nav')) return 'workflow';
  if (lower.includes('update') || lower.includes('--c') || lower.includes('--v') || lower.includes('--d')) return 'maintenance';
  return 'general';
}

function inferDescription(command) {
  const name = command.name.toLowerCase();
  if (name === 'ml test userdb') return 'Execute database connectivity and schema checks for userdb.';
  if (name === 'ml add userdb') return 'Import userdb SQL schema and supporting objects.';
  if (name === 'ml create --a') return 'Open an interactive account creation flow.';
  if (name === 'ml --c') return 'Check local CLI version against remote GitHub version.';
  if (name === 'ml update') return 'Update local CLI runtime files from remote installer assets.';
  if (name === 'ml serve') return 'Run remote serve helper and open project in browser.';
  if (name === 'ml nav') return 'Navigate to htdocs projects quickly and optionally open VS Code.';
  if (name === 'ml clone local') return 'Copy local repository files into CLI tools for development testing.';
  if (name === 'ml --d') return 'Download and execute remote installer downloader.';
  if (name === 'ml --b') return 'Backup MySQL schemas using configured server credentials.';
  if (name === 'ml create --config') return 'Create or update database backup connection config.';
  if (name === 'ml --h') return 'Show global or command-level help output.';
  if (name === 'ml --v') return 'Display currently installed CLI version.';
  return command.helpDescription || 'Run this CLI command to execute its linked workflow.';
}

function inferSyntax(command, helpDetails) {
  const keyMap = new Map([
    ['ml test userdb', 'test_userdb'],
    ['ml add userdb', 'add_userdb'],
    ['ml create --a', 'create_account'],
    ['ml --c', 'check_version'],
    ['ml update', 'update'],
    ['ml serve', 'serve'],
    ['ml nav', 'nav'],
    ['ml --d', 'download_installer'],
    ['ml --b', 'backup'],
    ['ml --v', 'show_version'],
    ['ml --h', 'show_help'],
    ['ml clone local', 'dev'],
    ['ml create --config', 'create_config'],
  ]);

  const key = keyMap.get(command.name.toLowerCase());
  if (key && helpDetails.has(key) && helpDetails.get(key).usage) {
    if (command.name.toLowerCase() === 'ml clone local') {
      return 'ml clone local [destination]';
    }
    return helpDetails.get(key).usage;
  }

  if (command.sub) {
    return `${command.top} ${command.sub}`.startsWith('ml ') ? `${command.top} ${command.sub}` : `ml ${command.top} ${command.sub}`;
  }
  return command.name;
}

function inferParams(command) {
  const lower = command.name.toLowerCase();
  if (lower === 'ml create --a') return ['interactive prompts: id, first_name, last_name, role'];
  if (lower === 'ml create --config') return ['interactive prompts: host, port, user, password, mysqldumpPath, backupPath'];
  if (lower === 'ml --b') return ['schema (optional, use all for all schemas)'];
  if (lower === 'ml nav') return ['--project_name (optional)', '--remote (optional)'];
  if (lower === 'ml serve') return ['project_name (optional)'];
  if (lower === 'ml clone local') return ['destination (optional)'];
  return [];
}

function inferExpectedResult(command) {
  const lower = command.name.toLowerCase();
  if (lower === 'ml test userdb') return 'Shows DB connection status and schema check result.';
  if (lower === 'ml add userdb') return 'Creates/imports required userdb tables and structures.';
  if (lower === 'ml update') return 'Downloads latest CLI runtime and refreshes installed tools.';
  if (lower === 'ml --c') return 'Displays whether a newer version is available.';
  if (lower === 'ml --v') return 'Prints current CLI version number.';
  if (lower === 'ml serve') return 'Opens the local project URL in your browser.';
  if (lower === 'ml nav') return 'Moves shell to selected project folder under htdocs.';
  if (lower === 'ml create --config') return 'Creates C:\\ML CLI\\Tools\\mlcli-config.json for backup connectivity.';
  if (lower === 'ml --b') return 'Creates schema SQL dumps under C:\\ML CLI\\Backup\\BACKUP_MM-DD-YY\\<schema>.';
  return 'Runs the command workflow and returns success or actionable error output.';
}

function inferWhenToUse(command) {
  const lower = command.name.toLowerCase();
  if (lower.includes('test userdb')) return 'Before development starts or after DB config changes.';
  if (lower.includes('add userdb')) return 'When userdb schema is missing or corrupted.';
  if (lower.includes('create --config')) return 'Before using schema backups or whenever DB credentials change.';
  if (lower.includes('--b')) return 'When you need on-demand backups for one schema or all schemas.';
  if (lower.includes('create')) return 'When starting a new project or account bootstrap flow.';
  if (lower.includes('serve')) return 'When you need to quickly preview the app in browser.';
  if (lower.includes('update') || lower.includes('--c') || lower.includes('--v')) return 'When maintaining CLI consistency across machines.';
  if (lower.includes('nav')) return 'When switching projects often inside htdocs.';
  return 'When this workflow matches your current task.';
}

function commandTutorial(command) {
  return {
    whenToUse: inferWhenToUse(command),
    scenario: `Typical ${command.category} workflow using ${command.name}.`,
    steps: [
      `Run: ${command.example}`,
      `Review output and confirm expected result: ${command.expectedResult}`,
      'If it fails, apply the recommended fix from Error Handling then retry.',
    ],
  };
}

function buildCommands(content) {
  const dispatch = parseDispatchCommands(content);
  const fromHelp = parseHelpCommands(content);
  const helpDetails = parseHelpDetails(content);

  const merged = new Map();
  for (const entry of [...dispatch, ...fromHelp]) {
    const key = entry.name.toLowerCase();
    if (!merged.has(key)) {
      merged.set(key, entry);
    } else {
      merged.set(key, { ...merged.get(key), ...entry });
    }
  }

  const results = [];
  for (const entry of merged.values()) {
    const syntax = inferSyntax(entry, helpDetails);
    const description = inferDescription(entry);
    const params = inferParams(entry);
    const example = entry.name;
    const category = inferCategory(entry.name);

    const item = {
      name: entry.name,
      description,
      syntax,
      params,
      example,
      category,
      expectedResult: inferExpectedResult(entry),
    };
    item.tutorial = commandTutorial(item);
    results.push(item);
  }

  if (!results.some((x) => x.name.toLowerCase() === 'ml create <project_name>')) {
    const createCmd = {
      name: 'ml create <project_name>',
      description: 'Generate a new project scaffold from templates.',
      syntax: 'ml create <project_name>',
      params: ['project_name'],
      example: 'ml create banking-system',
      category: 'project',
      expectedResult: 'Project directory and starter files are generated.',
    };
    createCmd.tutorial = commandTutorial(createCmd);
    results.push(createCmd);
  }

  return results.sort((a, b) => a.name.localeCompare(b.name));
}

function buildTutorials(commands) {
  const byName = (name) => commands.find((cmd) => cmd.name.toLowerCase() === name.toLowerCase());
  const cmdNav = byName('ml nav');
  const cmdCreatePbac = byName('ml create --pbac');
  const cmdCreateRbac = byName('ml create --rbac');
  const cmdGen = byName('ml gen');
  const cmdServe = byName('ml serve');
  const cmdTestUserdb = byName('ml test userdb');

  return {
    firstTimeSetup: [
      {
        step: 1,
        command: 'ml --v',
        explanation: 'Verify CLI is installed and available in PATH.',
        expectedResult: 'Current version is printed, e.g. ML CLI version X.Y.Z.',
      },
      {
        step: 2,
        command: 'ml nav',
        explanation: 'Jump directly to C:\\xampp\\htdocs before creating projects.',
        expectedResult: 'Terminal path changes to C:\\xampp\\htdocs.',
      },
      {
        step: 3,
        command: 'ml create <project_name>',
        explanation: 'Generate your first project scaffold.',
        expectedResult: 'Project structure and starter files are created.',
      },
      {
        step: 4,
        command: 'ml nav --<project_name>',
        explanation: 'Enter your new project directory and optionally open VS Code.',
        expectedResult: 'Terminal is inside your project folder.',
      },
      {
        step: 5,
        command: 'Y',
        explanation: 'Accept VS Code prompt when asked by nav helper.',
        expectedResult: 'Project opens in VS Code.',
      },
      {
        step: 6,
        command: 'Continue in terminal',
        explanation: 'Keep using CLI from the project directory.',
        expectedResult: 'You are ready for checks and serve flow.',
      },
      {
        step: 7,
        command: 'ml test userdb',
        explanation: 'Validate DB connection and schema.',
        expectedResult: 'Connection test passes.',
      },
      {
        step: 8,
        command: 'ml add userdb',
        explanation: 'Run this only if test fails to install missing schema.',
        expectedResult: 'Database objects are imported.',
      },
      {
        step: 9,
        command: 'ml test userdb',
        explanation: 'Retry verification after import.',
        expectedResult: 'Connection test now succeeds.',
      },
      {
        step: 10,
        command: 'ml serve',
        explanation: 'Open project in browser and begin development.',
        expectedResult: 'Browser launches local project URL.',
      },
    ],
    convertionPbac: [
      {
        step: 1,
        command: 'ml nav --<project_name>',
        explanation: 'Open your existing default scaffold project where PBAC will be applied.',
        expectedResult: cmdNav ? cmdNav.expectedResult : 'Terminal is inside your selected project.',
      },
      {
        step: 2,
        command: 'ml create --pbac <project_name>',
        explanation: cmdCreatePbac ? cmdCreatePbac.description : 'Create PBAC table and apply PBAC scaffold files.',
        expectedResult: cmdCreatePbac ? cmdCreatePbac.expectedResult : 'PBAC helper runs successfully for your target project.',
      },
      {
        step: 3,
        command: 'Y',
        explanation: 'Confirm the prompt so the conversion continues and scaffold files are applied.',
        expectedResult: 'PBAC conversion proceeds and project files are updated.',
      },
      {
        step: 4,
        command: 'ml gen',
        explanation: cmdGen ? cmdGen.description : 'Run ml gen which auto-reads tools\\generate_access_map.php in the current working directory to generate or refresh the PBAC access map.',
        expectedResult: 'Access map is generated/refreshed for menu and permission checks.',
      },
      {
        step: 5,
        command: 'ml serve',
        explanation: 'Run the app and verify menu-level and permission-level checks work as expected.',
        expectedResult: cmdServe ? cmdServe.expectedResult : 'Project opens and PBAC-gated navigation is testable in browser.',
      },
    ],
    convertionPbacGuide: {
      buttonLabel: 'HOW TO USE?',
      title: 'How to Use PBAC After Conversion',
      intro: 'After conversion, manage menu access (access level) and submenu/action permissions, then regenerate access map when rules change.',
      steps: [
        'Convert your project to PBAC using ml create --pbac <project_name>.',
        'Define access level as parent menu and permissions as child actions/submenus.',
        'Update your project access map whenever menu/permission mappings change.',
        'Verify restricted pages and actions using accounts with different access levels.',
      ],
      commands: [
        'ml create --pbac <project_name>',
        'ml gen',
      ],
      notes: [
        'ml gen looks for tools\\generate_access_map.php in the current working directory and runs it (local gen).',
        'If no map script exists, convert the project to PBAC first before generating map.',
      ],
    },
    convertionRbac: [
      {
        step: 1,
        command: 'ml nav --<project_name>',
        explanation: 'Open your target project before running RBAC table setup.',
        expectedResult: cmdNav ? cmdNav.expectedResult : 'Terminal is inside your selected project.',
      },
      {
        step: 2,
        command: 'ml create --rbac <project_name>',
        explanation: cmdCreateRbac ? cmdCreateRbac.description : 'Create RBAC table for your target project in userdb.',
        expectedResult: cmdCreateRbac ? cmdCreateRbac.expectedResult : 'RBAC helper runs successfully for your target project.',
      },
      {
        step: 3,
        command: 'ml test userdb',
        explanation: 'Validate DB connectivity and ensure RBAC-related schema is reachable.',
        expectedResult: cmdTestUserdb ? cmdTestUserdb.expectedResult : 'Connection test passes for userdb.',
      },
      {
        step: 4,
        command: 'ml serve',
        explanation: 'Run the app and validate role-based page/action access in browser.',
        expectedResult: cmdServe ? cmdServe.expectedResult : 'Project opens and RBAC flow can be verified.',
      },
    ],
    generalUsage: [
      {
        step: 1,
        command: 'ml nav',
        explanation: 'Move to htdocs workspace root.',
        expectedResult: 'Current directory is ready for project operations.',
      },
      {
        step: 2,
        command: 'ml create <project_name>',
        explanation: 'Create or scaffold your working app.',
        expectedResult: 'Project files generated.',
      },
      {
        step: 3,
        command: 'ml nav --<project_name>',
        explanation: 'Switch into project quickly.',
        expectedResult: 'Terminal context set to project.',
      },
      {
        step: 4,
        command: 'ml serve',
        explanation: 'Preview the app in browser and continue coding.',
        expectedResult: 'Project opens at local URL.',
      },
    ],
    commonMistakes: [
      'Running commands outside C:\\xampp\\htdocs project context.',
      'Skipping ml test userdb before first run.',
      'Ignoring version checks and using outdated CLI binaries.',
    ],
    commandLevel: commands.map((cmd) => ({
      command: cmd.name,
      whenToUse: cmd.tutorial.whenToUse,
      scenario: cmd.tutorial.scenario,
      steps: cmd.tutorial.steps,
    })),
    scenarios: [
      {
        title: 'Creating your first project',
        steps: ['ml --v', 'ml nav', 'ml create my-app', 'ml nav --my-app', 'ml serve'],
      },
      {
        title: 'Configuring and running schema backups',
        steps: ['ml create --config', 'ml --b', 'ml --b userdb', 'ml --b all'],
      },
      {
        title: 'Fixing database connection issues',
        steps: ['ml test userdb', 'ml add userdb', 'ml test userdb'],
      },
      {
        title: 'Starting development session',
        steps: ['ml --c', 'ml update (if needed)', 'ml nav --my-app', 'ml serve'],
      },
      {
        title: 'Recovering from setup errors',
        steps: ['ml --v', 'ml --c', 'ml update', 'ml test userdb'],
      },
    ],
    errors: [
      {
        error: 'Command not recognized',
        meaning: 'CLI not installed in PATH or shell session is stale.',
        fix: 'Re-run installer, then open a new terminal and run ml --v.',
      },
      {
        error: 'Failed to fetch remote script',
        meaning: 'Network issue or blocked GitHub endpoint.',
        fix: 'Check internet, proxy, and retry. Then run ml --c to validate connectivity.',
      },
      {
        error: 'Database test failed',
        meaning: 'Schema or credentials are not ready.',
        fix: 'Run ml add userdb then retry ml test userdb.',
      },
      {
        error: 'Error: missing config file',
        meaning: 'Backup command ran before DB backup config was created.',
        fix: 'Run ml create --config then retry ml --b.',
      },
      {
        error: 'mysqldump not found',
        meaning: 'mysqldump is not installed or not detected from PATH/XAMPP.',
        fix: 'Install MySQL client tools or set mysqldumpPath in ml create --config.',
      },
    ],
    faq: [
      {
        q: 'How often should I run ml --c?',
        a: 'Run it at the start of each dev session.',
      },
      {
        q: 'Do I need to run ml update every time?',
        a: 'Only when a newer version is reported.',
      },
      {
        q: 'What is the fastest start command sequence?',
        a: 'ml nav --your-project then ml serve.',
      },
    ],
  };
}

function buildJson() {
  const mlBat = readText(ML_BAT_PATH);
  const cliVersion = parseCliVersion(mlBat);
  const commands = buildCommands(mlBat);
  const tutorials = buildTutorials(commands);

  return {
    generatedAt: new Date().toISOString(),
    source: 'ml.bat',
    cliVersion,
    commands,
    tutorials,
  };
}

function writeOutput(payload) {
  ensureDir(OUTPUT_PATH);
  fs.writeFileSync(OUTPUT_PATH, `${JSON.stringify(payload, null, 2)}\n`, 'utf8');
  console.log(`[docs] generated ${path.relative(ROOT, OUTPUT_PATH)} (${payload.commands.length} commands)`);
}

function runBuild() {
  const payload = buildJson();
  writeOutput(payload);
}

function runWatch() {
  runBuild();
  console.log('[docs] watching ml.bat for changes...');

  let timer = null;
  fs.watch(ML_BAT_PATH, { persistent: true }, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
      try {
        runBuild();
      } catch (err) {
        console.error('[docs] build failed:', err.message);
      }
    }, 150);
  });
}

if (process.argv.includes('--watch')) {
  runWatch();
} else {
  runBuild();
}
