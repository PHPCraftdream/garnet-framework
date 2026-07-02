<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\SystemSettings\I18n {
    class SystemSettingsI18nDataEn {
        public const LANG = 'EN';

        /** @var array<string, string> */
        public static array $data = [
            'Admin_SystemSettings' => 'System Settings',
            'Admin_SystemSettings_Description' => 'Manage SMTP and registration availability.',
            'Admin_RegistrationSettings' => 'Registration',
            'Admin_RegistrationEnabled' => 'Allow registrations',
            'Admin_RegistrationEnabled_Hint' => 'When disabled, the /register page is closed for new users.',
            'Admin_SMTPSettings' => 'SMTP',
            'Admin_SMTPSettings_Hint' => 'Values here override email.ini and are used for outgoing mail.',
            'Admin_SMTPEnabled' => 'Enable email sending',
            'Admin_SMTPEnabled_Hint' => 'If disabled, the framework will not send emails.',
            'Admin_SMTPVerifyPeer' => 'Verify server TLS certificate',
            'Admin_SMTPScheme' => 'Scheme',
            'Admin_SMTPHost' => 'Host',
            'Admin_SMTPPort' => 'Port',
            'Admin_SMTPUser' => 'Username',
            'Admin_SMTPPassword' => 'Password',
            'Admin_SMTPFrom' => 'From',
            'Admin_SaveSettings' => 'Save settings',
            'Admin_SaveSettings_Saving' => 'Saving...',
            'Admin_SaveSettings_Success' => 'Settings saved',
            'Admin_SystemSettings_AccessDenied' => 'Access denied',
            'Admin_SystemSettings_InvalidPort' => 'Invalid SMTP port',
            'Admin_SystemSettings_MissingSmtpField' => 'A required SMTP field is missing',
            'Admin_SystemSettings_TestEmail_Title' => 'Test email',
            'Admin_SystemSettings_TestEmail_Hint' => 'Enter an address and send a test email using the current SMTP values from the form.',
            'Admin_SystemSettings_TestEmail_Address' => 'Test email address',
            'Admin_SystemSettings_TestEmail_Placeholder' => 'example@example.com',
            'Admin_SystemSettings_TestEmail_Send' => 'Send test email',
            'Admin_SystemSettings_TestEmail_Sending' => 'Sending...',
            'Admin_SystemSettings_TestEmail_Success' => 'Test email sent',
            'Admin_SystemSettings_TestEmail_InvalidAddress' => 'Enter a valid email address',
            'Admin_SystemSettings_TestEmail_SendFailed' => 'Failed to send the test email',
            'Admin_SystemSettings_TestEmail_Subject' => 'SMTP settings test',
            'Admin_SystemSettings_TestEmail_Body' => 'This is a test email sent from system settings.',
        ];
    }
}
