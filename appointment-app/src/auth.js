import { createHmac, scryptSync, randomBytes, timingSafeEqual } from 'node:crypto';
import { readFileSync, existsSync } from 'node:fs';

export const ROLES = {
  direction: 'Direction (PDG et entourage)',
  securite: 'Service de sécurité',
};

const SESSION_COOKIE = 'rdv_session';
const SESSION_DUREE_MS = 10 * 60 * 60 * 1000; // 10 heures

export function hashPassword(password) {
  const salt = randomBytes(16).toString('hex');
  const hash = scryptSync(password, salt, 64).toString('hex');
  return `scrypt:${salt}:${hash}`;
}

export function verifyPassword(password, stored) {
  const [algo, salt, hash] = String(stored || '').split(':');
  if (algo !== 'scrypt' || !salt || !hash) return false;
  const attendu = Buffer.from(hash, 'hex');
  const calcule = scryptSync(password, salt, attendu.length);
  return timingSafeEqual(attendu, calcule);
}

export function loadUsers(path) {
  if (!existsSync(path)) return [];
  const users = JSON.parse(readFileSync(path, 'utf8'));
  return users.filter((u) => u.username && u.passwordHash && ROLES[u.role]);
}

function sign(value, secret) {
  return createHmac('sha256', secret).update(value).digest('base64url');
}

export function createSessionManager({ secret, secure }) {
  const cookieOptions = {
    httpOnly: true,
    sameSite: 'strict',
    secure,
    path: '/',
    maxAge: SESSION_DUREE_MS,
  };

  function read(req) {
    const raw = parseCookies(req.headers.cookie)[SESSION_COOKIE];
    if (!raw) return null;
    const [payload, signature] = raw.split('.');
    if (!payload || !signature) return null;
    const attendu = Buffer.from(sign(payload, secret));
    const recu = Buffer.from(signature);
    if (attendu.length !== recu.length || !timingSafeEqual(attendu, recu)) return null;
    try {
      const session = JSON.parse(Buffer.from(payload, 'base64url').toString('utf8'));
      return session.exp > Date.now() ? session : null;
    } catch {
      return null;
    }
  }

  return {
    read,
    open(res, user) {
      const payload = Buffer.from(JSON.stringify({
        u: user.username, n: user.name, r: user.role, exp: Date.now() + SESSION_DUREE_MS,
      })).toString('base64url');
      res.cookie(SESSION_COOKIE, `${payload}.${sign(payload, secret)}`, cookieOptions);
    },
    close(res) {
      res.clearCookie(SESSION_COOKIE, { ...cookieOptions, maxAge: undefined });
    },
    // Middleware : exige une session avec l'un des rôles donnés.
    require(...roles) {
      return (req, res, next) => {
        const session = read(req);
        if (!session) return res.status(401).json({ erreur: 'Authentification requise.' });
        if (!roles.includes(session.r)) return res.status(403).json({ erreur: 'Accès refusé.' });
        req.session = session;
        next();
      };
    },
  };
}

function parseCookies(header = '') {
  const cookies = {};
  for (const part of header.split(';')) {
    const i = part.indexOf('=');
    if (i > 0) cookies[part.slice(0, i).trim()] = decodeURIComponent(part.slice(i + 1).trim());
  }
  return cookies;
}
