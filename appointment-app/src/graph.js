// Intégration Microsoft Graph (Outlook / Microsoft 365) via le flux "client credentials".
// Si les variables GRAPH_* ne sont pas renseignées, le client fonctionne en mode simulation :
// les e-mails et événements sont seulement journalisés dans la console.

const GRAPH_URL = 'https://graph.microsoft.com/v1.0';

export function createGraphClient(cfg, { fetchImpl = fetch, logger = console } = {}) {
  const configured = Boolean(cfg.tenantId && cfg.clientId && cfg.clientSecret && cfg.calendarUser);
  let token = null;
  let tokenExp = 0;

  async function getToken() {
    if (token && Date.now() < tokenExp - 60_000) return token;
    const res = await fetchImpl(`https://login.microsoftonline.com/${encodeURIComponent(cfg.tenantId)}/oauth2/v2.0/token`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        client_id: cfg.clientId,
        client_secret: cfg.clientSecret,
        scope: 'https://graph.microsoft.com/.default',
        grant_type: 'client_credentials',
      }),
    });
    if (!res.ok) throw new Error(`Authentification Microsoft échouée (${res.status}) : ${await res.text()}`);
    const data = await res.json();
    token = data.access_token;
    tokenExp = Date.now() + data.expires_in * 1000;
    return token;
  }

  async function call(method, path, body) {
    const res = await fetchImpl(`${GRAPH_URL}${path}`, {
      method,
      headers: {
        Authorization: `Bearer ${await getToken()}`,
        ...(body ? { 'Content-Type': 'application/json' } : {}),
      },
      body: body ? JSON.stringify(body) : undefined,
    });
    if (!res.ok && !(method === 'DELETE' && res.status === 404)) {
      throw new Error(`Microsoft Graph ${method} ${path} a échoué (${res.status}) : ${await res.text()}`);
    }
    return res.status === 204 || res.status === 202 || method === 'DELETE' ? null : res.json();
  }

  const user = (u) => `/users/${encodeURIComponent(u)}`;

  return {
    configured,

    async sendMail({ to, subject, html }) {
      const recipients = [].concat(to).filter(Boolean);
      if (!recipients.length) return;
      if (!configured) {
        logger.info(`[simulation e-mail] À : ${recipients.join(', ')} — ${subject}`);
        return;
      }
      await call('POST', `${user(cfg.senderUser)}/sendMail`, {
        message: {
          subject,
          body: { contentType: 'HTML', content: html },
          toRecipients: recipients.map((address) => ({ emailAddress: { address } })),
        },
        saveToSentItems: false,
      });
    },

    // Crée l'événement dans l'agenda Outlook du PDG et renvoie son identifiant.
    async createEvent({ subject, html, date, heure, duree, timeZone, location, transactionId }) {
      if (!configured) {
        logger.info(`[simulation agenda] ${date} ${heure} (${duree} min) — ${subject}`);
        return `simulation-${transactionId}`;
      }
      const event = await call('POST', `${user(cfg.calendarUser)}/calendar/events`, {
        subject,
        body: { contentType: 'HTML', content: html },
        start: { dateTime: `${date}T${heure}:00`, timeZone },
        end: { dateTime: addMinutes(date, heure, duree), timeZone },
        location: { displayName: location },
        showAs: 'busy',
        isReminderOn: true,
        reminderMinutesBeforeStart: 15,
        categories: ['Rendez-vous'],
        transactionId,
      });
      return event.id;
    },

    async deleteEvent(eventId) {
      if (!eventId) return;
      if (!configured || eventId.startsWith('simulation-')) {
        logger.info(`[simulation agenda] suppression ${eventId}`);
        return;
      }
      await call('DELETE', `${user(cfg.calendarUser)}/events/${encodeURIComponent(eventId)}`);
    },
  };
}

// Ajoute des minutes à une date/heure locale sans conversion de fuseau.
export function addMinutes(date, heure, minutes) {
  const [y, mo, d] = date.split('-').map(Number);
  const [h, mi] = heure.split(':').map(Number);
  const t = new Date(Date.UTC(y, mo - 1, d, h, mi + minutes));
  return t.toISOString().slice(0, 19);
}
