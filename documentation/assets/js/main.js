/* global DocsSearch */
async function loadData() {
  const response = await fetch('assets/data/commands.json', { cache: 'no-store' });
  if (!response.ok) {
    throw new Error(`Failed to load docs data: ${response.status}`);
  }
  return response.json();
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
  if (!guide || !Array.isArray(guide.steps) || guide.steps.length === 0) {
    return '';
  }

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

  const intro = document.createElement('p');
  intro.textContent = guide.intro || 'This guide explains PBAC usage after conversion.';

  const stepsTitle = document.createElement('p');
  stepsTitle.className = 'guide-subtitle';
  stepsTitle.textContent = 'How To Work With PBAC';

  const steps = document.createElement('ol');
  steps.className = 'guide-list';
  guide.steps.forEach((item) => {
    const li = document.createElement('li');
    li.textContent = item;
    steps.append(li);
  });

  details.append(summary, intro, stepsTitle, steps);

  if (Array.isArray(guide.commands) && guide.commands.length > 0) {
    const commandsTitle = document.createElement('p');
    commandsTitle.className = 'guide-subtitle';
    commandsTitle.textContent = 'Generate Or Refresh Access Map';

    const commands = document.createElement('ul');
    commands.className = 'guide-list';
    guide.commands.forEach((item) => {
      const li = document.createElement('li');
      li.textContent = item;
      commands.append(li);
    });

    details.append(commandsTitle, commands);
  }

  if (Array.isArray(guide.notes) && guide.notes.length > 0) {
    const notesTitle = document.createElement('p');
    notesTitle.className = 'guide-subtitle';
    notesTitle.textContent = 'Notes';

    const notes = document.createElement('ul');
    notes.className = 'guide-list';
    guide.notes.forEach((item) => {
      const li = document.createElement('li');
      li.textContent = item;
      notes.append(li);
    });

    details.append(notesTitle, notes);
  }

  btn.addEventListener('click', () => {
    details.open = true;
    details.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  actions.append(btn);
  root.append(actions, details);

  return [
    guide.buttonLabel,
    guide.title,
    guide.intro,
    (guide.steps || []).join(' '),
    (guide.commands || []).join(' '),
    (guide.notes || []).join(' '),
  ].join(' ');
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
  const data = await loadData();
  const tutorials = data.tutorials || {};
  document.getElementById('cliVersion').textContent = `Version: ${data.cliVersion}`;

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

  buildNav();
  DocsSearch.bindSearch(document.getElementById('searchInput'));
}

init().catch((err) => {
  const root = document.getElementById('intro');
  root.innerHTML = `<h3 class="section-title">Failed to load docs data</h3><p>${err.message}</p>`;
});
