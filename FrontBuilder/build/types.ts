export interface IBundleInfo {
	namespace: string;
	bundleDir: string;
	backendDir: string;
	frontendDir: string;
	twigEnv: string;
	twigTemplatesDir: string;
	twigCacheDir: string;
	bundleName: string;
	workDir: string;
	isFrameworkBundle: boolean;
}

export interface IAppInfo {
	bundles: IBundleInfo[],
	namespace: string;
	appDir: string;
	appDirName: string;
	publicDir: string;
	assetsDir: string;
	assetsGenDir: string;
	assetsDirName: string;
	workDir: string;
	configProdDir: string;
	configDevDir: string;
	fileCacheDir: string;
	logErrorDir: string;
	uploadDir: string;
	twigCacheDir: string;
	assetsDirFw: string;
	assetsDirFwJs: string;
	assetsDirFwCss: string;
}

export interface EntryMap {
	all: Record<string, string>;
	framework: Record<string, string>;
}
