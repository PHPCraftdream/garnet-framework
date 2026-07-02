# Email template — usage example

Framework email templates live under `Bundle/TwigTemplates/Email/*.twig`.
The entry template is **`Email/Email.twig`**; the helpers below render the
secondary blocks (`Email/Row.twig`, `Email/ButtonMain.twig`,
`Email/Value.twig`).

The framework is i18n-agnostic — your application supplies the rendered
strings in whichever language(s) it ships. Two parallel examples follow:
the first uses English copy, the second uses the Russian copy that the
bundled `Example` reference app actually sends.

## Example 1 — English copy (template: `Email/Email.twig`)

``` PHP
    $twig = TwigInit::get(IniConfig::ENV_TWIG_SYSTEM);

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
                    'We strive to give you the best experience and to help you whenever needed. '.
                        'If you have any questions, please reach out to our support team. '.
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

    $render = HtmlMinify::get()->minify($render);
```

## Example 2 — Russian copy (template: `Email/Email.twig`)

The same template — only the user-facing copy changes. This is what the
`Example` reference app produces from
`Apps/Example/Common/Services/EmailNotifications.php`.

``` PHP
    $twig = TwigInit::get(IniConfig::ENV_TWIG_SYSTEM);

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
                'title' => 'Регистрация успешно завершена',
                'rows' => [
                    'Добро пожаловать на наш сайт! Мы очень рады приветствовать вас в нашем сообществе.',
                    'Мы стремимся предоставить вам лучший опыт использования нашего сайта и помочь вам. '.
                        'Если у вас возникнут вопросы, не стесняйтесь обращаться к нашей команде поддержки. '.
                        'Мы всегда готовы помочь вам.',
                    'Желаем вам приятного времяпрепровождения на нашем сайте!',
                    'С наилучшими пожеланиями, команда сайта.',
                    ['raw' => $row($button('Авторизоваться', 'https://site.example/~auth/code~jbhgcgvc'), 'center')],
                    $val($dangerString),
                ],
            ],
        ],
        'bottom' => '&copy; 2023 See Twig integration for better HTML integration!'
    ]);

    $render = HtmlMinify::get()->minify($render);
```
