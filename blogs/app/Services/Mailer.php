<?php

declare(strict_types=1);

namespace BlogFlow\Services;

use BlogFlow\Core\App;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sends plain-text transactional email via SMTP (when configured) or PHP mail().
 */
final class Mailer
{
    public function __construct(private App $app)
    {
    }

    public function send(string $to, string $subject, string $body): bool
    {
        $s = $this->app->settings;
        $mail = new PHPMailer(true);
        try {
            $mail->CharSet = 'UTF-8';
            $host = $s->str('integrations.smtp_host');
            if ($host !== '') {
                $mail->isSMTP();
                $mail->Host = $host;
                $mail->Port = $s->int('integrations.smtp_port') ?: 587;
                $secure = $s->str('integrations.smtp_secure');
                $mail->SMTPSecure = $secure === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : ($secure === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : '');
                $mail->SMTPAutoTLS = $secure !== 'none';
                if ($s->str('integrations.smtp_user') !== '') {
                    $mail->SMTPAuth = true;
                    $mail->Username = $s->str('integrations.smtp_user');
                    $mail->Password = $s->str('integrations.smtp_pass');
                }
                $mail->Timeout = 15;
            } else {
                $mail->isMail();
            }
            $host = (string) parse_url($this->app->url->base(), PHP_URL_HOST);
            $from = $s->str('integrations.mail_from_email') ?: 'no-reply@' . preg_replace('/^www\./', '', $host ?: 'localhost');
            $mail->setFrom($from, $s->str('integrations.mail_from_name') ?: ($s->str('general.blog_name') ?: 'BlogFlow'));
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML(false);
            return $mail->send();
        } catch (\Throwable $e) {
            \BlogFlow\Core\Logger::error('Mail failed: ' . $mail->ErrorInfo . ' ' . $e->getMessage());
            return false;
        }
    }
}
