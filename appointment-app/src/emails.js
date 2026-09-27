export function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  })[c]);
}

export function formatDate(date) {
  const [y, m, d] = date.split('-').map(Number);
  return new Intl.DateTimeFormat('fr-FR', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC',
  }).format(new Date(Date.UTC(y, m - 1, d)));
}

export function nomComplet(r) {
  return [r.civilite, r.prenom, r.nom].filter(Boolean).join(' ');
}

function tableau(r) {
  const lignes = [
    ['Référence', r.reference],
    ['Demandeur', nomComplet(r)],
    ['Organisation', r.organisation],
    ['Fonction', r.fonction],
    ['E-mail', r.email],
    ['Téléphone', r.telephone],
    ['Objet', r.objet],
    ['Date', formatDate(r.date)],
    ['Heure', `${r.heure} (${r.duree} min)`],
    ['Participants', r.participants],
    ['Accompagnants', r.accompagnants],
    ['Message', r.message],
  ].filter(([, v]) => v !== null && v !== undefined && v !== '');
  return `<table cellpadding="6" style="border-collapse:collapse;font-family:Segoe UI,Arial,sans-serif;font-size:14px">${
    lignes.map(([k, v]) => `<tr><td style="color:#555;vertical-align:top"><strong>${escapeHtml(k)}</strong></td><td>${escapeHtml(v).replace(/\n/g, '<br>')}</td></tr>`).join('')
  }</table>`;
}

const wrap = (contenu) => `<div style="font-family:Segoe UI,Arial,sans-serif;font-size:14px;color:#1b1f24">${contenu}</div>`;

export function mailNouvelleDemande(r, { publicUrl }) {
  return {
    subject: `Nouvelle demande de rendez-vous — ${nomComplet(r)} — ${r.date} ${r.heure}`,
    html: wrap(`<p>Une nouvelle demande de rendez-vous avec le PDG a été déposée.</p>${tableau(r)}
      <p><a href="${escapeHtml(publicUrl)}/direction">Ouvrir l’espace direction pour valider ou refuser</a></p>`),
  };
}

export function mailAccuseReception(r, { organisation }) {
  return {
    subject: `Demande de rendez-vous reçue — ${r.reference}`,
    html: wrap(`<p>Bonjour ${escapeHtml(nomComplet(r))},</p>
      <p>Votre demande de rendez-vous a bien été reçue par ${escapeHtml(organisation)}. Elle sera étudiée
      et vous recevrez une confirmation par e-mail.</p>${tableau(r)}`),
  };
}

export function mailDecision(r, { organisation, location }) {
  if (r.statut === 'valide') {
    return {
      subject: `Rendez-vous confirmé — ${formatDate(r.date)} à ${r.heure}`,
      html: wrap(`<p>Bonjour ${escapeHtml(nomComplet(r))},</p>
        <p>Votre rendez-vous est <strong>confirmé</strong> le <strong>${escapeHtml(formatDate(r.date))}
        à ${escapeHtml(r.heure)}</strong> (${r.duree} min), lieu : ${escapeHtml(location)}.</p>
        <p>Merci de vous présenter à l’accueil muni d’une pièce d’identité et de votre référence
        <strong>${escapeHtml(r.reference)}</strong>.</p>
        ${r.decision_note ? `<p>Note : ${escapeHtml(r.decision_note)}</p>` : ''}
        <p>${escapeHtml(organisation)}</p>`),
    };
  }
  const verbe = r.statut === 'annule' ? 'annulé' : 'ne peut pas être accordé';
  return {
    subject: `Votre demande de rendez-vous ${r.reference}`,
    html: wrap(`<p>Bonjour ${escapeHtml(nomComplet(r))},</p>
      <p>Nous sommes au regret de vous informer que votre rendez-vous du ${escapeHtml(formatDate(r.date))}
      à ${escapeHtml(r.heure)} ${verbe}.</p>
      ${r.decision_note ? `<p>Motif : ${escapeHtml(r.decision_note)}</p>` : ''}
      <p>${escapeHtml(organisation)}</p>`),
  };
}

export function evenementOutlook(r) {
  return {
    subject: `RDV : ${nomComplet(r)}${r.organisation ? ` (${r.organisation})` : ''} — ${r.objet}`,
    html: tableau(r),
  };
}
