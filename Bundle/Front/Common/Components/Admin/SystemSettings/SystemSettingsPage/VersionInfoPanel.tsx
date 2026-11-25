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

/** Returns a display value for a version and its optional commit. */
const versionLine = (version?: string | null, commit?: string | null, unknown?: string): string => {
    if (!version) return unknown ?? '—';

    return commit ? `${version} @ ${commit}` : version;
};

/** Shows the application and framework versions supplied by the host. */
export const VersionInfoPanel: React.FC<{info: VersionInfo; labels: SystemSettingsLabels}> = ({info, labels}) => {
    const unknown = labels.versionInfoUnknown ?? '—';

    return (
        <section className="settings-version-panel" data-test-id="version-info-section">
            <h2 className="settings-version-title">
                {labels.versionInfoTitle ?? 'Version'}
            </h2>
            <dl className="settings-version-list">
                <div className="settings-version-row">
                    <dt className="settings-version-label">
                        {labels.versionInfoApp ?? 'App'}
                    </dt>
                    <dd className="settings-version-value" data-test-id="version-info-app">
                        {versionLine(info.appVersion, info.appCommit, unknown)}
                    </dd>
                </div>
                <div className="settings-version-row">
                    <dt className="settings-version-label">
                        {labels.versionInfoFramework ?? 'Framework'}
                    </dt>
                    <dd className="settings-version-value" data-test-id="version-info-framework">
                        {versionLine(info.frameworkVersion, info.frameworkCommit, unknown)}
                    </dd>
                </div>
                {info.builtAt && (
                    <div className="settings-version-row">
                        <dt className="settings-version-label">{labels.versionInfoBuiltAt ?? 'Built at'}</dt>
                        <dd
                            className="settings-version-value"
                            data-test-id="version-info-built-at"
                        >
                            {info.builtAt}
                        </dd>
                    </div>
                )}
            </dl>
        </section>
    );
};
