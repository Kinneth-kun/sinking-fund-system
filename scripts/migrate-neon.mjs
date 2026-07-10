import { readFile } from 'node:fs/promises';
import postgres from 'postgres';

const envText = await readFile(new URL('../.env.local', import.meta.url), 'utf8');
const values = {};
for (const line of envText.split(/\r?\n/)) {
  const match = line.match(/^([A-Z0-9_]+)=(.*)$/);
  if (!match) continue;
  let value = match[2].trim();
  if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
    value = value.slice(1, -1);
  }
  values[match[1]] = value.replace(/\\n/g, '\n');
}

const databaseUrl = values.DATABASE_URL_UNPOOLED || values.DATABASE_URL;
if (!databaseUrl) throw new Error('DATABASE_URL was not found in .env.local');

const schema = await readFile(new URL('../database/schema.postgresql.sql', import.meta.url), 'utf8');
const sql = postgres(databaseUrl, { ssl: 'require', max: 1 });
try {
  await sql.unsafe(schema);
  const tables = await sql`
    SELECT table_name
    FROM information_schema.tables
    WHERE table_schema = 'public'
    ORDER BY table_name
  `;
  console.log(`Schema imported. Tables: ${tables.map((row) => row.table_name).join(', ')}`);
} finally {
  await sql.end();
}
