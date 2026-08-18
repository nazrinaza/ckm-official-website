<?php
/**
 * CKM — Minimal SMTP Client for Zoho Mail
 * Self-contained, no external library needed.
 * Uses STARTTLS on port 587.
 *
 * Usage:
 *   $smtp = new CkmSmtp('smtp.zoho.com', 587, 'user@domain.com', 'password');
 *   $smtp->send('to@example.com', 'Subject', '<h1>HTML body</h1>', 'From Name');
 */
declare(strict_types=1);

class CkmSmtp
{
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private $socket   = null;
    private string $log = '';

    public function __construct(string $host, int $port, string $user, string $pass)
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
    }

    private function connect(): bool
    {
        $remote = ($this->port === 465) ? "ssl://{$this->host}:{$this->port}" : "{$this->host}:{$this->port}";
        $this->socket = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
        if (!$this->socket) {
            error_log("CKM SMTP connect error: {$errstr} ({$errno})");
            return false;
        }
        stream_set_timeout($this->socket, 15);
        return true;
    }

    private function read(): string
    {
        $data = '';
        while ($line = fgets($this->socket, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break; // Last line of response
        }
        $this->log .= "S: {$data}";
        return $data;
    }

    private function write(string $cmd): void
    {
        $this->log .= "C: {$cmd}\n";
        fputs($this->socket, $cmd . "\r\n");
    }

    private function expect(string $code): bool
    {
        $resp = $this->read();
        return str_starts_with($resp, $code);
    }

    private function authPlain(): bool
    {
        $auth = base64_encode("\0{$this->user}\0{$this->pass}");
        $this->write("AUTH PLAIN {$auth}");
        return $this->expect('235');
    }

    private function authLogin(): bool
    {
        $this->write('AUTH LOGIN');
        if (!$this->expect('334')) return false;
        $this->write(base64_encode($this->user));
        if (!$this->expect('334')) return false;
        $this->write(base64_encode($this->pass));
        return $this->expect('235');
    }

    public function send(string $to, string $subject, string $htmlBody, string $fromName = '', ?string $replyTo = null): bool
    {
        if (!$this->connect()) return false;

        // EHLO
        $this->write('EHLO ckm');
        if (!$this->expect('250')) { $this->close(); return false; }

        // STARTTLS (port 587)
        if ($this->port === 587) {
            $this->write('STARTTLS');
            if (!$this->expect('220')) { $this->close(); return false; }
            stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->write('EHLO ckm');
            if (!$this->expect('250')) { $this->close(); return false; }
        }

        // AUTH
        if (!$this->authPlain()) {
            if (!$this->authLogin()) { $this->close(); return false; }
        }

        // MAIL FROM
        $this->write("MAIL FROM: <{$this->user}>");
        if (!$this->expect('250')) { $this->close(); return false; }

        // RCPT TO
        $this->write("RCPT TO: <{$to}>");
        if (!$this->expect('250')) { $this->close(); return false; }

        // DATA
        $this->write('DATA');
        if (!$this->expect('354')) { $this->close(); return false; }

        // Build headers
        $fromName   = $fromName !== '' ? $fromName : 'cucikarpetmasjid.com';
        $replyAddr  = $replyTo ?? $this->user;
        $boundary   = '----=_CKM_' . md5((string)time());
        $encodedSub = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $headers = [
            "From: {$fromName} <{$this->user}>",
            "To: {$to}",
            "Subject: {$encodedSub}",
            "Reply-To: {$fromName} <{$replyAddr}>",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "Date: " . date(DATE_RFC2822),
            "Message-ID: <" . md5(uniqid('', true)) . "@cucikarpetmasjid.com>",
            "X-Mailer: CKM-ZohoSMTP/1.0",
        ];

        $body = "--{$boundary}\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode(strip_tags($htmlBody))) . "\r\n"
              . "--{$boundary}\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($htmlBody)) . "\r\n"
              . "--{$boundary}--\r\n";

        fputs($this->socket, implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n");
        $ok = $this->expect('250');

        $this->write('QUIT');
        $this->close();

        if (!$ok) {
            error_log("CKM SMTP send failed. Log:\n" . $this->log);
        }
        return $ok;
    }

    private function close(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    public function getLog(): string
    {
        return $this->log;
    }
}
