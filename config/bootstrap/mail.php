<?php
declare(strict_types=1);

function lex_mail_error(?string $message = null): ?string
{
    if ($message !== null) {
        $GLOBALS['lexLastMailError'] = $message;
    }
    return $GLOBALS['lexLastMailError'] ?? null;
}

function lex_smtp_send(string $to, string $subject, string $body): bool
{
    $host = lex_site_setting('smtp_host');
    $port = (int) lex_site_setting('smtp_port', '587');
    $user = lex_site_setting('smtp_user');
    $pass = lex_site_setting('smtp_pass');
    if ($host === '') {
        lex_mail_error('SMTP host is missing.');
        return false;
    }

    $secure = $port === 465 ? 'ssl://' : '';
    $remote = $secure . $host . ':' . $port;
    $stream = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
    if (!$stream) {
        lex_mail_error('Unable to connect to the SMTP server: ' . ($errstr ?: 'connection failed') . '.');
        return false;
    }
    stream_set_timeout($stream, 20);

    $read = static function ($stream): string {
        $data = '';
        while (($line = fgets($stream, 515)) !== false) {
            $data .= $line;
            if (preg_match('/^\d{3}\s/', $line)) {
                break;
            }
        }
        return $data;
    };
    $write = static function ($stream, string $command): void {
        fwrite($stream, $command . "\r\n");
    };
    $expect = static function (string $response, array $codes): bool {
        foreach ($codes as $code) {
            if (str_starts_with($response, (string) $code)) {
                return true;
            }
        }
        return false;
    };
    $formatResponse = static function (string $response): string {
        $response = trim(preg_replace('/\s+/', ' ', $response));
        return $response !== '' ? $response : 'no response';
    };

    $greeting = $read($stream);
    if (!$expect($greeting, [220])) {
        lex_mail_error('SMTP server did not return a valid greeting.');
        fclose($stream);
        return false;
    }

    $hostname = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $write($stream, "EHLO {$hostname}");
    $response = $read($stream);
    if (!$expect($response, [250])) {
        $write($stream, "HELO {$hostname}");
        $response = $read($stream);
        if (!$expect($response, [250])) {
            lex_mail_error('SMTP handshake failed.');
            fclose($stream);
            return false;
        }
    }

    if ($port !== 465 && str_contains($response, 'STARTTLS')) {
        $write($stream, 'STARTTLS');
        $response = $read($stream);
        if (!$expect($response, [220])) {
            lex_mail_error('SMTP server rejected STARTTLS.');
            fclose($stream);
            return false;
        }
        if (!stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            lex_mail_error('Could not establish a TLS connection to the SMTP server.');
            fclose($stream);
            return false;
        }
        $write($stream, "EHLO {$hostname}");
        $response = $read($stream);
        if (!$expect($response, [250])) {
            lex_mail_error('SMTP server rejected the secure handshake.');
            fclose($stream);
            return false;
        }
    }

    if ($user !== '') {
        $write($stream, 'AUTH LOGIN');
        $response = $read($stream);
        if (!$expect($response, [334])) {
            lex_mail_error('SMTP server did not accept AUTH LOGIN.');
            error_log('[SMTP] AUTH LOGIN rejected: ' . $formatResponse($response));
            fclose($stream);
            return false;
        }
        $write($stream, base64_encode($user));
        $response = $read($stream);
        if (!$expect($response, [334])) {
            $message = 'SMTP username was rejected by the server. Server replied: ' . $formatResponse($response) . '.';
            lex_mail_error($message);
            error_log('[SMTP] Username rejected: ' . $formatResponse($response));
            fclose($stream);
            return false;
        }
        $write($stream, base64_encode($pass));
        $response = $read($stream);
        if (!$expect($response, [235])) {
            $message = 'SMTP password was rejected by the server. Server replied: ' . $formatResponse($response) . '.';
            lex_mail_error($message);
            error_log('[SMTP] Password rejected: ' . $formatResponse($response));
            fclose($stream);
            return false;
        }
    }

    $from = $user !== '' ? $user : ('no-reply@' . ($hostname ?: 'localhost'));
    $fromName = lex_site_setting('site_name', LEX_APP_NAME);
    $messageId = sprintf('<%s@%s>', bin2hex(random_bytes(8)), preg_replace('/[^A-Za-z0-9\.\-]/', '', $hostname) ?: 'localhost');
    $headers = [
        'Date: ' . gmdate('D, d M Y H:i:s O'),
        'From: ' . $fromName . ' <' . $from . '>',
        'To: <' . $to . '>',
        'Subject: ' . $subject,
        'Message-ID: ' . $messageId,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    $write($stream, 'MAIL FROM:<' . $from . '>');
    if (!$expect($read($stream), [250])) {
        lex_mail_error('SMTP server rejected the sender address.');
        fclose($stream);
        return false;
    }
    $write($stream, 'RCPT TO:<' . $to . '>');
    if (!$expect($read($stream), [250, 251])) {
        lex_mail_error('SMTP server rejected the recipient address.');
        fclose($stream);
        return false;
    }
    $write($stream, 'DATA');
    if (!$expect($read($stream), [354])) {
        lex_mail_error('SMTP server did not accept the message body.');
        fclose($stream);
        return false;
    }

    $message = implode("\r\n", $headers) . "\r\n\r\n" . preg_replace("/\r?\n/", "\r\n", $body);
    $message = preg_replace('/^\./m', '..', $message);
    $write($stream, $message . "\r\n.");
    if (!$expect($read($stream), [250])) {
        lex_mail_error('SMTP server rejected the email after DATA.');
        fclose($stream);
        return false;
    }

    $write($stream, 'QUIT');
    fclose($stream);
    return true;
}

function lex_send_email(string $to, string $subject, string $body): bool
{
    lex_mail_error(null);
    if (lex_smtp_send($to, $subject, $body)) {
        return true;
    }
    $from = lex_site_setting('smtp_user');
    $headers = [
        'From: ' . ($from !== '' ? $from : (LEX_APP_NAME . ' <no-reply@localhost>')),
        'Reply-To: ' . ($from !== '' ? $from : 'no-reply@localhost'),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    if (@mail($to, $subject, $body, implode("\r\n", $headers))) {
        return true;
    }
    if (!lex_mail_error()) {
        lex_mail_error('The local mail function failed.');
    }
    return false;
}
