import { randomBytes, randomUUID, timingSafeEqual } from 'node:crypto';
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import Database from 'better-sqlite3';
import express from 'express';
import { rateLimit } from 'express-rate-limit';
import helmet from 'helmet';
import nodemailer from 'nodemailer';
import { z } from 'zod';
import { backupDatabase } from './scripts/backup-database.js';

const projectRoot = path.dirname(fileURLToPath(import.meta.url));
const publicPages = new Set([
  'about.html',
  'academics.html',
  'admissions.html',
  'contact.html',
  'index.html',
  'news-events.html',
]);
const classOptions = ['Early years', 'Primary', 'Junior school'];
const sessionCookieName = 'elite_admin_session';
const sessionLifetimeMs = 8 * 60 * 60 * 1000;

function getCookie(request, name) {
  const prefix = `${name}=`;
  const cookie = (request.headers.cookie || '')
    .split(';')
    .map(value => value.trim())
    .find(value => value.startsWith(prefix));

  return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : '';
}

function setSessionCookie(response, token, production) {
  const secure = production ? '; Secure' : '';
  response.setHeader(
    'Set-Cookie',
    `${sessionCookieName}=${encodeURIComponent(token)}; HttpOnly; SameSite=Strict; Path=/; Max-Age=${Math.floor(sessionLifetimeMs / 1000)}${secure}`,
  );
}

function clearSessionCookie(response, production) {
  const secure = production ? '; Secure' : '';
  response.setHeader(
    'Set-Cookie',
    `${sessionCookieName}=; HttpOnly; SameSite=Strict; Path=/; Max-Age=0${secure}`,
  );
}

function matchesSecret(supplied, configured) {
  const suppliedBytes = Buffer.from(supplied);
  const configuredBytes = Buffer.from(configured);
  return suppliedBytes.length === configuredBytes.length
    && timingSafeEqual(suppliedBytes, configuredBytes);
}

function createMailTransport() {
  const { SMTP_HOST, SMTP_USER, SMTP_PASSWORD, SMTP_FROM, NOTIFICATION_EMAIL } = process.env;
  const configured = [SMTP_HOST, SMTP_USER, SMTP_PASSWORD, SMTP_FROM, NOTIFICATION_EMAIL]
    .some(Boolean);

  if (!configured) {
    return null;
  }

  if (![SMTP_HOST, SMTP_USER, SMTP_PASSWORD, SMTP_FROM, NOTIFICATION_EMAIL].every(Boolean)) {
    throw new Error('Configure SMTP_HOST, SMTP_USER, SMTP_PASSWORD, SMTP_FROM, and NOTIFICATION_EMAIL together.');
  }

  const port = Number(process.env.SMTP_PORT || 587);
  if (!Number.isInteger(port) || port < 1 || port > 65535) {
    throw new Error('SMTP_PORT must be a valid TCP port.');
  }

  return nodemailer.createTransport({
    host: SMTP_HOST,
    port,
    secure: process.env.SMTP_SECURE === 'true' || port === 465,
    auth: { user: SMTP_USER, pass: SMTP_PASSWORD },
    connectionTimeout: 10000,
    socketTimeout: 15000,
  });
}

function isSameOrigin(request) {
  const origin = request.get('origin');
  if (!origin) return false;

  try {
    const parsedOrigin = new URL(origin);
    return parsedOrigin.host === request.get('host')
      && (process.env.NODE_ENV !== 'production' || parsedOrigin.protocol === 'https:');
  } catch {
    return false;
  }
}

function isConfiguredPassword(supplied, configured) {
  return typeof configured === 'string'
    && Buffer.byteLength(configured) >= 16
    && matchesSecret(supplied, configured);
}

const inquirySchema = z.object({
  type: z.enum(['admission', 'contact']),
  name: z.string().trim().min(2).max(100),
  phone: z.string().trim().min(7).max(30).regex(/^[+()\d\s.-]+$/),
  email: z.union([z.literal(''), z.email()]).optional().default(''),
  class: z.string().optional(),
  message: z.string().trim().max(3000).optional().default(''),
}).strict().superRefine((inquiry, context) => {
  if (inquiry.type === 'admission' && !classOptions.includes(inquiry.class ?? '')) {
    context.addIssue({
      code: 'custom',
      path: ['class'],
      message: 'Choose a valid class.',
    });
  }

  if (inquiry.type === 'contact' && (inquiry.message.length < 10 || !inquiry.message.trim())) {
    context.addIssue({
      code: 'custom',
      path: ['message'],
      message: 'Message must be between 10 and 3000 characters.',
    });
  }
});

function createDatabase(databasePath) {
  if (databasePath !== ':memory:') {
    mkdirSync(path.dirname(databasePath), { recursive: true });
  }

  const database = new Database(databasePath);
  database.pragma('journal_mode = WAL');
  database.pragma('foreign_keys = ON');
  database.exec(`
    CREATE TABLE IF NOT EXISTS inquiries (
      id TEXT PRIMARY KEY,
      type TEXT NOT NULL CHECK (type IN ('admission', 'contact')),
      name TEXT NOT NULL,
      phone TEXT NOT NULL,
      email TEXT NOT NULL DEFAULT '',
      class TEXT,
      message TEXT,
      notification_status TEXT NOT NULL DEFAULT 'disabled'
        CHECK (notification_status IN ('disabled', 'sent', 'failed')),
      created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
    );
    CREATE INDEX IF NOT EXISTS inquiries_created_at_idx ON inquiries (created_at);
  `);
  const columns = database.prepare('PRAGMA table_info(inquiries)').all();
  if (!columns.some(column => column.name === 'notification_status')) {
    database.exec(`
      ALTER TABLE inquiries ADD COLUMN notification_status TEXT NOT NULL DEFAULT 'disabled'
        CHECK (notification_status IN ('disabled', 'sent', 'failed'))
    `);
  }

  return database;
}

export function createApp({
  databasePath = process.env.DATABASE_PATH || path.join(projectRoot, 'data', 'inquiries.sqlite'),
  staticRoot = projectRoot,
  rateLimitWindowMs = 15 * 60 * 1000,
  rateLimitMax = 5,
  adminUsername = process.env.ADMIN_USERNAME,
  adminPassword = process.env.ADMIN_PASSWORD,
  mailTransport = createMailTransport(),
  notificationRecipient = process.env.NOTIFICATION_EMAIL,
} = {}) {
  const database = createDatabase(databasePath);
  const app = express();
  const sessions = new Map();
  const production = process.env.NODE_ENV === 'production';
  const insertInquiry = database.prepare(`
    INSERT INTO inquiries (id, type, name, phone, email, class, message, notification_status)
    VALUES (@id, @type, @name, @phone, @email, @class, @message, @notification_status)
  `);
  const updateNotificationStatus = database.prepare(
    'UPDATE inquiries SET notification_status = ? WHERE id = ?',
  );

  app.locals.database = database;
  app.locals.mailTransport = mailTransport;
  app.disable('x-powered-by');
  app.use(helmet({
    contentSecurityPolicy: {
      directives: {
        defaultSrc: ["'self'"],
        baseUri: ["'self'"],
        fontSrc: ["'self'", 'https://fonts.gstatic.com'],
        formAction: ["'self'"],
        frameAncestors: ["'none'"],
        imgSrc: ["'self'", 'data:'],
        objectSrc: ["'none'"],
        scriptSrc: ["'self'"],
        styleSrc: ["'self'", 'https://fonts.googleapis.com'],
      },
    },
  }));
  app.use(express.json({ limit: '8kb', strict: true }));

  app.get('/api/health', (_request, response) => {
    database.prepare('SELECT 1').get();
    response.json({ status: 'ok' });
  });

  const requireAdmin = (request, response, next) => {
    const token = getCookie(request, sessionCookieName);
    const now = Date.now();
    const expiresAt = sessions.get(token);
    if (!token || !expiresAt || expiresAt <= Date.now()) {
      sessions.delete(token);
      response.status(401).json({ error: 'Please sign in to continue.' });
      return;
    }

    sessions.set(token, now + sessionLifetimeMs);
    next();
  };

  app.get('/api/admin/session', (request, response) => {
    const configured = Boolean(adminUsername && Buffer.byteLength(adminPassword || '') >= 16);
    const token = getCookie(request, sessionCookieName);
    const expiresAt = sessions.get(token);
    const now = Date.now();
    for (const [sessionToken, expiry] of sessions) {
      if (expiry <= now) sessions.delete(sessionToken);
    }

    response.json({
      configured,
      authenticated: Boolean(expiresAt && expiresAt > Date.now()),
      emailNotifications: Boolean(mailTransport && notificationRecipient),
    });
  });

  app.post('/api/admin/session', rateLimit({
    windowMs: rateLimitWindowMs,
    limit: 5,
    standardHeaders: 'draft-8',
    legacyHeaders: false,
  }), (request, response) => {
    if (!isSameOrigin(request)) {
      response.status(403).json({ error: 'Sign-in request origin was rejected.' });
      return;
    }

    if (!adminUsername || Buffer.byteLength(adminPassword || '') < 16) {
      response.status(503).json({ error: 'Staff sign-in is not configured.' });
      return;
    }

    const credentials = z.object({
      username: z.string().min(1).max(100),
      password: z.string().min(1).max(200),
    }).strict().safeParse(request.body);
    if (!credentials.success) {
      response.status(400).json({ error: 'Enter a username and password.' });
      return;
    }

    const usernameMatches = matchesSecret(credentials.data.username, adminUsername);
    const passwordMatches = isConfiguredPassword(credentials.data.password, adminPassword);
    if (!usernameMatches || !passwordMatches) {
      response.status(401).json({ error: 'The username or password is incorrect.' });
      return;
    }

    const token = randomBytes(32).toString('base64url');
    sessions.set(token, Date.now() + sessionLifetimeMs);
    setSessionCookie(response, token, production);
    response.json({
      authenticated: true,
      emailNotifications: Boolean(mailTransport && notificationRecipient),
    });
  });

  app.post('/api/admin/logout', (request, response) => {
    if (!isSameOrigin(request)) {
      response.status(403).json({ error: 'Sign-out request origin was rejected.' });
      return;
    }

    sessions.delete(getCookie(request, sessionCookieName));
    clearSessionCookie(response, production);
    response.status(204).end();
  });

  app.get('/api/admin/inquiries', rateLimit({
    windowMs: rateLimitWindowMs,
    limit: 60,
    standardHeaders: 'draft-8',
    legacyHeaders: false,
  }), requireAdmin, (request, response) => {
    const pagination = z.object({
      limit: z.coerce.number().int().min(1).max(100).default(25),
      offset: z.coerce.number().int().min(0).max(100000).default(0),
    }).safeParse(request.query);
    if (!pagination.success) {
      response.status(400).json({ error: 'Invalid pagination parameters.' });
      return;
    }

    const inquiries = database.prepare(`
      SELECT id, type, name, phone, email, class, message, notification_status, created_at
      FROM inquiries
      ORDER BY created_at DESC, id DESC
      LIMIT ? OFFSET ?
    `).all(pagination.data.limit, pagination.data.offset);
    const total = database.prepare('SELECT COUNT(*) AS count FROM inquiries').get().count;

    response.json({
      inquiries,
      total,
      limit: pagination.data.limit,
      offset: pagination.data.offset,
    });
  });

  app.post('/api/inquiries', rateLimit({
    windowMs: rateLimitWindowMs,
    limit: rateLimitMax,
    standardHeaders: 'draft-8',
    legacyHeaders: false,
    message: { error: 'Too many enquiries. Please try again later.' },
  }), async (request, response) => {
    const result = inquirySchema.safeParse(request.body);
    if (!result.success) {
      response.status(400).json({
        error: 'Please check the enquiry details and try again.',
        fields: result.error.issues.map(issue => ({
          field: issue.path.join('.'),
          message: issue.message,
        })),
      });
      return;
    }

    const inquiry = result.data;
    const id = randomUUID();
    let notificationStatus = mailTransport && notificationRecipient ? 'failed' : 'disabled';
    insertInquiry.run({
      id,
      type: inquiry.type,
      name: inquiry.name,
      phone: inquiry.phone,
      email: inquiry.email,
      class: inquiry.type === 'admission' ? inquiry.class : null,
      message: inquiry.type === 'contact' ? inquiry.message : null,
      notification_status: notificationStatus,
    });

    if (mailTransport && notificationRecipient) {
      try {
        await mailTransport.sendMail({
          from: process.env.SMTP_FROM,
          to: notificationRecipient,
          subject: `New ${inquiry.type} enquiry (${id})`,
          text: `A new ${inquiry.type} enquiry was received. Sign in to the Elite Academy staff dashboard to review it.\n\nReference: ${id}`,
        });
        notificationStatus = 'sent';
      } catch (error) {
        notificationStatus = 'failed';
        console.error('Enquiry notification delivery failed:', error.code || error.name || 'unknown error');
      }

      updateNotificationStatus.run(notificationStatus, id);
    }

    response.status(201).json({
      message: 'Your enquiry has been received.',
      reference: id,
      notification: notificationStatus,
    });
  });

  app.use('/assets', express.static(path.join(staticRoot, 'assets'), {
    fallthrough: false,
    index: false,
    maxAge: process.env.NODE_ENV === 'production' ? '1h' : 0,
  }));

  app.get('/', (_request, response) => {
    response.sendFile(path.join(staticRoot, 'index.html'));
  });

  app.get('/admin', (_request, response) => {
    response.sendFile(path.join(staticRoot, 'admin.html'));
  });

  app.get('/:page', (request, response, next) => {
    if (!publicPages.has(request.params.page)) {
      next();
      return;
    }

    response.sendFile(path.join(staticRoot, request.params.page));
  });

  app.use('/api', (_request, response) => {
    response.status(404).json({ error: 'API endpoint not found.' });
  });

  app.use((error, _request, response, _next) => {
    if (response.headersSent) {
      return;
    }

    if (error.type === 'entity.too.large') {
      response.status(413).json({ error: 'The request is too large.' });
      return;
    }

    if (error.type === 'entity.parse.failed') {
      response.status(400).json({ error: 'Request body must be valid JSON.' });
      return;
    }

    if (error.status === 404) {
      response.status(404).json({ error: 'Page or asset not found.' });
      return;
    }

    console.error('Request failed:', error);
    response.status(500).json({ error: 'An unexpected server error occurred.' });
  });

  return app;
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const port = Number(process.env.PORT || 3000);
  const host = process.env.HOST || '127.0.0.1';
  const backupIntervalHours = Number(process.env.BACKUP_INTERVAL_HOURS || 24);
  if (!Number.isInteger(backupIntervalHours) || backupIntervalHours < 1 || backupIntervalHours > 8760) {
    throw new Error('BACKUP_INTERVAL_HOURS must be an integer from 1 to 8760.');
  }

  const app = createApp();
  const server = app.listen(port, host, () => {
    console.log(`Elite Academy site and API listening at http://${host}:${port}`);
  });
  const createScheduledBackup = async () => {
    try {
      const backupPath = await backupDatabase();
      console.log(`Database backup created: ${backupPath}`);
    } catch (error) {
      console.error('Scheduled database backup failed:', error.message);
    }
  };
  const backupTimer = setInterval(createScheduledBackup, backupIntervalHours * 60 * 60 * 1000);
  backupTimer.unref();
  createScheduledBackup();

  const shutdown = () => {
    clearInterval(backupTimer);
    server.close(() => {
      app.locals.database.close();
      process.exit(0);
    });
  };

  process.on('SIGINT', shutdown);
  process.on('SIGTERM', shutdown);
}
