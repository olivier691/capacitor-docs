(async () => {
  const form = document.getElementById('form');
  const globale = document.getElementById('erreur-globale');
  const bouton = document.getElementById('envoyer');
  const participants = document.getElementById('participants');
  const blocAccompagnants = document.getElementById('bloc-accompagnants');

  const config = await api('/api/config');
  document.getElementById('date').min = config.aujourdhui;
  const duree = document.getElementById('duree');
  for (const d of config.durees) {
    const libelle = d < 60 ? `${d} min` : `${Math.floor(d / 60)} h${d % 60 ? ` ${d % 60}` : ''}`;
    duree.append(el('option', { value: d, selected: d === 30 }, libelle));
  }

  const majAccompagnants = () => { blocAccompagnants.hidden = Number(participants.value) <= 1; };
  participants.addEventListener('input', majAccompagnants);

  function afficherErreurs(erreurs = {}) {
    for (const zone of form.querySelectorAll('.field-error')) {
      const champ = zone.dataset.for;
      zone.textContent = erreurs[champ] || '';
      form.elements[champ]?.setAttribute('aria-invalid', erreurs[champ] ? 'true' : 'false');
    }
    const premier = Object.keys(erreurs)[0];
    if (premier) form.elements[premier]?.focus();
  }

  function verifierLocalement(data) {
    const e = {};
    for (const champ of ['prenom', 'nom', 'objet', 'date', 'heure']) {
      if (!data[champ]) e[champ] = 'Ce champ est obligatoire.';
    }
    if (!form.elements.email.checkValidity() || !data.email) e.email = 'Adresse e-mail invalide.';
    if (!/^\+?[0-9 ().-]{8,20}$/.test(data.telephone)) e.telephone = 'Numéro de téléphone invalide.';
    if (data.date && data.date < config.aujourdhui) e.date = 'La date doit être aujourd’hui ou ultérieure.';
    if (data.participants > 1 && !data.accompagnants) e.accompagnants = 'Indiquez le nom des personnes qui vous accompagnent.';
    if (!data.consentement) e.consentement = 'Vous devez accepter le traitement de vos données.';
    return e;
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    globale.hidden = true;
    const fd = new FormData(form);
    const data = Object.fromEntries([...fd.entries()].map(([k, v]) => [k, typeof v === 'string' ? v.trim() : v]));
    data.participants = Number(data.participants);
    data.duree = Number(data.duree);
    data.consentement = form.elements.consentement.checked;
    if (data.participants <= 1) data.accompagnants = '';

    const erreurs = verifierLocalement(data);
    afficherErreurs(erreurs);
    if (Object.keys(erreurs).length) return;

    bouton.disabled = true;
    bouton.textContent = 'Envoi en cours…';
    try {
      const res = await api('/api/rendez-vous', { method: 'POST', body: data });
      document.getElementById('reference').textContent = res.reference;
      form.hidden = true;
      document.getElementById('succes').hidden = false;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (err) {
      afficherErreurs(err.erreurs);
      globale.textContent = err.message;
      globale.hidden = false;
    } finally {
      bouton.disabled = false;
      bouton.textContent = 'Envoyer la demande';
    }
  });

  document.getElementById('nouvelle').addEventListener('click', () => {
    form.reset();
    majAccompagnants();
    afficherErreurs();
    form.hidden = false;
    document.getElementById('succes').hidden = true;
  });
})();
