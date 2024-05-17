import build from '../build/build.js';
import { DateTime, Interval } from 'luxon';
import { intro, outro } from '@clack/prompts';
import { util, helpers } from '@apertia/extension-build-tools';
import { env } from '../util/env.js';

/**
 * Builds & deploys the extension.
 * @returns {Promise<void>}
 */
const deploy = async () => {
	const START_DATE = DateTime.now();

	const zipPath = await build();

	intro('Deploying extension');

	if (helpers.isSSHEnvironment(env)) {
		await util.remoteDeploy(zipPath, env);
	} else {
		if (env.LOCAL_IS_DOCKER) {
			await util.dockerDeploy(zipPath, env);
		} else {
			await util.localDeploy(zipPath, env);
		}
	}

	const END_DATE = DateTime.now();
	const DEPLOY_TIME = Interval.fromDateTimes(START_DATE, END_DATE).toDuration(
		'seconds',
	);

	outro(`Deploy completed in ${DEPLOY_TIME.toMillis()}ms`);
};

export default deploy;
