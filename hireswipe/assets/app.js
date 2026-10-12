// Show / hide password
document.querySelectorAll('[data-toggle-pass]').forEach(btn => btn.addEventListener('click', () => {
  const input = btn.parentElement.querySelector('input');
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  btn.textContent = show ? 'Hide' : 'Show';
}));

// Inline validation: message appears next to the field, not in a browser bubble
const msgFor = el => {
  const v = el.validity;
  if (v.customError) return el.validationMessage;
  if (v.valueMissing) return el.type === 'checkbox' ? 'Please accept to continue.' : 'This field is required.';
  if (v.typeMismatch) return el.type === 'email' ? 'Enter a valid email address.' : 'Enter a valid value.';
  if (v.tooShort) return `Use at least ${el.minLength} characters.`;
  if (v.rangeUnderflow || v.rangeOverflow) return 'This value is out of range.';
  return 'Please check this field.';
};
function flag(el) {
  const host = el.closest('.pw,.row') || (el.type === 'checkbox' ? el.closest('label') : el);
  let p = host.parentNode.querySelector(`.err.js[data-for="${el.name}"]`);
  if (el.checkValidity()) { el.classList.remove('invalid'); if (p) p.remove(); return; }
  el.classList.add('invalid');
  if (!p) { p = document.createElement('p'); p.className = 'err js'; p.dataset.for = el.name; host.after(p); }
  p.textContent = msgFor(el);
}
document.querySelectorAll('form').forEach(form => {
  const fields = [...form.querySelectorAll('input:not([type=hidden]):not([type=file]),select,textarea')].filter(f => f.name);
  fields.forEach(f => {
    f.addEventListener('blur', () => { if (f.value || f.classList.contains('invalid')) flag(f); });
    f.addEventListener('input', () => {
      f.closest('label')?.querySelectorAll('.err:not(.js)').forEach(x => x.remove());
      if (f.classList.contains('invalid')) flag(f);
    });
  });
  form.addEventListener('submit', e => {
    if (!form.checkValidity()) {
      e.preventDefault();
      fields.forEach(flag);
      form.querySelector('.invalid')?.focus();
      return;
    }
    const btn = form.querySelector('button.btn[type=submit]');
    if (btn) { btn.disabled = true; btn.textContent = 'Please wait…'; }
  });
});

// Password strength meter + rule checklist + confirm match
const pw = document.getElementById('password'), cf = document.getElementById('confirm_password');
if (pw) {
  const rules = { len: v => v.length >= 8, case: v => /[a-z]/.test(v) && /[A-Z]/.test(v), num: v => /\d/.test(v), sym: v => /[^a-zA-Z0-9]/.test(v) };
  const list = document.getElementById('pw-rules');
  const meter = document.createElement('div'); meter.className = 'meter'; meter.innerHTML = '<i></i>';
  const label = document.createElement('span'); label.className = 'meter-label';
  list.before(meter, label);
  const levels = [['', 0, 'var(--bad)'], ['Weak', 25, 'var(--bad)'], ['Fair', 50, 'var(--amber)'], ['Good', 75, 'var(--blue)'], ['Strong', 100, 'var(--ok)']];
  const update = () => {
    let score = 0;
    list.querySelectorAll('li').forEach(li => { const ok = rules[li.dataset.rule](pw.value); li.classList.toggle('ok', ok); if (ok) score++; });
    const [txt, w, c] = pw.value ? levels[score] : levels[0];
    meter.firstChild.style.width = w + '%'; meter.firstChild.style.background = c;
    label.textContent = txt ? `Password strength: ${txt}` : '';
    if (cf) { cf.setCustomValidity(cf.value && cf.value !== pw.value ? 'Passwords do not match.' : ''); if (cf.value) flag(cf); }
  };
  pw.addEventListener('input', update);
  cf?.addEventListener('input', update);
  update();
}

// Upload drop zones with preview and instant size / type feedback
document.querySelectorAll('input[type=file]').forEach(inp => {
  const isCv = inp.name === 'cv', max = (isCv ? 5 : 2) * 1048576;
  const idle = isCv ? 'Drop your CV here, or click to browse' : 'Drop an image here, or click to browse';
  const box = document.createElement('div'); box.className = 'drop';
  const img = document.createElement('img'); img.alt = ''; img.hidden = true;
  const msg = document.createElement('span'); msg.className = 'drop-msg'; msg.textContent = idle;
  inp.parentNode.insertBefore(box, inp); box.append(img, msg, inp);
  inp.addEventListener('change', () => {
    const f = inp.files[0];
    box.classList.remove('has', 'bad'); img.hidden = true;
    if (!f) { msg.textContent = idle; return; }
    if (f.size > max) { inp.value = ''; box.classList.add('bad'); msg.textContent = `That file is over ${max / 1048576} MB. Choose a smaller one.`; return; }
    msg.textContent = `${f.name} · ${(f.size / 1024).toFixed(0)} KB`;
    box.classList.add('has');
    if (f.type.startsWith('image/')) { img.src = URL.createObjectURL(f); img.hidden = false; }
  });
  ['dragenter', 'dragover'].forEach(ev => box.addEventListener(ev, () => box.classList.add('over')));
  ['dragleave', 'drop'].forEach(ev => box.addEventListener(ev, () => box.classList.remove('over')));
});

// Industry chips: max 5, with a live hint
const chips = [...document.querySelectorAll('input[name="industries[]"]')];
chips.forEach(c => c.addEventListener('change', () => {
  if (chips.filter(x => x.checked).length > 5) c.checked = false;
}));

// Character counter for long text
document.querySelectorAll('textarea[maxlength]').forEach(t => {
  const n = document.createElement('span'); n.className = 'count'; t.after(n);
  const upd = () => n.textContent = `${t.value.length} / ${t.maxLength}`;
  t.addEventListener('input', upd); upd();
});

// Demo login helper
document.getElementById('fill-demo')?.addEventListener('click', () => {
  const f = document.querySelector('form');
  f.email.value = 'demo@hireswipe.com'; f.password.value = 'Demo@1234';
  f.email.dispatchEvent(new Event('input')); f.password.dispatchEvent(new Event('input'));
  f.querySelector('button.btn').focus();
});
