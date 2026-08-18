#!/usr/bin/env node
/**
 * Ensure a local Composer (composer.phar) exists, then install dependencies
 * if vendor/ is missing. Mirrors the Beplus plugin standard.
 */
import { execSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, '..');
const composer = join(root, 'composer.phar');

if (!existsSync(composer)) {
	console.log('ensure-composer: downloading composer.phar …');
	try {
		execSync(
			`curl -sS https://getcomposer.org/installer | php -- --install-dir="${root}" --filename=composer.phar`,
			{ stdio: 'inherit' },
		);
	} catch (e) {
		console.error('ensure-composer: could not download Composer:', e.message);
		process.exit(1);
	}
}

if (!existsSync(join(root, 'vendor'))) {
	console.log('ensure-composer: installing dependencies …');
	try {
		execSync(`node "${join(__dirname, 'composer.mjs')}" install`, { stdio: 'inherit' });
	} catch (e) {
		process.exit(e.status || 1);
	}
}

console.log('ensure-composer: OK');
