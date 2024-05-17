import cpy from 'cpy';
import makeDir from 'make-dir';
import fs from 'fs-extra';
import { intro, outro, log, cancel } from '@clack/prompts';
import { DateTime, Interval } from 'luxon';
import { exit } from 'process';
import { join } from 'path/posix';
import { deleteAsync as del } from 'del';
import { helpers, util } from '@apertia/extension-build-tools';
import { tsContext } from '@apertia/extension-build-tools/dist/contexts/ts.js';
import { cssContext } from '@apertia/extension-build-tools/dist/contexts/css.js';
import { metadata } from '../util/metadata.js';

const {
	DIST,
	backendPath,
	clientPath,
	toPascalCase,
	isComposerInstalled,
	installDependencies: composerInstallDependencies,
	jsonExists: composerJsonExists,
	VENDOR,
	SRC_BACKEND,
	SRC_CLIENT,
	TS_BUILD,
	CSS_BUILD,
	SRC_SCRIPTS,
} = helpers;

const { copyLibs, zipDir, createManifest } = util;

/**
 * Builds the extension.
 * @returns {Promise<string>} The path to the built extension .zip file.
 */
const build = async () => {
	const START_DATE = DateTime.now();

	const namePascalCase = toPascalCase(metadata.espocrm.extensionName);
	const OUTPUT_NAME = `${namePascalCase}-v${metadata.version}`;

	const OUTPUT_PATH = join(DIST, OUTPUT_NAME);
	const OUTPUT_ZIP_PATH = join(DIST, `${OUTPUT_NAME}.zip`);
	const BACKEND_PATH = join(OUTPUT_PATH, 'files', backendPath(metadata));
	const CLIENT_PATH = join(OUTPUT_PATH, 'files', clientPath(metadata));

	intro(`Building extension (${OUTPUT_NAME})`);

	log.step('Removing any previous build files');
	await del(OUTPUT_PATH);
	await del(OUTPUT_ZIP_PATH);

	log.step('Creating build directory');
	await makeDir(OUTPUT_PATH);

	log.step('Compiling TypeScript');

	await tsContext.rebuild();
	await tsContext.dispose();

	log.step('Compiling CSS');

	await cssContext?.rebuild();
	await cssContext?.dispose();

	// Composer install + copy vendor directory

	if (await composerJsonExists()) {
		log.step('Found project composer.json');

		if (!(await isComposerInstalled())) {
			cancel('Composer is not installed.');
			exit(1);
		}

		log.step('Cleaning vendor directory');
		await del(VENDOR);

		log.step('Installing composer dependencies');
		if (!(await composerInstallDependencies())) {
			cancel('Composer installation failed.');
			exit(1);
		}

		log.step('Copying vendor directory');
		await cpy(join(VENDOR, '**', '*'), join(BACKEND_PATH, 'vendor'));
	}

	// External Libraries

	log.step('Copying libraries');

	await copyLibs(metadata, CLIENT_PATH);

	log.step('Copying backend directory');
	await cpy(join(SRC_BACKEND, '**', '*'), BACKEND_PATH);

	log.step('Copying client directory (exluding TS/CSS)');
	await cpy(join(SRC_CLIENT, '**', '*.!(ts|d.ts|css)'), CLIENT_PATH);

	log.step('Copying compiled TypeScript files');
	await cpy(join(TS_BUILD, '**', '*.(js|js.map)'), CLIENT_PATH);

	log.step('Copying CSS');
	await cpy(
		join(CSS_BUILD, '**', '*.(css|css.map)'),
		join(CLIENT_PATH, 'css'),
	);

	log.step('Copying scripts directory');
	await cpy(join(SRC_SCRIPTS, '**', '*'), join(OUTPUT_PATH, 'scripts'));

	log.step('Creating manifest.json');
	const manifest = createManifest(metadata);
	await fs.writeJson(join(OUTPUT_PATH, 'manifest.json'), manifest, {
		spaces: 4,
	});

	log.step('Packaging build');

	await zipDir(OUTPUT_PATH, OUTPUT_ZIP_PATH);

	const END_DATE = DateTime.now();
	const BUILD_TIME = Interval.fromDateTimes(START_DATE, END_DATE).toDuration(
		'seconds',
	);

	log.info(`Location: ${OUTPUT_ZIP_PATH}`);

	outro(`Build completed in ${BUILD_TIME.toMillis()}ms`);

	return OUTPUT_ZIP_PATH;
};

export default build;
