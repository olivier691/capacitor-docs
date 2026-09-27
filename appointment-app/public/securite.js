(async () => {
  await initSessionHeader();

  const champDate = document.getElementById('date');
  const liste = document.getElementById('liste');
  const message = document.getElementById('message');
  let aujourdhui = null;

  const heure = (iso) => new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });

  function decaler(jours) {
    const [y, m, d] = champDate.value.split('-').map(Number);
    champDate.value = new Date(Date.UTC(y, m - 1, d + jours)).toISOString().slice(0, 10);
    charger();
  }

  function ligne(r, modifiable) {
    const coche = el('input', { type: 'checkbox', checked: Boolean(r.arrive_le), disabled: !modifiable, 'aria-label': `Présence de ${r.visiteur}` });
    const item = el('div', { className: `visit${r.arrive_le ? ' present' : ''}` },
      el('div', { className: 'time' }, r.heure),
      el('div', { className: 'who' },
        el('strong', {}, r.visiteur),
        el('span', {}, [r.fonction, r.organisation].filter(Boolean).join(' · ') || '—'),
        el('span', {}, `${r.participants} personne(s) · ${r.duree} min · ${r.reference}`),
        r.accompagnants ? el('span', {}, `Accompagnants : ${r.accompagnants}`) : null,
        r.arrive_le ? el('span', {}, `Arrivé à ${heure(r.arrive_le)} (pointé par ${r.arrive_par})`) : null),
      el('label', { className: 'presence' }, coche, 'Venu'));
    coche.addEventListener('change', async () => {
      coche.disabled = true;
      try {
        const maj = await api(`/api/securite/rendez-vous/${r.id}/presence`, { method: 'POST', body: { present: coche.checked } });
        item.replaceWith(ligne(maj, true));
        compter();
      } catch (err) {
        coche.checked = !coche.checked;
        coche.disabled = false;
        message.textContent = err.message;
        message.className = 'alert error';
        message.hidden = false;
      }
    });
    return item;
  }

  function compter() {
    const total = liste.querySelectorAll('.visit').length;
    const venus = liste.querySelectorAll('.visit.present').length;
    document.getElementById('stats').replaceChildren(
      el('span', { className: 'badge' }, `${total} rendez-vous`),
      el('span', { className: 'badge valide' }, `${venus} venu(s)`),
      el('span', { className: 'badge en_attente' }, `${total - venus} attendu(s)`),
    );
  }

  async function charger() {
    message.hidden = true;
    try {
      const res = await api(`/api/securite/rendez-vous?date=${champDate.value || ''}`);
      aujourdhui = res.aujourdhui;
      champDate.value = res.date;
      document.getElementById('titre').textContent = formatDateFr(res.date);
      const modifiable = res.date === res.aujourdhui;
      liste.replaceChildren(...(res.rendezVous.length
        ? res.rendezVous.map((r) => ligne(r, modifiable))
        : [el('p', { className: 'empty' }, 'Aucun rendez-vous validé pour cette date.')]));
      compter();
    } catch (err) {
      liste.replaceChildren(el('div', { className: 'alert error' }, err.message));
    }
  }

  champDate.addEventListener('change', charger);
  document.getElementById('precedent').addEventListener('click', () => decaler(-1));
  document.getElementById('suivant').addEventListener('click', () => decaler(1));
  document.getElementById('aujourdhui').addEventListener('click', () => { champDate.value = aujourdhui; charger(); });

  await charger();
  setInterval(() => { if (!document.hidden) charger(); }, 60_000);
})();
