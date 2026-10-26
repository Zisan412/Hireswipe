(() => {
  const S = window.HS;
  const $ = (s, r = document) => r.querySelector(s);
  const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; };
  let cards = S.cards.slice();
  let busy = false;

  /* ---------- tabs ---------- */
  function showTab(name) {
    document.querySelectorAll('.tab').forEach(t => t.classList.toggle('active', t.id === 'tab-' + name));
    document.querySelectorAll('.topnav [data-tab]').forEach(b => b.classList.toggle('on', b.dataset.tab === name));
    if (name === 'search') { renderResults(); $('#q').focus(); }
    window.scrollTo(0, 0);
  }
  document.querySelectorAll('[data-tab]').forEach(b => b.addEventListener('click', () => showTab(b.dataset.tab)));

  /* ---------- notifications ---------- */
  const drawer = $('#notif'), bell = $('#bell');
  const toggleDrawer = open => {
    drawer.classList.toggle('open', open); bell.setAttribute('aria-expanded', open);
    if (open) { $('#badge')?.remove(); drawer.querySelectorAll('.unread').forEach(n => n.classList.remove('unread')); }
  };
  bell.addEventListener('click', e => { e.stopPropagation(); toggleDrawer(!drawer.classList.contains('open')); });
  document.addEventListener('click', e => { if (!drawer.contains(e.target)) toggleDrawer(false); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') toggleDrawer(false); });

  /* ---------- toast ---------- */
  let tt;
  function toast(msg) { const t = $('#toast'); t.textContent = msg; t.classList.add('show'); clearTimeout(tt); tt = setTimeout(() => t.classList.remove('show'), 1600); }

  /* ---------- card ---------- */
  function cardEl(c) {
    const card = el('article', 'card');
    const media = el('div', 'media' + (c.round ? ' round' : ''));
    if (c.photo) { const i = el('img'); i.src = c.photo; i.alt = c.title; i.draggable = false; media.append(i); }
    else { media.textContent = c.tile; media.style.background = c.color; }
    const who = el('div', 'who');
    if (c.kicker) who.append(el('small', 'kicker', c.kicker));
    who.append(el('h3', '', c.title));
    const facts = el('dl', 'facts');
    c.facts.forEach(([k, v]) => { const r = el('div'); r.append(el('dt', '', k), el('dd', '', v)); facts.append(r); });
    card.append(el('span', 'stamp yes', S.labels.accept), el('span', 'stamp no', S.labels.reject), media, who, facts);
    if (c.details && c.details.length) {
      const more = el('div', 'more');
      c.details.forEach(([k, v]) => { const r = el('div', 'drow'); r.append(el('b', '', k), el('p', '', v)); more.append(r); });
      card.append(el('p', 'hint', 'Tap card for details'), more);
      card.dataset.expand = '1';
    }
    return card;
  }

  function attachDrag(card) {
    let sx = 0, dx = 0, down = false, moved = false;
    card.addEventListener('pointerdown', e => { down = true; moved = false; sx = e.clientX; dx = 0; card.setPointerCapture(e.pointerId); card.style.transition = 'none'; });
    card.addEventListener('pointermove', e => {
      if (!down) return;
      dx = e.clientX - sx; if (Math.abs(dx) > 6) moved = true;
      card.style.transform = `translateX(${dx}px) rotate(${dx / 18}deg)`;
      card.dataset.dir = dx > 40 ? 'accept' : dx < -40 ? 'reject' : '';
    });
    const end = () => {
      if (!down) return; down = false; card.style.transition = '';
      if (Math.abs(dx) > 110) { swipe(dx > 0 ? 'accept' : 'reject'); return; }
      card.style.transform = ''; card.dataset.dir = '';
      if (!moved && card.dataset.expand) card.classList.toggle('open');
    };
    card.addEventListener('pointerup', end);
    card.addEventListener('pointercancel', end);
  }

  function render() {
    const deck = $('#deck'); deck.replaceChildren();
    $('#actions').hidden = cards.length === 0;
    if (!cards.length) {
      const e = el('div', 'empty'); e.append(el('b', '', 'No data found'), el('p', '', 'You have gone through every card. Check back later, or reset the demo from the Profile tab.'));
      deck.append(e); return;
    }
    if (cards[1]) { const n = cardEl(cards[1]); n.classList.add('next'); deck.append(n); }
    const top = cardEl(cards[0]); top.classList.add('front'); deck.append(top); attachDrag(top);
  }

  function swipe(dir) {
    const card = $('#deck .card.front');
    if (!card || busy) return;
    busy = true;
    const c = cards[0];
    card.dataset.dir = dir; card.style.transition = '';
    card.style.transform = `translateX(${dir === 'accept' ? 130 : -130}%) rotate(${dir === 'accept' ? 18 : -18}deg)`;
    card.style.opacity = '0';
    const body = new FormData();
    body.append('action', 'swipe'); body.append('id', c.id); body.append('dir', dir); body.append('csrf', S.csrf);
    fetch('home.php', { method: 'POST', body, credentials: 'same-origin' }).catch(() => {});
    toast(`${dir === 'accept' ? S.labels.accept : S.labels.reject}: ${c.title}`);
    setTimeout(() => { cards.shift(); busy = false; render(); }, 260);
  }
  $('#btn-accept').addEventListener('click', () => swipe('accept'));
  $('#btn-reject').addEventListener('click', () => swipe('reject'));
  document.addEventListener('keydown', e => {
    if (!$('#tab-home').classList.contains('active') || e.target.matches('input,textarea')) return;
    if (e.key === 'ArrowRight') swipe('accept'); else if (e.key === 'ArrowLeft') swipe('reject');
  });

  /* ---------- search + filters: text AND location AND industry ---------- */
  const allCards = S.cards.slice();
  const fillSelect = (sel, key) => sel.options.length > 1 ? 0 : [...new Set(allCards.map(c => c[key]).filter(Boolean))].sort()
    .forEach(v => { const o = el('option', '', v); o.value = v; sel.append(o); });
  fillSelect($('#f-loc'), 'loc'); fillSelect($('#f-ind'), 'ind');

  function renderResults() {
    const q = $('#q').value.trim().toLowerCase(), loc = $('#f-loc').value, ind = $('#f-ind').value;
    const box = $('#results'); box.replaceChildren();
    const hits = cards.filter(c =>
      (!loc || c.loc === loc) &&                       // location filter (if chosen)
      (!ind || c.ind === ind) &&                       // industry filter (if chosen)
      (!q || [c.title, c.kicker, c.loc, c.ind, ...c.facts.map(f => f[1])].join(' ').toLowerCase().includes(q)));
    $('#count').textContent = hits.length + (hits.length === 1 ? ' result' : ' results');
    if (!hits.length) { box.append(el('div', 'empty', 'No data found')); return; }
    hits.forEach(c => {
      const row = el('button', 'item'); row.type = 'button';
      const tile = el('div', 'tile sm', c.tile); tile.style.background = c.color;
      const txt = el('div');
      txt.append(el('b', '', c.title), el('small', '', [c.kicker, c.loc, c.ind].filter(Boolean).join(' · ')),
                 el('small', '', c.facts.filter(f => f[0] !== 'Industry').map(f => f[1]).join(' · ')));
      row.append(tile, txt);
      row.addEventListener('click', () => { cards = [c, ...cards.filter(x => x.id !== c.id)]; render(); showTab('home'); });
      box.append(row);
    });
  }
  $('#q').addEventListener('input', renderResults);
  $('#f-loc').addEventListener('change', renderResults);
  $('#f-ind').addEventListener('change', renderResults);
  $('#f-clear').addEventListener('click', () => { $('#q').value = ''; $('#f-loc').value = ''; $('#f-ind').value = ''; renderResults(); });

  render();
})();