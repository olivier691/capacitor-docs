/* Fonctions partagées par les pages. */
window.api = async function api(url, options = {}) {
  const res = await fetch(url, {
    ...options,
    credentials: 'same-origin',
    headers: options.body ? { 'Content-Type': 'application/json' } : {},
    body: options.body ? JSON.stringify(options.body) : undefined,
  });
  const data = await res.json().catch(() => ({}));
  if (res.status === 401 && !url.startsWith('/api/auth/login')) {
    window.location.href = `/connexion?suite=${encodeURIComponent(window.location.pathname)}`;
  }
  if (!res.ok) {
    const err = new Error(data.erreur || 'Une erreur est survenue.');
    Object.assign(err, data, { status: res.status });
    throw err;
  }
  return data;
};

window.formatDateFr = function formatDateFr(date, opts = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) {
  const [y, m, d] = date.split('-').map(Number);
  return new Intl.DateTimeFormat('fr-FR', { ...opts, timeZone: 'UTC' }).format(new Date(Date.UTC(y, m - 1, d)));
};

window.el = function el(tag, attrs = {}, ...children) {
  const node = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) {
    if (v === false || v === null || v === undefined) continue;
    if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
    else if (k === 'className') node.className = v;
    else node.setAttribute(k, v === true ? '' : v);
  }
  for (const c of children.flat()) if (c !== null && c !== undefined && c !== false) node.append(c);
  return node;
};

window.initSessionHeader = async function initSessionHeader() {
  const me = await window.api('/api/auth/me');
  document.getElementById('utilisateur').textContent = `${me.nom} — ${me.libelleRole}`;
  document.getElementById('deconnexion').addEventListener('click', async () => {
    await window.api('/api/auth/logout', { method: 'POST', body: {} });
    window.location.href = '/connexion';
  });
  return me;
};
