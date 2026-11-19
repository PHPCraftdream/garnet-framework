/**
 * Вкладка SEO: описание, картинка для соцсетей, аккаунт в Twitter.
 *
 * Поле картинки работает двумя способами: если переданы адреса загрузки и
 * удаления — полноценная загрузка файла, иначе просто ввод адреса. Второй
 * путь нужен там, где загрузка файлов не настроена, и без него вкладка
 * оказалась бы нерабочей вместо ограниченно рабочей.
 */
import * as React from 'react';
import {ImageUploadField} from '@common/Components/Controls/ImageUploadField';
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

export class SeoTab extends React.Component<SeoTabProps> {
    private readonly handleDescription = (event: React.ChangeEvent<HTMLTextAreaElement>): void => {
        const value = event.target.value;
        this.props.setSettings(prev => ({...prev, seo: {...prev.seo, description: value}}));
    };

    private readonly handleImage = (value: string): void => {
        this.props.setSettings(prev => ({...prev, seo: {...prev.seo, ogImage: value}}));
    };

    private readonly handleImageInput = (event: React.ChangeEvent<HTMLInputElement>): void => {
        this.handleImage(event.target.value);
    };

    private readonly handleTwitter = (event: React.ChangeEvent<HTMLInputElement>): void => {
        const value = event.target.value;
        this.props.setSettings(prev => ({...prev, seo: {...prev.seo, twitterSite: value}}));
    };

    private renderDescription() {
        const {settings, labels} = this.props;
        return (
            <label className="settings-field-label">
                <span className="settings-field-caption">{labels.seoDescription}</span>
                {labels.seoDescriptionHint && (
                    <span className="settings-seo-hint">{labels.seoDescriptionHint}</span>
                )}
                <textarea
                    rows={3}
                    className="settings-native-field"
                    value={settings.seo?.description ?? ''}
                    onChange={this.handleDescription}
                />
            </label>
        );
    }

    private renderImage() {
        const {settings, labels, sending, uploadImageUrl, deleteImageUrl} = this.props;
        return (
            <div className="settings-field-label">
                <span className="settings-field-caption">{labels.seoOgImage}</span>
                {labels.seoOgImageHint && (
                    <span className="settings-seo-image-hint">{labels.seoOgImageHint}</span>
                )}
                {uploadImageUrl && deleteImageUrl ? (
                    <ImageUploadField
                        value={settings.seo?.ogImage ?? ''}
                        onChange={this.handleImage}
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
                        className="settings-native-field"
                        value={settings.seo?.ogImage ?? ''}
                        onChange={this.handleImageInput}
                    />
                )}
            </div>
        );
    }

    private renderTwitter() {
        const {settings, labels} = this.props;
        return (
            <label className="settings-penalty-label">
                <span className="settings-field-caption">{labels.seoTwitterSite}</span>
                <input
                    className="settings-native-field"
                    placeholder={labels.seoTwitterSitePlaceholder ?? '@yoursite'}
                    value={settings.seo?.twitterSite ?? ''}
                    onChange={this.handleTwitter}
                />
            </label>
        );
    }

    public render() {
        const {labels, sending} = this.props;
        return (
            <section className="settings-general-panel">
                <div className="settings-general-header">
                    <h2 className="settings-general-title">{labels.seoTitle}</h2>
                    <p className="settings-general-hint">{labels.seoHint}</p>
                </div>

                <div className="settings-seo-fields">
                    {this.renderDescription()}
                    {this.renderImage()}
                    {this.renderTwitter()}
                </div>

                <SaveButton labels={labels} sending={sending} />
            </section>
        );
    }
}
