import 'dotenv/config';
import { helpers } from '@apertia/extension-build-tools';

export const env = await helpers.env.parseAsync(process.env);
