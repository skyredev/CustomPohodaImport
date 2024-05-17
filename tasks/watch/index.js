import { watch } from 'chokidar';
import SFTP from 'ssh2-sftp-client';
import { packageDirectory as pkgDir } from 'pkg-dir';
import { dirname, join, relative } from 'path/posix';
import anymatch from 'anymatch';
import { helpers } from '@apertia/extension-build-tools';
import { log } from '@clack/prompts';
import cpy from 'cpy';
import { env } from '../util/env.js';
import { metadata } from '../util/metadata.js';
import { tsContext } from '@apertia/extension-build-tools/dist/contexts/ts.js';
import { cssContext } from '@apertia/extension-build-tools/dist/contexts/css.js';
import { platform } from 'os';
import slash from 'slash';

const ROOT_DIR = await pkgDir();

if (!ROOT_DIR) {
	throw new Error('Could not find root directory of this project.');
}

const isWindows = platform() === 'win32';
const isSSH = helpers.isSSHEnvironment(env);
const espocrmRootDirectory = isSSH
	? env.SSH_ESPO_ROOT_DIR
	: env.LOCAL_ESPO_ROOT_DIR;

if (!espocrmRootDirectory) {
	throw new Error('SSH_ESPO_ROOT_DIR or LOCAL_ESPO_ROOT_DIR is not set');
}

const BACKEND_PATH = helpers.backendPath(metadata);
const CLIENT_PATH = helpers.clientPath(metadata);

/**
 * @typedef {Object} Preparator
 * @property {string} match The glob pattern of files to match.
 * @property {function(string): string} convert The function that converts the local path to the remote path.
 */

/** @type {Array<Preparator>} */
const preparators = [
	{
		match: join(helpers.SRC_BACKEND, '**', '*'),
		convert: local =>
			join(
				espocrmRootDirectory,
				BACKEND_PATH,
				relative(helpers.SRC_BACKEND, local),
			),
	},
	{
		match: join(helpers.TS_BUILD, '**', '*.(js|js.map)'),
		convert: local =>
			join(
				espocrmRootDirectory,
				CLIENT_PATH,
				relative(helpers.TS_BUILD, local),
			),
	},
	{
		match: join(helpers.CSS_BUILD, '**', '*.(css|css.map)'),
		convert: local =>
			join(
				espocrmRootDirectory,
				CLIENT_PATH,
				'css',
				relative(helpers.CSS_BUILD, local),
			),
	},
	{
		match: join(helpers.SRC_CLIENT, '**', '*.!(ts|d.ts|css)'),
		convert: local =>
			join(
				espocrmRootDirectory,
				CLIENT_PATH,
				relative(helpers.SRC_CLIENT, local),
			),
	},
];

const sftp = new SFTP();

sftp.on('end', () => {
	console.error('SFTP connection ended, exiting...');
	process.exit(1);
});

if (isSSH) {
	await sftp.connect(await helpers.createSSHConfig(env));
}

/**
 * @param {string} local
 * @param {string} remote
 * @returns {Promise<void>}
 */
const upload = async (local, remote) => {
	await sftp.mkdir(dirname(remote), true);
	await sftp.put(local, remote);
};

/**
 * @param {string} path
 * @returns {string|null}
 */
const preparePath = path => {
	const preparator = preparators.find(({ match }) => {
		return anymatch(match, path, { dot: true });
	});

	if (!preparator) {
		return null;
	}

	return preparator.convert(path);
};

/**
 * @param {string} path
 * @returns {Promise<void>}
 */
const processPath = async path => {
	const relativePath = isWindows
		? relative(slash(ROOT_DIR), slash(path))
		: relative(ROOT_DIR, path);

	const remotePath = preparePath(relativePath);

	if (!remotePath) {
		return;
	}

	await (isSSH
		? upload(relativePath, remotePath)
		: cpy(relativePath, dirname(remotePath)));

	log.success(`Transferred: ${relativePath}`);
};

await tsContext.rebuild();
await tsContext.watch();

await cssContext?.rebuild();
await cssContext?.watch();

log.info('Watching for changes...');

const watcher = watch(ROOT_DIR, {
	ignoreInitial: true,
	ignored: '**/node_modules/**/*',
	awaitWriteFinish: {
		stabilityThreshold: 300,
	},
});

watcher.on('add', path => processPath(path));
watcher.on('change', path => processPath(path));
