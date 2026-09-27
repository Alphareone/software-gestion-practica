<?php

class MailService {
    private $fromEmail;
    private $fromName;

    public function __construct() {
        $this->fromEmail = defined('APP_EMAIL') ? APP_EMAIL : 'noreply@localhost';
        $this->fromName  = defined('BRAND_NAME') ? BRAND_NAME : 'Sistema';
    }

    public function send($to, $subject, $template, $data = []) {
        $userId   = $data['user_id'] ?? null;
        $senderId = $data['sender_id'] ?? null;
        unset($data['user_id'], $data['sender_id']);

        $body = $this->render($template, $data);

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: {$this->fromName} <{$this->fromEmail}>\r\n";
        $headers .= "Reply-To: noreply-software@epracticas.cl\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

        $sent = mail($to, $encodedSubject, $body, $headers);

        $recipientUser = $data['username'] ?? null;

        $logModel = new EmailLogModel();
        $logModel->log(
            $to,
            $recipientUser,
            $template,
            $subject,
            $sent ? 'sent' : 'failed',
            $userId,
            $senderId,
            json_encode($data, JSON_UNESCAPED_UNICODE)
        );

        error_log("[MailService] Enviado a {$to} (template: {$template}): " . ($sent ? 'OK' : 'FALLÓ'));
        return $sent;
    }

    public function sendWithAttachment($to, $subject, $template, $data = [], $attachmentPath = '', $attachmentName = '') {
        $userId   = $data['user_id'] ?? null;
        $senderId = $data['sender_id'] ?? null;
        unset($data['user_id'], $data['sender_id']);

        $htmlContent = $this->render($template, $data);
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        if (!empty($attachmentPath) && file_exists($attachmentPath)) {
            $boundary = '----=_NextPart_' . md5(time());

            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "From: {$this->fromName} <{$this->fromEmail}>\r\n";
            $headers .= "Reply-To: noreply-software@epracticas.cl\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";

            $body = "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $body .= $htmlContent . "\r\n\r\n";

            $fileData = file_get_contents($attachmentPath);
            $filename = !empty($attachmentName) ? $attachmentName : basename($attachmentPath);
            $encodedFile = chunk_split(base64_encode($fileData));

            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; name=\"{$filename}\"\r\n";
            $body .= "Content-Description: {$filename}\r\n";
            $body .= "Content-Disposition: attachment; filename=\"{$filename}\"; size=" . strlen($fileData) . ";\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= $encodedFile . "\r\n\r\n";
            $body .= "--{$boundary}--";

            $sent = mail($to, $encodedSubject, $body, $headers);
        } else {
            $sent = $this->send($to, $subject, $template, $data);
            return $sent;
        }

        $recipientUser = $data['username'] ?? null;
        $logModel = new EmailLogModel();
        $logModel->log(
            $to,
            $recipientUser,
            $template,
            $subject,
            $sent ? 'sent' : 'failed',
            $userId,
            $senderId,
            json_encode($data, JSON_UNESCAPED_UNICODE)
        );

        error_log("[MailService] Enviado con adjunto a {$to} (template: {$template}): " . ($sent ? 'OK' : 'FALLÓ'));
        return $sent;
    }

    private function render($template, $data = []) {
        $this->prepareData($data);

        extract($data);
        ob_start();
        include __DIR__ . '/../Views/emails/' . $template . '.php';
        $content = ob_get_clean();

        ob_start();
        include __DIR__ . '/../Views/emails/layout.php';
        return ob_get_clean();
    }

    private function prepareData(&$data) {
        $data['logo_url']   = defined('BRAND_LOGO') ? BRAND_LOGO : '';
        $data['brand_name'] = defined('BRAND_NAME') ? BRAND_NAME : 'Sistema';
        $data['brand_company'] = defined('BRAND_COMPANY') ? BRAND_COMPANY : '';
        $data['app_email']  = $this->fromEmail;
        $data['sitename']   = defined('SITENAME') ? SITENAME : '';
    }

    public static function parseUserAgent($ua) {
        if (!$ua) return 'Desconocido';

        $browser = 'Navegador desconocido';
        if (str_contains($ua, 'Firefox') && !str_contains($ua, 'Seamonkey')) $browser = 'Firefox';
        elseif (str_contains($ua, 'Edg')) $browser = 'Edge';
        elseif (str_contains($ua, 'Chrome') && !str_contains($ua, 'Edg')) $browser = 'Chrome';
        elseif (str_contains($ua, 'Safari') && !str_contains($ua, 'Chrome')) $browser = 'Safari';
        elseif (str_contains($ua, 'OPR') || str_contains($ua, 'Opera')) $browser = 'Opera';

        $os = 'Sistema operativo desconocido';
        if (str_contains($ua, 'Windows NT 10')) $os = 'Windows 10';
        elseif (str_contains($ua, 'Windows NT 11')) $os = 'Windows 11';
        elseif (str_contains($ua, 'Windows NT 6.3')) $os = 'Windows 8.1';
        elseif (str_contains($ua, 'Windows NT 6.1')) $os = 'Windows 7';
        elseif (str_contains($ua, 'Mac OS X')) $os = 'macOS';
        elseif (str_contains($ua, 'iPhone')) $os = 'iOS';
        elseif (str_contains($ua, 'Android')) $os = 'Android';
        elseif (str_contains($ua, 'Linux') && !str_contains($ua, 'Android')) $os = 'Linux';

        return "{$browser} · {$os}";
    }
}
