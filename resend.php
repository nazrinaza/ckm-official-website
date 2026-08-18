<?php
/**
 * CKM — Resend Email API Helper
 * Sends email via Resend HTTP API (port 443) — bypasses hosting SMTP block.
 * Requires: RESEND_API_KEY in config.php, domain verified at resend.com
 */
declare(strict_types=1);

class CkmResend
{
    private string $apiKey;
    private string $fromEmail;
    private string $fromName;
    private string $lastError = '';

    public function __construct(string $apiKey, string $fromEmail, string $fromName = 'cucikarpetmasjid.com')
    {
        $this->apiKey   = $apiKey;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
    }

    /**
     * Send email via Resend API.
     * @param string $to      Recipient email
     * @param string $subject Email subject
     * @param string $html    HTML body
     * @return bool True on success
     */
    public function send(string $to, string $subject, string $html): bool
    {
        $this->lastError = '';

        $payload = json_encode([
            'from'    => $this->fromName . ' <' . $this->fromEmail . '>',
            'to'      => $to,
            'subject' => $subject,
            'html'    => $html,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $this->lastError = 'cURL error: ' . $curlErr;
            error_log('CKM Resend cURL error: ' . $curlErr);
            return false;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return true; // Success
        }

        // Error response from Resend
        $this->lastError = "HTTP {$httpCode}: {$response}";
        error_log('CKM Resend API error: ' . $this->lastError);
        return false;
    }

    public function getError(): string
    {
        return $this->lastError;
    }
}
