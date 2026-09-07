<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Generates, stores and delivers OTP codes for ShuleSoft-community login.
 * Ported from the talent platform's App\Services\Notifications\OtpService:
 *   - email  -> Unified Notification API (notifications.shulesoft.africa)
 *   - phone  -> Meta WhatsApp Cloud API ("otp" template)
 * Every delivery fails soft (logs, never throws) so a channel outage never
 * blocks the login flow. Codes: 6-digit, 5-minute TTL, max 5 attempts.
 */
class Otp_service
{
    /** @var CI_Controller */
    private $CI;
    /** @var array|null parsed talent .env cache */
    private $talent_env = null;

    /** config key => talent .env key, for values left blank in shulesoft_auth.php */
    private $env_map = [
        'notification_base_url'  => 'NOTIFICATION_BASE_URL',
        'notification_bearer'    => 'NOTIFICATION_BEARER_TOKEN',
        'notification_schema'    => 'NOTIFICATION_SCHEMA_NAME',
        'meta_base_url'          => 'META_WHATSAPP_BASE_URL',
        'meta_api_version'       => 'META_WHATSAPP_API_VERSION',
        'meta_phone_number_id'   => 'META_WHATSAPP_PHONE_NUMBER_ID',
        'meta_access_token'      => 'META_WHATSAPP_ACCESS_TOKEN',
        'meta_otp_template_name' => 'META_WHATSAPP_OTP_TEMPLATE_NAME',
        'meta_otp_template_lang' => 'META_WHATSAPP_OTP_TEMPLATE_LANGUAGE',
    ];

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->database();
        $this->CI->config->load('shulesoft_auth', true);
    }

    /** Config value, falling back to the talent .env when left blank (shared secrets). */
    private function cfg($key)
    {
        $val = $this->CI->config->item($key, 'shulesoft_auth');
        if (($val === '' || $val === null) && isset($this->env_map[$key])) {
            $val = $this->talent_env($this->env_map[$key]);
        }
        return $val;
    }

    /** Read a single key from the talent project's .env (parsed once, cached). */
    private function talent_env($key)
    {
        if ($this->talent_env === null) {
            $this->talent_env = [];
            $path = $this->CI->config->item('talent_env_path', 'shulesoft_auth');
            if ($path && is_readable($path)) {
                foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                        continue;
                    }
                    list($k, $v) = explode('=', $line, 2);
                    $v = trim($v);
                    if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
                        $v = substr($v, 1, -1);
                    }
                    $this->talent_env[trim($k)] = $v;
                }
            }
        }
        return isset($this->talent_env[$key]) ? $this->talent_env[$key] : '';
    }

    /**
     * Generate + store + deliver a code. Returns the 6-digit code (for logging/tests).
     * @param string      $phoneOrEmail identifier the user typed
     * @param string      $purpose      login|verify_phone|verify_email
     * @param string|null $extraEmail   when identifier is a phone, also copy to this email
     */
    public function send($phoneOrEmail, $purpose = 'login', $extraEmail = null)
    {
        $isEmail = (strpos($phoneOrEmail, '@') !== false);
        $channel = $isEmail ? 'email' : 'whatsapp';
        $code    = (string) random_int(100000, 999999);
        $ttl     = (int) ($this->cfg('otp_ttl_minutes') ?: 5);

        $this->CI->db->insert('otps', [
            'phone_or_email' => $phoneOrEmail,
            'code'           => $code,
            'purpose'        => $purpose,
            'channel'        => $channel,
            'expires_at'     => date('Y-m-d H:i:s', time() + $ttl * 60),
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->deliver($phoneOrEmail, $code, $isEmail, $extraEmail, $ttl);

        return $code;
    }

    /**
     * @return string success|invalid_code|too_many_attempts|expired_or_missing
     */
    public function verify($phoneOrEmail, $code, $purpose = 'login')
    {
        $max = (int) ($this->cfg('otp_max_attempts') ?: 5);

        $otp = $this->CI->db
            ->where('phone_or_email', $phoneOrEmail)
            ->where('purpose', $purpose)
            ->where('verified_at', null)
            ->order_by('id', 'desc')
            ->limit(1)
            ->get('otps')
            ->row();

        if (!$otp || strtotime($otp->expires_at) < time()) {
            return 'expired_or_missing';
        }
        if ((int) $otp->attempts >= $max) {
            return 'too_many_attempts';
        }
        if (!hash_equals((string) $otp->code, (string) $code)) {
            $this->CI->db->where('id', $otp->id)->set('attempts', 'attempts + 1', false)->update('otps');
            return 'invalid_code';
        }

        $this->CI->db->where('id', $otp->id)->update('otps', ['verified_at' => date('Y-m-d H:i:s')]);
        return 'success';
    }

    // ----------------------------------------------------------------- delivery

    private function deliver($phoneOrEmail, $code, $isEmail, $extraEmail, $ttl)
    {
        $message = "Your ShuleSoft Academy verification code is: {$code}. It expires in {$ttl} minutes.";

        if ($isEmail) {
            $this->send_email($phoneOrEmail, $message);
            return;
        }

        $this->send_whatsapp_otp($phoneOrEmail, $code);
        if (!empty($extraEmail)) {
            $this->send_email($extraEmail, $message);
        }
    }

    private function send_email($to, $message)
    {
        $bearer = $this->cfg('notification_bearer');
        $schema = $this->cfg('notification_schema');
        if (empty($bearer) || empty($schema)) {
            log_message('error', 'Otp_service: notification bearer/schema missing; email skipped');
            return;
        }
        $this->http_post_json(
            rtrim($this->cfg('notification_base_url'), '/') . '/api/notifications/send',
            [
                'schema_name' => $schema,
                'channel'     => 'email',
                'to'          => $to,
                'subject'     => 'Your ShuleSoft Academy verification code',
                'message'     => $message,
            ],
            $bearer
        );
    }

    private function send_whatsapp_otp($phone, $code)
    {
        $token = $this->cfg('meta_access_token');
        if (empty($token)) {
            log_message('error', 'Otp_service: Meta access token missing; WhatsApp skipped');
            return;
        }
        $to  = $this->normalize_phone($phone);
        $url = rtrim($this->cfg('meta_base_url'), '/') . '/' . $this->cfg('meta_api_version')
             . '/' . $this->cfg('meta_phone_number_id') . '/messages';

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template'          => [
                'name'     => $this->cfg('meta_otp_template_name') ?: 'otp',
                'language' => ['code' => $this->cfg('meta_otp_template_lang') ?: 'en'],
                'components' => [
                    ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
                    ['type' => 'button', 'sub_type' => 'url', 'index' => '0',
                     'parameters' => [['type' => 'text', 'text' => $code]]],
                ],
            ],
        ];
        $this->http_post_json($url, $payload, $token);
    }

    /** TZ-aware phone normalisation, ported from MetaWhatsAppService::formatPhoneNumber. */
    private function normalize_phone($phone)
    {
        $cc      = $this->cfg('otp_default_country') ?: '255';
        $cleaned = preg_replace('/[^0-9+]/', '', (string) $phone);
        if (strpos($cleaned, '+') === 0)                        return $cleaned;
        if (strpos($cleaned, '0') === 0 && strlen($cleaned) === 10) return '+' . $cc . substr($cleaned, 1);
        if (strlen($cleaned) >= 10)                             return '+' . $cleaned;
        return '+' . $cc . $cleaned;
    }

    /** Fire-and-forget JSON POST with bearer auth; logs failures, never throws. */
    private function http_post_json($url, array $body, $bearer)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $bearer,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        $resp   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($err || $status < 200 || $status >= 300) {
            log_message('error', 'Otp_service: delivery HTTP failure (' . $status . ') ' . $err . ' ' . substr((string) $resp, 0, 300));
            return $resp;
        }

        // 2xx — the notification API still reports per-message success in its JSON body.
        $decoded = json_decode((string) $resp, true);
        if (is_array($decoded) && array_key_exists('success', $decoded) && ! $decoded['success']) {
            log_message('error', 'Otp_service: notification API reported failure: ' . substr((string) $resp, 0, 300));
        } else {
            log_message('info', 'Otp_service: notification accepted (' . $status . ')');
        }
        return $resp;
    }
}
