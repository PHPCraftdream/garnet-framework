// noinspection DuplicatedCode
import {I18nDataEN} from './I18nDataEN';
import {I18nDataRU} from './I18nDataRU';
import {I18nBase} from '@common/Utils/I18nBase';

/** Translation lookup function signature. */
type t = (args?: (string|number)[]) => string;

/** Provides typed access to this bundle's translations. */
class I18n extends I18nBase {
    /** Translation lookup for `MainPage`. */
    MainPage: t = (a = []) => this.t('MainPage', a);
    /** Translation lookup for `No_Access`. */
    No_Access: t = (a = []) => this.t('No_Access', a);
    /** Translation lookup for `Auth`. */
    Auth: t = (a = []) => this.t('Auth', a);
    /** Translation lookup for `Auth_AuthWithCode`. */
    Auth_AuthWithCode: t = (a = []) => this.t('Auth_AuthWithCode', a);
    /** Translation lookup for `Auth_CodeHint`. */
    Auth_CodeHint: t = (a = []) => this.t('Auth_CodeHint', a);
    /** Translation lookup for `Auth_CodeOutdated`. */
    Auth_CodeOutdated: t = (a = []) => this.t('Auth_CodeOutdated', a);
    /** Translation lookup for `Auth_CodePlaceholder`. */
    Auth_CodePlaceholder: t = (a = []) => this.t('Auth_CodePlaceholder', a);
    /** Translation lookup for `Auth_CodeSent`. */
    Auth_CodeSent: t = (a = []) => this.t('Auth_CodeSent', a);
    /** Translation lookup for `Auth_EmailHint`. */
    Auth_EmailHint: t = (a = []) => this.t('Auth_EmailHint', a);
    /** Translation lookup for `Auth_EmailRequired`. */
    Auth_EmailRequired: t = (a = []) => this.t('Auth_EmailRequired', a);
    /** Translation lookup for `Auth_ExhaustedInputAttempts`. */
    Auth_ExhaustedInputAttempts: t = (a = []) => this.t('Auth_ExhaustedInputAttempts', a);
    /** Translation lookup for `Auth_FailCheckCSRF`. */
    Auth_FailCheckCSRF: t = (a = []) => this.t('Auth_FailCheckCSRF', a);
    /** Translation lookup for `Auth_IncorrectCode`. */
    Auth_IncorrectCode: t = (a = []) => this.t('Auth_IncorrectCode', a);
    /** Translation lookup for `Auth_RequestCode`. */
    Auth_RequestCode: t = (a = []) => this.t('Auth_RequestCode', a);
    /** Translation lookup for `Auth_RequestNewCode`. */
    Auth_RequestNewCode: t = (a = []) => this.t('Auth_RequestNewCode', a);
    /** Translation lookup for `Auth_Required`. */
    Auth_Required: t = (a = []) => this.t('Auth_Required', a);
    /** Translation lookup for `Auth_TriesRemains`. */
    Auth_TriesRemains: t = (a = []) => this.t('Auth_TriesRemains', a);
    /** Translation lookup for `Auth_Login`. */
    Auth_Login: t = (a = []) => this.t('Auth_Login', a);
    /** Translation lookup for `Auth_LoginRequired`. */
    Auth_LoginRequired: t = (a = []) => this.t('Auth_LoginRequired', a);
    /** Translation lookup for `Auth_LoginPlaceholder`. */
    Auth_LoginPlaceholder: t = (a = []) => this.t('Auth_LoginPlaceholder', a);
    /** Translation lookup for `Auth_InvalidCodeFormat`. */
    Auth_InvalidCodeFormat: t = (a = []) => this.t('Auth_InvalidCodeFormat', a);
    /** Translation lookup for `Auth_InvalidCodes`. */
    Auth_InvalidCodes: t = (a = []) => this.t('Auth_InvalidCodes', a);
    /** Translation lookup for `Auth_Register`. */
    Auth_Register: t = (a = []) => this.t('Auth_Register', a);
    /** Translation lookup for `Auth_UserAlreadyExists`. */
    Auth_UserAlreadyExists: t = (a = []) => this.t('Auth_UserAlreadyExists', a);
    /** Translation lookup for `Auth_UserNotFound`. */
    Auth_UserNotFound: t = (a = []) => this.t('Auth_UserNotFound', a);
    /** Translation lookup for `Auth_UserNotConfigured`. */
    Auth_UserNotConfigured: t = (a = []) => this.t('Auth_UserNotConfigured', a);
    /** Translation lookup for `Auth_Continue`. */
    Auth_Continue: t = (a = []) => this.t('Auth_Continue', a);
    /** Translation lookup for `Auth_ScanFirstQr`. */
    Auth_ScanFirstQr: t = (a = []) => this.t('Auth_ScanFirstQr', a);
    /** Translation lookup for `Auth_ScanSecondQr`. */
    Auth_ScanSecondQr: t = (a = []) => this.t('Auth_ScanSecondQr', a);
    /** Translation lookup for `Auth_EnterFirstCode`. */
    Auth_EnterFirstCode: t = (a = []) => this.t('Auth_EnterFirstCode', a);
    /** Translation lookup for `Auth_EnterSecondCode`. */
    Auth_EnterSecondCode: t = (a = []) => this.t('Auth_EnterSecondCode', a);
    /** Translation lookup for `Auth_FirstCodeVerified`. */
    Auth_FirstCodeVerified: t = (a = []) => this.t('Auth_FirstCodeVerified', a);
    /** Translation lookup for `Auth_Verifying`. */
    Auth_Verifying: t = (a = []) => this.t('Auth_Verifying', a);
    /** Translation lookup for `Auth_SendingCode`. */
    Auth_SendingCode: t = (a = []) => this.t('Auth_SendingCode', a);
    /** Translation lookup for `Auth_VerifyCode`. */
    Auth_VerifyCode: t = (a = []) => this.t('Auth_VerifyCode', a);
    /** Translation lookup for `Auth_Complete`. */
    Auth_Complete: t = (a = []) => this.t('Auth_Complete', a);
    /** Translation lookup for `Auth_Logout`. */
    Auth_Logout: t = (a = []) => this.t('Auth_Logout', a);
    /** Translation lookup for `Auth_MagicLink_Error_Unknown`. */
    Auth_MagicLink_Error_Unknown: t = (a = []) => this.t('Auth_MagicLink_Error_Unknown', a);
    /** Translation lookup for `Auth_MagicLink_Error_Expired`. */
    Auth_MagicLink_Error_Expired: t = (a = []) => this.t('Auth_MagicLink_Error_Expired', a);
    /** Translation lookup for `Auth_MagicLink_Error_Used`. */
    Auth_MagicLink_Error_Used: t = (a = []) => this.t('Auth_MagicLink_Error_Used', a);
    /** Translation lookup for `Theme_SwitchToDark`. */
    Theme_SwitchToDark: t = (a = []) => this.t('Theme_SwitchToDark', a);
    /** Translation lookup for `Theme_SwitchToLight`. */
    Theme_SwitchToLight: t = (a = []) => this.t('Theme_SwitchToLight', a);
    /** Translation lookup for `Upload_TooManyFiles`. */
    Upload_TooManyFiles: t = (a = []) => this.t('Upload_TooManyFiles', a);
    /** Translation lookup for `Upload_TooLarge`. */
    Upload_TooLarge: t = (a = []) => this.t('Upload_TooLarge', a);
    /** Translation lookup for `Upload_Empty`. */
    Upload_Empty: t = (a = []) => this.t('Upload_Empty', a);
    /** Translation lookup for `Upload_ExtNotAllowed`. */
    Upload_ExtNotAllowed: t = (a = []) => this.t('Upload_ExtNotAllowed', a);
    /** Translation lookup for `Upload_ContentNotAllowed`. */
    Upload_ContentNotAllowed: t = (a = []) => this.t('Upload_ContentNotAllowed', a);
    /** Translation lookup for `Upload_Incomplete`. */
    Upload_Incomplete: t = (a = []) => this.t('Upload_Incomplete', a);
    /** Translation lookup for `Upload_StoreFailed`. */
    Upload_StoreFailed: t = (a = []) => this.t('Upload_StoreFailed', a);
    /** Translation lookup for `Upload_NotAnImage`. */
    Upload_NotAnImage: t = (a = []) => this.t('Upload_NotAnImage', a);
    /** Translation lookup for `Upload_NotAPdf`. */
    Upload_NotAPdf: t = (a = []) => this.t('Upload_NotAPdf', a);
    /** Translation lookup for `Upload_ImagesOnly`. */
    Upload_ImagesOnly: t = (a = []) => this.t('Upload_ImagesOnly', a);
    /** Translation lookup for `Upload_BrokenImage`. */
    Upload_BrokenImage: t = (a = []) => this.t('Upload_BrokenImage', a);
    /** Translation lookup for `Upload_ImageHint`. */
    Upload_ImageHint: t = (a = []) => this.t('Upload_ImageHint', a);
    /** Translation lookup for `Common_FromHasError`. */
    Common_FromHasError: t = (a = []) => this.t('Common_FromHasError', a);
    /** Translation lookup for `Common_IncorrectValue`. */
    Common_IncorrectValue: t = (a = []) => this.t('Common_IncorrectValue', a);
    /** Translation lookup for `Common_Len`. */
    Common_Len: t = (a = []) => this.t('Common_Len', a);
    /** Translation lookup for `Common_MinLength`. */
    Common_MinLength: t = (a = []) => this.t('Common_MinLength', a);
    /** Translation lookup for `Common_MaxLength`. */
    Common_MaxLength: t = (a = []) => this.t('Common_MaxLength', a);
    /** Translation lookup for `Common_ImgProcError`. */
    Common_ImgProcError: t = (a = []) => this.t('Common_ImgProcError', a);
    /** Translation lookup for `Common_ImgSavingError`. */
    Common_ImgSavingError: t = (a = []) => this.t('Common_ImgSavingError', a);
    /** Translation lookup for `Common_RequiredValue`. */
    Common_RequiredValue: t = (a = []) => this.t('Common_RequiredValue', a);
    /** Translation lookup for `Common_RequestError`. */
    Common_RequestError: t = (a = []) => this.t('Common_RequestError', a);
    /** Translation lookup for `Common_Maintenance`. */
    Common_Maintenance: t = (a = []) => this.t('Common_Maintenance', a);
    /** Translation lookup for `Common_UnknownRequestError`. */
    Common_UnknownRequestError: t = (a = []) => this.t('Common_UnknownRequestError', a);
    /** Translation lookup for `Common_NavigationError_Offline`. */
    Common_NavigationError_Offline: t = (a = []) => this.t('Common_NavigationError_Offline', a);
    /** Translation lookup for `Common_NavigationError_Generic`. */
    Common_NavigationError_Generic: t = (a = []) => this.t('Common_NavigationError_Generic', a);
    /** Translation lookup for `Common_Save`. */
    Common_Save: t = (a = []) => this.t('Common_Save', a);
    /** Translation lookup for `Common_Cancel`. */
    Common_Cancel: t = (a = []) => this.t('Common_Cancel', a);
    /** Translation lookup for `Common_OK`. */
    Common_OK: t = (a = []) => this.t('Common_OK', a);
    /** Translation lookup for `Common_Confirm`. */
    Common_Confirm: t = (a = []) => this.t('Common_Confirm', a);
    /** Translation lookup for `Common_Add`. */
    Common_Add: t = (a = []) => this.t('Common_Add', a);
    /** Translation lookup for `Common_Create`. */
    Common_Create: t = (a = []) => this.t('Common_Create', a);
    /** Translation lookup for `Common_Edit`. */
    Common_Edit: t = (a = []) => this.t('Common_Edit', a);
    /** Translation lookup for `Common_Delete`. */
    Common_Delete: t = (a = []) => this.t('Common_Delete', a);
    /** Translation lookup for `Common_DeleteConfirm`. */
    Common_DeleteConfirm: t = (a = []) => this.t('Common_DeleteConfirm', a);
    /** Translation lookup for `Common_DeleteError`. */
    Common_DeleteError: t = (a = []) => this.t('Common_DeleteError', a);
    /** Translation lookup for `Common_Email`. */
    Common_Email: t = (a = []) => this.t('Common_Email', a);
    /** Translation lookup for `Common_Int`. */
    Common_Int: t = (a = []) => this.t('Common_Int', a);
    /** Translation lookup for `Common_Min`. */
    Common_Min: t = (a = []) => this.t('Common_Min', a);
    /** Translation lookup for `Common_Max`. */
    Common_Max: t = (a = []) => this.t('Common_Max', a);
    /** Translation lookup for `Common_Url`. */
    Common_Url: t = (a = []) => this.t('Common_Url', a);
    /** Translation lookup for `Common_Alphanumeric`. */
    Common_Alphanumeric: t = (a = []) => this.t('Common_Alphanumeric', a);
    /** Translation lookup for `Common_Match`. */
    Common_Match: t = (a = []) => this.t('Common_Match', a);
    /** Translation lookup for `Common_Pattern`. */
    Common_Pattern: t = (a = []) => this.t('Common_Pattern', a);
    /** Translation lookup for `Email_Auth_CodeLifetime`. */
    Email_Auth_CodeLifetime: t = (a = []) => this.t('Email_Auth_CodeLifetime', a);
    /** Translation lookup for `Email_Auth_TeamSign`. */
    Email_Auth_TeamSign: t = (a = []) => this.t('Email_Auth_TeamSign', a);
    /** Translation lookup for `Email_Auth_Hello`. */
    Email_Auth_Hello: t = (a = []) => this.t('Email_Auth_Hello', a);
    /** Translation lookup for `Email_Auth_CodeIsSecret`. */
    Email_Auth_CodeIsSecret: t = (a = []) => this.t('Email_Auth_CodeIsSecret', a);
    /** Translation lookup for `Email_Auth_SuccessLogin_Title`. */
    Email_Auth_SuccessLogin_Title: t = (a = []) => this.t('Email_Auth_SuccessLogin_Title', a);
    /** Translation lookup for `Auth_TooManyRequests`. */
    Auth_TooManyRequests: t = (a = []) => this.t('Auth_TooManyRequests', a);
    /** Translation lookup for `Auth_RegistrationsDisabled`. */
    Auth_RegistrationsDisabled: t = (a = []) => this.t('Auth_RegistrationsDisabled', a);
    /** Translation lookup for `Auth_RegistrationsDisabledOwnDomainOk`. */
    Auth_RegistrationsDisabledOwnDomainOk: t = (a = []) => this.t('Auth_RegistrationsDisabledOwnDomainOk', a);
    /** Translation lookup for `Email_Auth_UseAuthButton`. */
    Email_Auth_UseAuthButton: t = (a = []) => this.t('Email_Auth_UseAuthButton', a);
    /** Translation lookup for `Email_Auth_UseAuthButtonOnlyForYou`. */
    Email_Auth_UseAuthButtonOnlyForYou: t = (a = []) => this.t('Email_Auth_UseAuthButtonOnlyForYou', a);
    /** Translation lookup for `Email_Auth_SuccessLogin_A`. */
    Email_Auth_SuccessLogin_A: t = (a = []) => this.t('Email_Auth_SuccessLogin_A', a);
    /** Translation lookup for `Email_Auth_SuccessLogin_B`. */
    Email_Auth_SuccessLogin_B: t = (a = []) => this.t('Email_Auth_SuccessLogin_B', a);
    /** Translation lookup for `Reg_Title`. */
    Reg_Title: t = (a = []) => this.t('Reg_Title', a);
    /** Translation lookup for `Reg_YourName`. */
    Reg_YourName: t = (a = []) => this.t('Reg_YourName', a);
    /** Translation lookup for `Reg_TimeZone`. */
    Reg_TimeZone: t = (a = []) => this.t('Reg_TimeZone', a);
    /** Translation lookup for `Reg_AccountType`. */
    Reg_AccountType: t = (a = []) => this.t('Reg_AccountType', a);
    /** Translation lookup for `Reg_AccountTypeUser`. */
    Reg_AccountTypeUser: t = (a = []) => this.t('Reg_AccountTypeUser', a);
    /** Translation lookup for `Reg_Action`. */
    Reg_Action: t = (a = []) => this.t('Reg_Action', a);
    /** Translation lookup for `Reg_ProfileImage`. */
    Reg_ProfileImage: t = (a = []) => this.t('Reg_ProfileImage', a);
    /** Translation lookup for `Reg_About`. */
    Reg_About: t = (a = []) => this.t('Reg_About', a);
    /** Translation lookup for `Profile_Page`. */
    Profile_Page: t = (a = []) => this.t('Profile_Page', a);
    /** Translation lookup for `Profile_PersonalData`. */
    Profile_PersonalData: t = (a = []) => this.t('Profile_PersonalData', a);
    /** Translation lookup for `Profile`. */
    Profile: t = (a = []) => this.t('Profile', a);
    /** Translation lookup for `Action_Del`. */
    Action_Del: t = (a = []) => this.t('Action_Del', a);
    /** Translation lookup for `Action_Replace`. */
    Action_Replace: t = (a = []) => this.t('Action_Replace', a);
    /** Translation lookup for `Action_Upload`. */
    Action_Upload: t = (a = []) => this.t('Action_Upload', a);
    /** Translation lookup for `Menu_DashBoard`. */
    Menu_DashBoard: t = (a = []) => this.t('Menu_DashBoard', a);
    /** Translation lookup for `Menu_Users`. */
    Menu_Users: t = (a = []) => this.t('Menu_Users', a);
    /** Translation lookup for `UserFlag_ADMIN`. */
    UserFlag_ADMIN: t = (a = []) => this.t('UserFlag_ADMIN', a);
    /** Translation lookup for `UserFlag_MODERATOR`. */
    UserFlag_MODERATOR: t = (a = []) => this.t('UserFlag_MODERATOR', a);
    /** Translation lookup for `UserFlag_APPROVED`. */
    UserFlag_APPROVED: t = (a = []) => this.t('UserFlag_APPROVED', a);
    /** Translation lookup for `UserFlag_DISABLED`. */
    UserFlag_DISABLED: t = (a = []) => this.t('UserFlag_DISABLED', a);
    /** Translation lookup for `User_login`. */
    User_login: t = (a = []) => this.t('User_login', a);
    /** Translation lookup for `User_name`. */
    User_name: t = (a = []) => this.t('User_name', a);
    /** Translation lookup for `User_timezone`. */
    User_timezone: t = (a = []) => this.t('User_timezone', a);
    /** Translation lookup for `User_about`. */
    User_about: t = (a = []) => this.t('User_about', a);
    /** Translation lookup for `User_reg_time`. */
    User_reg_time: t = (a = []) => this.t('User_reg_time', a);
    /** Translation lookup for `User_last_auth_time`. */
    User_last_auth_time: t = (a = []) => this.t('User_last_auth_time', a);
    /** Translation lookup for `User_last_online_time`. */
    User_last_online_time: t = (a = []) => this.t('User_last_online_time', a);
    /** Translation lookup for `Yes`. */
    Yes: t = (a = []) => this.t('Yes', a);
    /** Translation lookup for `No`. */
    No: t = (a = []) => this.t('No', a);
    /** Translation lookup for `Admin_Logs`. */
    Admin_Logs: t = (a = []) => this.t('Admin_Logs', a);
    /** Translation lookup for `Admin_Log_Actor`. */
    Admin_Log_Actor: t = (a = []) => this.t('Admin_Log_Actor', a);
    /** Translation lookup for `Admin_Log_Target`. */
    Admin_Log_Target: t = (a = []) => this.t('Admin_Log_Target', a);
    /** Translation lookup for `Admin_Log_Action`. */
    Admin_Log_Action: t = (a = []) => this.t('Admin_Log_Action', a);
    /** Translation lookup for `Admin_Log_OldValue`. */
    Admin_Log_OldValue: t = (a = []) => this.t('Admin_Log_OldValue', a);
    /** Translation lookup for `Admin_Log_NewValue`. */
    Admin_Log_NewValue: t = (a = []) => this.t('Admin_Log_NewValue', a);
    /** Translation lookup for `Admin_Log_CreatedAt`. */
    Admin_Log_CreatedAt: t = (a = []) => this.t('Admin_Log_CreatedAt', a);
    /** Translation lookup for `Admin_Log_Empty`. */
    Admin_Log_Empty: t = (a = []) => this.t('Admin_Log_Empty', a);
    /** Translation lookup for `Admin_Log_Filter_All`. */
    Admin_Log_Filter_All: t = (a = []) => this.t('Admin_Log_Filter_All', a);
    /** Translation lookup for `Admin_Log_Filter_Actor`. */
    Admin_Log_Filter_Actor: t = (a = []) => this.t('Admin_Log_Filter_Actor', a);
    /** Translation lookup for `Admin_Log_Filter_Target`. */
    Admin_Log_Filter_Target: t = (a = []) => this.t('Admin_Log_Filter_Target', a);
    /** Translation lookup for `Admin_Log_Filter_DateFrom`. */
    Admin_Log_Filter_DateFrom: t = (a = []) => this.t('Admin_Log_Filter_DateFrom', a);
    /** Translation lookup for `Admin_Log_Filter_DateTo`. */
    Admin_Log_Filter_DateTo: t = (a = []) => this.t('Admin_Log_Filter_DateTo', a);
    /** Translation lookup for `Admin_Log_Filter_Action`. */
    Admin_Log_Filter_Action: t = (a = []) => this.t('Admin_Log_Filter_Action', a);
    /** Translation lookup for `Admin_Log_Filter_ActorType`. */
    Admin_Log_Filter_ActorType: t = (a = []) => this.t('Admin_Log_Filter_ActorType', a);
    /** Translation lookup for `Admin_Log_Filter_SearchUser`. */
    Admin_Log_Filter_SearchUser: t = (a = []) => this.t('Admin_Log_Filter_SearchUser', a);
    /** Translation lookup for `Admin_Log_Filter_NoMatches`. */
    Admin_Log_Filter_NoMatches: t = (a = []) => this.t('Admin_Log_Filter_NoMatches', a);
    /** Translation lookup for `Admin_Log_Filter_Reset`. */
    Admin_Log_Filter_Reset: t = (a = []) => this.t('Admin_Log_Filter_Reset', a);
    /** Translation lookup for `Admin_MailLog`. */
    Admin_MailLog: t = (a = []) => this.t('Admin_MailLog', a);
    /** Translation lookup for `Admin_MailLog_Date`. */
    Admin_MailLog_Date: t = (a = []) => this.t('Admin_MailLog_Date', a);
    /** Translation lookup for `Admin_MailLog_Recipient`. */
    Admin_MailLog_Recipient: t = (a = []) => this.t('Admin_MailLog_Recipient', a);
    /** Translation lookup for `Admin_MailLog_Type`. */
    Admin_MailLog_Type: t = (a = []) => this.t('Admin_MailLog_Type', a);
    /** Translation lookup for `Admin_MailLog_Subject`. */
    Admin_MailLog_Subject: t = (a = []) => this.t('Admin_MailLog_Subject', a);
    /** Translation lookup for `Admin_MailLog_Status`. */
    Admin_MailLog_Status: t = (a = []) => this.t('Admin_MailLog_Status', a);
    /** Translation lookup for `Admin_MailLog_Error`. */
    Admin_MailLog_Error: t = (a = []) => this.t('Admin_MailLog_Error', a);
    /** Translation lookup for `Admin_MailLog_Empty`. */
    Admin_MailLog_Empty: t = (a = []) => this.t('Admin_MailLog_Empty', a);
    /** Translation lookup for `Admin_MailLog_Filter_All`. */
    Admin_MailLog_Filter_All: t = (a = []) => this.t('Admin_MailLog_Filter_All', a);
    /** Translation lookup for `Admin_MailLog_Filter_Recipient`. */
    Admin_MailLog_Filter_Recipient: t = (a = []) => this.t('Admin_MailLog_Filter_Recipient', a);
    /** Translation lookup for `Admin_MailLog_Filter_Status`. */
    Admin_MailLog_Filter_Status: t = (a = []) => this.t('Admin_MailLog_Filter_Status', a);
    /** Translation lookup for `Admin_MailLog_Filter_Subject`. */
    Admin_MailLog_Filter_Subject: t = (a = []) => this.t('Admin_MailLog_Filter_Subject', a);
    /** Translation lookup for `Admin_MailLog_Filter_Type`. */
    Admin_MailLog_Filter_Type: t = (a = []) => this.t('Admin_MailLog_Filter_Type', a);
    /** Translation lookup for `Admin_MailLog_Filter_NoAccount`. */
    Admin_MailLog_Filter_NoAccount: t = (a = []) => this.t('Admin_MailLog_Filter_NoAccount', a);
    /** Translation lookup for `Admin_MailLog_Filter_SearchUser`. */
    Admin_MailLog_Filter_SearchUser: t = (a = []) => this.t('Admin_MailLog_Filter_SearchUser', a);
    /** Translation lookup for `Admin_MailLog_Filter_NoMatches`. */
    Admin_MailLog_Filter_NoMatches: t = (a = []) => this.t('Admin_MailLog_Filter_NoMatches', a);
    /** Translation lookup for `Admin_MailLog_Body`. */
    Admin_MailLog_Body: t = (a = []) => this.t('Admin_MailLog_Body', a);
    /** Translation lookup for `Admin_RequestLog`. */
    Admin_RequestLog: t = (a = []) => this.t('Admin_RequestLog', a);
    /** Translation lookup for `RequestLog_Date`. */
    RequestLog_Date: t = (a = []) => this.t('RequestLog_Date', a);
    /** Translation lookup for `RequestLog_NoData`. */
    RequestLog_NoData: t = (a = []) => this.t('RequestLog_NoData', a);
    /** Translation lookup for `RequestLog_Method`. */
    RequestLog_Method: t = (a = []) => this.t('RequestLog_Method', a);
    /** Translation lookup for `RequestLog_Uri`. */
    RequestLog_Uri: t = (a = []) => this.t('RequestLog_Uri', a);
    /** Translation lookup for `RequestLog_Status`. */
    RequestLog_Status: t = (a = []) => this.t('RequestLog_Status', a);
    /** Translation lookup for `RequestLog_Duration`. */
    RequestLog_Duration: t = (a = []) => this.t('RequestLog_Duration', a);
    /** Translation lookup for `RequestLog_Account`. */
    RequestLog_Account: t = (a = []) => this.t('RequestLog_Account', a);
    /** Translation lookup for `RequestLog_Ip`. */
    RequestLog_Ip: t = (a = []) => this.t('RequestLog_Ip', a);
    /** Translation lookup for `RequestLog_Time`. */
    RequestLog_Time: t = (a = []) => this.t('RequestLog_Time', a);
    /** Translation lookup for `RequestLog_Ua`. */
    RequestLog_Ua: t = (a = []) => this.t('RequestLog_Ua', a);
    /** Translation lookup for `Logs_Requests_ColumnUser`. */
    Logs_Requests_ColumnUser: t = (a = []) => this.t('Logs_Requests_ColumnUser', a);
    /** Translation lookup for `Logs_Requests_FilterByUser`. */
    Logs_Requests_FilterByUser: t = (a = []) => this.t('Logs_Requests_FilterByUser', a);
    /** Translation lookup for `Logs_Requests_FilterByUserAll`. */
    Logs_Requests_FilterByUserAll: t = (a = []) => this.t('Logs_Requests_FilterByUserAll', a);
    /** Translation lookup for `Logs_Requests_Guest`. */
    Logs_Requests_Guest: t = (a = []) => this.t('Logs_Requests_Guest', a);
    /** Translation lookup for `Logs_Title`. */
    Logs_Title: t = (a = []) => this.t('Logs_Title', a);
    /** Translation lookup for `Logs_Tab_Actions`. */
    Logs_Tab_Actions: t = (a = []) => this.t('Logs_Tab_Actions', a);
    /** Translation lookup for `Logs_Tab_Mails`. */
    Logs_Tab_Mails: t = (a = []) => this.t('Logs_Tab_Mails', a);
    /** Translation lookup for `Logs_Tab_Requests`. */
    Logs_Tab_Requests: t = (a = []) => this.t('Logs_Tab_Requests', a);
    /** Translation lookup for `Logs_Tab_Errors`. */
    Logs_Tab_Errors: t = (a = []) => this.t('Logs_Tab_Errors', a);
    /** Translation lookup for `Logs_Tab_Cron`. */
    Logs_Tab_Cron: t = (a = []) => this.t('Logs_Tab_Cron', a);
    /** Translation lookup for `Logs_Tab_JsErrors`. */
    Logs_Tab_JsErrors: t = (a = []) => this.t('Logs_Tab_JsErrors', a);
    /** Translation lookup for `Logs_Errors_Empty`. */
    Logs_Errors_Empty: t = (a = []) => this.t('Logs_Errors_Empty', a);
    /** Translation lookup for `Logs_Errors_Date`. */
    Logs_Errors_Date: t = (a = []) => this.t('Logs_Errors_Date', a);
    /** Translation lookup for `Logs_Errors_Search`. */
    Logs_Errors_Search: t = (a = []) => this.t('Logs_Errors_Search', a);
    /** Translation lookup for `Logs_Errors_Name`. */
    Logs_Errors_Name: t = (a = []) => this.t('Logs_Errors_Name', a);
    /** Translation lookup for `Logs_Errors_Message`. */
    Logs_Errors_Message: t = (a = []) => this.t('Logs_Errors_Message', a);
    /** Translation lookup for `Logs_Errors_File`. */
    Logs_Errors_File: t = (a = []) => this.t('Logs_Errors_File', a);
    /** Translation lookup for `CronLog_Date`. */
    CronLog_Date: t = (a = []) => this.t('CronLog_Date', a);
    /** Translation lookup for `CronLog_Task`. */
    CronLog_Task: t = (a = []) => this.t('CronLog_Task', a);
    /** Translation lookup for `CronLog_Status`. */
    CronLog_Status: t = (a = []) => this.t('CronLog_Status', a);
    /** Translation lookup for `CronLog_Duration`. */
    CronLog_Duration: t = (a = []) => this.t('CronLog_Duration', a);
    /** Translation lookup for `CronLog_Output`. */
    CronLog_Output: t = (a = []) => this.t('CronLog_Output', a);
    /** Translation lookup for `CronLog_Error`. */
    CronLog_Error: t = (a = []) => this.t('CronLog_Error', a);
    /** Translation lookup for `CronLog_Empty`. */
    CronLog_Empty: t = (a = []) => this.t('CronLog_Empty', a);
    /** Translation lookup for `CronLog_Filter_Task`. */
    CronLog_Filter_Task: t = (a = []) => this.t('CronLog_Filter_Task', a);
    /** Translation lookup for `CronLog_Filter_All`. */
    CronLog_Filter_All: t = (a = []) => this.t('CronLog_Filter_All', a);
    /** Translation lookup for `JsErrorLog_LastSeen`. */
    JsErrorLog_LastSeen: t = (a = []) => this.t('JsErrorLog_LastSeen', a);
    /** Translation lookup for `JsErrorLog_Count`. */
    JsErrorLog_Count: t = (a = []) => this.t('JsErrorLog_Count', a);
    /** Translation lookup for `JsErrorLog_Message`. */
    JsErrorLog_Message: t = (a = []) => this.t('JsErrorLog_Message', a);
    /** Translation lookup for `JsErrorLog_File`. */
    JsErrorLog_File: t = (a = []) => this.t('JsErrorLog_File', a);
    /** Translation lookup for `JsErrorLog_Account`. */
    JsErrorLog_Account: t = (a = []) => this.t('JsErrorLog_Account', a);
    /** Translation lookup for `JsErrorLog_Stack`. */
    JsErrorLog_Stack: t = (a = []) => this.t('JsErrorLog_Stack', a);
    /** Translation lookup for `JsErrorLog_Url`. */
    JsErrorLog_Url: t = (a = []) => this.t('JsErrorLog_Url', a);
    /** Translation lookup for `JsErrorLog_UserAgent`. */
    JsErrorLog_UserAgent: t = (a = []) => this.t('JsErrorLog_UserAgent', a);
    /** Translation lookup for `JsErrorLog_Empty`. */
    JsErrorLog_Empty: t = (a = []) => this.t('JsErrorLog_Empty', a);
    /** Translation lookup for `JsErrorLog_Filter_File`. */
    JsErrorLog_Filter_File: t = (a = []) => this.t('JsErrorLog_Filter_File', a);
    /** Translation lookup for `JsErrorLog_Filter_Message`. */
    JsErrorLog_Filter_Message: t = (a = []) => this.t('JsErrorLog_Filter_Message', a);
    /** Translation lookup for `LogDetail_Title`. */
    LogDetail_Title: t = (a = []) => this.t('LogDetail_Title', a);
    /** Translation lookup for `LogDetail_Close`. */
    LogDetail_Close: t = (a = []) => this.t('LogDetail_Close', a);
    /** Translation lookup for `LogDetail_Id`. */
    LogDetail_Id: t = (a = []) => this.t('LogDetail_Id', a);
    /** Translation lookup for `LogDetail_RawJson`. */
    LogDetail_RawJson: t = (a = []) => this.t('LogDetail_RawJson', a);
    /** Translation lookup for `LogDetail_Meta`. */
    LogDetail_Meta: t = (a = []) => this.t('LogDetail_Meta', a);
    /** Translation lookup for `EntityHistory_Title`. */
    EntityHistory_Title: t = (a = []) => this.t('EntityHistory_Title', a);
    /** Translation lookup for `EntityHistory_Loading`. */
    EntityHistory_Loading: t = (a = []) => this.t('EntityHistory_Loading', a);
    /** Translation lookup for `EntityHistory_Empty`. */
    EntityHistory_Empty: t = (a = []) => this.t('EntityHistory_Empty', a);
    /** Translation lookup for `EntityHistory_When`. */
    EntityHistory_When: t = (a = []) => this.t('EntityHistory_When', a);
    /** Translation lookup for `EntityHistory_Actor`. */
    EntityHistory_Actor: t = (a = []) => this.t('EntityHistory_Actor', a);
    /** Translation lookup for `EntityHistory_Action`. */
    EntityHistory_Action: t = (a = []) => this.t('EntityHistory_Action', a);
    /** Translation lookup for `EntityHistory_Changes`. */
    EntityHistory_Changes: t = (a = []) => this.t('EntityHistory_Changes', a);
    /** Translation lookup for `EntityHistory_Close`. */
    EntityHistory_Close: t = (a = []) => this.t('EntityHistory_Close', a);
    /** Translation lookup for `EntityHistory_DefaultLabel`. */
    EntityHistory_DefaultLabel: t = (a = []) => this.t('EntityHistory_DefaultLabel', a);
    /** Translation lookup for `EntityHistory_Refresh`. */
    EntityHistory_Refresh: t = (a = []) => this.t('EntityHistory_Refresh', a);
    /** Translation lookup for `EntityHistory_Field`. */
    EntityHistory_Field: t = (a = []) => this.t('EntityHistory_Field', a);
    /** Translation lookup for `EntityHistory_Old`. */
    EntityHistory_Old: t = (a = []) => this.t('EntityHistory_Old', a);
    /** Translation lookup for `EntityHistory_New`. */
    EntityHistory_New: t = (a = []) => this.t('EntityHistory_New', a);
    /** Translation lookup for `EntityHistory_FieldsCount`. */
    EntityHistory_FieldsCount: t = (a = []) => this.t('EntityHistory_FieldsCount', a);
    /** Translation lookup for `AdminAction_user_approve`. */
    AdminAction_user_approve: t = (a = []) => this.t('AdminAction_user_approve', a);
    /** Translation lookup for `AdminAction_user_disable`. */
    AdminAction_user_disable: t = (a = []) => this.t('AdminAction_user_disable', a);
    /** Translation lookup for `AdminAction_user_flag_set`. */
    AdminAction_user_flag_set: t = (a = []) => this.t('AdminAction_user_flag_set', a);
    /** Translation lookup for `AdminAction_support_assign`. */
    AdminAction_support_assign: t = (a = []) => this.t('AdminAction_support_assign', a);
    /** Translation lookup for `AdminAction_support_status_change`. */
    AdminAction_support_status_change: t = (a = []) => this.t('AdminAction_support_status_change', a);
    /** Translation lookup for `AdminAction_balance_adjust`. */
    AdminAction_balance_adjust: t = (a = []) => this.t('AdminAction_balance_adjust', a);
    /** Translation lookup for `AdminAction_update_user`. */
    AdminAction_update_user: t = (a = []) => this.t('AdminAction_update_user', a);
    /** Translation lookup for `AdminAction_update`. */
    AdminAction_update: t = (a = []) => this.t('AdminAction_update', a);
    /** Translation lookup for `AdminAction_create`. */
    AdminAction_create: t = (a = []) => this.t('AdminAction_create', a);
    /** Translation lookup for `AdminAction_delete`. */
    AdminAction_delete: t = (a = []) => this.t('AdminAction_delete', a);
    /** Translation lookup for `AdminAction_COMMENT_HIDE`. */
    AdminAction_COMMENT_HIDE: t = (a = []) => this.t('AdminAction_COMMENT_HIDE', a);
    /** Translation lookup for `AdminAction_COMMENT_UNHIDE`. */
    AdminAction_COMMENT_UNHIDE: t = (a = []) => this.t('AdminAction_COMMENT_UNHIDE', a);
    /** Translation lookup for `AdminAction_COMMENT_APPROVE`. */
    AdminAction_COMMENT_APPROVE: t = (a = []) => this.t('AdminAction_COMMENT_APPROVE', a);
    /** Translation lookup for `AdminAction_COMMENT_FLAG`. */
    AdminAction_COMMENT_FLAG: t = (a = []) => this.t('AdminAction_COMMENT_FLAG', a);
    /** Translation lookup for `AdminAction_COMMENT_REJECT`. */
    AdminAction_COMMENT_REJECT: t = (a = []) => this.t('AdminAction_COMMENT_REJECT', a);
    /** Translation lookup for `AdminAction_IS_ADMIN`. */
    AdminAction_IS_ADMIN: t = (a = []) => this.t('AdminAction_IS_ADMIN', a);
    /** Translation lookup for `AdminAction_IS_OWNER`. */
    AdminAction_IS_OWNER: t = (a = []) => this.t('AdminAction_IS_OWNER', a);
    /** Translation lookup for `AdminAction_IS_MODERATOR`. */
    AdminAction_IS_MODERATOR: t = (a = []) => this.t('AdminAction_IS_MODERATOR', a);
    /** Translation lookup for `AdminAction_IS_APPROVED`. */
    AdminAction_IS_APPROVED: t = (a = []) => this.t('AdminAction_IS_APPROVED', a);
    /** Translation lookup for `AdminAction_IS_DISABLED`. */
    AdminAction_IS_DISABLED: t = (a = []) => this.t('AdminAction_IS_DISABLED', a);
    /** Translation lookup for `AdminAction_set_type`. */
    AdminAction_set_type: t = (a = []) => this.t('AdminAction_set_type', a);
    /** Translation lookup for `AdminAction_disable`. */
    AdminAction_disable: t = (a = []) => this.t('AdminAction_disable', a);
    /** Translation lookup for `AdminAction_enable`. */
    AdminAction_enable: t = (a = []) => this.t('AdminAction_enable', a);
    /** Translation lookup for `AdminAction_remove_photo`. */
    AdminAction_remove_photo: t = (a = []) => this.t('AdminAction_remove_photo', a);
    /** Translation lookup for `AdminAction_clear_user`. */
    AdminAction_clear_user: t = (a = []) => this.t('AdminAction_clear_user', a);
    /** Translation lookup for `Grid_Search`. */
    Grid_Search: t = (a = []) => this.t('Grid_Search', a);
    /** Translation lookup for `Grid_PrevPage`. */
    Grid_PrevPage: t = (a = []) => this.t('Grid_PrevPage', a);
    /** Translation lookup for `Grid_NextPage`. */
    Grid_NextPage: t = (a = []) => this.t('Grid_NextPage', a);
    /** Translation lookup for `Grid_Items`. */
    Grid_Items: t = (a = []) => this.t('Grid_Items', a);
    /** Translation lookup for `Pagination_Prev`. */
    Pagination_Prev: t = (a = []) => this.t('Pagination_Prev', a);
    /** Translation lookup for `Pagination_Next`. */
    Pagination_Next: t = (a = []) => this.t('Pagination_Next', a);
    /** Translation lookup for `Pagination_Of`. */
    Pagination_Of: t = (a = []) => this.t('Pagination_Of', a);
    /** Translation lookup for `Pagination_Items`. */
    Pagination_Items: t = (a = []) => this.t('Pagination_Items', a);
    /** Translation lookup for `Grid_NoData`. */
    Grid_NoData: t = (a = []) => this.t('Grid_NoData', a);
    /** Translation lookup for `Grid_PerPage`. */
    Grid_PerPage: t = (a = []) => this.t('Grid_PerPage', a);
    /** Translation lookup for `Tz_Info`. */
    Tz_Info: t = (a = []) => this.t('Tz_Info', a);
    /** Translation lookup for `Tz_Mismatch_Title`. */
    Tz_Mismatch_Title: t = (a = []) => this.t('Tz_Mismatch_Title', a);
    /** Translation lookup for `Tz_Mismatch_Browser`. */
    Tz_Mismatch_Browser: t = (a = []) => this.t('Tz_Mismatch_Browser', a);
    /** Translation lookup for `Tz_Mismatch_Profile`. */
    Tz_Mismatch_Profile: t = (a = []) => this.t('Tz_Mismatch_Profile', a);
    /** Translation lookup for `Tz_Mismatch_Diff`. */
    Tz_Mismatch_Diff: t = (a = []) => this.t('Tz_Mismatch_Diff', a);
    /** Translation lookup for `Tz_Mismatch_Footer`. */
    Tz_Mismatch_Footer: t = (a = []) => this.t('Tz_Mismatch_Footer', a);
    /** Translation lookup for `Tz_Mismatch_BrowserLabel`. */
    Tz_Mismatch_BrowserLabel: t = (a = []) => this.t('Tz_Mismatch_BrowserLabel', a);
    /** Translation lookup for `Tz_Mismatch_ProfileLabel`. */
    Tz_Mismatch_ProfileLabel: t = (a = []) => this.t('Tz_Mismatch_ProfileLabel', a);
    /** Translation lookup for `Tz_Diff_Hours`. */
    Tz_Diff_Hours: t = (a = []) => this.t('Tz_Diff_Hours', a);
    /** Translation lookup for `Tz_Diff_HoursMinutes`. */
    Tz_Diff_HoursMinutes: t = (a = []) => this.t('Tz_Diff_HoursMinutes', a);
    /** Translation lookup for `Tz_Diff_Ahead`. */
    Tz_Diff_Ahead: t = (a = []) => this.t('Tz_Diff_Ahead', a);
    /** Translation lookup for `Tz_Diff_Behind`. */
    Tz_Diff_Behind: t = (a = []) => this.t('Tz_Diff_Behind', a);
    /** Translation lookup for `Tz_Diff_Same`. */
    Tz_Diff_Same: t = (a = []) => this.t('Tz_Diff_Same', a);
    /** Translation lookup for `Consent_PD`. */
    Consent_PD: t = (a = []) => this.t('Consent_PD', a);
    /** Translation lookup for `Consent_PD_Required`. */
    Consent_PD_Required: t = (a = []) => this.t('Consent_PD_Required', a);
    /** Translation lookup for `Consent_Marketing`. */
    Consent_Marketing: t = (a = []) => this.t('Consent_Marketing', a);
    /** Translation lookup for `Consent_PD_Required_Error`. */
    Consent_PD_Required_Error: t = (a = []) => this.t('Consent_PD_Required_Error', a);
    /** Translation lookup for `StaticPages_Nav_MenuToggle`. */
    StaticPages_Nav_MenuToggle: t = (a = []) => this.t('StaticPages_Nav_MenuToggle', a);
    /** Translation lookup for `StaticPages_ThemeToggle`. */
    StaticPages_ThemeToggle: t = (a = []) => this.t('StaticPages_ThemeToggle', a);
    /** Translation lookup for `StaticPages_NotFound_Title`. */
    StaticPages_NotFound_Title: t = (a = []) => this.t('StaticPages_NotFound_Title', a);
    /** Translation lookup for `StaticPages_NotFound_Text`. */
    StaticPages_NotFound_Text: t = (a = []) => this.t('StaticPages_NotFound_Text', a);
}

/** Active translations for this bundle. */
export const I18nFramework = new I18n([I18nDataEN, I18nDataRU]);
