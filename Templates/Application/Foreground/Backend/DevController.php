<?php declare(strict_types=1);

namespace PHPCraftdream\Application\Foreground\Backend {
    use PHPCraftdream\Garnet\Bundle\I18n\FwI18n;
    use PHPCraftdream\Garnet\Bundle\Modules\Auth\Middlewares\AuthMiddleware;
    use PHPCraftdream\Garnet\Kernel\Core\FrameworkController;
    use PHPCraftdream\Garnet\Kernel\Core\Tools\StrTools;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\Session;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Settings\Settings;
    use PHPCraftdream\Garnet\Kernel\Exceptions\CommonException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\HtmlMinify\HtmlMinify;
    use PHPCraftdream\Garnet\Kernel\Io\IoTools;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\Twig;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\TwigParams;

    class DevController extends FrameworkController {
        public static function get__main(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            return ControllerTools::ok(IoTools::pr($params));
        }

        public static function get__who(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            return ControllerTools::ok(IoTools::pr($params));
        }

        public static function get__ok(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $settings = Settings::get();
            $val = $settings->getValue('enabled');
            $settings->setValue('enabled', 'true');
            $settings->setValue('pageSize', '15');
            $settings->unsetValue('pageSize');

            $session = Session::get();
            $session->readDataAsyncPollFinishAll();

            $session->setValue('a', StrTools::randomString());
            $session->setValue('b', StrTools::randomString());
            $session->setValue('c', StrTools::randomString());
            $session->setValue('d', StrTools::randomString());

            $session->unsetValue('a');
            // $session->unsetValue('b');
            // $session->unsetValue('c');
            $session->unsetValue('d');

            return ControllerTools::ok('Ok, World!' .
                IoTools::pr("--{$val}--") .
                IoTools::pr($session->getToken()) .
                IoTools::pr($params) .
                IoTools::pr($globals->readServerAll()) .
                IoTools::pr(Employees::get()->selectPage(1))
            );
        }

        public static function get__ex(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            throw new CommonException('A custom error has been triggered');
        }

        public static function get__test_send_email(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $twig = Twig::get();

            $row = fn (string $str, string $align = 'left') => $twig->render('Email/Row.twig', ['row' => $str, 'align' => $align]);
            $button = fn (string $text, string $href) => $twig->render('Email/ButtonMain.twig', ['text' => $text, 'href' => $href]);
            $val = fn (string $v) => $twig->render('Email/Value.twig', ['val' => $v]);

            $dangerString = '<hello>';

            $render = $twig->render('Email/Email.twig', [
                'content_align' => 'left',
                'head_align' => 'left',
                'block_title_align' => 'left',
                'bottom_align' => 'center',
                'page_title' => 'Twig email template',
                'head' => 'HH visor',
                'info_blocks' => [
                    [
                        'title' => 'Registration complete',
                        'rows' => [
                            'Welcome to our site! We are delighted to have you in our community.',
                            'We strive to give you the best experience and to help you whenever needed. ' .
                            'If you have any questions, please reach out to our support team. ' .
                            'We are always glad to assist.',
                            'Have a great time on our site!',
                            'Best regards, the site team.',
                            ['raw' => $row($button('Sign in', 'https://site.example/~auth/code~jbhgcgvc'), 'center')],
                            $val($dangerString),
                        ],
                    ],
                ],
                'bottom' => '&copy; 2023 See Twig integration for better HTML integration!'
            ]);

            $render = $twig->render('Email/Email.twig', AuthMiddleware::authEmailParams($globals, StrTools::randomString(10)));

            $render = HtmlMinify::get()->minify($render);

            //            Mailer::get()->sendHtmlMail(
            //                'you@example.com',
            //                'Time for Symfony Mailer!',
            //                $render,
            //            );

            return ControllerTools::ok($render);
        }

        public static function get__test_send_email2(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $twig = Twig::get();

            $result = TwigParams::init()->get(TwigParams::DEF_EMAIL_PARAMS);
            $result['info_blocks'] = [
                [
                    'title' => FwI18n::t('Email_Auth_SuccessLogin_Title'),
                    'rows' => [
                        FwI18n::t('Email_Auth_SuccessLogin_A', [$globals->ip(), date('H:i')]),
                        FwI18n::t('Email_Auth_SuccessLogin_B'),
                    ],
                ],
            ];

            $render = $twig->render('Email/Email.twig', $result);
            $render = HtmlMinify::get()->minify($render);

            //            Mailer::get()->sendHtmlMail(
            //                'you@example.com',
            //                'Time for Symfony Mailer!',
            //                $render,
            //            );

            return ControllerTools::ok($render);
        }
    }
}
