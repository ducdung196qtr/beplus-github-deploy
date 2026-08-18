/** @type {import('lint-staged').Config} */
module.exports = {
	'*.php': 'node ./scripts/lint-staged-php.mjs',
};
