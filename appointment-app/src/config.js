import { readFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';

// Charge un fichier .env simple (CLE=valeur) sans dépendance externe.
function loadDotEnv(path) {
  if (!existsSync(path)) return;
  for (const line of readFileSync(path, 'utf8').split(/\r?\n/)) {
    const m = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
    if (!m || process.env[m[1]] !== undefined) continue;
    process.env[m[1]] = m[2].replace(/^(['"])(.*)\1$/, '$2');
  }
}

const list = (v) => (v || '').split(/[,;]/).map((s) => s.trim()).filter(Boolean);

export function loadConfig(overrides = {}) {
  loadDotEnv(resolve(process.cwd(), '.env'));
  const env = process.env;
  const config = {
    port: Number(env.PORT || 3000),
    production: env.NODE_ENV === 'production',
    publicUrl: (env.PUBLIC_URL || `http://localhost:${env.PORT || 3000}`).replace(/\/$/, ''),
    sessionSecret: env.SESSION_SECRET || '',
    dbPath: env.DB_PATH || resolve(process.cwd(), 'data/rendez-vous.db'),
    usersFile: env.USERS_FILE || resolve(process.cwd(), 'config/users.json'),
    timeZone: env.TIMEZONE || 'Europe/Paris',
    organisation: env.ORGANISATION_NAME || 'Direction Générale',
    location: env.MEETING_LOCATION || 'Bureau du PDG',
    // PDG + entourage habilité à connaître l'agenda : destinataires des notifications.
    notifyEmails: list(env.NOTIFY_EMAILS),
    graph: {
      tenantId: env.GRAPH_TENANT_ID || '',
      clientId: env.GRAPH_CLIENT_ID || '',
      clientSecret: env.GRAPH_CLIENT_SECRET || '',
      // Boîte dont l'agenda reçoit les rendez-vous validés (celle du PDG).
      calendarUser: env.GRAPH_CALENDAR_USER || '',
      // Boîte utilisée pour envoyer les e-mails (ex. no-reply@entreprise.com).
      senderUser: env.GRAPH_SENDER_USER || env.GRAPH_CALENDAR_USER || '',
    },
    ...overrides,
  };
  if (!config.sessionSecret) {
    if (config.production) throw new Error('SESSION_SECRET est obligatoire en production.');
    config.sessionSecret = 'dev-secret-a-remplacer';
  }
  return config;
}
