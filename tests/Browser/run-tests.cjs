/**
 * Runs every jsdom regression check in tests/Browser and stops at the first
 * failure.
 *
 * Each *.cjs file in this directory (other than this runner) is a standalone
 * script that exits non-zero on failure. The runner executes them one at a
 * time in a separate Node process, in sorted order, prints each file name with
 * its result, and exits with the failing script's status. A run that finds no
 * test files, or lacks the Cacti jQuery UI build several checks load, fails
 * instead of reporting success.
 *
 * Usage: CACTI_JS_DIR=/path/to/cacti/include/js npm test
 */
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const {spawnSync} = require('node:child_process');

/** Upper bound for one test file, so a hung timer cannot stall CI indefinitely. */
const TEST_TIMEOUT_MS = 120000;

/**
 * Lists the test scripts to run.
 *
 * @param {string} dir Directory that holds the test scripts.
 * @returns {string[]} Sorted file names, excluding this runner.
 */
function findTests(dir) {
	const self = path.basename(__filename);
	return fs.readdirSync(dir)
		.filter((name) => name.endsWith('.cjs') && name !== self)
		.sort();
}

/**
 * Confirms that CACTI_JS_DIR names a Cacti include/js directory.
 *
 * @param {string|undefined} jsDir Value of the CACTI_JS_DIR environment variable.
 * @returns {string|null} An error message, or null when the directory is usable.
 */
function checkCactiJsDir(jsDir) {
	if (!jsDir) {
		return 'CACTI_JS_DIR is not set; point it at a Cacti checkout\'s include/js directory.';
	}
	if (!fs.existsSync(path.join(jsDir, 'jquery-ui.js'))) {
		return 'CACTI_JS_DIR does not contain jquery-ui.js: ' + jsDir;
	}
	return null;
}

/**
 * Runs one test script in a child Node process with inherited output.
 *
 * @param {string} file Absolute path of the test script.
 * @returns {number} The script's exit status; 1 when it was killed, timed out
 *   or could not start.
 */
function runTest(file) {
	const result = spawnSync(process.execPath, [file], {
		cwd: path.dirname(file),
		env: process.env,
		stdio: 'inherit',
		timeout: TEST_TIMEOUT_MS,
	});
	if (result.error) {
		console.error(result.error.message);
		return 1;
	}
	if (result.status === null) {
		console.error('Terminated by ' + result.signal);
		return 1;
	}
	return result.status;
}

/**
 * Runs all test scripts and sets the process exit status.
 *
 * @returns {void}
 */
function main() {
	const configError = checkCactiJsDir(process.env.CACTI_JS_DIR);
	if (configError) {
		console.error(configError);
		process.exitCode = 2;
		return;
	}

	const tests = findTests(__dirname);
	if (tests.length === 0) {
		console.error('No browser test files found in ' + __dirname);
		process.exitCode = 2;
		return;
	}

	for (const [index, name] of tests.entries()) {
		console.log('[' + (index + 1) + '/' + tests.length + '] ' + name);
		const status = runTest(path.join(__dirname, name));
		if (status !== 0) {
			console.error('FAIL ' + name + ' (exit ' + status + '); stopping.');
			process.exitCode = status;
			return;
		}
		console.log('ok   ' + name);
	}
	console.log('All ' + tests.length + ' browser test files passed.');
}

main();
