import path from 'path';
import fs from 'fs';
import type {Configuration} from '@rspack/core';
import {resolveConfig, moduleRules} from './build/moduleConfig';

const baseDir = __dirname;
const frameworkRoot = path.resolve(baseDir, '..');
// Admin frontend lives next to its PHP backend at
// <framework>/Kernel/Io/GarnetCli/Admin/Front/ — same Front/-as-sibling
// pattern used by Apps/<App>/<Bundle>/Front/. Compiled output drops
// into <framework>/Kernel/Io/GarnetCli/Admin/dist/ which the CLI's
// AdminView.php references. FrontBuilder sits at the framework root, so
// one hop up reaches it.
const adminBackendDir = path.resolve(frameworkRoot, 'Kernel', 'Io', 'GarnetCli', 'Admin');
const adminSrcDir = path.resolve(adminBackendDir, 'Front');
const distDir = path.resolve(adminBackendDir, 'dist');

if (!fs.existsSync(distDir)) {
	fs.mkdirSync(distDir, {recursive: true});
}

const isProduction = process.env.NODE_ENV === 'production';

const configuration: Configuration = {
	experiments: {css: true},
	mode: isProduction ? 'production' : 'development',
	target: ['web', 'es2020'],

	entry: {
		admin: path.resolve(adminSrcDir, 'EntryPoints', 'Admin.tsx'),
		admin_css: path.resolve(adminSrcDir, 'Styles', 'admin.less'),
	},

	output: {
		filename: '[name].js',
		path: distDir,
		publicPath: '/__garnet/assets/',
		clean: true,
		library: {type: 'umd'},
		cssFilename: (pathData: any) => {
			const name = pathData.chunk?.name || '';
			return name === 'admin_css' ? 'admin.css' : '[name].css';
		},
	},

	resolve: resolveConfig([path.resolve(baseDir, 'node_modules')]),

	module: {
		parser: {css: {url: false}},
		rules: moduleRules(isProduction),
	},

	optimization: {
		splitChunks: false,
	},

	devtool: isProduction ? false : 'source-map',

	performance: {
		hints: false,
	},
};

export default configuration;
