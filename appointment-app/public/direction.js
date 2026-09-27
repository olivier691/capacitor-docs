(async () => {
  await initSessionHeader();

  const LIBELLES = { en_attente: 'En attente', valide: 'Validé', refuse: 'Refusé', annule: 'Annulé' };
  const liste = document.getElementById('liste');
  const message = document.getElementById('message');
  const onglets = document.getElementById('onglets');
  let statut = 'en_attente';

  function info(texte, type = 'success') {
    message.textContent = texte;
    message.className = `alert ${type}`;
    message.hidden = false;
    setTimeout(() => { message.hidden = true; }, 6000);
  }

  const ligne = (dt, dd) => (dd ? [el('dt', {}, dt), el('dd', {}, String(dd))] : []);
  const dateHeure = (iso) => new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });

  function carte(r) {
    const nom = [r.civilite, r.prenom, r.nom].filter(Boolean).join(' ');
    const actions = el('div', { className: 'actions' });
    if (r.statut === 'en_attente') {
      actions.append(
        el('button', { type: 'button', className: 'success', onclick: () => ouvrir('valider', r) }, 'Valider'),
        el('button', { type: 'button', className: 'danger', onclick: () => ouvrir('refuser', r) }, 'Refuser'),
      );
    } else if (r.statut === 'valide') {
      actions.append(el('button', { type: 'button', className: 'secondary', onclick: () => ouvrir('annuler', r) }, 'Annuler le rendez-vous'));
    }
    return el('article', { className: 'card' },
      el('div', { className: 'rdv-head' },
        el('h3', {}, `${formatDateFr(r.date)} · ${r.heure} (${r.duree} min)`),
        el('span', { className: `badge ${r.statut}` }, LIBELLES[r.statut])),
      r.conflits?.length ? el('div', { className: 'alert warning', 'data-mt': true }, `Chevauche un rendez-vous déjà validé : ${r.conflits.join(', ')}`) : null,
      el('dl', { className: 'details' },
        ligne('Demandeur', nom),
        ligne('Organisation', r.organisation),
        ligne('Fonction', r.fonction),
        ligne('Objet', r.objet),
        ligne('E-mail', r.email),
        ligne('Téléphone', r.telephone),
        ligne('Personnes', r.participants),
        ligne('Accompagnants', r.accompagnants),
        ligne('Message', r.message),
        ligne('Référence', r.reference),
        ligne('Reçu le', dateHeure(r.cree_le)),
        ligne('Décision', r.decision_par && `${r.decision_par}, le ${dateHeure(r.decision_le)}`),
        ligne('Note', r.decision_note),
        r.statut === 'valide' ? ligne('Arrivée', r.arrive_le ? `Présent — pointé à ${dateHeure(r.arrive_le)} par ${r.arrive_par}` : 'Non pointée') : []),
      actions);
  }

  async function charger() {
    liste.replaceChildren(el('p', { className: 'empty' }, 'Chargement…'));
    try {
      const rdvs = await api(`/api/direction/rendez-vous?statut=${statut}`);
      liste.replaceChildren(...(rdvs.length ? rdvs.map(carte) : [el('p', { className: 'empty' }, 'Aucun rendez-vous.')]));
    } catch (err) {
      liste.replaceChildren(el('div', { className: 'alert error' }, err.message));
    }
  }

  onglets.addEventListener('click', (e) => {
    const bouton = e.target.closest('button[data-statut]');
    if (!bouton) return;
    statut = bouton.dataset.statut;
    for (const b of onglets.querySelectorAll('button')) b.setAttribute('aria-selected', String(b === bouton));
    charger();
  });

  // ---------- Dialogue de décision ----------
  const dialogue = document.getElementById('dialogue');
  const form = document.getElementById('dialogue-form');
  const TEXTES = {
    valider: {
      titre: 'Valider le rendez-vous',
      texte: 'Le rendez-vous sera ajouté à l’agenda Outlook du PDG et le demandeur recevra une confirmation. Vous pouvez ajuster le créneau.',
      note: 'Note pour le demandeur (facultatif)',
      bouton: 'Valider',
    },
    refuser: { titre: 'Refuser la demande', texte: '', note: 'Motif (facultatif)', bouton: 'Refuser' },
    annuler: {
      titre: 'Annuler le rendez-vous',
      texte: 'Le rendez-vous sera retiré de l’agenda Outlook du PDG.',
      note: 'Motif (facultatif)',
      bouton: 'Annuler le rendez-vous',
    },
  };
  let courant = null;

  function ouvrir(action, r) {
    courant = { action, r };
    const t = TEXTES[action];
    document.getElementById('dialogue-titre').textContent = t.titre;
    document.getElementById('dialogue-texte').textContent = `${[r.prenom, r.nom].join(' ')} — ${t.texte}`;
    document.getElementById('d-note-label').textContent = t.note;
    document.getElementById('d-confirmer').textContent = t.bouton;
    document.getElementById('d-confirmer').className = action === 'valider' ? 'success' : 'danger';
    document.getElementById('dialogue-creneau').hidden = action !== 'valider';
    document.getElementById('d-informer').parentElement.hidden = action === 'valider';
    form.date.value = r.date;
    form.heure.value = r.heure;
    form.duree.value = String(r.duree);
    form.note.value = '';
    document.getElementById('d-informer').checked = true;
    dialogue.showModal();
  }

  document.getElementById('d-annuler').addEventListener('click', () => dialogue.close());

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const { action, r } = courant;
    const body = { note: form.note.value, informer: document.getElementById('d-informer').checked };
    if (action === 'valider') Object.assign(body, { date: form.date.value, heure: form.heure.value, duree: Number(form.duree.value) });
    const bouton = document.getElementById('d-confirmer');
    bouton.disabled = true;
    try {
      try {
        await api(`/api/direction/rendez-vous/${r.id}/${action}`, { method: 'POST', body });
      } catch (err) {
        if (!err.confirmationRequise) throw err;
        const ok = window.confirm(`${err.message}\n${err.conflits.join('\n')}\n\nValider quand même ?`);
        if (!ok) return;
        await api(`/api/direction/rendez-vous/${r.id}/${action}`, { method: 'POST', body: { ...body, forcer: true } });
      }
      dialogue.close();
      info({ valider: 'Rendez-vous validé et ajouté à l’agenda Outlook.', refuser: 'Demande refusée.', annuler: 'Rendez-vous annulé.' }[action]);
      charger();
    } catch (err) {
      dialogue.close();
      info(err.message, 'error');
    } finally {
      bouton.disabled = false;
    }
  });

  charger();
  setInterval(() => { if (!dialogue.open) charger(); }, 60_000);
})();
