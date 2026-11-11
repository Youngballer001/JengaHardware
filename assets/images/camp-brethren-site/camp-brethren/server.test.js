import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { createServer } from 'node:http';
import { createApp } from './server.js';

let app;
let server;
let baseUrl;

before(async () => {
  app = createApp({ databasePath: ':memory:', rateLimitMax: 20 });
  server = createServer(app);
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  baseUrl = `http://127.0.0.1:${server.address().port}`;
});

after(async () => {
  await new Promise((resolve, reject) => {
    server.close(error => error ? reject(error) : resolve());
  });
  app.locals.database.close();
});

test('serves the home page and health endpoint without exposing the database', async () => {
  const [home, health, privateFile, staffPage, staffAssets, staffSession] = await Promise.all([
    fetch(baseUrl),
    fetch(`${baseUrl}/api/health`),
    fetch(`${baseUrl}/data/inquiries.sqlite`),
    fetch(`${baseUrl}/admin`),
    fetch(`${baseUrl}/assets/admin.js`),
    fetch(`${baseUrl}/api/admin/session`),
  ]);

  assert.equal(home.status, 200);
  assert.match(await home.text(), /Elite Academy/);
  assert.deepEqual(await health.json(), { status: 'ok' });
  assert.equal(privateFile.status, 404);
  assert.equal(staffPage.status, 200);
  assert.match(await staffPage.text(), /Staff sign in/);
  assert.equal(staffAssets.status, 200);
  assert.deepEqual(await staffSession.json(), {
    configured: false,
    authenticated: false,
    emailNotifications: false,
  });
});

test('stores a valid admission enquiry and returns a reference', async () => {
  const response = await fetch(`${baseUrl}/api/inquiries`, {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({
      type: 'admission',
      name: 'Taylor Parent',
      phone: '+254 700 123 456',
      email: 'parent@example.com',
      class: 'Primary',
    }),
  });
  const body = await response.json();
  const saved = app.locals.database.prepare('SELECT * FROM inquiries WHERE id = ?').get(body.reference);

  assert.equal(response.status, 201);
  assert.match(body.reference, /^[0-9a-f-]{36}$/i);
  assert.equal(saved.name, 'Taylor Parent');
  assert.equal(saved.type, 'admission');
  assert.equal(saved.class, 'Primary');
  assert.equal(saved.message, null);
});

test('stores a valid contact enquiry', async () => {
  const response = await fetch(`${baseUrl}/api/inquiries`, {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({
      type: 'contact',
      name: 'Jordan Guardian',
      phone: '0700123456',
      message: 'I would like to arrange a school visit.',
    }),
  });
  const body = await response.json();
  const saved = app.locals.database.prepare('SELECT * FROM inquiries WHERE id = ?').get(body.reference);

  assert.equal(response.status, 201);
  assert.equal(saved.message, 'I would like to arrange a school visit.');
  assert.equal(saved.class, null);
});

test('rejects malformed and invalid enquiries without saving them', async () => {
  const invalidBodies = [
    '{invalid json',
    JSON.stringify({ type: 'admission', name: 'A', phone: '555', class: 'Other' }),
    JSON.stringify({
      type: 'contact',
      name: 'Taylor Parent',
      phone: '0700123456',
      email: 'not-an-email',
      message: 'Too short',
    }),
    JSON.stringify({
      type: 'contact',
      name: 'Taylor Parent',
      phone: '0700123456',
      message: 'A valid message but an unexpected field is included.',
      isAdmin: true,
    }),
  ];

  for (const body of invalidBodies) {
    const response = await fetch(`${baseUrl}/api/inquiries`, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body,
    });
    assert.equal(response.status, 400);
  }

  assert.equal(app.locals.database.prepare('SELECT COUNT(*) AS count FROM inquiries').get().count, 2);
});

test('does not expose any inquiry read endpoint', async () => {
  const response = await fetch(`${baseUrl}/api/inquiries`);
  assert.equal(response.status, 404);
  assert.deepEqual(await response.json(), { error: 'API endpoint not found.' });
});

test('protects the staff dashboard with same-origin password sessions', async () => {
  const staffApp = createApp({
    databasePath: ':memory:',
    rateLimitMax: 20,
    adminUsername: 'school-office',
    adminPassword: 'test-only-password-1234',
  });
  staffApp.locals.database.prepare(`
    INSERT INTO inquiries (id, type, name, phone) VALUES (?, ?, ?, ?)
  `).run('staff-test-inquiry', 'admission', 'Test Guardian', '0700123456');
  const staffServer = createServer(staffApp);
  await new Promise(resolve => staffServer.listen(0, '127.0.0.1', resolve));
  const staffUrl = `http://127.0.0.1:${staffServer.address().port}`;

  try {
    const unauthorized = await fetch(`${staffUrl}/api/admin/inquiries`);
    assert.equal(unauthorized.status, 401);

    const rejectedOrigin = await fetch(`${staffUrl}/api/admin/session`, {
      method: 'POST',
      headers: {
        origin: 'https://attacker.example',
        'content-type': 'application/json',
      },
      body: JSON.stringify({ username: 'school-office', password: 'test-only-password-1234' }),
    });
    assert.equal(rejectedOrigin.status, 403);

    const invalidLogin = await fetch(`${staffUrl}/api/admin/session`, {
      method: 'POST',
      headers: { origin: staffUrl, 'content-type': 'application/json' },
      body: JSON.stringify({ username: 'school-office', password: 'wrong-password' }),
    });
    assert.equal(invalidLogin.status, 401);

    const login = await fetch(`${staffUrl}/api/admin/session`, {
      method: 'POST',
      headers: { origin: staffUrl, 'content-type': 'application/json' },
      body: JSON.stringify({ username: 'school-office', password: 'test-only-password-1234' }),
    });
    assert.equal(login.status, 200);
    const sessionCookie = login.headers.get('set-cookie').split(';')[0];

    const response = await fetch(`${staffUrl}/api/admin/inquiries?limit=1&offset=0`, {
      headers: { cookie: sessionCookie },
    });
    const body = await response.json();
    assert.equal(response.status, 200);
    assert.equal(body.limit, 1);
    assert.equal(body.offset, 0);
    assert.equal(body.inquiries.length, 1);
    assert.equal(body.total, 1);
    assert.ok(body.inquiries[0].name);

    const invalidPagination = await fetch(`${staffUrl}/api/admin/inquiries?limit=101`, {
      headers: { cookie: sessionCookie },
    });
    assert.equal(invalidPagination.status, 400);

    const logout = await fetch(`${staffUrl}/api/admin/logout`, {
      method: 'POST',
      headers: { origin: staffUrl, cookie: sessionCookie },
    });
    assert.equal(logout.status, 204);
    const afterLogout = await fetch(`${staffUrl}/api/admin/inquiries`, {
      headers: { cookie: sessionCookie },
    });
    assert.equal(afterLogout.status, 401);
  } finally {
    await new Promise((resolve, reject) => {
      staffServer.close(error => error ? reject(error) : resolve());
    });
    staffApp.locals.database.close();
  }
});

test('sends a minimal email notification and records delivery status', async () => {
  let notification;
  const mailApp = createApp({
    databasePath: ':memory:',
    rateLimitMax: 20,
    notificationRecipient: 'office@example.com',
    mailTransport: {
      async sendMail(message) {
        notification = message;
      },
    },
  });
  const mailServer = createServer(mailApp);
  await new Promise(resolve => mailServer.listen(0, '127.0.0.1', resolve));

  try {
    const response = await fetch(`http://127.0.0.1:${mailServer.address().port}/api/inquiries`, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({
        type: 'contact',
        name: 'Email Test Parent',
        phone: '0700123456',
        email: 'parent@example.com',
        message: 'I would like to arrange a school visit.',
      }),
    });
    const body = await response.json();
    const saved = mailApp.locals.database
      .prepare('SELECT notification_status FROM inquiries WHERE id = ?')
      .get(body.reference);

    assert.equal(response.status, 201);
    assert.equal(body.notification, 'sent');
    assert.equal(saved.notification_status, 'sent');
    assert.equal(notification.to, 'office@example.com');
    assert.equal(notification.text.includes('parent@example.com'), false);
    assert.match(notification.text, new RegExp(body.reference));
  } finally {
    await new Promise((resolve, reject) => {
      mailServer.close(error => error ? reject(error) : resolve());
    });
    mailApp.locals.database.close();
  }
});

test('rate-limits repeated submissions from the same client', async () => {
  const limitedApp = createApp({ databasePath: ':memory:', rateLimitMax: 2 });
  const limitedServer = createServer(limitedApp);
  await new Promise(resolve => limitedServer.listen(0, '127.0.0.1', resolve));
  const url = `http://127.0.0.1:${limitedServer.address().port}/api/inquiries`;
  const body = JSON.stringify({
    type: 'contact',
    name: 'Taylor Parent',
    phone: '0700123456',
    message: 'I would like to arrange a school visit.',
  });

  try {
    const responses = [];
    for (let attempt = 0; attempt < 3; attempt += 1) {
      responses.push(await fetch(url, {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body,
      }));
    }

    assert.deepEqual(responses.map(response => response.status), [201, 201, 429]);
  } finally {
    await new Promise((resolve, reject) => {
      limitedServer.close(error => error ? reject(error) : resolve());
    });
    limitedApp.locals.database.close();
  }
});
