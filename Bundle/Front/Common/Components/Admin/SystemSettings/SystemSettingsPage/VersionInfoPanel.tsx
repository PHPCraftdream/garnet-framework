/**
 * Версия фреймворка и приложения — на что именно смотрит хост прямо сейчас.
 *
 * Чисто отображение: данные уже пришли в пропсах (VersionInfo, посчитан
 * сервером через AppVersionInfo::current()), никакого запроса при монтировании
 * не нужно. Живёт рядом с OpcacheResetPanel — оба этот раздел про хост, а не
 * про настройки.
 */

import * as React from 'react';
import {SystemSettingsLabels, VersionInfo} from './types';

const versionLine = (version?: string | null, commit?: string | null, unknown?: string): string => {
    if (!version) return unknown ?? '—';

    return commit ? `${version} @ ${commit}` : version;
};

export const VersionInfoPanel: React.FC<{info: VersionInfo; labels: SystemSettingsLabels}> = ({info, labels}) => {
    const unknown = labels.versionInfoUnknown ?? '—';

    return (
        <section className="rounded-lg border border-default bg-surface p-5 mt-6" data-test-id="version-info-section">
            <h2 className="text-lg font-semibold text-on-surface mb-3">
                {labels.versionInfoTitle ?? 'Version'}
            </h2>
            <dl className="text-sm space-y-1">
                <div className="flex gap-2">
                    <dt className="text-muted min-w-[140px]">{labels.versionInfoApp ?? 'App'}</dt>
                    <dd className="font-mono" data-test-id="version-info-app">
                        {versionLine(info.appVersion, info.appCommit, unknown)}
                    </dd>
                </div>
                <div className="flex gap-2">
                    <dt className="text-muted min-w-[140px]">{labels.versionInfoFramework ?? 'Framework'}</dt>
                    <dd className="font-mono" data-test-id="version-info-framework">
                        {versionLine(info.frameworkVersion, info.frameworkCommit, unknown)}
                    </dd>
                </div>
                {info.builtAt && (
                    <div className="flex gap-2">
                        <dt className="text-muted min-w-[140px]">{labels.versionInfoBuiltAt ?? 'Built at'}</dt>
                        <dd className="font-mono" data-test-id="version-info-built-at">{info.builtAt}</dd>
                    </div>
                )}
            </dl>
        </section>
    );
};
