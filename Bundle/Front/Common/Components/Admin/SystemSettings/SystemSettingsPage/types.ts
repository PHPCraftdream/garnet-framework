/**
 * Формы данных и подписи экрана системных настроек.
 */

export type SettingsData = {
    registrationsEnabled: boolean;
    cancellationPenaltyPercent: number;
    supportContacts: {
        email: string;
        phone: string;
        telegram: string;
    };
    smtp: {
        enabled: boolean;
        scheme: string;
        host: string;
        port: string;
        user: string;
        password: string;
        from: string;
        verify_peer: boolean;
    };
    seo?: {
        description?: string;
        ogImage?: string;
        twitterSite?: string;
    };
};

export type SystemSettingsLabels = {
    title: string;
    subtitle: string;
    registrationTitle: string;
    registrationTab: string;
    registrationEnabled: string;
    registrationHint: string;
    smtpTitle: string;
    smtpTab: string;
    smtpHint: string;
    smtpEnabled: string;
    smtpEnabledHint: string;
    smtpVerifyPeer: string;
    smtpScheme: string;
    smtpHost: string;
    smtpPort: string;
    smtpUser: string;
    smtpPassword: string;
    smtpFrom: string;
    save: string;
    saving: string;
    saved: string;
    testEmailTitle: string;
    testEmailHint: string;
    testEmailLabel: string;
    testEmailPlaceholder: string;
    testEmailSend: string;
    testEmailSending: string;
    testEmailSuccess: string;
    error: string;
    cancellationPenaltyTab?: string;
    cancellationPenaltyTitle?: string;
    cancellationPenaltyLabel?: string;
    cancellationPenaltyHint?: string;
    cancellationPenaltyInvalid?: string;
    testEmailType?: string;
    testEmailTypeGeneric?: string;
    supportContactsTab?: string;
    supportContactsTitle?: string;
    supportContactsHint?: string;
    supportContactEmail?: string;
    supportContactPhone?: string;
    supportContactTelegram?: string;
    seoTab?: string;
    seoTitle?: string;
    seoHint?: string;
    seoDescription?: string;
    seoDescriptionHint?: string;
    seoOgImage?: string;
    seoOgImageHint?: string;
    seoOgImageUpload?: string;
    seoOgImageRemove?: string;
    seoOgImageRemoveConfirm?: string;
    seoTwitterSite?: string;
    seoTwitterSitePlaceholder?: string;
    historyTab: string;
    historyTitle: string;
    historyHint: string;
    historyEmpty: string;
    historyLoading: string;
    historyRefresh: string;
    opcacheResetTitle?: string;
    opcacheResetHint?: string;
    opcacheResetBtn?: string;
    opcacheResetSuccess?: string;
    opcacheResetUnavailable?: string;
};


export interface MailTypeOption {
    id: string;
    label: string;
}

export interface SystemSettingsPageProps {
    settings: SettingsData;
    saveUrl: string;
    testEmailUrl: string;
    /** Optional endpoint for the audit-log "History" tab. When omitted, the tab is hidden. */
    historyListUrl?: string;
    /** Endpoints for the SEO OG-image upload field. */
    uploadImageUrl?: string;
    deleteImageUrl?: string;
    /** Owner-only OPcache reset endpoint. When omitted, the button is hidden. */
    opcacheResetUrl?: string;
    labels: SystemSettingsLabels;
    mailTypes?: MailTypeOption[];
}

export type TabKey = 'smtp' | 'registration' | 'penalty' | 'contacts' | 'seo' | 'history';
