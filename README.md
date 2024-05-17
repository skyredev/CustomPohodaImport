# Module - Pohoda Import
> CRM connection to Pohoda accounting system

## Table of Contents
* [Requirements](#requirements)
* [Installation](#installation)

## Requirements
* [EspoCRM](https://www.espocrm.com/) (>= 7.5.0)
* [PHP](https://www.php.net/) (>= 8.2.0)

## Installation

### Pre-build extension release

1. Download the latest release from the [Releases](https://gitlab.apertia.cz/autocrm/modules/pohoda-import/-/releases) page.
2. Go to **Administration** -> **Extensions** and upload the downloaded archive.

### Build from source
*(requires Node, NPM and potentially Composer to be installed)*

1. Clone the repository.
2. Run `npm install`.
3. Run `npm run build`. This will create a `dist` folder with the final extension package.

### Deploying

Optionally you can create a `.env` file based on the `.env.template` file.
The `.env` file will be used to deploy the extension to an existing EspoCRM installation.

**Linux example**

```shell
mv .env.template .env
vim .env # set your environment variables
npm run deploy
```

## Development
This extension was created via the [Extension Template](https://gitlab.apertia.cz/autocrm/extension-template),
all the necessary information about the development process can be found there.
