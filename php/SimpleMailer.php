<?php
/**
 * SimpleMailer — Pure PHP Gmail SMTP mailer
 * Works on XAMPP with no Composer / PHPMailer installation.
 * Requires: allow_url_fopen = On, OpenSSL extension enabled (default on XAMPP).
 */
class SimpleMailer
{
    private string $host     = 'smtp.gmail.com';
    private int    $port     = 587;
    private string $username;
    private string $password;
    private string $fromEmail;
    private string $fromName;

    /** @var resource */
    private $socket;

    public function __construct(string $username, string $password, string $fromName = 'AI System')
    {
        $this->username  = $username;
        $this->password  = $password;
        $this->fromEmail = $username;
        $this->fromName  = $fromName;
    }

    // ── Low-level helpers ───────────────────────────────────────────────────

    private function write(string $cmd): string
    {
        fwrite($this->socket, $cmd . "\r\n");
        return $this->read();
    }

    private function read(): string
    {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            if ($line[3] === ' ') break;   // last response line has space after code
        }
        return $response;
    }

    private function expect(string $response, string $code): void
    {
        if (strncmp($response, $code, strlen($code)) !== 0) {
            throw new RuntimeException("SMTP error (expected $code): $response");
        }
    }

    // ── Public send method ──────────────────────────────────────────────────

    /**
     * @param string $toEmail  Recipient e-mail address
     * @param string $toName   Recipient display name
     * @param string $subject  Email subject
     * @param string $htmlBody Full HTML email body
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody): void
    {
        // 1. Open TCP connection (plain, then upgrade to TLS)
        $errno  = 0;
        $errstr = '';
        $this->socket = @fsockopen('tcp://' . $this->host, $this->port, $errno, $errstr, 30);
        if (!$this->socket) {
            throw new RuntimeException("Cannot connect to {$this->host}:{$this->port} — $errstr ($errno)");
        }

        stream_set_timeout($this->socket, 30);

        // 2. SMTP handshake
        $this->expect($this->read(), '220');                 // server greeting
        $this->expect($this->write("EHLO localhost"), '250');
        $this->expect($this->write("STARTTLS"), '220');

        // 3. Upgrade to TLS
        if (!stream_socket_enable_crypto(
            $this->socket, true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
        )) {
            throw new RuntimeException("TLS negotiation failed.");
        }

        // 4. Re-identify after TLS
        $this->expect($this->write("EHLO localhost"), '250');

        // 5. Authenticate (AUTH LOGIN)
        $this->expect($this->write("AUTH LOGIN"), '334');
        $this->expect($this->write(base64_encode($this->username)), '334');
        $authResp = $this->write(base64_encode($this->password));
        $this->expect($authResp, '235');

        // 6. Envelope
        $this->expect($this->write("MAIL FROM:<{$this->fromEmail}>"), '250');
        $this->expect($this->write("RCPT TO:<{$toEmail}>"), '250');
        $this->expect($this->write("DATA"), '354');

        // 7. Build RFC-2822 message with HTML body
        $safeFromName = $this->mimeEncode($this->fromName);
        $safeToName   = $this->mimeEncode($toName);
        $safeSubject  = $this->mimeEncode($subject);
        $date         = date('r');

        $message  = "Date: $date\r\n";
        $message .= "From: $safeFromName <{$this->fromEmail}>\r\n";
        $message .= "To: $safeToName <{$toEmail}>\r\n";
        $message .= "Subject: $safeSubject\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= "\r\n";
        $message .= chunk_split(base64_encode($htmlBody));
        $message .= "\r\n.";                                 // end-of-data marker

        $this->expect($this->write($message), '250');

        // 8. Goodbye
        $this->write("QUIT");
        fclose($this->socket);
    }

    /** Encode a header value as UTF-8 base64 per RFC 2047 */
    private function mimeEncode(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
