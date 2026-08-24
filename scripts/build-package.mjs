#!/usr/bin/env node
/**
 * Package the plugin into a distributable ZIP for WordPress.
 * Cross-platform (Windows/macOS/Linux) — uses archiver, not the zip CLI.
 *
 * Ships runtime files only (PHP + admin JS/CSS + helpers).
 * Dev tooling (docs, scripts, composer, husky, node_modules, vendor) is excluded.
 *
 * Usage:
 *   npm run build
 *   node scripts/build-package.mjs
 */

import { createRequire } from 'node:module';

const require = createRequire( import.meta.url );
const archiver = require( 'archiver' );
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { globSync } from 'glob';

const __dirname = path.dirname( fileURLToPath( import.meta.url ) );
const ROOT = path.resolve( __dirname, '..' );
const PLUGIN_SLUG = path.basename( ROOT );

/**
 * Allowlist — only paths needed to run the plugin in WordPress.
 */
const INCLUDE_GLOBS = [
	'beplus-github-deploy.php',
	'readme.txt',
	'src/**/*.php',
	'includes/**/*.php',
	'admin/css/**',
	'admin/js/*.js',
	'languages/**',
];

function readVersion() {
	const bootstrap = fs.readFileSync(
		path.join( ROOT, 'beplus-github-deploy.php' ),
		'utf8',
	);
	const m = bootstrap.match(
		/define\(\s*'BEPLUS_MANAGER_VERSION'\s*,\s*'([^']+)'\s*\)/,
	);
	if ( ! m ) {
		console.error( 'Could not parse BEPLUS_MANAGER_VERSION from beplus-github-deploy.php' );
		process.exit( 1 );
	}
	return m[ 1 ];
}

function collectFiles() {
	/** @type {Set<string>} */
	const files = new Set();

	for ( const pattern of INCLUDE_GLOBS ) {
		for ( const rel of globSync( pattern, {
			cwd: ROOT,
			dot: false,
			nodir: true,
			nocase: process.platform === 'win32',
		} ) ) {
			files.add( rel.split( path.sep ).join( '/' ) );
		}
	}
	return [ ...files ].sort();
}

function createZip( zipPath, files ) {
	return new Promise( ( resolve, reject ) => {
		const output = fs.createWriteStream( zipPath );
		const archive = archiver( 'zip', { zlib: { level: 9 } } );

		output.on( 'close', resolve );
		archive.on( 'error', reject );
		output.on( 'error', reject );

		archive.pipe( output );

		for ( const rel of files ) {
			const abs = path.join( ROOT, rel );
			if ( ! fs.existsSync( abs ) ) {
				continue;
			}
			const entryName = `${PLUGIN_SLUG}/${rel}`;
			archive.file( abs, { name: entryName } );
		}

		archive.finalize();
	} );
}

const version = readVersion();
const zipName = `${PLUGIN_SLUG}-v${version}.zip`;
const zipPath = path.join( ROOT, zipName );

if ( fs.existsSync( zipPath ) ) {
	fs.unlinkSync( zipPath );
}

const files = collectFiles();

if ( files.length === 0 ) {
	console.error( 'No files matched the release allowlist. Run npm run build first.' );
	process.exit( 1 );
}

console.log( `Packaging ${PLUGIN_SLUG} v${version} → ${zipName} (${files.length} files)` );

await createZip( zipPath, files );

const { size } = fs.statSync( zipPath );
console.log( `Done: ${zipName} (${( size / 1024 ).toFixed( 1 )} KB)` );
