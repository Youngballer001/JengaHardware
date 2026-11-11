import assert from 'node:assert/strict';
import {
  existsSync,
  mkdtempSync,
  readFileSync,
  rmSync,
  statSync,
  utimesSync,
  writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { test } from 'node:test';
import Database from 'better-sqlite3';
import { backupDatabase } from './scripts/backup-database.js';

test('creates a consistent SQLite backup and prunes only expired backups', async () => {
  const directory = mkdtempSync(path.join(tmpdir(), 'elite-academy-backup-test-'));
  const databasePath = path.join(directory, 'source.sqlite');
  const backupDirectory = path.join(directory, 'backups');
  const now = new Date('2026-10-22T12:00:00.000Z');
  const database = new Database(databasePath);
  database.exec('CREATE TABLE inquiries (id TEXT PRIMARY KEY, name TEXT NOT NULL)');
  database.prepare('INSERT INTO inquiries (id, name) VALUES (?, ?)').run('test-id', 'Test Parent');
  database.close();

  try {
    const expiredBackup = path.join(backupDirectory, 'elite-academy-2026-09-01T00-00-00-000Z.sqlite');
    const unrelatedFile = path.join(backupDirectory, 'keep-me.txt');
    const firstBackup = await backupDatabase({ databasePath, backupDirectory, now });
    writeFileSync(unrelatedFile, 'preserve');
    const futureBackup = await backupDatabase({
      databasePath,
      backupDirectory,
      now: new Date(now.getTime() + 60_000),
    });
    const oldTime = new Date(now.getTime() - 31 * 24 * 60 * 60 * 1000);
    const oldDatabase = new Database(expiredBackup);
    oldDatabase.close();
    utimesSync(expiredBackup, oldTime, oldTime);

    await backupDatabase({ databasePath, backupDirectory, now });

    const backup = new Database(firstBackup, { readonly: true });
    try {
      assert.equal(backup.prepare('SELECT name FROM inquiries WHERE id = ?').get('test-id').name, 'Test Parent');
    } finally {
      backup.close();
    }
    assert.ok(statSync(futureBackup).isFile());
    assert.ok(!existsSync(expiredBackup));
    assert.equal(readFileSync(unrelatedFile, 'utf8'), 'preserve');
  } finally {
    rmSync(directory, { recursive: true, force: true });
  }
});

test('rejects unsafe backup settings', async () => {
  await assert.rejects(
    backupDatabase({ databasePath: ':memory:' }),
    /in-memory database/,
  );
  await assert.rejects(
    backupDatabase({ databasePath: 'unavailable.sqlite', retentionDays: 0 }),
    /BACKUP_RETENTION_DAYS/,
  );
});
