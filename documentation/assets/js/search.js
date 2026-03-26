const DocsSearch = (() => {
  function score(text, term) {
    if (!term) return 1;
    const index = text.indexOf(term);
    if (index === -1) return 0;
    return 1 / (index + 1);
  }

  function bindSearch(input) {
    const sections = Array.from(document.querySelectorAll('main .panel'));
    const cards = Array.from(document.querySelectorAll('.searchable-command'));

    input.addEventListener('input', () => {
      const term = input.value.trim().toLowerCase();

      cards.forEach((card) => {
        const txt = (card.dataset.searchText || '').toLowerCase();
        const visible = !term || txt.includes(term);
        card.classList.toggle('hidden-by-search', !visible);
      });

      sections.forEach((section) => {
        const base = (section.dataset.searchText || '').toLowerCase();
        const sectionVisible = !term || base.includes(term);

        const commandCards = section.querySelectorAll('.searchable-command');
        const hasVisibleCards = Array.from(commandCards).some((c) => !c.classList.contains('hidden-by-search'));

        const visible = sectionVisible || hasVisibleCards;
        section.classList.toggle('hidden-by-search', !visible);
      });

      const sorted = cards
        .map((card) => ({ card, s: score((card.dataset.searchText || '').toLowerCase(), term) }))
        .filter((x) => x.s > 0)
        .sort((a, b) => b.s - a.s)
        .map((x) => x.card);

      const grid = document.querySelector('#commands-reference .cards');
      if (grid && term && sorted.length > 0) {
        sorted.forEach((node) => grid.appendChild(node));
      }
    });
  }

  return { bindSearch };
})();
