import { helpers } from '@apertia/extension-build-tools';
import fs from 'fs-extra';

const packageJson = await fs.readJSON('package.json');

export const metadata = await helpers.metadata.parseAsync(packageJson);
