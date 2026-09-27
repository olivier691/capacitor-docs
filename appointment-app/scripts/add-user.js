// Ajoute ou met à jour un compte : npm run add-user -- <identifiant> <direction|securite> "<Nom affiché>"
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { createInterface } from 'node:readline/promises';
import { hashPassword, ROLES } from '../src/auth.js';

const [username, role, ...nom] = process.argv.slice(2);
if (!username || !ROLES[role]) {
  console.error('Usage : npm run add-user -- <identifiant> <direction|securite> "<Nom affiché>"');
  process.exit(1);
}
const file = process.env.USERS_FILE || resolve(process.cwd(), 'config/users.json');
let password = process.env.USER_PASSWORD;
if (!password) {
  const rl = createInterface({ input: process.stdin, output: process.stdout });
  password = await rl.question('Mot de passe (12 caractères minimum) : ');
  rl.close();
}
if (password.length < 12) {
  console.error('Mot de passe trop court (12 caractères minimum).');
  process.exit(1);
}
const users = existsSync(file) ? JSON.parse(readFileSync(file, 'utf8')) : [];
const id = username.trim().toLowerCase();
const entry = { username: id, name: nom.join(' ') || id, role, passwordHash: hashPassword(password) };
const i = users.findIndex((u) => u.username === id);
if (i >= 0) users[i] = entry; else users.push(entry);
mkdirSync(dirname(file), { recursive: true });
writeFileSync(file, `${JSON.stringify(users, null, 2)}\n`, { mode: 0o600 });
console.log(`Compte « ${id} » (${ROLES[role]}) enregistré dans ${file}.`);
