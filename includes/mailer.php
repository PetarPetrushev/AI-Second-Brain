<?php
/**
 * includes/mailer.php
 * Native socket-based SSL SMTP client (RFC 5321 / 5322 compliant).
 * Uses stream_socket_client on port 465 — no PHPMailer, no external libs.
 */

require_once __DIR__ . '/../config.php';

// ═══════════════════════════════════════════════════════════════════════════════
// SMTP COMMAND HELPERS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Read the next SMTP response line(s) from the socket.
 * Handles multi-line responses (e.g. 250-... 250 OK).
 *
 * @param  resource $socket
 * @param  int      $expected  Expected 3-digit SMTP code
 * @return string              Full response text
 * @throws RuntimeException    If the response code doesn't match
 */
function smtp_read(mixed $socket, int $expected): string
{
    $response = '';
    while ($line = fgets($socket, 512)) {
        $response .= $line;
        // Multi-line response: "250-..." continues; "250 ..." ends
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    $code = (int) substr($response, 0, 3);
    if ($code !== $expected) {
        throw new RuntimeException("SMTP expected $expected but got $code: " . trim($response));
    }

    return trim($response);
}

/**
 * Send a single SMTP command string (appends CRLF automatically).
 *
 * @param  resource $socket
 * @param  string   $command
 */
function smtp_send(mixed $socket, string $command): void
{
    fwrite($socket, $command . "\r\n");
}

// ═══════════════════════════════════════════════════════════════════════════════
// MAIN MAILER
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Send an HTML email via native SSL SMTP (port 465).
 *
 * @param  string      $to           Recipient email address
 * @param  string      $subject      Email subject line
 * @param  string      $html_body    Full HTML email body
 * @param  array|null  $custom       Config overrides: host, port, user, pass, from, from_name
 * @return bool                      True on success
 * @throws RuntimeException          On any SMTP protocol / socket failure
 */
function send_smtp_ssl(
    string $to,
    string $subject,
    string $html_body,
    ?array $custom = null
): bool {
    $host      = $custom['host']      ?? get_config('SMTP_HOST');
    $port      = (int)($custom['port']      ?? get_config('SMTP_PORT', 465));
    $user      = $custom['user']      ?? get_config('SMTP_USER');
    $pass      = $custom['pass']      ?? get_config('SMTP_PASS');
    $from      = $custom['from']      ?? get_config('SMTP_FROM');
    $from_name = $custom['from_name'] ?? get_config('SMTP_FROM_NAME', 'Second Brain');

    // ── Open SSL socket ──────────────────────────────────────────────────────
    $context = stream_context_create([
        'ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'allow_self_signed' => false,
        ],
    ]);

    $socket = stream_socket_client(
        "ssl://{$host}:{$port}",
        $errno,
        $errstr,
        30,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$socket) {
        throw new RuntimeException("SMTP socket failed to connect to ssl://{$host}:{$port}: [$errno] $errstr");
    }

    stream_set_timeout($socket, 30);

    // ── RFC 5321 Command Sequence ─────────────────────────────────────────────
    smtp_read($socket, 220);                    // Server greeting

    smtp_send($socket, 'EHLO localhost');
    smtp_read($socket, 250);                    // EHLO accepted

    smtp_send($socket, 'AUTH LOGIN');
    smtp_read($socket, 334);                    // Username prompt

    smtp_send($socket, base64_encode($user));
    smtp_read($socket, 334);                    // Password prompt

    smtp_send($socket, base64_encode($pass));
    smtp_read($socket, 235);                    // Auth successful

    smtp_send($socket, "MAIL FROM:<{$from}>");
    smtp_read($socket, 250);                    // Sender accepted

    smtp_send($socket, "RCPT TO:<{$to}>");
    smtp_read($socket, 250);                    // Recipient accepted

    smtp_send($socket, 'DATA');
    smtp_read($socket, 354);                    // Start input

    // ── Build RFC 5322 Message Headers ───────────────────────────────────────
    $message_id  = '<' . uniqid('sb', true) . '@secondbrain.local>';
    $date_header = date('r');
    $encoded_subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $encoded_from = '=?UTF-8?B?' . base64_encode($from_name) . '?= <' . $from . '>';

    $headers  = "From: {$encoded_from}\r\n";
    $headers .= "To: <{$to}>\r\n";
    $headers .= "Subject: {$encoded_subj}\r\n";
    $headers .= "Date: {$date_header}\r\n";
    $headers .= "Message-ID: {$message_id}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: quoted-printable\r\n";
    $headers .= "\r\n";

    // Encode body as quoted-printable
    $qp_body = quoted_printable_encode($html_body);

    // Escape lone dots (RFC 5321 transparency)
    $body_escaped = preg_replace('/^\.$/m', '..', $qp_body);

    fwrite($socket, $headers . $body_escaped . "\r\n.\r\n");
    smtp_read($socket, 250);                    // Message accepted

    smtp_send($socket, 'QUIT');
    smtp_read($socket, 221);                    // Goodbye

    fclose($socket);
    return true;
}

// ═══════════════════════════════════════════════════════════════════════════════
// DIAGNOSTIC HELPER
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Test SMTP connectivity without sending a real email.
 * Connects, authenticates, then issues RSET and QUIT.
 *
 * @param  array|null $custom  Optional config override (same keys as send_smtp_ssl)
 * @return array{success: bool, message: string, steps: string[]}
 */
function test_smtp_connection(?array $custom = null): array
{
    $host = $custom['host'] ?? get_config('SMTP_HOST');
    $port = (int)($custom['port'] ?? get_config('SMTP_PORT', 465));
    $user = $custom['user'] ?? get_config('SMTP_USER');
    $pass = $custom['pass'] ?? get_config('SMTP_PASS');
    $steps = [];

    try {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
            ],
        ]);

        $socket = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            15,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            throw new RuntimeException("Socket connection failed: [$errno] $errstr");
        }

        stream_set_timeout($socket, 15);
        $steps[] = '✓ Socket connected to ssl://' . $host . ':' . $port;

        smtp_read($socket, 220);
        $steps[] = '✓ Received 220 greeting';

        smtp_send($socket, 'EHLO localhost');
        smtp_read($socket, 250);
        $steps[] = '✓ EHLO accepted';

        smtp_send($socket, 'AUTH LOGIN');
        smtp_read($socket, 334);
        smtp_send($socket, base64_encode($user));
        smtp_read($socket, 334);
        smtp_send($socket, base64_encode($pass));
        smtp_read($socket, 235);
        $steps[] = '✓ Authentication successful';

        smtp_send($socket, 'RSET');
        smtp_read($socket, 250);
        smtp_send($socket, 'QUIT');
        fclose($socket);
        $steps[] = '✓ Connection closed cleanly';

        return ['success' => true, 'message' => 'SMTP connection test passed.', 'steps' => $steps];

    } catch (Throwable $e) {
        $steps[] = '✗ ' . $e->getMessage();
        return ['success' => false, 'message' => $e->getMessage(), 'steps' => $steps];
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// EMAIL TEMPLATE HELPER
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Wrap plain text / markdown-ish digest content in a clean HTML email template.
 *
 * @param  string $date     YYYY-MM-DD
 * @param  string $content  Digest HTML content to embed
 * @return string           Full HTML email string
 */
function build_digest_email(string $date, string $content): string
{
    $formatted_date = date('l, F j, Y', strtotime($date));
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Second Brain Digest — {$formatted_date}</title>
<style>
  body{margin:0;padding:0;background:#0f0f13;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#e4e4e7;}
  .wrap{max-width:680px;margin:32px auto;background:#18181b;border-radius:12px;overflow:hidden;border:1px solid #27272a;}
  .header{padding:32px 36px;background:linear-gradient(135deg,#1e1e2e,#1a1a2e);border-bottom:1px solid #27272a;}
  .header h1{margin:0;font-size:22px;font-weight:600;color:#f4f4f5;}
  .header p{margin:6px 0 0;font-size:13px;color:#71717a;}
  .body{padding:32px 36px;}
  .body h2{font-size:16px;font-weight:600;color:#a1a1aa;text-transform:uppercase;letter-spacing:.06em;margin:28px 0 10px;padding-bottom:6px;border-bottom:1px solid #27272a;}
  .body h2:first-child{margin-top:0;}
  .body p{font-size:15px;line-height:1.7;color:#d4d4d8;margin:0 0 14px;}
  .body ul{margin:0 0 16px;padding-left:20px;}
  .body li{font-size:15px;line-height:1.7;color:#d4d4d8;margin-bottom:6px;}
  .body blockquote{margin:0 0 16px;padding:12px 16px;background:#1e1e2e;border-left:3px solid #6366f1;border-radius:4px;font-style:italic;color:#a1a1aa;}
  .footer{padding:20px 36px;background:#111113;border-top:1px solid #27272a;text-align:center;}
  .footer p{margin:0;font-size:12px;color:#52525b;}
  .tag{display:inline-block;padding:2px 8px;background:#1e1e2e;border:1px solid #3f3f46;border-radius:20px;font-size:11px;color:#a1a1aa;margin:0 4px 4px 0;}
  .sources-container{margin-top:12px;}
  .source-card{background:#1f1f28;border:1px solid #2e2e3e;border-radius:8px;padding:12px 14px;margin-bottom:10px;}
  .source-header{display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap;}
  .source-title{font-size:14px;font-weight:600;color:#f4f4f5;}
  .source-badge{display:inline-block;padding:1px 6px;border-radius:10px;font-size:10px;font-weight:600;text-transform:uppercase;}
  .source-badge.today{background:rgba(52,211,153,0.18);color:#34d399;}
  .source-badge.past{background:rgba(124,106,247,0.18);color:#a78bfa;}
  .source-date{font-size:11px;color:#71717a;margin-left:auto;}
  .source-snippet{font-size:13px;line-height:1.5;color:#a1a1aa;margin-bottom:6px;}
  .source-meta{font-size:11px;color:#71717a;}
  .source-id{font-family:monospace;font-size:10px;color:#52525b;}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1>🧠 Second Brain Daily Digest</h1>
    <p>{$formatted_date}</p>
  </div>
  <div class="body">
    {$content}
  </div>
  <div class="footer">
    <p>Generated by your private Second Brain engine &middot; {$formatted_date}</p>
  </div>
</div>
</body>
</html>
HTML;
}
