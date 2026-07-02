import path from 'path';
import fs from 'fs';
import rspack from '@rspack/core';
import type {Configuration} from '@rspack/core';
import {IAppInfo} from './build/types';
import {makeEntry, buildModulesArrByEntryMap, makeCssEntry} from './build/entryPoints';
import {PhpClassGeneratorPlugin} from './build/PhpClassGeneratorPlugin';
import {resolveConfig, moduleRules} from './build/moduleConfig';

const {execSync} = require("child_process");

//-----------------------------------------------------------------------------
// PHP Integration
//-----------------------------------------------------------------------------

if (!process.env.COMMON_GARNET_WEB_DIR) {
	console.error('Empty process.env.COMMON_GARNET_WEB_DIR');
	process.exit();
}

const garnetCli = path.resolve(process.env.COMMON_GARNET_WEB_DIR, "garnet");
const paramsStr = execSync(`php ${garnetCli} prepare`).toString();
const appInfo: IAppInfo = JSON.parse(paramsStr) as IAppInfo;

//-----------------------------------------------------------------------------
// Entry Points & Output Paths
//-----------------------------------------------------------------------------

const isProduction = process.env.NODE_ENV === 'production';

const entry = makeEntry(appInfo, isProduction);
const modules = buildModulesArrByEntryMap(entry.all, __dirname);
const cssEntry = makeCssEntry(appInfo);
const allEntries = {...entry.all, ...cssEntry};

const genOutputPath = path.resolve(__dirname, '..', '..', appInfo.publicDir, `assets/${appInfo.assetsDirName}/gen`);
const jsOutputPath = path.resolve(genOutputPath, 'js');
const cssOutputPath = path.resolve(genOutputPath, 'css');

// Ensure output directories exist
[genOutputPath, jsOutputPath, cssOutputPath, appInfo.assetsDirFwJs, appInfo.assetsDirFwCss].forEach(dir => {
	if (!fs.existsSync(dir)) {
		fs.mkdirSync(dir, {recursive: true});
	}
});

console.log({projectPaths: appInfo, entry, cssEntry, genOutputPath, jsOutputPath, cssOutputPath, modules});
console.log({bundles: appInfo.bundles});

// Clear output directories before build
const clearDirectory = (directoryPath: string): void => {
	if (!fs.existsSync(directoryPath)) return;
	for (const file of fs.readdirSync(directoryPath)) {
		const filePath = path.join(directoryPath, file);
		if (fs.statSync(filePath).isFile()) {
			fs.unlinkSync(filePath);
		} else {
			clearDirectory(filePath);
			fs.rmdirSync(filePath);
		}
	}
};

clearDirectory(jsOutputPath);
clearDirectory(cssOutputPath);
clearDirectory(appInfo.assetsDirFwJs);
clearDirectory(appInfo.assetsDirFwCss);

//-----------------------------------------------------------------------------
// Rspack Configuration
//-----------------------------------------------------------------------------

const configuration: Configuration = {
	experiments: {css: true},
	mode: isProduction ? 'production' : 'development',
	target: ['web', 'es2020'],
	entry: allEntries,

	output: {
		filename: "js/[name].[contenthash:16].gen.js",
		path: genOutputPath,
		publicPath: `/assets/${appInfo.assetsDirName}/gen/`,
		clean: true,
		library: {type: "umd"},
		cssFilename: (pathData: any) => {
			const name = pathData.chunk?.name || '';
			const cleanName = name.replace(/^css_/, '');
			return `css/${cleanName}.[contenthash:16].gen.css`;
		},
	},

	resolve: resolveConfig(modules),

	module: {
		parser: {css: {url: false}},
		rules: moduleRules(isProduction),
	},

	plugins: [
		new PhpClassGeneratorPlugin(appInfo, entry.framework, jsOutputPath, cssOutputPath),
		new rspack.DefinePlugin({
			__GARNET_DEBUG__: JSON.stringify(process.env.GARNET_DEBUG === '1'),
		}),
	],

	optimization: {
		splitChunks: {
			cacheGroups: {
				vendorReact: {
					test: /[\\/]node_modules[\\/](react|react-dom|scheduler)[\\/]/,
					name: 'vendor-react',
					chunks: 'all',
					priority: 20,
					enforce: true,
				},
				vendorOther: {
					test: /[\\/]node_modules[\\/]/,
					name: 'vendor-other',
					chunks: 'all',
					priority: 10,
					minChunks: 2,
					enforce: true,
					reuseExistingChunk: true,
				},
			},
		},
	},

	devtool: isProduction ? false : 'source-map',

	watchOptions: {
		ignored: /\.php$/,
	},

	performance: {
		hints: false,
		maxEntrypointSize: 5120000,
		maxAssetSize: 5120000,
	},
};

export default configuration;
