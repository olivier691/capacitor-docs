import { loadConfig } from './config.js';
import { openDb, createRepository } from './db.js';
import { loadUsers } from './auth.js';
import { createGraphClient } from './graph.js';
import { createApp } from './app.js';

const config = loadConfig();
const repo = createRepository(openDb(config.dbPath));
const users = loadUsers(config.usersFile);
const graph = createGraphClient(config.graph);

if (!users.length) {
  console.warn(`Aucun utilisateur dans ${config.usersFile}. Créez-en avec : npm run add-user`);
}
if (!graph.configured) {
  console.warn('Microsoft Graph non configuré : e-mails et agenda Outlook en mode simulation (console).');
}
if (!config.notifyEmails.length) {
  console.warn('NOTIFY_EMAILS est vide : personne ne sera notifié des nouvelles demandes.');
}

createApp({ config, repo, users, graph }).listen(config.port, () => {
  console.log(`Application de rendez-vous disponible sur ${config.publicUrl}`);
});
