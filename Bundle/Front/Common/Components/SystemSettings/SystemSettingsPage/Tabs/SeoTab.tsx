/**
 * Вкладка SEO: описание, картинка для соцсетей, аккаунт в Twitter.
 *
 * Поле картинки работает двумя способами: если переданы адреса загрузки и
 * удаления — полноценная загрузка файла, иначе просто ввод адреса. Второй
 * путь нужен там, где загрузка файлов не настроена, и без него вкладка
 * оказалась бы нерабочей вместо ограниченно рабочей.
 */
import * as React from 'react';
import {ImageUploadField} from '@common/Components/ImageUploadField';
import {SettingsData, SystemSettingsLabels} from '../types';
import {SaveButton} from './SaveButton';

interface SeoTabProps {
    settings: SettingsData;
    setSettings: React.Dispatch<React.SetStateAction<SettingsData>>;
    labels: SystemSettingsLabels;
    sending: boolean;
    uploadImageUrl?: string;
    deleteImageUrl?: string;
}

export const SeoTab: React.FC<SeoTabProps> = ({settings, setSettings, labels, sending, uploadImageUrl, deleteImageUrl}) => (
<section className="rounded-lg border border-default bg-surface p-5">
    <div className="mb-4">
        <h2 className="text-lg font-semibold text-on-surface">{labels.seoTitle}</h2>
        <p className="mt-1 text-sm text-secondary">{labels.seoHint}</p>
    </div>

    <div className="grid gap-4">
        <label className="block">
            <span className="mb-1 block text-sm font-medium text-on-surface">{labels.seoDescription}</span>
            {labels.seoDescriptionHint && (
                <span className="mb-1 block text-xs text-secondary">{labels.seoDescriptionHint}</span>
            )}
            <textarea
                rows={3}
                className="form-control w-full border-default"
                value={settings.seo?.description ?? ''}
                onChange={(e) => setSettings(prev => ({
                    ...prev,
                    seo: {...prev.seo, description: e.target.value},
                }))}
            />
        </label>
        <div className="block">
            <span className="mb-1 block text-sm font-medium text-on-surface">{labels.seoOgImage}</span>
            {labels.seoOgImageHint && (
                <span className="mb-2 block text-xs text-secondary">{labels.seoOgImageHint}</span>
            )}
            {uploadImageUrl && deleteImageUrl ? (
                <ImageUploadField
                    value={settings.seo?.ogImage ?? ''}
                    onChange={(url) => setSettings(prev => ({
                        ...prev,
                        seo: {...prev.seo, ogImage: url},
                    }))}
                    uploadUrl={uploadImageUrl}
                    deleteUrl={deleteImageUrl}
                    uploadLabel={labels.seoOgImageUpload}
                    removeLabel={labels.seoOgImageRemove}
                    removeConfirm={labels.seoOgImageRemoveConfirm}
                    errorLabel={labels.error}
                    disabled={sending}
                />
            ) : (
                <input
                    type="url"
                    className="form-control w-full border-default"
                    value={settings.seo?.ogImage ?? ''}
                    onChange={(e) => setSettings(prev => ({
                        ...prev,
                        seo: {...prev.seo, ogImage: e.target.value},
                    }))}
                />
            )}
        </div>
        <label className="block max-w-xs">
            <span className="mb-1 block text-sm font-medium text-on-surface">{labels.seoTwitterSite}</span>
            <input
                className="form-control w-full border-default"
                placeholder={labels.seoTwitterSitePlaceholder ?? '@yoursite'}
                value={settings.seo?.twitterSite ?? ''}
                onChange={(e) => setSettings(prev => ({
                    ...prev,
                    seo: {...prev.seo, twitterSite: e.target.value},
                }))}
            />
        </label>
    </div>

    <SaveButton labels={labels} sending={sending} />
</section>
);
