import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { openDb, createRepository } from '../src/db.js';
import { hashPassword } from '../src/auth.js';
import { createApp } from '../src/app.js';
import { addMinutes } from '../src/graph.js';
import { today } from '../src/validation.js';

const config = {
  production: false,
  publicUrl: 'http://localhost',
  sessionSecret: 'secret-de-test',
  timeZone: 'Europe/Paris',
  organisation: 'Test SA',
  location: 'Bureau du PDG',
  notifyEmails: ['pdg@test.fr', 'assistante@test.fr'],
};

const graph = {
  mails: [],
  events: [],
  deleted: [],
  failNextEvent: false,
  async sendMail(m) { this.mails.push(m); },
  async createEvent(e) {
    if (this.failNextEvent) { this.failNextEvent = false; throw new Error('Graph indisponible'); }
    this.events.push(e);
    return `evt-${this.events.length}`;
  },
  async deleteEvent(id) { this.deleted.push(id); },
};

const users = [
  { username: 'assistante', name: 'Assistante PDG', role: 'direction', passwordHash: hashPassword('motdepasse-direction') },
  { username: 'garde', name: 'Agent Sécurité', role: 'securite', passwordHash: hashPassword('motdepasse-securite') },
];

let server;
let base;
const silent = { info() {}, error() {} };

before(async () => {
  const repo = createRepository(openDb(':memory:'));
  const app = createApp({ config, repo, users, graph, logger: silent });
  await new Promise((resolve) => { server = app.listen(0, resolve); });
  base = `http://127.0.0.1:${server.address().port}`;
});
after(() => server.close());

async function req(path, { method = 'GET', body, cookie } = {}) {
  const res = await fetch(base + path, {
    method,
    redirect: 'manual',
    headers: {
      ...(body ? { 'Content-Type': 'application/json' } : {}),
      ...(cookie ? { Cookie: cookie } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  const text = await res.text();
  let data;
  try { data = JSON.parse(text); } catch { data = text; }
  return { status: res.status, data, headers: res.headers };
}

async function login(username, password) {
  const res = await req('/api/auth/login', { method: 'POST', body: { username, password } });
  assert.equal(res.status, 200);
  return res.headers.get('set-cookie').split(';')[0];
}

const demande = (extra = {}) => ({
  civilite: 'M.', prenom: 'Jean', nom: 'Kouassi', organisation: 'ACME', fonction: 'Directeur',
  email: 'jean@acme.com', telephone: '+225 07 00 00 00 00', objet: 'Partenariat',
  date: today(config.timeZone), heure: '10:00', duree: 30, participants: 1, consentement: true,
  ...extra,
});

test('le formulaire refuse une demande incomplète', async () => {
  const res = await req('/api/rendez-vous', { method: 'POST', body: { nom: 'X' } });
  assert.equal(res.status, 400);
  assert.ok(res.data.erreurs.prenom);
  assert.ok(res.data.erreurs.email);
  assert.ok(res.data.erreurs.consentement);
});

test('le formulaire refuse une date passée et des accompagnants manquants', async () => {
  const res = await req('/api/rendez-vous', { method: 'POST', body: demande({ date: '2000-01-01', participants: 3 }) });
  assert.equal(res.status, 400);
  assert.ok(res.data.erreurs.date);
  assert.ok(res.data.erreurs.accompagnants);
});

let rdvId;

test('une demande valide notifie le PDG, son entourage et le demandeur', async () => {
  graph.mails.length = 0;
  const res = await req('/api/rendez-vous', { method: 'POST', body: demande() });
  assert.equal(res.status, 201);
  assert.match(res.data.reference, /^RDV-\d{8}-[0-9A-F]{6}$/);
  assert.deepEqual(graph.mails[0].to, config.notifyEmails);
  assert.match(graph.mails[0].subject, /Nouvelle demande/);
  assert.equal(graph.mails[1].to, 'jean@acme.com');
});

test('les espaces protégés exigent une connexion et le bon rôle', async () => {
  assert.equal((await req('/api/direction/rendez-vous')).status, 401);
  assert.equal((await req('/api/securite/rendez-vous')).status, 401);
  const bad = await req('/api/auth/login', { method: 'POST', body: { username: 'garde', password: 'faux' } });
  assert.equal(bad.status, 401);
  const garde = await login('garde', 'motdepasse-securite');
  assert.equal((await req('/api/direction/rendez-vous', { cookie: garde })).status, 403);
  const page = await req('/direction', { cookie: garde });
  assert.equal(page.status, 302);
  assert.equal(page.headers.get('location'), '/securite');
  assert.equal((await req('/securite')).headers.get('location'), '/connexion?suite=%2Fsecurite');
});

test('un cookie de session falsifié est rejeté', async () => {
  const garde = await login('garde', 'motdepasse-securite');
  const [nom, valeur] = garde.split('=');
  const [payload, signature] = valeur.split('.');
  const session = JSON.parse(Buffer.from(payload, 'base64url').toString());
  const faux = Buffer.from(JSON.stringify({ ...session, r: 'direction' })).toString('base64url');
  const res = await req('/api/direction/rendez-vous', { cookie: `${nom}=${faux}.${signature}` });
  assert.equal(res.status, 401);
});

test('la direction voit la demande et la valide : événement Outlook créé', async () => {
  const cookie = await login('assistante', 'motdepasse-direction');
  const liste = await req('/api/direction/rendez-vous?statut=en_attente', { cookie });
  assert.equal(liste.data.length, 1);
  rdvId = liste.data[0].id;

  graph.failNextEvent = true;
  const echec = await req(`/api/direction/rendez-vous/${rdvId}/valider`, { method: 'POST', cookie, body: {} });
  assert.equal(echec.status, 502);
  assert.equal((await req('/api/direction/rendez-vous?statut=en_attente', { cookie })).data.length, 1);

  graph.mails.length = 0;
  const res = await req(`/api/direction/rendez-vous/${rdvId}/valider`, { method: 'POST', cookie, body: { heure: '11:00' } });
  assert.equal(res.status, 200);
  assert.equal(res.data.statut, 'valide');
  assert.equal(res.data.heure, '11:00');
  assert.equal(res.data.outlook_event_id, 'evt-1');
  assert.equal(graph.events[0].heure, '11:00');
  assert.equal(graph.events[0].timeZone, 'Europe/Paris');
  assert.match(graph.events[0].subject, /Jean Kouassi/);
  assert.match(graph.mails[0].subject, /confirmé/);

  const again = await req(`/api/direction/rendez-vous/${rdvId}/valider`, { method: 'POST', cookie, body: {} });
  assert.equal(again.status, 409);
});

test('un chevauchement demande une confirmation explicite', async () => {
  await req('/api/rendez-vous', { method: 'POST', body: demande({ heure: '11:15', email: 'b@b.com' }) });
  const cookie = await login('assistante', 'motdepasse-direction');
  const [second] = (await req('/api/direction/rendez-vous?statut=en_attente', { cookie })).data;
  assert.equal(second.conflits.length, 1);
  const res = await req(`/api/direction/rendez-vous/${second.id}/valider`, { method: 'POST', cookie, body: {} });
  assert.equal(res.status, 409);
  assert.equal(res.data.confirmationRequise, true);
  const refus = await req(`/api/direction/rendez-vous/${second.id}/refuser`, { method: 'POST', cookie, body: { note: 'Agenda complet' } });
  assert.equal(refus.data.statut, 'refuse');
});

test('la sécurité consulte en lecture seule et pointe la présence', async () => {
  const cookie = await login('garde', 'motdepasse-securite');
  const res = await req('/api/securite/rendez-vous', { cookie });
  assert.equal(res.status, 200);
  assert.equal(res.data.rendezVous.length, 1, 'seuls les rendez-vous validés sont visibles');
  const vue = res.data.rendezVous[0];
  assert.equal(vue.visiteur, 'M. Jean Kouassi');
  assert.equal(vue.email, undefined, 'les coordonnées ne sont pas exposées');
  assert.equal(vue.objet, undefined, "l'objet reste confidentiel");

  const refuse = await req(`/api/direction/rendez-vous/${vue.id}/annuler`, { method: 'POST', cookie, body: {} });
  assert.equal(refuse.status, 403, 'la sécurité ne peut rien modifier');

  const ok = await req(`/api/securite/rendez-vous/${vue.id}/presence`, { method: 'POST', cookie, body: { present: true } });
  assert.equal(ok.status, 200);
  assert.ok(ok.data.arrive_le);
  assert.equal(ok.data.arrive_par, 'Agent Sécurité');

  const direction = await login('assistante', 'motdepasse-direction');
  const pointage = await req(`/api/securite/rendez-vous/${vue.id}/presence`, { method: 'POST', cookie: direction, body: { present: false } });
  assert.equal(pointage.status, 403, 'seule la sécurité pointe les arrivées');
});

test("l'annulation retire l'événement de l'agenda Outlook", async () => {
  const cookie = await login('assistante', 'motdepasse-direction');
  const res = await req(`/api/direction/rendez-vous/${rdvId}/annuler`, { method: 'POST', cookie, body: {} });
  assert.equal(res.data.statut, 'annule');
  assert.deepEqual(graph.deleted, ['evt-1']);
});

test('les requêtes cross-origin et non JSON sont bloquées', async () => {
  const res = await fetch(`${base}/api/rendez-vous`, {
    method: 'POST', headers: { Origin: 'https://evil.example', 'Content-Type': 'application/json' }, body: '{}',
  });
  assert.equal(res.status, 403);
  const form = await fetch(`${base}/api/rendez-vous`, {
    method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'a=b',
  });
  assert.equal(form.status, 415);
});

test('addMinutes calcule la fin sans conversion de fuseau', () => {
  assert.equal(addMinutes('2026-10-01', '23:30', 45), '2026-10-02T00:15:00');
  assert.equal(addMinutes('2026-10-01', '10:00', 30), '2026-10-01T10:30:00');
});
