<?php
/**
 * SMTP Mailer for Travel Buddy (Raw PHP Implementation)
 * Works with Gmail SMTP, Outlook, Brevo, cPanel Webmail, etc.
 */

class SmtpMailer {
    private $host;
    private $port;
    private $username;
    private $password;
    private $fromEmail;
    private $fromName;
    private $timeout = 15;
    public $error = '';

    public function __construct($config) {
        $this->host      = $config['smtp_host'] ?? 'smtp.gmail.com';
        $this->port      = (int)($config['smtp_port'] ?? 465);
        $this->username  = $config['smtp_user'] ?? '';
        $this->password  = str_replace(' ', '', $config['smtp_pass'] ?? '');
        $this->fromEmail = $config['from_email'] ?? $this->username;
        $this->fromName  = $config['from_name'] ?? 'Travel Buddy';
    }

    public function send($toEmail, $toName, $subject, $htmlContent) {
        if (empty($this->username) || empty($this->password) || strpos($this->username, 'YOUR_EMAIL') !== false) {
            $this->error = "SMTP credentials not configured. Please set your Gmail and App Password in api/mail_config.php!";
            return false;
        }

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $prefix = ($this->port === 465) ? 'ssl://' : '';
        $socket = @stream_socket_client(
            $prefix . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            $this->error = "Failed to connect to SMTP server ({$this->host}:{$this->port}): $errstr ($errno)";
            return false;
        }

        // 1. Read greeting
        $resp = $this->readResponse($socket);
        if (substr($resp, 0, 3) !== '220') {
            $this->error = "SMTP Server greeting error: $resp";
            fclose($socket);
            return false;
        }

        // 2. EHLO
        $this->write($socket, "EHLO " . gethostname());
        $resp = $this->readResponse($socket);

        // 3. STARTTLS if port 587
        if ($this->port === 587) {
            $this->write($socket, "STARTTLS");
            $resp = $this->readResponse($socket);
            if (substr($resp, 0, 3) !== '220') {
                $this->error = "STARTTLS failed: $resp";
                fclose($socket);
                return false;
            }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->error = "TLS handshake failed.";
                fclose($socket);
                return false;
            }
            $this->write($socket, "EHLO " . gethostname());
            $this->readResponse($socket);
        }

        // 4. AUTH LOGIN
        $this->write($socket, "AUTH LOGIN");
        $resp = $this->readResponse($socket);
        if (substr($resp, 0, 3) !== '334') {
            $this->error = "AUTH LOGIN rejected: $resp";
            fclose($socket);
            return false;
        }

        // 5. Send Username
        $this->write($socket, base64_encode($this->username));
        $resp = $this->readResponse($socket);
        if (substr($resp, 0, 3) !== '334') {
            $this->error = "Username rejected: $resp";
            fclose($socket);
            return false;
        }

        // 6. Send Password
        $this->write($socket, base64_encode($this->password));
        $resp = $this->readResponse($socket);
        if (substr($resp, 0, 3) !== '235') {
            $this->error = "Authentication failed! Make sure you use a 16-character Google App Password (not your personal Gmail password). Server response: $resp";
            fclose($socket);
            return false;
        }

        // 7. MAIL FROM
        $this->write($socket, "MAIL FROM: <{$this->fromEmail}>");
        $resp = $this->readResponse($socket);
        if (substr($resp, 0, 3) !== '250') {
            $this->error = "MAIL FROM error: $resp";
            fclose($socket);
            return false;
        }

        // 8. RCPT TO
        $this->write($socket, "RCPT TO: <{$toEmail}>");
        $resp = $this->readResponse($socket);
        if (substr($resp, 0, 3) !== '250') {
            $this->error = "RCPT TO error: $resp";
            fclose($socket);
            return false;
        }

        // 9. DATA
        $this->write($socket, "DATA");
        $resp = $this->readResponse($socket);
        if (substr($resp, 0, 3) !== '354') {
            $this->error = "DATA initiation failed: $resp";
            fclose($socket);
            return false;
        }

        // 10. Message Headers & Body
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->fromEmail}>\r\n";
        $headers .= "To: =?UTF-8?B?" . base64_encode($toName) . "?= <{$toEmail}>\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "Date: " . date('r') . "\r\n";

        $fullPayload = $headers . "\r\n" . $htmlContent . "\r\n.\r\n";

        fwrite($socket, $fullPayload);
        $resp = $this->readResponse($socket);
        if (substr($resp, 0, 3) !== '250') {
            $this->error = "Failed to deliver message: $resp";
            fclose($socket);
            return false;
        }

        // 11. QUIT
        $this->write($socket, "QUIT");
        fclose($socket);

        return true;
    }

    private function write($socket, $cmd) {
        fwrite($socket, $cmd . "\r\n");
    }

    private function readResponse($socket) {
        $response = "";
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == " ") {
                break;
            }
        }
        return trim($response);
    }
}

/**
 * Helper function to send OTP email
 */
function sendOTPEmail($toEmail, $toName, $otp, &$errorMessage = '') {
    $configFile = __DIR__ . '/mail_config.php';
    if (!file_exists($configFile)) {
        $errorMessage = "mail_config.php not found!";
        return false;
    }

    $config = require $configFile;
    $mailer = new SmtpMailer($config);

    $subject = "Travel Buddy - Password Reset OTP";
    $htmlMessage = "
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset='utf-8'>
      <title>Password Reset OTP</title>
    </head>
    <body style='font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px;'>
      <div style='max-width: 550px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
        <div style='text-align: center; padding-bottom: 20px; border-bottom: 2px solid #008080;'>
          <h2 style='color: #008080; margin: 0;'>Travel Buddy</h2>
        </div>
        <div style='padding: 25px 0;'>
          <p style='font-size: 16px; color: #333;'>Hello <strong>" . htmlspecialchars($toName) . "</strong>,</p>
          <p style='font-size: 14px; color: #555; line-height: 1.6;'>
            We received a request to reset your password. Use the following 6-digit OTP code to continue:
          </p>
          <div style='margin: 25px 0; text-align: center;'>
            <span style='display: inline-block; font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #008080; background: #e6f7f7; padding: 12px 30px; border-radius: 8px; border: 1px dashed #008080;'>
              {$otp}
            </span>
          </div>
          <p style='font-size: 14px; color: #777;'>
            This OTP code will expire in <strong>5 minutes</strong>. If you did not request this, please ignore this email.
          </p>
        </div>
        <div style='border-top: 1px solid #eeeeee; padding-top: 15px; text-align: center; font-size: 12px; color: #999;'>
          <p>&copy; " . date('Y') . " Travel Buddy. All rights reserved.</p>
        </div>
      </div>
    </body>
    </html>
    ";

    $result = $mailer->send($toEmail, $toName, $subject, $htmlMessage);
    if (!$result) {
        $errorMessage = $mailer->error;
    }
    return $result;
}
?>
