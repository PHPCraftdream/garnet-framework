import path from 'path';
import type {RuleSetRule} from '@rspack/core';

// FrontBuilder lives inside the Framework package (Framework/FrontBuilder/),
// so two hops up from FrontBuilder/build/ reach the Framework root. Shared
// frontend sources live in Bundle/Front/ next to it.
const frameworkRoot = path.resolve(__dirname, '..', '..');
const fwFrontDir = path.resolve(frameworkRoot, 'Bundle', 'Front');

export const resolveConfig = (modules: string[]) => ({
	modules: modules,
	extensions: ['.ts', '.tsx', '.js', '.jsx', '.less', '.css'],
	alias: {
		'react': path.resolve(frameworkRoot, 'FrontBuilder', 'node_modules', 'react'),
		'react-dom': path.resolve(frameworkRoot, 'FrontBuilder', 'node_modules', 'react-dom'),
		'@common': path.resolve(fwFrontDir, 'Common'),
		'@framework': fwFrontDir,
	},
});

// The app root for Tailwind's @source in framework.less. The stylesheet used to
// guess it as six directories up from its own real path, which is the app only
// when the framework sits in <app>/vendor/phpcraftdream/; behind a symlink or
// junction the real path is shallow and six hops up reach the drive root.
const garnetAppRoot = (process.env.COMMON_GARNET_WEB_DIR || frameworkRoot).split(path.sep).join('/');

export const moduleRules = (isProduction: boolean): RuleSetRule[] => [
	{
		test: /\.tsx?$/,
		use: {
			loader: 'builtin:swc-loader',
			options: {
				jsc: {
					parser: {
						syntax: 'typescript',
						tsx: true,
					},
					transform: {
						react: {
							runtime: 'automatic',
						},
					},
				},
			},
		},
		type: 'javascript/auto',
	},
	{
		test: /\.jsx?$/,
		use: {
			loader: 'builtin:swc-loader',
			options: {
				jsc: {
					parser: {
						syntax: 'ecmascript',
						jsx: true,
					},
					transform: {
						react: {
							runtime: 'automatic',
						},
					},
				},
			},
		},
		type: 'javascript/auto',
	},
	{
		test: /\.less$/,
		use: [
			{
				loader: 'postcss-loader',
				options: {
					postcssOptions: {
						plugins: [
							'@tailwindcss/postcss',
							'autoprefixer',
							...(isProduction ? [['cssnano', {preset: 'default'}]] : []),
						],
					},
				},
			},
			{
				loader: 'less-loader',
				options: {lessOptions: {globalVars: {garnetAppRoot: JSON.stringify(garnetAppRoot)}}},
			},
		],
		type: 'css',
	},
	{
		test: /\.css$/,
		type: 'css',
	},
];
