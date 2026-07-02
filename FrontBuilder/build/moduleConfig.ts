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
		'@common': path.resolve(fwFrontDir, 'Common'),
		'@framework': fwFrontDir,
	},
});

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
			{loader: 'less-loader'},
		],
		type: 'css',
	},
	{
		test: /\.css$/,
		type: 'css',
	},
];
