import express from 'express';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { createSessionManager, verifyPassword, ROLES } from './auth.js';
import { validateDemande, isValidDate, isValidHeure, today, DUREES } from './validation.js';
import { STATUTS } from './db.js';
import {
  mailNouvelleDemande, mailAccuseReception, mailDecision, evenementOutlook, nomComplet,
} from './emails.js';

const PUBLIC_DIR = join(dirname(fileURLToPath(import.meta.url)), '..', 'public');

export function createApp({ config, repo, users, graph, logger = console }) {
  const app = express();
  const sessions = createSessionManager({ secret: config.sessionSecret, secure: config.production });
  const findUser = (username) => users.find((u) => u.username === username);

  app.disable('x-powered-by');
  if (config.production) app.set('trust proxy', 1);

  app.use((req, res, next) => {
    res.set({
      'Content-Security-Policy': "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; form-action 'self'",
      'X-Content-Type-Options': 'nosniff',
      'X-Frame-Options': 'DENY',
      'Referrer-Policy': 'same-origin',
    });
    next();
  });
  app.use(express.json({ limit: '20kb' }));

  // Protection CSRF : les requêtes modifiantes doivent venir du même site et être en JSON.
  app.use('/api', (req, res, next) => {
    if (req.method === 'GET') return next();
    const origin = req.get('origin');
    if (origin && new URL(origin).host !== req.get('host')) {
      return res.status(403).json({ erreur: 'Origine non autorisée.' });
    }
    if (!req.is('application/json')) return res.status(415).json({ erreur: 'JSON attendu.' });
    next();
  });

  // Anti-abus minimal pour le formulaire public : 5 demandes / 15 min par adresse IP.
  const tentatives = new Map();
  function limiter(max, fenetreMs) {
    return (req, res, next) => {
      const cle = `${req.path}:${req.ip}`;
      const maintenant = Date.now();
      const liste = (tentatives.get(cle) || []).filter((t) => maintenant - t < fenetreMs);
      if (liste.length >= max) {
        return res.status(429).json({ erreur: 'Trop de tentatives, veuillez réessayer plus tard.' });
      }
      liste.push(maintenant);
      tentatives.set(cle, liste);
      next();
    };
  }

  async function bestEffort(label, fn) {
    try {
      await fn();
    } catch (err) {
      logger.error(`[${label}] ${err.message}`);
    }
  }

  // ---------- Formulaire public ----------
  app.get('/api/config', (req, res) => {
    res.json({ organisation: config.organisation, durees: DUREES, aujourdhui: today(config.timeZone) });
  });

  app.post('/api/rendez-vous', limiter(5, 15 * 60 * 1000), async (req, res) => {
    const { data, erreurs, valide } = validateDemande(req.body, config);
    if (!valide) return res.status(400).json({ erreur: 'Formulaire incomplet.', erreurs });
    const rdv = repo.create(data);
    await bestEffort('notification direction', () =>
      graph.sendMail({ to: config.notifyEmails, ...mailNouvelleDemande(rdv, config) }));
    await bestEffort('accusé de réception', () =>
      graph.sendMail({ to: rdv.email, ...mailAccuseReception(rdv, config) }));
    res.status(201).json({ reference: rdv.reference });
  });

  // ---------- Authentification ----------
  app.post('/api/auth/login', limiter(10, 15 * 60 * 1000), (req, res) => {
    const { username, password } = req.body || {};
    const user = typeof username === 'string' && findUser(username.trim().toLowerCase());
    if (!user || typeof password !== 'string' || !verifyPassword(password, user.passwordHash)) {
      return res.status(401).json({ erreur: 'Identifiant ou mot de passe incorrect.' });
    }
    sessions.open(res, user);
    res.json({ nom: user.name, role: user.role, accueil: `/${user.role}` });
  });

  app.post('/api/auth/logout', (req, res) => {
    sessions.close(res);
    res.json({ ok: true });
  });

  app.get('/api/auth/me', (req, res) => {
    const s = sessions.read(req);
    if (!s || !findUser(s.u)) return res.status(401).json({ erreur: 'Non connecté.' });
    res.json({ username: s.u, nom: s.n, role: s.r, libelleRole: ROLES[s.r] });
  });

  // ---------- Espace direction (PDG + entourage habilité) ----------
  const direction = sessions.require('direction');

  function chargerRdv(req, res) {
    const rdv = repo.get(Number(req.params.id));
    if (!rdv) res.status(404).json({ erreur: 'Rendez-vous introuvable.' });
    return rdv;
  }

  app.get('/api/direction/rendez-vous', direction, (req, res) => {
    const statut = STATUTS.includes(req.query.statut) ? req.query.statut : undefined;
    const liste = repo.list({ statut }).map((r) => ({
      ...r,
      conflits: r.statut === 'en_attente' ? repo.conflits(r).map((c) => c.reference) : [],
    }));
    res.json(liste);
  });

  app.post('/api/direction/rendez-vous/:id/valider', direction, async (req, res) => {
    const rdv = chargerRdv(req, res);
    if (!rdv) return;
    if (rdv.statut !== 'en_attente') {
      return res.status(409).json({ erreur: 'Ce rendez-vous a déjà été traité.' });
    }
    const b = req.body || {};
    const creneau = {
      date: b.date || rdv.date,
      heure: b.heure || rdv.heure,
      duree: b.duree ? Number(b.duree) : rdv.duree,
    };
    if (!isValidDate(creneau.date) || !isValidHeure(creneau.heure) || !DUREES.includes(creneau.duree)) {
      return res.status(400).json({ erreur: 'Créneau invalide.' });
    }
    const conflits = repo.conflits({ id: rdv.id, ...creneau });
    if (conflits.length && b.forcer !== true) {
      return res.status(409).json({
        erreur: `Ce créneau chevauche ${conflits.length} rendez-vous déjà validé(s).`,
        conflits: conflits.map((c) => `${c.heure} — ${nomComplet(c)}`),
        confirmationRequise: true,
      });
    }
    const aValider = { ...rdv, ...creneau };
    let outlookEventId;
    try {
      outlookEventId = await graph.createEvent({
        ...evenementOutlook(aValider),
        ...creneau,
        timeZone: config.timeZone,
        location: config.location,
        transactionId: rdv.reference,
      });
    } catch (err) {
      logger.error(`[agenda Outlook] ${err.message}`);
      return res.status(502).json({ erreur: "Impossible d'ajouter le rendez-vous à l'agenda Outlook. Réessayez." });
    }
    const maj = repo.decide(rdv.id, {
      statut: 'valide', par: req.session.n, note: textNote(b.note), ...creneau, outlookEventId,
    });
    await bestEffort('confirmation demandeur', () =>
      graph.sendMail({ to: maj.email, ...mailDecision(maj, config) }));
    res.json(maj);
  });

  app.post('/api/direction/rendez-vous/:id/refuser', direction, async (req, res) => {
    const rdv = chargerRdv(req, res);
    if (!rdv) return;
    if (rdv.statut !== 'en_attente') {
      return res.status(409).json({ erreur: 'Ce rendez-vous a déjà été traité.' });
    }
    const maj = repo.decide(rdv.id, { statut: 'refuse', par: req.session.n, note: textNote(req.body?.note) });
    if (req.body?.informer !== false) {
      await bestEffort('refus demandeur', () => graph.sendMail({ to: maj.email, ...mailDecision(maj, config) }));
    }
    res.json(maj);
  });

  app.post('/api/direction/rendez-vous/:id/annuler', direction, async (req, res) => {
    const rdv = chargerRdv(req, res);
    if (!rdv) return;
    if (rdv.statut !== 'valide') {
      return res.status(409).json({ erreur: 'Seul un rendez-vous validé peut être annulé.' });
    }
    try {
      await graph.deleteEvent(rdv.outlook_event_id);
    } catch (err) {
      logger.error(`[agenda Outlook] ${err.message}`);
      return res.status(502).json({ erreur: "Impossible de retirer le rendez-vous de l'agenda Outlook. Réessayez." });
    }
    const maj = repo.decide(rdv.id, { statut: 'annule', par: req.session.n, note: textNote(req.body?.note) });
    if (req.body?.informer !== false) {
      await bestEffort('annulation demandeur', () => graph.sendMail({ to: maj.email, ...mailDecision(maj, config) }));
    }
    res.json(maj);
  });

  // ---------- Espace sécurité : lecture seule + pointage des arrivées ----------
  const securite = sessions.require('securite', 'direction');
  const vueSecurite = (r) => ({
    id: r.id,
    reference: r.reference,
    date: r.date,
    heure: r.heure,
    duree: r.duree,
    visiteur: nomComplet(r),
    organisation: r.organisation,
    fonction: r.fonction,
    participants: r.participants,
    accompagnants: r.accompagnants,
    arrive_le: r.arrive_le,
    arrive_par: r.arrive_par,
  });

  app.get('/api/securite/rendez-vous', securite, (req, res) => {
    const date = isValidDate(req.query.date) ? req.query.date : today(config.timeZone);
    res.json({ date, aujourdhui: today(config.timeZone), rendezVous: repo.listValidesDuJour(date).map(vueSecurite) });
  });

  app.post('/api/securite/rendez-vous/:id/presence', sessions.require('securite'), (req, res) => {
    const rdv = chargerRdv(req, res);
    if (!rdv) return;
    if (rdv.statut !== 'valide') return res.status(409).json({ erreur: "Ce rendez-vous n'est pas validé." });
    if (rdv.date !== today(config.timeZone)) {
      return res.status(409).json({ erreur: 'La présence ne peut être pointée que le jour du rendez-vous.' });
    }
    const maj = repo.setPresence(rdv.id, req.body?.present === true, req.session.n);
    res.json(vueSecurite(maj));
  });

  // ---------- Pages ----------
  function page(fichier, role) {
    return (req, res) => {
      if (role) {
        const s = sessions.read(req);
        if (!s || !findUser(s.u)) return res.redirect(`/connexion?suite=${encodeURIComponent(req.path)}`);
        if (s.r !== role) return res.redirect(`/${s.r}`);
      }
      res.sendFile(join(PUBLIC_DIR, fichier));
    };
  }
  app.get('/', page('index.html'));
  app.get('/connexion', page('connexion.html'));
  app.get('/direction', page('direction.html', 'direction'));
  app.get('/securite', page('securite.html', 'securite'));
  app.use(express.static(PUBLIC_DIR, { index: false, extensions: [] }));

  app.use('/api', (req, res) => res.status(404).json({ erreur: 'Ressource introuvable.' }));
  app.use((err, req, res, next) => {
    if (err.type === 'entity.parse.failed') return res.status(400).json({ erreur: 'JSON invalide.' });
    logger.error(err);
    res.status(500).json({ erreur: 'Erreur interne.' });
  });

  return app;
}

function textNote(v) {
  return typeof v === 'string' && v.trim() ? v.trim().slice(0, 500) : null;
}
