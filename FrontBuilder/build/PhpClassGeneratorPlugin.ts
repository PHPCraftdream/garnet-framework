import path from 'path';
import fs from 'fs';
import type {Compiler, Compilation} from '@rspack/core';
import {IAppInfo, IBundleInfo} from './types';

const makeMethod = (methodName: string, file: string, assetsDir: string): string =>
	`        public static function ${methodName}(): string {
            return '/assets/${assetsDir}/gen/${file}';
        }
`;

// Stable accessor that has no chunk to point at — returns '' so callers
// (which array_filter() empty asset URLs) simply skip it.
const makeEmptyMethod = (methodName: string): string =>
	`        public static function ${methodName}(): string {
            return '';
        }
`;

const phpClassBuilder = (
	compilation: Compilation,
	appInfo: IAppInfo,
	fwBundles: Record<string, string>,
	jsOutputPath: string,
	cssOutputPath: string
) => {
	const bundlesMap: Record<string, IBundleInfo> = {};
	const bundlesJsMethods: Record<string, string[]> = {};
	const bundlesCssMethods: Record<string, string[]> = {};

	appInfo?.bundles?.forEach((bundle: IBundleInfo) => {
		bundlesMap[bundle.bundleName] = bundle;
	});

	const jsChunks = Object.fromEntries(compilation.namedChunks);

	// Numbered split-chunks emitted by rspack splitChunks. Collected here at
	// build time → baked into `<Bundle>JsGen::commonChunks()` so the runtime
	// never has to glob() / scandir() the gen dirs at request time. URLs use
	// the same `/assets/<assetsDirName>/gen/js/…` prefix as the entry chunks
	// and get PublicPathRebrander-rewritten during `bundle`. We don't go
	// through `compilation.namedChunks` because rspack assigns these chunks
	// numeric IDs (no name) so they don't show up there — scan the output
	// dir instead, which is authoritative once `afterEmit` runs.
	const bundleCommonChunks: Record<string, string[]> = {};
	const appBundleName = (appInfo?.bundles ?? [])
		.map((b: IBundleInfo) => b.bundleName.toLowerCase())
		.find((n) => n !== 'framework') ?? 'foreground';
	if (fs.existsSync(jsOutputPath)) {
		for (const name of fs.readdirSync(jsOutputPath)) {
			if (!/^\d+\.[a-f0-9]+\.gen\.js$/.test(name)) continue;
			const url = `/assets/${appInfo.assetsDirName}/gen/js/${name}`;
			if (!bundleCommonChunks[appBundleName]) {
				bundleCommonChunks[appBundleName] = [];
			}
			bundleCommonChunks[appBundleName].push(url);
		}
	}

	Object.keys(jsChunks).forEach((chunkName: string) => {
		const item = jsChunks[chunkName];
		const files: string[] = Array.from(item.files);

		// Handle CSS entries - delete empty JS files and process CSS
		if (chunkName.startsWith('css_')) {
			const jsFile = files.find(f => f.endsWith('.js'));
			if (jsFile) {
				const jsFilePath = path.resolve(jsOutputPath, '..', jsFile);
				if (fs.existsSync(jsFilePath)) {
					fs.unlinkSync(jsFilePath);
				}
			}

			const jsMapFile = files.find(f => f.endsWith('.js.map'));
			if (jsMapFile) {
				const jsMapFilePath = path.resolve(jsOutputPath, '..', jsMapFile);
				if (fs.existsSync(jsMapFilePath)) {
					fs.unlinkSync(jsMapFilePath);
				}
			}

			const cssFile = files.find(f => f.endsWith('.css'));
			if (!cssFile) return;

			const parts = chunkName.replace('css_', '').split('_');
			const isFw = parts.length === 1;
			const bundleName = isFw ? 'framework' : parts[0];
			const methodName = isFw ? parts[0] : parts[1];

			if (!bundlesCssMethods[bundleName]) {
				bundlesCssMethods[bundleName] = [];
			}

			let finalCssFile = isFw ? 'css/' + path.basename(cssFile) : cssFile;

			if (isFw) {
				const srcFile = path.resolve(cssOutputPath, '..', cssFile);
				const dstFile = path.resolve(appInfo.assetsDirFwCss, path.basename(cssFile));

				if (fs.existsSync(srcFile)) {
					if (fs.existsSync(dstFile)) {
						fs.unlinkSync(dstFile);
					}
					fs.renameSync(srcFile, dstFile);
				}
			}

			const method = makeMethod(
				methodName,
				finalCssFile,
				isFw ? 'framework' : appInfo.assetsDirName
			);
			bundlesCssMethods[bundleName].push(method);
			return;
		}

		// Handle vendor chunks
		if (chunkName.startsWith('vendor-')) {
			const file = files.find(f => f.endsWith('.js'));
			if (!file) return;

			const methodName = chunkName.replace(/-/g, '_');

			const srcFile = path.resolve(jsOutputPath, '..', file);
			const dstFile = path.resolve(appInfo.assetsDirFwJs, path.basename(file));

			if (fs.existsSync(srcFile)) {
				if (fs.existsSync(dstFile)) fs.unlinkSync(dstFile);
				fs.renameSync(srcFile, dstFile);
			}

			const srcFileMap = path.resolve(jsOutputPath, '..', file + '.map');
			const dstFileMap = path.resolve(appInfo.assetsDirFwJs, path.basename(file) + '.map');

			if (fs.existsSync(srcFileMap)) {
				if (fs.existsSync(dstFileMap)) fs.unlinkSync(dstFileMap);
				fs.renameSync(srcFileMap, dstFileMap);
			}

			const method = makeMethod(methodName, 'js/' + path.basename(file), 'framework');

			if (!bundlesJsMethods['framework']) {
				bundlesJsMethods['framework'] = [];
			}
			bundlesJsMethods['framework'].push(method);
			return;
		}

		// Handle JS entries
		const isFwBundle = !!fwBundles[chunkName];
		const file = files.find(f => f.endsWith('.js'));

		if (!file) return;

		let [bundleName, chunkItem] = chunkName.split('.');

		if (isFwBundle) {
			bundleName = 'framework';
		}

		if (!bundlesJsMethods[bundleName]) {
			bundlesJsMethods[bundleName] = [];
		}

		let finalJsFile = file;

		if (isFwBundle) {
			const srcFile = path.resolve(jsOutputPath, '..', file);
			const dstFile = path.resolve(appInfo.assetsDirFwJs, path.basename(file));

			if (fs.existsSync(srcFile)) {
				if (fs.existsSync(dstFile)) {
					fs.unlinkSync(dstFile);
				}
				fs.renameSync(srcFile, dstFile);
				finalJsFile = 'js/' + path.basename(file);
			}

			const srcFileMap = path.resolve(jsOutputPath, '..', file + '.map');
			const dstFileMap = path.resolve(appInfo.assetsDirFwJs, path.basename(file) + '.map');

			if (fs.existsSync(srcFileMap)) {
				if (fs.existsSync(dstFileMap)) {
					fs.unlinkSync(dstFileMap);
				}
				fs.renameSync(srcFileMap, dstFileMap);
			}
		}

		const method = makeMethod(
			isFwBundle ? chunkName : chunkItem,
			finalJsFile,
			isFwBundle ? 'framework' : appInfo.assetsDirName
		);
		bundlesJsMethods[bundleName].push(method);
	});

	// The framework vendor split-chunks (vendor-react, vendor-other) only
	// materialise when their modules are present in the build graph — a minimal
	// app may not produce `vendor-other`. That would drop
	// FrameworkJsGen::vendor_other() and break phpstan for any (richer) app that
	// references it once this shared class is overwritten. Always emit the
	// standard framework vendor accessors; one with no chunk returns '' (callers
	// array_filter() empty asset URLs away), so the method set is stable across
	// every app build.
	const fwMethods = bundlesJsMethods['framework'] ?? (bundlesJsMethods['framework'] = []);

	for (const name of ['vendor_react', 'vendor_other']) {
		if (!fwMethods.some(m => m.includes(`function ${name}(`))) {
			fwMethods.push(makeEmptyMethod(name));
		}
	}

	// Write PHP classes. The template ships with the framework package; this
	// plugin lives in FrontBuilder/build/, so two hops up reach the framework
	// root regardless of where the app dir is (same anchor as moduleConfig.ts).
	const templatePath = path.resolve(__dirname, '..', '..', 'Templates', 'CodeFiles', 'Class.template');
	const classTemplate = fs.readFileSync(templatePath).toString();

	const makeCommonChunksMethod = (urls: string[]): string => {
		const lines = urls.map(u => `                '${u}',`).join('\n');
		return `        /**
         * Numbered async chunks emitted by rspack splitChunks. Use them
         * to seed \`<link rel="prefetch">\` so the browser warms its cache
         * during idle time after the main page paints.
         *
         * @return string[]
         */
        public static function commonChunks(): array {
            return [
${lines}
            ];
        }
`;
	};

	appInfo?.bundles?.forEach((bundle: IBundleInfo) => {
		const bundleKey = bundle.bundleName.toLowerCase();
		const methods = bundlesJsMethods[bundleKey] ?? [];
		const commonUrls = bundleCommonChunks[bundleKey] ?? [];

		if (commonUrls.length > 0) {
			methods.push(makeCommonChunksMethod(commonUrls));
		}

		if (methods.length > 0) {
			const result = classTemplate
				.replace('[[methods]]', methods.join('\r\n').trimEnd())
				.replace('[[className]]', bundle.bundleName + 'JsGen')
				.replace('[[namespace]]', bundle.namespace);

			const classPath = path.resolve(bundle.bundleDir, bundle.bundleName + 'JsGen.php');
			fs.writeFileSync(classPath, result);
			console.log(`Generated: ${classPath}`);
		}
	});

	appInfo?.bundles?.forEach((bundle: IBundleInfo) => {
		const bundleKey = bundle.isFrameworkBundle ? 'framework' : bundle.bundleName.toLowerCase();
		const methods = bundlesCssMethods[bundleKey];

		if (methods && methods.length > 0) {
			const result = classTemplate
				.replace('[[methods]]', methods.join('\r\n').trimEnd())
				.replace('[[className]]', bundle.bundleName + 'CssGen')
				.replace('[[namespace]]', bundle.namespace);

			const classPath = path.resolve(bundle.bundleDir, bundle.bundleName + 'CssGen.php');
			fs.writeFileSync(classPath, result);
			console.log(`Generated: ${classPath}`);
		}
	});
};

export class PhpClassGeneratorPlugin {
	constructor(
		private appInfo: IAppInfo,
		private fwBundles: Record<string, string>,
		private jsOutputPath: string,
		private cssOutputPath: string
	) {}

	apply(compiler: Compiler) {
		compiler.hooks.afterEmit.tap('PhpClassGeneratorPlugin', (compilation: Compilation) => {
			phpClassBuilder(compilation, this.appInfo, this.fwBundles, this.jsOutputPath, this.cssOutputPath);
		});
	}
}
