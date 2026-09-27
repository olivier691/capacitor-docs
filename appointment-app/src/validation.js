export const DUREES = [15, 30, 45, 60, 90, 120];
export const CIVILITES = ['M.', 'Mme', 'Dr', 'Pr', 'Me'];

const EMAIL_RE = /^[^\s@<>]+@[^\s@<>]+\.[^\s@<>]{2,}$/;
const TEL_RE = /^\+?[0-9 ().-]{8,20}$/;
const DATE_RE = /^\d{4}-\d{2}-\d{2}$/;
const HEURE_RE = /^([01]\d|2[0-3]):[0-5]\d$/;

const texte = (v, max) => (typeof v === 'string' ? v.trim().slice(0, max) : '');

export function isValidDate(value) {
  if (!DATE_RE.test(value)) return false;
  const d = new Date(`${value}T00:00:00Z`);
  return !Number.isNaN(d.getTime()) && d.toISOString().slice(0, 10) === value;
}

export function isValidHeure(value) {
  return HEURE_RE.test(value);
}

// Date du jour (AAAA-MM-JJ) dans le fuseau de l'organisation.
export function today(timeZone) {
  return new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit' })
    .format(new Date());
}

// Valide et normalise une demande de rendez-vous issue du formulaire public.
export function validateDemande(body, { timeZone }) {
  const b = body && typeof body === 'object' ? body : {};
  const data = {
    civilite: CIVILITES.includes(b.civilite) ? b.civilite : null,
    nom: texte(b.nom, 80),
    prenom: texte(b.prenom, 80),
    organisation: texte(b.organisation, 120) || null,
    fonction: texte(b.fonction, 120) || null,
    email: texte(b.email, 160).toLowerCase(),
    telephone: texte(b.telephone, 20),
    objet: texte(b.objet, 200),
    date: texte(b.date, 10),
    heure: texte(b.heure, 5),
    duree: Number(b.duree),
    participants: Number(b.participants ?? 1),
    accompagnants: texte(b.accompagnants, 500) || null,
    message: texte(b.message, 2000) || null,
  };
  const erreurs = {};
  if (!data.nom) erreurs.nom = 'Le nom est obligatoire.';
  if (!data.prenom) erreurs.prenom = 'Le prénom est obligatoire.';
  if (!EMAIL_RE.test(data.email)) erreurs.email = 'Adresse e-mail invalide.';
  if (!TEL_RE.test(data.telephone)) erreurs.telephone = 'Numéro de téléphone invalide.';
  if (!data.objet) erreurs.objet = "L'objet du rendez-vous est obligatoire.";
  if (!isValidDate(data.date)) erreurs.date = 'Date invalide.';
  else if (data.date < today(timeZone)) erreurs.date = 'La date doit être aujourd’hui ou ultérieure.';
  if (!isValidHeure(data.heure)) erreurs.heure = 'Heure invalide.';
  if (!DUREES.includes(data.duree)) erreurs.duree = 'Durée invalide.';
  if (!Number.isInteger(data.participants) || data.participants < 1 || data.participants > 20) {
    erreurs.participants = 'Le nombre de participants doit être compris entre 1 et 20.';
  }
  if (data.participants > 1 && !data.accompagnants) {
    erreurs.accompagnants = 'Indiquez le nom des personnes qui vous accompagnent.';
  }
  if (b.consentement !== true) {
    erreurs.consentement = 'Vous devez accepter le traitement de vos données.';
  }
  return { data, erreurs, valide: Object.keys(erreurs).length === 0 };
}
