import path, {ParsedPath} from 'path';
import fs from 'fs';
import {IAppInfo, IBundleInfo, EntryMap} from './types';

export const makeEntry = (appInfo: IAppInfo, isProduction = false): EntryMap => {
	const entry: Record<string, string> = {};
	const framework: Record<string, string> = {};
	const files: { dir: string, file: ParsedPath, bundle: string, isFramework: boolean }[] = [];

	appInfo?.bundles?.forEach((bundleInfo: IBundleInfo) => {
		const dir = path.resolve(bundleInfo.frontendDir, 'EntryPoints');

		if (!fs.existsSync(dir)) return;

		fs.readdirSync(dir)
			.filter((f: string) => /.*\.[tj]sx?$/ig.test(f))
			.filter((f: string) => !(isProduction && /Dev\.[tj]sx?$/i.test(f)))
			.map((s) => path.parse(s))
			.forEach((file) => files.push({
				dir,
				file,
				bundle: bundleInfo.bundleName,
				isFramework: bundleInfo.isFrameworkBundle
			}));
	});

	files.forEach((f) => {
		const key = f.isFramework ? f.file.name.toLocaleLowerCase() : `${f.bundle.toLocaleLowerCase()}.${f.file.name.toLocaleLowerCase()}`;
		const val = path.resolve(`${f.dir}/${f.file.base}`);

		entry[key] = path.resolve(val);

		if (f.isFramework) {
			framework[key] = path.resolve(val);
		}
	});

	return {all: entry, framework};
};

export const buildModulesArrByEntryMap = (entryMap: Record<string, string>, baseDir: string): string[] => {
	const result: Record<string, boolean> = {};

	const builderNodeDir = path.resolve(baseDir, 'node_modules');

	if (fs.existsSync(builderNodeDir)) {
		result[builderNodeDir] = true;
	}

	Object.keys(entryMap).forEach((key) => {
		const entryFile = entryMap[key];
		const fileInfo = path.parse(entryFile);
		const nodeDir = path.resolve(path.parse(fileInfo.dir).dir, 'node_modules');

		if (fs.existsSync(nodeDir)) {
			result[nodeDir] = true;
		}
	});

	return Object.keys(result).reverse();
};

export const makeCssEntry = (appInfo: IAppInfo): Record<string, string> => {
	const entry: Record<string, string> = {};

	appInfo?.bundles?.forEach((bundleInfo: IBundleInfo) => {
		const dir = path.resolve(bundleInfo.frontendDir, 'Styles');

		if (!fs.existsSync(dir)) return;

		fs.readdirSync(dir)
			.filter((f: string) => f.endsWith('.less'))
			.forEach((file: string) => {
				const parsed = path.parse(file);
				const isFw = bundleInfo.isFrameworkBundle;
				const key = isFw
					? `css_${parsed.name.toLowerCase()}`
					: `css_${bundleInfo.bundleName.toLowerCase()}_${parsed.name.toLowerCase()}`;

				entry[key] = path.resolve(dir, file);
			});
	});

	return entry;
};
