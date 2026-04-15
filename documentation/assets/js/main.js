/* global DocsSearch */
async function loadData() {
  const response = await fetch('assets/data/commands.json', { cache: 'no-store' });
  if (!response.ok) {
    throw new Error(`Failed to load docs data: ${response.status}`);
  }
  return response.json();
}

async function loadVersionHistory() {
  try {
    const response = await fetch('assets/data/version-history.json', { cache: 'no-store' });
    if (!response.ok) {
      return null;
    }
    return await response.json();
  } catch {
    return null;
  }
}

function createCopyBlock(command) {
  const wrap = document.createElement('div');
  wrap.className = 'code-line';

  const code = document.createElement('code');
  code.textContent = command;

  const btn = document.createElement('button');
  btn.className = 'copy-btn';
  btn.type = 'button';
  btn.textContent = 'Copy';
  btn.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(command);
      btn.textContent = 'Copied';
      setTimeout(() => {
        btn.textContent = 'Copy';
      }, 800);
    } catch {
      btn.textContent = 'Failed';
      setTimeout(() => {
        btn.textContent = 'Copy';
      }, 800);
    }
  });

  wrap.append(code, btn);
  return wrap;
}

function createCopyPreBlock(text) {
  const wrap = document.createElement('div');
  wrap.className = 'guide-code-block';

  const pre = document.createElement('pre');
  const code = document.createElement('code');
  code.textContent = text;
  pre.appendChild(code);

  const btn = document.createElement('button');
  btn.className = 'copy-btn';
  btn.type = 'button';
  btn.textContent = 'Copy';
  btn.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(text);
      btn.textContent = 'Copied';
      setTimeout(() => { btn.textContent = 'Copy'; }, 800);
    } catch {
      btn.textContent = 'Failed';
      setTimeout(() => { btn.textContent = 'Copy'; }, 800);
    }
  });

  wrap.append(pre, btn);
  return wrap;
}

function renderIntro(root, data) {
  root.innerHTML = '';
  root.dataset.searchText = [
    'introduction',
    'what is ml',
    'features',
    data.cliVersion,
    data.commands.map((x) => x.name).join(' '),
  ].join(' ').toLowerCase();

  const h = document.createElement('h3');
  h.className = 'section-title';
  h.textContent = 'Introduction';

  const p = document.createElement('p');
  p.className = 'section-sub';
  p.textContent = 'M LHUILLIER ML CLI is a workflow command-line tool for project scaffolding, database setup, navigation, and update automation. It also provides utilities for account and schema management, developer helpers, and remote workflow helpers.';

  const cards = document.createElement('div');
  cards.className = 'cards';

  const highlights = [
    `CLI version tracked: ${data.cliVersion}`,
    `Total parsed commands: ${data.commands.length}`,
    'Includes PBAC and RBAC table creation workflows',
    'Step-by-step tutorials and scenario guides included',
  ];

  highlights.forEach((text) => {
    const card = document.createElement('article');
    card.className = 'step-card';
    card.textContent = text;
    cards.append(card);
  });

  root.append(h, p, cards);
}

function renderTutorialSection(root, title, subtitle, steps) {
  const safeSteps = Array.isArray(steps) ? steps : [];
  root.innerHTML = '';
  root.dataset.searchText = [title, subtitle, safeSteps.map((s) => `${s.command || ''} ${s.explanation || ''} ${s.expectedResult || ''}`).join(' ')].join(' ').toLowerCase();

  const h = document.createElement('h3');
  h.className = 'section-title';
  h.textContent = title;
  const p = document.createElement('p');
  p.className = 'section-sub';
  p.textContent = subtitle;

  const cards = document.createElement('div');
  cards.className = 'cards';

  safeSteps.forEach((step) => {
    const card = document.createElement('article');
    card.className = 'step-card';

    const idx = document.createElement('span');
    idx.className = 'step-index';
    idx.textContent = String(step.step);

    const explain = document.createElement('p');
    explain.textContent = step.explanation;

    const expected = document.createElement('p');
    expected.className = 'section-sub';
    expected.textContent = `Expected: ${step.expectedResult}`;

    card.append(idx, createCopyBlock(step.command), explain, expected);
    cards.append(card);
  });

  root.append(h, p, cards);
}

function renderPbacHowToGuide(root, guide) {
  if (!guide || !Array.isArray(guide.steps) || guide.steps.length === 0) return '';

  const actions = document.createElement('div');
  actions.className = 'section-actions';

  const btn = document.createElement('button');
  btn.type = 'button';
  btn.className = 'section-action-btn';
  btn.textContent = guide.buttonLabel || 'HOW TO USE?';

  const details = document.createElement('details');
  details.className = 'guide-details';

  const summary = document.createElement('summary');
  summary.textContent = guide.title || 'How to Use PBAC After Conversion';

  const content = document.createElement('div');
  content.className = 'guide-content';

  const intro = document.createElement('p');
  intro.textContent = guide.intro || 'This guide explains PBAC usage after conversion.';
  content.appendChild(intro);

  // Steps
  const stepsTitle = document.createElement('p');
  stepsTitle.className = 'guide-subtitle';
  stepsTitle.textContent = 'How To Work With PBAC';
  content.appendChild(stepsTitle);

  const stepsOl = document.createElement('ol');
  stepsOl.className = 'guide-list';

  guide.steps.forEach((item) => {
    const li = document.createElement('li');

    if (typeof item === 'string') {
      const exIndex = item.indexOf('Example:');
      if (exIndex !== -1) {
        const textPart = item.substring(0, exIndex).trim();
        const codePart = item.substring(exIndex + 'Example:'.length).trim();
        if (textPart) {
          const p = document.createElement('div');
          p.textContent = textPart;
          li.appendChild(p);
        }
        if (codePart) li.appendChild(createCopyPreBlock(codePart));
      } else if (item.includes('<?php') || item.includes('<li') || item.includes('<a')) {
        // treat as code-heavy step
        li.appendChild(createCopyPreBlock(item));
      } else {
        li.textContent = item;
      }
    } else {
      li.textContent = String(item);
    }

    stepsOl.appendChild(li);
  });

  content.appendChild(stepsOl);

  // Commands (copyable)
  if (Array.isArray(guide.commands) && guide.commands.length > 0) {
    const commandsTitle = document.createElement('p');
    commandsTitle.className = 'guide-subtitle';
    commandsTitle.textContent = 'Generate Or Refresh Access Map';
    content.appendChild(commandsTitle);

    const commandsUl = document.createElement('ul');
    commandsUl.className = 'guide-list';
    guide.commands.forEach((cmd) => {
      const li = document.createElement('li');
      li.appendChild(createCopyBlock(cmd));
      commandsUl.appendChild(li);
    });
    content.appendChild(commandsUl);
  }

  // Notes
  if (Array.isArray(guide.notes) && guide.notes.length > 0) {
    const notesTitle = document.createElement('p');
    notesTitle.className = 'guide-subtitle';
    notesTitle.textContent = 'Notes';
    content.appendChild(notesTitle);

    const notesUl = document.createElement('ul');
    notesUl.className = 'guide-list';
    guide.notes.forEach((n) => {
      const li = document.createElement('li');
      li.textContent = n;
      notesUl.appendChild(li);
    });
    content.appendChild(notesUl);
  }

  details.appendChild(summary);
  details.appendChild(content);

  btn.addEventListener('click', () => {
    details.open = true;
    details.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  actions.appendChild(btn);
  root.append(actions, details);

  return [guide.buttonLabel, guide.title, guide.intro, (guide.steps || []).join(' '), (guide.commands || []).join(' '), (guide.notes || []).join(' ')].join(' ');
}

function renderCommands(root, commands) {
  root.innerHTML = '';
  root.dataset.searchText = commands
    .map((c) => `${c.name} ${c.description} ${c.syntax} ${(c.params || []).join(' ')} ${c.tutorial.whenToUse} ${c.tutorial.scenario}`)
    .join(' ')
    .toLowerCase();

  const h = document.createElement('h3');
  h.className = 'section-title';
  h.textContent = 'Commands Reference';

  const p = document.createElement('p');
  p.className = 'section-sub';
  p.textContent = 'Dynamic command cards generated from commands.json. Each command includes usage, parameters, example, and tutorial guidance.';

  const cards = document.createElement('div');
  cards.className = 'cards';

  commands.forEach((cmd) => {
    const card = document.createElement('article');
    card.className = 'command-card searchable-command';
    card.dataset.searchText = `${cmd.name} ${cmd.description} ${cmd.syntax} ${(cmd.params || []).join(' ')} ${cmd.tutorial.whenToUse} ${cmd.tutorial.scenario}`.toLowerCase();

    const head = document.createElement('div');
    head.className = 'command-head';

    const title = document.createElement('h4');
    title.textContent = cmd.name;

    const tag = document.createElement('span');
    tag.className = 'tag';
    tag.textContent = cmd.category;

    head.append(title, tag);

    const desc = document.createElement('p');
    desc.textContent = cmd.description;

    const syntax = document.createElement('p');
    syntax.className = 'section-sub';
    syntax.textContent = `Syntax: ${cmd.syntax}`;

    const params = document.createElement('p');
    params.className = 'section-sub';
    params.textContent = `Parameters: ${(cmd.params && cmd.params.length) ? cmd.params.join(', ') : 'None'}`;

    const tutorial = document.createElement('details');
    tutorial.open = false;
    const summary = document.createElement('summary');
    summary.textContent = 'Command tutorial';

    const when = document.createElement('p');
    when.textContent = `When to use: ${cmd.tutorial.whenToUse}`;

    const scenario = document.createElement('p');
    scenario.textContent = `Scenario: ${cmd.tutorial.scenario}`;

    const list = document.createElement('ol');
    list.className = 'list';
    cmd.tutorial.steps.forEach((item) => {
      const li = document.createElement('li');
      li.textContent = item;
      list.append(li);
    });

    tutorial.append(summary, when, scenario, list);

    card.append(head, desc, syntax, params, createCopyBlock(cmd.example), tutorial);
    cards.append(card);
  });

  root.append(h, p, cards);
}

function renderScenarios(root, scenarios) {
  const safeScenarios = Array.isArray(scenarios) ? scenarios : [];
  root.innerHTML = '';
  root.dataset.searchText = safeScenarios.map((s) => `${s.title || ''} ${Array.isArray(s.steps) ? s.steps.join(' ') : ''}`).join(' ').toLowerCase();

  const h = document.createElement('h3');
  h.className = 'section-title';
  h.textContent = 'Scenario-Based Tutorials';
  const p = document.createElement('p');
  p.className = 'section-sub';
  p.textContent = 'Apply commands in realistic flows for onboarding, troubleshooting, and day-to-day development.';

  const cards = document.createElement('div');
  cards.className = 'cards';

  safeScenarios.forEach((scenario) => {
    const card = document.createElement('article');
    card.className = 'scenario-card';
    const title = document.createElement('h4');
    title.textContent = scenario.title;
    const list = document.createElement('ol');
    list.className = 'list';
    (Array.isArray(scenario.steps) ? scenario.steps : []).forEach((step) => {
      const li = document.createElement('li');
      li.textContent = step;
      list.append(li);
    });
    card.append(title, list);
    cards.append(card);
  });

  root.append(h, p, cards);
}

function renderErrors(root, errors) {
  const safeErrors = Array.isArray(errors) ? errors : [];
  root.innerHTML = '';
  root.dataset.searchText = safeErrors.map((e) => `${e.error || ''} ${e.meaning || ''} ${e.fix || ''}`).join(' ').toLowerCase();

  const h = document.createElement('h3');
  h.className = 'section-title';
  h.textContent = 'Error Handling';

  const cards = document.createElement('div');
  cards.className = 'cards';

  safeErrors.forEach((item) => {
    const card = document.createElement('article');
    card.className = 'error-card';

    const name = document.createElement('h4');
    name.textContent = item.error;

    const meaning = document.createElement('p');
    meaning.textContent = `Meaning: ${item.meaning}`;

    const fix = document.createElement('p');
    fix.className = 'section-sub';
    fix.textContent = `Fix: ${item.fix}`;

    card.append(name, meaning, fix);
    cards.append(card);
  });

  root.append(h, cards);
}

function renderFaq(root, faq, tips) {
  const safeFaq = Array.isArray(faq) ? faq : [];
  const safeTips = Array.isArray(tips) ? tips : [];
  root.innerHTML = '';
  root.dataset.searchText = [...safeFaq.map((f) => `${f.q || ''} ${f.a || ''}`), ...safeTips].join(' ').toLowerCase();

  const h = document.createElement('h3');
  h.className = 'section-title';
  h.textContent = 'FAQ / Tips';

  const cards = document.createElement('div');
  cards.className = 'cards';

  safeFaq.forEach((item) => {
    const card = document.createElement('article');
    card.className = 'faq-card';
    const q = document.createElement('h4');
    q.textContent = item.q;
    const a = document.createElement('p');
    a.textContent = item.a;
    card.append(q, a);
    cards.append(card);
  });

  if (safeTips.length) {
    const tipsCard = document.createElement('article');
    tipsCard.className = 'faq-card';
    const title = document.createElement('h4');
    title.textContent = 'Best Practices';
    const list = document.createElement('ul');
    list.className = 'list';
    safeTips.forEach((tip) => {
      const li = document.createElement('li');
      li.textContent = tip;
      list.append(li);
    });
    tipsCard.append(title, list);
    cards.append(tipsCard);
  }

  root.append(h, cards);
}

function renderLatestReleaseMini(root, history) {
  if (!root) return;
  root.innerHTML = '';

  if (!history || !Array.isArray(history.releases) || history.releases.length === 0) {
    root.classList.add('is-empty');
    root.textContent = 'Latest release highlights unavailable.';
    return;
  }

  root.classList.remove('is-empty');
  const latest = history.releases.find((x) => x.isLatest) || history.releases[0];
  const highlights = Array.isArray(latest.highlights) ? latest.highlights.slice(0, 3) : [];

  const card = document.createElement('div');
  card.className = 'latest-mini-card';

  const head = document.createElement('div');
  head.className = 'latest-mini-head';

  const version = document.createElement('span');
  version.className = 'latest-mini-version';
  version.textContent = `Latest: ${latest.version || 'unknown'}`;

  const count = document.createElement('span');
  count.className = 'latest-mini-meta';
  count.textContent = `${latest.commitCount || 0} commits`;

  head.append(version, count);
  card.appendChild(head);

  if (highlights.length > 0) {
    const list = document.createElement('ul');
    list.className = 'latest-mini-list';
    highlights.forEach((item) => {
      const li = document.createElement('li');
      li.textContent = item;
      list.appendChild(li);
    });
    card.appendChild(list);
  }

  const jump = document.createElement('a');
  jump.className = 'latest-mini-link';
  jump.href = '#version-history';
  jump.textContent = 'View full changelog';
  card.appendChild(jump);

  root.appendChild(card);
}

function renderVersionHistory(root, history) {
  root.innerHTML = '';

  const h = document.createElement('h3');
  h.className = 'section-title';
  h.textContent = 'Version History';

  const sub = document.createElement('p');
  sub.className = 'section-sub';

  if (!history || !Array.isArray(history.releases) || history.releases.length === 0) {
    sub.textContent = 'No version history data found yet. Generate it with node version-history/generate-version-history.js';
    root.dataset.searchText = 'version history changelog releases';
    root.append(h, sub);
    return;
  }

  const latestVersion = history.latestVersion || 'unknown';
  const generatedAt = history.generatedAt || '';
  sub.textContent = `Latest version: ${latestVersion}${generatedAt ? ` | Generated: ${generatedAt}` : ''}`;

  const cards = document.createElement('div');
  cards.className = 'version-cards';

  const typeOrder = ['release', 'feature', 'fix', 'docs', 'refactor', 'performance', 'security', 'test', 'chore', 'other'];
  const typeLabel = {
    release: 'Release',
    feature: 'Feature',
    fix: 'Fix',
    docs: 'Docs',
    refactor: 'Refactor',
    performance: 'Performance',
    security: 'Security',
    test: 'Test',
    chore: 'Chore',
    other: 'Other',
  };

  const searchTokens = ['version history', latestVersion];

  history.releases.forEach((release) => {
    const card = document.createElement('article');
    card.className = 'release-card';

    const top = document.createElement('div');
    top.className = 'release-top';

    const versionBadge = document.createElement('span');
    versionBadge.className = `release-version${release.isLatest ? ' is-latest' : ''}`;
    versionBadge.textContent = release.isLatest ? `${release.version} (Latest)` : String(release.version || 'unversioned');

    const meta = document.createElement('span');
    meta.className = 'release-meta';
    const from = release.dateRange && release.dateRange.from ? release.dateRange.from : 'n/a';
    const to = release.dateRange && release.dateRange.to ? release.dateRange.to : 'n/a';
    meta.textContent = `${release.commitCount || 0} commits | ${from} -> ${to}`;

    top.append(versionBadge, meta);

    const statWrap = document.createElement('div');
    statWrap.className = 'release-stats';
    typeOrder.forEach((type) => {
      const count = release.stats && release.stats[type] ? release.stats[type] : 0;
      if (!count) return;
      const badge = document.createElement('span');
      badge.className = `change-type change-${type}`;
      badge.textContent = `${typeLabel[type]}: ${count}`;
      statWrap.append(badge);
    });

    const details = document.createElement('details');
    details.className = 'release-details';

    const summary = document.createElement('summary');
    summary.textContent = 'Show changelog';

    const list = document.createElement('ul');
    list.className = 'release-list';

    const commits = Array.isArray(release.commits) ? release.commits : [];
    commits.forEach((commit) => {
      const item = document.createElement('li');
      item.className = 'release-item';

      const type = document.createElement('span');
      type.className = `change-type change-${commit.type || 'other'}`;
      type.textContent = typeLabel[commit.type] || 'Other';

      const text = document.createElement('span');
      text.className = 'release-item-text';
      const shortHash = commit.shortHash || (commit.hash || '').slice(0, 7);
      text.textContent = `${commit.date || ''} | ${shortHash} | ${commit.title || ''}`;

      item.append(type, text);
      list.append(item);

      searchTokens.push(commit.title || '');
      searchTokens.push(release.version || '');
    });

    details.append(summary, list);
    card.append(top, statWrap, details);
    cards.append(card);
  });

  root.dataset.searchText = searchTokens.join(' ').toLowerCase();
  root.append(h, sub, cards);
}

function buildNav() {
  const sections = [
    ['Introduction', 'intro'],
    ['First Time Setup', 'first-time-setup'],
    ['PBAC Conversion', 'convertion-pbac'],
    ['RBAC Setup', 'convertion-rbac'],
    ['General Usage', 'general-usage'],
    ['Commands', 'commands-reference'],
    ['Scenarios', 'scenarios'],
    ['Error Handling', 'error-handling'],
    ['FAQ / Tips', 'faq-tips'],
    ['Version History', 'version-history'],
  ];

  const nav = document.getElementById('sideNav');
  nav.innerHTML = '';
  sections.forEach(([name, id]) => {
    const link = document.createElement('a');
    link.href = `#${id}`;
    link.textContent = name;
    link.className = 'nav-link';
    nav.append(link);
  });

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      const id = entry.target.id;
      document.querySelectorAll('.nav-link').forEach((link) => {
        link.classList.toggle('active', link.getAttribute('href') === `#${id}`);
      });
    });
  }, { threshold: 0.3 });

  sections.forEach(([, id]) => {
    const target = document.getElementById(id);
    if (target) observer.observe(target);
  });
}

async function init() {
  const [data, versionHistory] = await Promise.all([loadData(), loadVersionHistory()]);
  const tutorials = data.tutorials || {};
  document.getElementById('cliVersion').textContent = `Version: ${data.cliVersion}`;
  renderLatestReleaseMini(document.getElementById('latest-release-mini'), versionHistory);

  renderIntro(document.getElementById('intro'), data);
  renderTutorialSection(
    document.getElementById('first-time-setup'),
    'First Time Setup',
    'Guided setup from zero to first successful browser run.',
    tutorials.firstTimeSetup
  );
  const pbacRoot = document.getElementById('convertion-pbac');
  renderTutorialSection(
    pbacRoot,
    'PBAC Conversion',
    'Permission Based Access Control conversion flow for an existing default scaffold.',
    tutorials.convertionPbac || []
  );
  const pbacGuideSearchText = renderPbacHowToGuide(pbacRoot, tutorials.convertionPbacGuide);
  if (pbacGuideSearchText) {
    pbacRoot.dataset.searchText = `${pbacRoot.dataset.searchText || ''} ${pbacGuideSearchText}`.trim().toLowerCase();
  }
  renderTutorialSection(
    document.getElementById('convertion-rbac'),
    'RBAC Setup',
    'Role Based Access Control setup flow. This is separate from PBAC and should be selected as an alternative path.',
    tutorials.convertionRbac || []
  );
  renderTutorialSection(
    document.getElementById('general-usage'),
    'General Usage',
    'Standard day-to-day command path with common pitfalls in mind.',
    tutorials.generalUsage
  );
  renderCommands(document.getElementById('commands-reference'), data.commands);
  renderScenarios(document.getElementById('scenarios'), tutorials.scenarios);
  renderErrors(document.getElementById('error-handling'), tutorials.errors);
  renderFaq(document.getElementById('faq-tips'), tutorials.faq, tutorials.commonMistakes);
  renderVersionHistory(document.getElementById('version-history'), versionHistory);

  buildNav();
  DocsSearch.bindSearch(document.getElementById('searchInput'));
}

init().catch((err) => {
  const root = document.getElementById('intro');
  root.innerHTML = `<h3 class="section-title">Failed to load docs data</h3><p>${err.message}</p>`;
});
