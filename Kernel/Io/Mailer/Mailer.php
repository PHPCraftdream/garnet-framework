<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Mailer {
    use PHPCraftdream\Garnet\Kernel\Exceptions\IniConfigException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IMailer;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
    use Symfony\Component\Mailer\Mailer as SymfonyMailer;
    use Symfony\Component\Mailer\MailerInterface as ISymfonyMailer;
    use Symfony\Component\Mailer\Transport\Dsn as SymfonyDsnObj;
    use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory as SymfonySmtpFactory;
    use Symfony\Component\Mime\Email as SymfonyEmail;

    class Mailer implements IMailer {
        protected static ?IMailer $instance = null;

        protected function __construct(protected ISymfonyMailer $mailer) {
        }

        public static function reset(): void {
            static::$instance = null;
        }

        public static function setInstance(IMailer $mailer): void {
            static::$instance = $mailer;
        }

        public static function get(): IMailer {
            if (empty(static::$instance)) {
                $config = IniConfig::email();

                $dsnObj = new SymfonyDsnObj(
                    $config->paramString('scheme', 'smtp'),
                    $config->paramString('host'),
                    $config->paramString('user'),
                    $config->paramString('password'),
                    $config->paramInt('port', 465),
                    ['verify_peer' => $config->paramBool('verify_peer', false)]
                );

                $transport = (new SymfonySmtpFactory())->create($dsnObj);
                $mailer = new SymfonyMailer($transport);

                static::$instance = new static($mailer);
            }

            return static::$instance;
        }

        /**
         * @param string $to
         * @param string $subject
         * @param string $htmlMessage
         * @return void
         * @throws TransportExceptionInterface
         * @throws IniConfigException
         */
        public function sendHtmlMail(string $to, string $subject, string $htmlMessage): void {
            $config = IniConfig::email();

            if (abs($config->paramInt('enabled')) < 1) {
                return;
            }

            $email = (new SymfonyEmail())
                ->from($config->paramString('from'))
                ->to($to)
                ->subject($subject)
                ->html($htmlMessage);

            $this->mailer->send($email);
        }
    }
}
