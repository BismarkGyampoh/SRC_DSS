<?php

class EmailService
{
    private string $smtpHost;
    private int $smtpPort;
    private string $smtpUsername;
    private string $smtpPassword;
    private string $fromEmail;
    private string $fromName;
    private bool $enabled;

    public function __construct()
    {
        $this->smtpHost = (string) ($_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: '127.0.0.1');
        $this->smtpPort = (int) ($_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: 25);
        $this->smtpUsername = (string) ($_ENV['SMTP_USERNAME'] ?? getenv('SMTP_USERNAME') ?: '');
        $this->smtpPassword = (string) ($_ENV['SMTP_PASSWORD'] ?? getenv('SMTP_PASSWORD') ?: '');
        $this->fromEmail = (string) ($_ENV['SMTP_FROM_EMAIL'] ?? getenv('SMTP_FROM_EMAIL') ?: 'noreply@umat.edu.gh');
        $this->fromName = (string) ($_ENV['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: 'UMaT SRC DSS');
        $this->enabled = (bool) ($_ENV['SMTP_ENABLED'] ?? getenv('SMTP_ENABLED') ?: false);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function send(string $to, string $subject, string $body, string $toName = ''): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $toHeader = $toName !== '' ? $toName . ' <' . $to . '>' : $to;

        if (!empty($this->smtpHost) && !empty($this->smtpPort)) {
            return $this->sendViaSmtp($to, $toHeader, $subject, $body);
        }

        $subjectHeader = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = [];
        $headers[] = 'From: ' . $this->fromName . ' <' . $this->fromEmail . '>';
        $headers[] = 'Reply-To: ' . $this->fromEmail;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'X-Mailer: PHP/' . phpversion();

        $headerString = implode("\r\n", $headers);

        $fullBody = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</title></head><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">' . $body . '</body></html>';

        return mail($toHeader, $subjectHeader, $fullBody, $headerString);
    }

    private function sendViaSmtp(string $to, string $toHeader, string $subject, string $body): bool
    {
        $host = $this->smtpHost;
        $port = (int) $this->smtpPort;
        $username = $this->smtpUsername;
        $password = $this->smtpPassword;
        $fromEmail = $this->fromEmail;
        $fromName = $this->fromName;

        $crlf = "\r\n";
        $subjectHeader = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $headers = [];
        $headers[] = 'From: ' . $this->fromName . ' <' . $this->fromEmail . '>';
        $headers[] = 'Reply-To: ' . $this->fromEmail;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'X-Mailer: PHP/' . phpversion();

        $headerString = implode($crlf, $headers);
        $fullBody = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</title></head><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">' . $body . '</body></html>';

        $timeout = (int) ($_ENV['SMTP_TIMEOUT'] ?? getenv('SMTP_TIMEOUT') ?: 10);

        $connectString = ($port === 465 ? 'ssl://' : '') . $host . ':' . $port;

        $socket = @fsockopen($connectString, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            return false;
        }

        stream_set_timeout($socket, $timeout);

        $this->smtpRead($socket);

        $this->smtpWrite($socket, 'EHLO ' . ($host ?: 'localhost') . $crlf);
        $this->smtpRead($socket);

        if (!empty($username) && !empty($password)) {
            $this->smtpWrite($socket, 'AUTH LOGIN' . $crlf);
            $this->smtpRead($socket);

            $this->smtpWrite($socket, base64_encode($username) . $crlf);
            $this->smtpRead($socket);

            $this->smtpWrite($socket, base64_encode($password) . $crlf);
            $this->smtpRead($socket);
        }

        $this->smtpWrite($socket, 'MAIL FROM: <' . $fromEmail . '>' . $crlf);
        $this->smtpRead($socket);

        $this->smtpWrite($socket, 'RCPT TO: <' . $to . '>' . $crlf);
        $response = $this->smtpRead($socket);

        if (strpos($response, '250') !== 0 && strpos($response, '251') !== 0) {
            fclose($socket);
            return false;
        }

        $this->smtpWrite($socket, 'DATA' . $crlf);
        $this->smtpRead($socket);

        $data = 'To: ' . $toHeader . $crlf;
        $data .= 'Subject: ' . $subjectHeader . $crlf;
        $data .= $headerString . $crlf . $crlf;
        $data .= $fullBody . $crlf . '.' . $crlf;

        $this->smtpWrite($socket, $data);
        $this->smtpRead($socket);

        $this->smtpWrite($socket, 'QUIT' . $crlf);
        fclose($socket);

        return true;
    }

    private function smtpWrite($socket, string $string): void
    {
        fwrite($socket, $string);
    }

    private function smtpRead($socket): string
    {
        $data = '';
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            if ($line === false) {
                break;
            }
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        return trim($data);
    }

    public static function createFromDbConfig(PDO $pdo): self
    {
        $instance = new self();
        try {
            // system_config is a single-row table with named columns
            // (active_academic_year, maintenance_mode). It does not store
            // SMTP credentials, so SMTP settings continue to come from the
            // environment / local config read in the constructor. We query
            // the real shape only to confirm the row exists and stay in sync
            // with the actual schema instead of the old key/value design.
            $stmt = $pdo->query('SELECT active_academic_year, maintenance_mode FROM system_config ORDER BY config_id DESC LIMIT 1');
            $stmt->fetch();
        } catch (PDOException $e) {
            // system_config may not exist yet — leave SMTP defaults from constructor
        }
        return $instance;
    }
}
