import { DatabaseSync } from 'node:sqlite';
import { mkdirSync } from 'node:fs';
import { dirname } from 'node:path';
import { randomBytes } from 'node:crypto';

export const STATUTS = ['en_attente', 'valide', 'refuse', 'annule'];

export function openDb(path) {
  if (path !== ':memory:') mkdirSync(dirname(path), { recursive: true });
  const db = new DatabaseSync(path);
  db.exec(`
    PRAGMA journal_mode = WAL;
    CREATE TABLE IF NOT EXISTS rendez_vous (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      reference TEXT NOT NULL UNIQUE,
      cree_le TEXT NOT NULL,
      statut TEXT NOT NULL DEFAULT 'en_attente',
      civilite TEXT,
      nom TEXT NOT NULL,
      prenom TEXT NOT NULL,
      organisation TEXT,
      fonction TEXT,
      email TEXT NOT NULL,
      telephone TEXT NOT NULL,
      objet TEXT NOT NULL,
      date TEXT NOT NULL,
      heure TEXT NOT NULL,
      duree INTEGER NOT NULL,
      participants INTEGER NOT NULL DEFAULT 1,
      accompagnants TEXT,
      message TEXT,
      decision_par TEXT,
      decision_le TEXT,
      decision_note TEXT,
      outlook_event_id TEXT,
      arrive_le TEXT,
      arrive_par TEXT
    );
    CREATE INDEX IF NOT EXISTS idx_rdv_date ON rendez_vous (date, heure);
    CREATE INDEX IF NOT EXISTS idx_rdv_statut ON rendez_vous (statut);
  `);
  return db;
}

function nouvelleReference() {
  const d = new Date().toISOString().slice(0, 10).replace(/-/g, '');
  return `RDV-${d}-${randomBytes(3).toString('hex').toUpperCase()}`;
}

export function createRepository(db) {
  const now = () => new Date().toISOString();
  return {
    create(data) {
      const reference = nouvelleReference();
      const stmt = db.prepare(`
        INSERT INTO rendez_vous (reference, cree_le, civilite, nom, prenom, organisation, fonction,
          email, telephone, objet, date, heure, duree, participants, accompagnants, message)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`);
      const { lastInsertRowid } = stmt.run(
        reference, now(), data.civilite, data.nom, data.prenom, data.organisation, data.fonction,
        data.email, data.telephone, data.objet, data.date, data.heure, data.duree,
        data.participants, data.accompagnants, data.message,
      );
      return this.get(Number(lastInsertRowid));
    },
    get(id) {
      return db.prepare('SELECT * FROM rendez_vous WHERE id = ?').get(id);
    },
    list({ statut } = {}) {
      if (statut) {
        return db.prepare('SELECT * FROM rendez_vous WHERE statut = ? ORDER BY date, heure').all(statut);
      }
      return db.prepare('SELECT * FROM rendez_vous ORDER BY date DESC, heure DESC').all();
    },
    listValidesDuJour(date) {
      return db.prepare(`SELECT * FROM rendez_vous WHERE statut = 'valide' AND date = ? ORDER BY heure`).all(date);
    },
    // Rendez-vous validés qui chevauchent le créneau demandé (hors rendez-vous courant).
    conflits({ id, date, heure, duree }) {
      const debut = toMinutes(heure);
      const fin = debut + duree;
      return db.prepare(`SELECT * FROM rendez_vous WHERE statut = 'valide' AND date = ? AND id != ?`)
        .all(date, id ?? -1)
        .filter((r) => toMinutes(r.heure) < fin && toMinutes(r.heure) + r.duree > debut);
    },
    decide(id, { statut, par, note, date, heure, duree, outlookEventId }) {
      db.prepare(`
        UPDATE rendez_vous SET statut = ?, decision_par = ?, decision_le = ?, decision_note = ?,
          date = COALESCE(?, date), heure = COALESCE(?, heure), duree = COALESCE(?, duree),
          outlook_event_id = ?
        WHERE id = ?`).run(statut, par, now(), note ?? null, date ?? null, heure ?? null,
        duree ?? null, outlookEventId ?? null, id);
      return this.get(id);
    },
    setPresence(id, present, par) {
      db.prepare('UPDATE rendez_vous SET arrive_le = ?, arrive_par = ? WHERE id = ?')
        .run(present ? now() : null, present ? par : null, id);
      return this.get(id);
    },
  };
}

export function toMinutes(heure) {
  const [h, m] = heure.split(':').map(Number);
  return h * 60 + m;
}
