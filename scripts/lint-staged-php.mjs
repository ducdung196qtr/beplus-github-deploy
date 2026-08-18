#!/usr/bin/env node
/**
 * lint-staged helper for PHP — runs php-cs-fixer (auto-fix) on staged files,
 * then phpstan on the whole project. Mirrors the Beplus plugin standard.
 */
import { execSync } from 'node:child_process';
import { existsSync } from 'node:fs';

// PHP binary resolution: PHP_BIN env > local php > docker exec wp-app php.
const resolvePhp = () => {
	const candidates = [
		process.env.PHP_BIN,
		'php',
		'docker exec wp-app php',
	].filter(Boolean);
	for (const c of candidates) {
		try {
			execSync(`${c} --version`, { stdio: 'ignore' });
			return c;
		} catch {
			// try next
		}
	}
	return null;
};

const php = resolvePhp();
if (!php) {
	console.error('lint-staged-php: no PHP binary found (set PHP_BIN or run inside the wp-app container).');
	process.exit(1);
}

const files = process.argv.slice(2);

if (files.length === 0) {
	process.exit(0);
}

const run = (cmd) => {
	try {
		execSync(cmd, { stdio: 'inherit' });
	} catch {
		process.exit(1);
	}
};

// 1. Auto-fix staged PHP with php-cs-fixer (dry-run off = it fixes in place).
if (existsSync('vendor/bin/php-cs-fixer')) {
	run(
		`${php} vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --allow-unsupported-php-version=yes ${files
			.map((f) => JSON.stringify(f))
			.join(' ')}`,
	);
}

// 2. Re-add the (possibly rewritten) files so the fixes are staged.
try {
	execSync(`git add ${files.map((f) => JSON.stringify(f)).join(' ')}`, { stdio: 'ignore' });
} catch {
	// Non-fatal.
}

// 3. Full-project PHPStan gate.
if (existsSync('vendor/bin/phpstan')) {
	run(`${php} vendor/bin/phpstan analyse --memory-limit=3G --no-progress`);
}
