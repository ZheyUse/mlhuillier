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

    const m = line.match(/^echo\s{2,}(.+?)\s{2,}(.+)$/i);
    if (!m) {
      continue;
    }

    const token = m[1].trim().replace(/\^/g, '');
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
  if (lower.includes('migrate')) return 'database';
  if (lower.includes('--b') || lower.includes('userdb') || lower.includes('test') || lower.includes('add')) return 'database';
  if (lower.includes('create') || lower.includes('clone')) return 'project';
  if (lower.includes('serve') || lower.includes('nav')) return 'workflow';
  if (lower.includes('update') || lower.includes('--c') || lower.includes('--v') || lower.includes('--d')) return 'maintenance';
  if (lower.includes('--ai')) return 'ai';
  return 'general';
}

function inferDescription(command) {
  const name = command.name.toLowerCase();
  if (name === 'ml migrate -db <database>') return 'Migrate userdb table structures and data to a decentralized target database.';
  if (name === 'ml migrate') return 'Run database decentralization helper (use -db <DATABASE>).';
  if (name === 'ml serve') return 'Open local project in browser using localhost.';
  if (command.helpDescription) return command.helpDescription;
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
  return 'Run this CLI command to execute its linked workflow.';
}

function inferSyntax(command, helpDetails) {
  const lower = command.name.toLowerCase();
  if (lower === 'ml migrate') return 'ml migrate -db <DATABASE>';
  if (lower === 'ml migrate -db <database>') return 'ml migrate -db <DATABASE>';
  if (lower === 'ml serve') return 'ml serve | ml serve --projectname';
  if (command.syntax) return command.syntax;
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
  if (Array.isArray(command.params)) return command.params;
  const lower = command.name.toLowerCase();
  if (lower === 'ml migrate' || lower === 'ml migrate -db <database>') return ['-db <DATABASE>'];
  if (lower === 'ml serve') return ['--projectname (optional)'];
  if (lower === 'ml create --a') return ['interactive prompts: id, first_name, last_name, role'];
  if (lower === 'ml create --config') return ['interactive prompts: host, port, user, password, mysqldumpPath, backupPath'];
  if (lower === 'ml --b') return ['schema (optional, use all for all schemas)'];
  if (lower === 'ml nav') return ['--project_name (optional)', '--remote (optional)'];
  if (lower === 'ml serve') return ['project_name (optional)'];
  if (lower === 'ml clone local') return ['destination (optional)'];
  return [];
}

function inferExpectedResult(command) {
  if (command.expectedResult) return command.expectedResult;
  const lower = command.name.toLowerCase();
  if (lower === 'ml migrate' || lower === 'ml migrate -db <database>') return 'Copies userdb table structures and data to target DB, rewrites project DB references, and writes migration-log.md.';
  if (lower === 'ml test userdb') return 'Shows DB connection status and schema check result.';
  if (lower === 'ml add userdb') return 'Creates/imports required userdb tables and structures.';
  if (lower === 'ml update') return 'Downloads latest CLI runtime and refreshes installed tools.';
  if (lower === 'ml --c') return 'Displays whether a newer version is available.';
  if (lower === 'ml --v') return 'Prints current CLI version number.';
  if (lower === 'ml serve') return 'Opens http://localhost/<project_name> in your browser.';
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

  const syntheticCommands = [
    {
      name: 'ml serve -o',
      top: 'serve',
      sub: '-o',
      helpDescription: 'Start ngrok online tunnel for current project and open shareable URL.',
      syntax: 'ml serve -o',
      params: ['project_name (resolved from current directory)'],
      expectedResult: 'Opens shareable ngrok URL for the current project.',
    },
    {
      name: 'ml serve --projectname -o',
      top: 'serve',
      sub: '--projectname -o',
      helpDescription: 'Start ngrok online tunnel for explicit project and open shareable URL.',
      syntax: 'ml serve --projectname -o',
      params: ['--projectname'],
      expectedResult: 'Opens shareable ngrok URL for the selected project.',
    },
    {
      name: 'ml serve projectname -o',
      top: 'serve',
      sub: 'projectname -o',
      helpDescription: 'Start ngrok online tunnel for explicit project and open shareable URL.',
      syntax: 'ml serve projectname -o',
      params: ['projectname'],
      expectedResult: 'Opens shareable ngrok URL for the selected project.',
    },
    {
      name: 'ml serve projectname --online',
      top: 'serve',
      sub: 'projectname --online',
      helpDescription: 'Start ngrok online tunnel for explicit project and open shareable URL.',
      syntax: 'ml serve projectname --online',
      params: ['projectname'],
      expectedResult: 'Opens shareable ngrok URL for the selected project.',
    },
    {
      name: 'ml serve -stop',
      top: 'serve',
      sub: '-stop',
      helpDescription: 'Stop active ngrok online tunnel process.',
      syntax: 'ml serve -stop',
      params: [],
      expectedResult: 'Stops active ngrok tunnel process if running.',
    },
    {
      name: 'ml --ai claude',
      top: '--ai',
      sub: 'claude',
      helpDescription: 'Start uvicorn in background and Claude Code visibly — Claude Code runs in the current working directory.',
      syntax: 'ml --ai claude',
      params: [],
      expectedResult: 'Uvicorn runs in background on port 8082; Claude Code window opens in your current directory.',
    },
    {
      name: 'ml --ai bg',
      top: '--ai',
      sub: 'bg',
      helpDescription: 'Start both uvicorn and Claude Code entirely in the background (no visible terminal windows).',
      syntax: 'ml --ai bg',
      params: [],
      expectedResult: 'Both processes start silently in the background. Process PIDs tracked in the ML CLI state file.',
    },
    {
      name: 'ml --ai stop',
      top: '--ai',
      sub: 'stop',
      helpDescription: 'Stop all Free Claude Code processes started by ml --ai and clean up the state file.',
      syntax: 'ml --ai stop',
      params: [],
      expectedResult: 'Kill signal sent to all tracked uvicorn and claude processes. State file and temp scripts removed.',
    },
    {
      name: 'ml --ai restart',
      top: '--ai',
      sub: 'restart',
      helpDescription: 'Stop all running Free Claude Code processes, then start both uvicorn and Claude Code in the background.',
      syntax: 'ml --ai restart',
      params: [],
      expectedResult: 'Existing processes stopped cleanly, then both servers restart in background mode.',
    },
    {
      name: 'ml --ai cm',
      top: '--ai',
      sub: 'cm',
      helpDescription: 'Interactively change the configured AI model for Opus, Sonnet, Haiku, or the default model tier.',
      syntax: 'ml --ai cm',
      params: [],
      expectedResult: 'Interactive prompt lets you select a model tier and input a new nvidia_nim model path. .env updated. Restart required.',
    },
    {
      name: 'ml --ai key',
      top: '--ai',
      sub: 'key',
      helpDescription: 'Update the NVIDIA_NIM_API_KEY stored in the .env file for Free Claude Code authentication.',
      syntax: 'ml --ai key',
      params: [],
      expectedResult: 'Interactive prompt for new key. Value written to .env. A restart is needed for the new key to take effect.',
    },
    {
      name: 'ml install ai',
      top: 'install',
      sub: 'ai',
      helpDescription: 'Install Free Claude Code stack: clones free-claude-code repo, installs prerequisites (uv, Python 3.14), configures .env with models and API key, installs Claude Code, and patches VS Code settings.',
      syntax: 'ml install ai',
      params: [],
      expectedResult: 'Free Claude Code installed to C:\\free-claude-code\\free-claude-code with all prerequisites and config.',
    },
  ];

  for (const entry of syntheticCommands) {
    if (results.some((x) => x.name.toLowerCase() === entry.name.toLowerCase())) {
      continue;
    }
    const item = {
      name: entry.name,
      description: inferDescription(entry),
      syntax: inferSyntax(entry, helpDetails),
      params: inferParams(entry),
      example: entry.name,
      category: inferCategory(entry.name),
      expectedResult: inferExpectedResult(entry),
    };
    item.tutorial = commandTutorial(item);
    results.push(item);
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
      intro: 'PBAC controls parent menu visibility (access levels) and submenu/action permissions. Use the provided helpers and access map to manage visibility and route protection.',
      steps: [
        "Edit src/templates/sidebar.php and wrap the parent menu with a parent guard: <?php if (has_menu_access('Maintenance')): ?> ... <?php endif; ?>",
        "Protect each submenu link with has_permission('MenuLabel SubmenuLabel'). Example: <?php if (has_permission('Maintenance Access Level')): ?> <li> <a href=\"/src/pages/maintenance/accesslevel/accesslevel.php\">Access Level</a> </li> <?php endif; ?>",
        "Keep permission keys stable. The default generator uses the format 'MenuLabel SubmenuLabel' (for example 'Maintenance Access Level') — use the same string when calling has_permission() and when assigning permissions to users.",
        "Run 'ml gen' (or 'php tools/generate_access_map.php') from your project root to generate src/assets/js/accesslevel-map.json. This file maps menus, submenu permission ids, and access_level bitmasks used by the admin UI.",
        "Use the Access Level page (Maintenance → Access Level) to assign an access_level and a set of permission ids to a user. At login the PBAC session loader populates $_SESSION['user_access_level'] and $_SESSION['user_permissions'].",
        "To protect routes or controllers use require_mapped_permission_for_current_route() or call has_permission('Permission Key') in your middleware/controllers. The scaffold also provides helpers like requirePermission() and requireMenuAccess().",
        "Admin rule: an access_level of -1 grants all permissions. Admin rows with missing permissions may be auto-populated by the scaffold's session loader.",
      ],
      commands: [
        'ml gen',
        'php tools/generate_access_map.php'
      ],
      notes: [
        "Permission ID format: 'MenuLabel SubmenuLabel' (space-separated). The generator uses this convention — changing sidebar labels requires re-running 'ml gen' to keep the map in sync.",
        "If 'ml gen' is not available, run tools/generate_access_map.php manually from the project root to produce src/assets/js/accesslevel-map.json.",
        "Sidebar PHP snippet patterns are used by the map generator: ensure submenu link labels (span.sidebar__submenu-label) and href targets are correct so permissions and targets are extracted properly.",
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
    installAndUseClaudeCode: [
      {
        step: 1,
        command: 'ml install ai',
        explanation: 'Install Free Claude Code: clones free-claude-code repo, installs uv + Python 3.14, configures .env with default models, prompts for NVIDIA NIM API key, installs Claude Code via npm, and patches VS Code settings.',
        expectedResult: 'Free Claude Code installed to C:\\free-claude-code\\free-claude-code with everything configured.',
      },
      {
        step: 2,
        command: 'Get NVIDIA NIM API key',
        explanation: 'Sign up / log in at https://build.nvidia.com/ and copy your API key. During install you will be prompted to enter it.',
        expectedResult: 'NVIDIA NIM API key is stored in C:\\free-claude-code\\free-claude-code\\.env.',
      },
      {
        step: 3,
        command: 'ml --ai',
        explanation: 'Start uvicorn (API server on port 8082) and Claude Code in visible terminal windows. Uvicorn runs in free-claude-code directory; Claude Code inherits your current working directory.',
        expectedResult: 'Two PowerShell windows open — one for uvicorn, one for Claude Code.',
      },
      {
        step: 4,
        command: 'ml --ai claude',
        explanation: 'Start uvicorn in background and Claude Code visibly in your project directory. Use this when you are already inside your project folder and want Claude Code to start there instead of free-claude-code.',
        expectedResult: 'Uvicorn runs silently in background on port 8082; Claude Code window opens in your current directory.',
      },
      {
        step: 5,
        command: 'ml --ai cm',
        explanation: 'Change the active AI model. Select Opus, Sonnet, Haiku, or the default model tier and enter a nvidia_nim model slug.',
        expectedResult: '.env updated with the new model. Restart with ml --ai restart to load it.',
      },
      {
        step: 6,
        command: 'ml --ai restart',
        explanation: 'Stop and restart both processes (e.g., after changing models or API key in .env).',
        expectedResult: 'Fresh uvicorn + Claude Code processes running with updated configuration.',
      },
    ],
    installAndUseClaudeCodeGuide: {
      buttonLabel: 'HOW TO USE CLAUDE CODE?',
      title: 'How to Use Free Claude Code with ML CLI',
      intro: 'Free Claude Code is a full-stack setup: a uvicorn API server on port 8082 acts as the proxy to NVIDIA NIM models, while Claude Code connects to it as the AI engine. Use ml --ai commands to manage the stack.',
      steps: [
        'Run ml install ai to set up the entire stack: uv, Python 3.14, free-claude-code repo, Claude Code npm package, and VS Code configuration.',
        'Get an NVIDIA NIM API key from https://build.nvidia.com/ — enter it when prompted by the installer or later with ml --ai key.',
        'Default models are pre-configured on install: MODEL_OPUS=nvidia_nim/deepseek-ai/deepseek-v4-pro, MODEL_SONNET=nvidia_nim/minimaxai/minimax-m2.7, MODEL_HAIKU=nvidia_nim/z-ai/glm4.7, MODEL=nvidia_nim/z-ai/glm-5.1.',
        'Run ml --ai to start uvicorn and Claude Code in visible terminal windows. Both processes must be running for Claude Code to work.',
        'Run ml --ai claude from your project folder when you want Claude Code to start in that directory instead of free-claude-code. Uvicorn stays in the background.',
        'Run ml --ai bg when you want both processes running silently with no open terminal windows.',
        'To switch models: ml --ai cm, pick a tier, enter the model slug. Then ml --ai restart for changes to take effect.',
        'To update your API key: ml --ai key, paste the new key. Then ml --ai restart.',
        'Shut everything down with ml --ai stop when you are done. Use ml --ai restart to reload after any .env changes.',
        'Claude Code runs in your terminal — you can ask it to read files, write code, explain functions, run commands, and more using natural language.',
      ],
      commands: [
        'ml install ai',
        'ml --ai',
        'ml --ai claude',
        'ml --ai bg',
        'ml --ai stop',
        'ml --ai restart',
        'ml --ai cm',
        'ml --ai key',
      ],
      notes: [
        'Uvicorn must be running before Claude Code — ml --ai starts uvicorn first, then Claude Code. ml --ai claude starts uvicorn in background first, then Claude Code.',
        'Claude Code in ml --ai uses your current working directory. Navigate to your project first with ml nav --my-project before running ml --ai claude.',
        'Models are stored in C:\\free-claude-code\\free-claude-code\\.env. You can edit this file directly or use ml --ai cm.',
        'Get a fresh NVIDIA NIM API key at https://build.nvidia.com/ if yours expires or hits rate limits.',
        'The VS Code extension for Claude Code connects to the same uvicorn server started by ml --ai.',
      ],
    },
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
        title: 'Workbench Export (Interactive)',
        steps: [
          'ml wb --export',
          'Enter database names (comma-separated) or type all',
          'For each selected database, enter table names or type all',
          'Choose export method (1..6)',
          'Enter folder name or press Enter for default',
          'Confirm export',
          'Check output in C:\\ML CLI\\Exports\\<MM-DD-YYYY>\\<FOLDER_NAME>\\',
        ],
      },
      {
        title: 'Apply PBAC Scaffold',
        steps: [
          'ml nav --<project_name>',
          'ml create --pbac <project_name>',
          'Confirm PBAC conversion prompt',
          'ml gen',
          'ml serve',
        ],
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
        title: 'Migrating to a decentralized database',
        steps: [
          'ml nav --<project_name>',
          'ml migrate -db <DATABASE>',
          'Confirm migration prompt with Y',
          'Review migration-log.md for copied rows and rewritten files',
        ],
      },
      {
        title: 'Starting development session',
        steps: ['ml --c', 'ml update (if needed)', 'ml nav --my-app', 'ml serve'],
      },
      {
        title: 'Recovering from setup errors',
        steps: ['ml --v', 'ml --c', 'ml update', 'ml test userdb'],
      },
    
      {
        title: 'Automated Scenario Example',
        steps: [
          'Run ml wb --export',
          'Select DB names',
          'Confirm export'
        ]
      },
      {
        title: 'Serve Current Project (Local)',
        steps: [
          'ml serve',
          'Opens http://localhost/<current_project> in browser'
        ]
      },
      {
        title: 'Serve Current Project (Online)',
        steps: [
          'ml serve -o',
          'Creates ngrok tunnel on 80 (fallback 8080)',
          'Opens https://<ngrok-domain>/<current_project>'
        ]
      },
      {
        title: 'Serve Specific Project (Local)',
        steps: [
          'ml serve --projectname',
          'Opens http://localhost/<projectname> in browser'
        ]
      },
      {
        title: 'Serve Specific Project (Online)',
        steps: [
          'ml serve --projectname -o',
          'ml serve projectname -o',
          'ml serve projectname --online',
          'Creates ngrok tunnel on 80 (fallback 8080)',
          'Opens https://<ngrok-domain>/<projectname>'
        ]
      },
      {
        title: 'Stop Online Tunnel',
        steps: [
          'ml serve -o',
          'Share link is created via ngrok',
          'ml serve -stop',
        ],
      },
      {
        title: 'Install Free Claude Code',
        steps: [
          'ml install ai',
          'Enter your NVIDIA NIM API key when prompted (get from https://build.nvidia.com/)',
          'Wait for prerequisites (uv, Python 3.14) to install',
          'Claude Code installed via npm and VS Code settings configured',
        ],
      },
      {
        title: 'Claude Code Only — Run from Your Project Directory',
        steps: [
          'ml nav --my-project',
          'ml --ai claude',
          'Uvicorn starts in background on port 8082',
          'Claude Code window opens in your current project directory',
          'Work on project files using natural language',
        ],
      },
      {
        title: 'Switch AI Model',
        steps: [
          'ml --ai cm',
          'Select model tier: 1=Opus, 2=Sonnet, 3=Haiku, 4=Default',
          'Enter nvidia_nim model slug (e.g., minimaxai/minimax-m2.7)',
          'ml --ai restart',
        ],
      },
      {
        title: 'Update NVIDIA API Key',
        steps: [
          'Get new key from https://build.nvidia.com/',
          'ml --ai key',
          'Paste the new NVIDIA_NIM_API_KEY',
          'ml --ai restart',
        ],
      },
      {
        title: 'Stop and Restart Free Claude Code',
        steps: [
          'ml --ai stop',
          'ml --ai restart',
        ],
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
