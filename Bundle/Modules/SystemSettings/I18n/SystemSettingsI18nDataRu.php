<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\SystemSettings\I18n {
    class SystemSettingsI18nDataRu {
        public const LANG = 'RU';

        /** @var array<string, string> */
        public static array $data = [
            'Admin_SystemSettings' => 'Системные настройки',
            'Admin_SystemSettings_Description' => 'Управление SMTP и правилами регистрации.',
            'Admin_RegistrationSettings' => 'Регистрация',
            'Admin_RegistrationEnabled' => 'Разрешить регистрации',
            'Admin_RegistrationEnabled_Hint' => 'При выключении страница /register станет недоступной для новых пользователей.',
            'Admin_SMTPSettings' => 'SMTP',
            'Admin_SMTPSettings_Hint' => 'Значения здесь перекрывают email.ini и используются для отправки писем.',
            'Admin_SMTPEnabled' => 'Включить отправку писем',
            'Admin_SMTPEnabled_Hint' => 'Если выключено, фреймворк не будет отправлять email.',
            'Admin_SMTPVerifyPeer' => 'Проверять TLS сертификат сервера',
            'Admin_SMTPScheme' => 'Схема',
            'Admin_SMTPHost' => 'Хост',
            'Admin_SMTPPort' => 'Порт',
            'Admin_SMTPUser' => 'Логин',
            'Admin_SMTPPassword' => 'Пароль',
            'Admin_SMTPFrom' => 'From',
            'Admin_SaveSettings' => 'Сохранить настройки',
            'Admin_SaveSettings_Saving' => 'Сохранение...',
            'Admin_SaveSettings_Success' => 'Настройки сохранены',
            'Admin_SystemSettings_AccessDenied' => 'Доступ запрещен',
            'Admin_SystemSettings_InvalidPort' => 'Некорректный SMTP порт',
            'Admin_SystemSettings_MissingSmtpField' => 'Не заполнено обязательное SMTP поле',
            'Admin_SystemSettings_TestEmail_Title' => 'Тестовое письмо',
            'Admin_SystemSettings_TestEmail_Hint' => 'Укажите адрес и отправьте тестовое письмо с текущими SMTP настройками из формы.',
            'Admin_SystemSettings_TestEmail_Address' => 'Email для теста',
            'Admin_SystemSettings_TestEmail_Placeholder' => 'example@example.com',
            'Admin_SystemSettings_TestEmail_Send' => 'Отправить тестовое письмо',
            'Admin_SystemSettings_TestEmail_Sending' => 'Отправка...',
            'Admin_SystemSettings_TestEmail_Success' => 'Тестовое письмо отправлено',
            'Admin_SystemSettings_TestEmail_InvalidAddress' => 'Укажите корректный email адрес',
            'Admin_SystemSettings_TestEmail_SendFailed' => 'Не удалось отправить тестовое письмо',
            'Admin_SystemSettings_TestEmail_Subject' => 'Тест SMTP настроек',
            'Admin_SystemSettings_TestEmail_Body' => 'Это тестовое письмо, отправленное из системных настроек.',
        ];
    }
}
