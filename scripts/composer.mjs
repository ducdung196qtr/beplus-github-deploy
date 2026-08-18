#!/usr/bin/env node
/**
 * Composer wrapper — runs Composer through a local PHP binary (no global
 * Composer required). Mirrors the Beplus plugin standard.
 */
import { execSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { platform } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, '..');

const findPhp = () => {
	const candidates =
		platform() === 'win32'
			? [
					process.env.PHP_BIN,
					'C:\\tools\\php\\php.exe',
					'C:\\xampp\\php\\php.exe',
					'C:\\laragon\\bin\\php\\php-8.3\\php.exe',
					'C:\\wamp64\\bin\\php\\php-8.2.0\\php.exe',
				].filter(Boolean)
			: [process.env.PHP_BIN, 'php', '/usr/bin/php', '/usr/local/bin/php'].filter(Boolean);

	for (const c of candidates) {
		try {
			execSync(`"${c}" --version`, { stdio: 'ignore' });
			return c;
		} catch {
			// try next
		}
	}
	return null;
};

const main = () => {
	const args = process.argv.slice(2);
	if (args.length === 0) {
		console.error('Usage: node scripts/composer.mjs <install|update|phpstan|php-cs-fixer|php-cs-fixer:check>');
		process.exit(1);
	}

	const php = findPhp();
	if (!php) {
		console.error('composer.mjs: no PHP binary found. Set PHP_BIN or add php to PATH.');
		process.exit(1);
	}

	const composer = join(root, 'composer.phar');
	const cmd = args.join(' ');
	const full = `cd "${root}" && "${php}" "${composer}" ${cmd}`;

	try {
		execSync(full, { stdio: 'inherit' });
	} catch (e) {
		process.exit(e.status || 1);
	}
};

main();
