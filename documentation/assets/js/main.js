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
  p.textContent = 'ml is a workflow CLI for project scaffolding, database setup, navigation, and update automation. This site is generated from ml.bat and refreshes via the parser pipeline.';

  const cards = document.createElement('div');
  cards.className = 'cards';

  const highlights = [
    `CLI version tracked: ${data.cliVersion}`,
    `Total parsed commands: ${data.commands.length}`,
    'Self-updating command reference through commands.json',
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
  root.innerHTML = '';
  root.dataset.searchText = [title, subtitle, steps.map((s) => `${s.command} ${s.explanation} ${s.expectedResult}`).join(' ')].join(' ').toLowerCase();

  const h = document.createElement('h3');
  h.className = 'section-title';
  h.textContent = title;
  const p = document.createElement('p');
  p.className = 'section-sub';
  p.textContent = subtitle;

  const cards = document.createElement('div');
  cards.className = 'cards';

  steps.forEach((step) => {
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
  root.innerHTML = '';
  root.dataset.searchText = scenarios.map((s) => `${s.title} ${s.steps.join(' ')}`).join(' ').toLowerCase();

  const h = document.createElement('h3');
  h.className = 'section-title';
  h.textContent = 'Scenario-Based Tutorials';
  const p = document.createElement('p');
  p.className = 'section-sub';
  p.textContent = 'Apply commands in realistic flows for onboarding, troubleshooting, and day-to-day development.';

  const cards = document.createElement('div');
  cards.className = 'cards';

  scenarios.forEach((scenario) => {
    const card = document.createElement('article');
    card.className = 'scenario-card';
    const title = document.createElement('h4');
    title.textContent = scenario.title;
    const list = document.createElement('ol');
    list.className = 'list';
    scenario.steps.forEach((step) => {
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
  root.innerHTML = '';
  root.dataset.searchText = errors.map((e) => `${e.error} ${e.meaning} ${e.fix}`).join(' ').toLowerCase();

  const h = document.createElement('h3');
  h.className = 'section-title';
  h.textContent = 'Error Handling';

  const cards = document.createElement('div');
  cards.className = 'cards';

  errors.forEach((item) => {
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
  root.innerHTML = '';
  root.dataset.searchText = [...faq.map((f) => `${f.q} ${f.a}`), ...tips].join(' ').toLowerCase();

  const h = document.createElement('h3');
  h.className = 'section-title';
  h.textContent = 'FAQ / Tips';

  const cards = document.createElement('div');
  cards.className = 'cards';

  faq.forEach((item) => {
    const card = document.createElement('article');
    card.className = 'faq-card';
    const q = document.createElement('h4');
    q.textContent = item.q;
    const a = document.createElement('p');
    a.textContent = item.a;
    card.append(q, a);
    cards.append(card);
  });

  if (tips && tips.length) {
    const tipsCard = document.createElement('article');
    tipsCard.className = 'faq-card';
    const title = document.createElement('h4');
    title.textContent = 'Best Practices';
    const list = document.createElement('ul');
    list.className = 'list';
    tips.forEach((tip) => {
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
  document.getElementById('cliVersion').textContent = `Version: ${data.cliVersion}`;

  renderIntro(document.getElementById('intro'), data);
  renderTutorialSection(
    document.getElementById('first-time-setup'),
    'First Time Setup',
    'Guided setup from zero to first successful browser run.',
    data.tutorials.firstTimeSetup
  );
  renderTutorialSection(
    document.getElementById('general-usage'),
    'General Usage',
    'Standard day-to-day command path with common pitfalls in mind.',
    data.tutorials.generalUsage
  );
  renderCommands(document.getElementById('commands-reference'), data.commands);
  renderScenarios(document.getElementById('scenarios'), data.tutorials.scenarios);
  renderErrors(document.getElementById('error-handling'), data.tutorials.errors);
  renderFaq(document.getElementById('faq-tips'), data.tutorials.faq, data.tutorials.commonMistakes);

  buildNav();
  DocsSearch.bindSearch(document.getElementById('searchInput'));
}

init().catch((err) => {
  const root = document.getElementById('intro');
  root.innerHTML = `<h3 class="section-title">Failed to load docs data</h3><p>${err.message}</p>`;
});
