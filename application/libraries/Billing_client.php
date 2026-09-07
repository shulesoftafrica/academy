<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ShuleSoft Billing (Safaribank) client — port of the talent app's
 * ShulesoftBillingClient + ShulesoftAuthService. Same org account.
 *   - OAuth client-credentials token (cached), fallback to static access token.
 *   - createInvoice() -> POST /invoices
 *   - getPaymentGateways() -> GET /invoices/{id}/payment-gateways  (UCN/Stripe/Flutterwave)
 *   - getOrCreateAssessmentProduct() -> a reusable TZS price plan for assessments
 * Credentials read from shulesoft_auth.php, falling back to talent/.env.
 */
class Billing_client
{
    private $CI;
    private $talent_env = null;
    private $token_cache;
    private $product_cache;

    private $env_map = [
        'billing_api_url'        => 'BILLING_API_URL',
        'billing_oauth_url'      => 'SHULESOFT_API_URL',
        'billing_client_id'      => 'SHULESOFT_CLIENT_ID',
        'billing_client_secret'  => 'SHULESOFT_CLIENT_SECRET',
        'billing_access_token'   => 'BILLING_ACCESS_TOKEN',
        'billing_org_id'         => 'BILLING_ORGANIZATION_ID',
        'billing_webhook_secret' => 'BILLING_WEBHOOK_SECRET',
        'billing_price_plan_id'  => 'BILLING_VERIFICATION_PRICE_PLAN_ID',
    ];

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->config->load('shulesoft_auth', true);
        $cache_dir           = APPPATH . 'cache/';
        $this->token_cache   = $cache_dir . 'billing_token.json';
        $this->product_cache = $cache_dir . 'billing_assessment_product.json';
    }

    public function is_configured()
    {
        return $this->cfg('billing_client_id') !== '' || $this->cfg('billing_access_token') !== '';
    }

    // ---- invoice ------------------------------------------------------------
    /**
     * @param array $customer ['name'=>,'email'=>,'phone'=>]
     * @return array ['success'=>bool,'invoice_id'=>?string,'error'=>?string]
     */
    public function create_invoice(array $customer, $price_plan_id, $amount, $description, $currency = 'TZS')
    {
        if (! $this->is_configured()) {
            return ['success' => false, 'invoice_id' => null, 'error' => 'not_configured'];
        }
        $res = $this->request('POST', '/invoices', [
            'organization_id' => (int) ($this->cfg('billing_org_id') ?: 1),
            'customer'        => $customer,
            'products'        => [['price_plan_id' => (int) $price_plan_id, 'amount' => (float) $amount]],
            'description'     => $description,
            'currency'        => $currency,
            'status'          => 'issued',
        ]);
        if (! $res['success']) {
            return ['success' => false, 'invoice_id' => null, 'error' => $res['error']];
        }
        $d = $res['data'];
        $invoice_id = $d['data']['invoice']['id'] ?? $d['data']['id'] ?? $d['invoice']['id'] ?? null;
        if (! $invoice_id) {
            log_message('error', 'Billing_client: invoice created but no id in response: ' . json_encode($d));
            return ['success' => false, 'invoice_id' => null, 'error' => 'no_invoice_id'];
        }
        return ['success' => true, 'invoice_id' => (string) $invoice_id, 'error' => null];
    }

    /** @return array ['ucn'=>?,'stripe_link'=>?,'flutterwave_link'=>?] */
    public function get_payment_gateways($invoice_id)
    {
        $out = ['ucn' => null, 'stripe_link' => null, 'flutterwave_link' => null];
        $res = $this->request('GET', "/invoices/{$invoice_id}/payment-gateways");
        if (! $res['success']) {
            return $out;
        }
        $data = $res['data']['data'] ?? $res['data'] ?? [];
        $out['ucn'] = $data['ucn'] ?? $data['control_number'] ?? null;
        $gws = $data['price_plans'][0]['payment_gateways'] ?? $data['payment_gateways'] ?? [];
        foreach ($gws as $g) {
            $n = $g['gateway_name'] ?? null;
            if ($n === 'Universal Control Number' && isset($g['references'])) { $out['ucn'] = $out['ucn'] ?? $g['references']; }
            if ($n === 'Stripe' && isset($g['payment_link']))      { $out['stripe_link'] = $out['stripe_link'] ?? $g['payment_link']; }
            if ($n === 'Flutterwave' && isset($g['payment_link'])) { $out['flutterwave_link'] = $out['flutterwave_link'] ?? $g['payment_link']; }
        }
        $links = $data['price_plans'][0]['payment_links'] ?? $data['payment_links'] ?? [];
        $out['ucn'] = $out['ucn'] ?? ($links['ucn'] ?? null);
        $out['stripe_link'] = $out['stripe_link'] ?? ($links['stripe'] ?? null);
        $out['flutterwave_link'] = $out['flutterwave_link'] ?? ($links['flutterwave'] ?? null);
        return $out;
    }

    const ASSESSMENT_PRODUCT_CODE = 'academy-skills-assessment';

    /**
     * TZS price plan id for assessment invoices (the invoice amount is set
     * per-purchase from the admin's assessment price). If a price plan is
     * explicitly configured (billing_price_plan_id / BILLING_*_PRICE_PLAN_ID)
     * it wins; otherwise we create-or-get the Academy product on the billing
     * platform to obtain one — exactly as talent's getOrCreateTalentPremiumProduct().
     */
    public function get_assessment_price_plan($currency = 'TZS')
    {
        $configured = (int) ($this->cfg('billing_price_plan_id') ?: 0);
        if ($configured > 0) {
            return $configured;
        }
        $p = $this->get_or_create_assessment_product();
        return $currency === 'USD' ? (int) ($p['usd_price_plan_id'] ?? 0) : (int) ($p['tzs_price_plan_id'] ?? 0);
    }

    /**
     * Gets (or, on first call, creates) the "ShuleSoft Academy — Skills
     * Assessment" product on the billing platform and returns its per-currency
     * price plan ids. Mirrors talent ShulesoftBillingClient::getOrCreateTalentPremiumProduct()
     * (usage/wallet product, rate=1 so amount==quantity; local records are the
     * source of truth for access). Cached to a file so it runs at most once.
     *
     * @return array ['tzs_price_plan_id'=>?int,'usd_price_plan_id'=>?int]
     */
    public function get_or_create_assessment_product()
    {
        // TZS is the assessment billing currency, so a TZS price plan is enough
        // (USD optional — only needed if international pricing is ever added).
        if (is_file($this->product_cache)) {
            $c = json_decode((string) file_get_contents($this->product_cache), true);
            if (! empty($c['tzs_price_plan_id'])) {
                return $c;
            }
        }

        // Already on the platform?
        $existing = $this->request('GET', '/products/' . self::ASSESSMENT_PRODUCT_CODE);
        if ($existing['success']) {
            $plans = $this->extract_price_plan_ids($existing['data']['data'] ?? []);
            if ($plans['tzs_price_plan_id']) {
                @file_put_contents($this->product_cache, json_encode($plans));
                return $plans;
            }
        }

        // Create it (one-time; the platform is slow, so callers give this room).
        $currency_ids = ['USD' => 1, 'TZS' => 2]; // confirmed platform ids (1=USD, 2=TZS)
        $created = $this->request('POST', '/products', [
            'organization_id' => (int) ($this->cfg('billing_org_id') ?: 1),
            'product_type_id' => 3, // usage/wallet
            'name'            => 'ShuleSoft Academy — Skills Assessment',
            'product_code'    => self::ASSESSMENT_PRODUCT_CODE,
            'description'     => 'ShuleSoft Academy — skills assessment & verification credential',
            'unit'            => 'Assessment',
            'active'          => true,
            'price_plans'     => [
                ['name' => 'Skills Assessment — Tanzania', 'currency_id' => $currency_ids['TZS'], 'rate' => 1],
                ['name' => 'Skills Assessment — International', 'currency_id' => $currency_ids['USD'], 'rate' => 1],
            ],
        ]);
        if (! $created['success']) {
            log_message('error', 'Billing_client: failed to create Academy assessment product: ' . json_encode($created['data']));
            return ['tzs_price_plan_id' => null, 'usd_price_plan_id' => null];
        }
        $plans = $this->extract_price_plan_ids($created['data']['data'] ?? []);
        if ($plans['tzs_price_plan_id']) {
            @file_put_contents($this->product_cache, json_encode($plans));
        }
        return $plans;
    }

    /** Diagnostics: token status + raw product listing (for setup/probe only). */
    public function probe()
    {
        $token = $this->get_token();
        $out = [
            'configured'  => $this->is_configured(),
            'has_token'   => $token ? true : false,
            'oauth_url'   => $this->cfg('billing_oauth_url'),
            'api_url'     => $this->cfg('billing_api_url'),
            'org_id'      => $this->cfg('billing_org_id'),
        ];
        $existing = $this->request('GET', '/products/' . self::ASSESSMENT_PRODUCT_CODE);
        $out['get_product'] = ['success' => $existing['success'], 'error' => $existing['error'] ?? null, 'data' => $existing['data'] ?? null];
        return $out;
    }

    private function extract_price_plan_ids($product_data)
    {
        $r = ['tzs_price_plan_id' => null, 'usd_price_plan_id' => null];
        foreach ($product_data['price_plans'] ?? [] as $plan) {
            $cur = $plan['currency'] ?? null;
            if ($cur === 'TZS') {
                $r['tzs_price_plan_id'] = $plan['id'] ?? null;
            } elseif ($cur === 'USD') {
                $r['usd_price_plan_id'] = $plan['id'] ?? null;
            }
        }
        return $r;
    }

    /** Sign a raw body with the webhook secret (test/simulation helper only). */
    public function sign_body($raw)
    {
        return hash_hmac('sha256', (string) $raw, (string) $this->cfg('billing_webhook_secret'));
    }

    // ---- webhook signature (HMAC-SHA256 of raw body) ------------------------
    public function verify_webhook($raw_body, $signature_header)
    {
        $secret = $this->cfg('billing_webhook_secret');
        if ($secret === '') {
            log_message('error', 'Billing_client: webhook secret not configured — skipping signature check');
            return true;
        }
        if (! $signature_header) {
            return false;
        }
        $sig = (strpos($signature_header, '=') !== false) ? substr($signature_header, strpos($signature_header, '=') + 1) : $signature_header;
        return hash_equals(hash_hmac('sha256', (string) $raw_body, $secret), $sig);
    }

    // ---- oauth token --------------------------------------------------------
    private function get_token()
    {
        if (is_file($this->token_cache)) {
            $c = json_decode((string) file_get_contents($this->token_cache), true);
            if (! empty($c['token']) && ! empty($c['expires']) && time() < $c['expires']) {
                return $c['token'];
            }
        }
        $cid = $this->cfg('billing_client_id');
        $sec = $this->cfg('billing_client_secret');
        if ($cid !== '' && $sec !== '') {
            $resp = $this->http('POST', rtrim($this->cfg('billing_oauth_url'), '/') . '/oauth/token', [
                'grant_type' => 'client_credentials', 'client_id' => $cid, 'client_secret' => $sec, 'scope' => '*',
            ], null);
            $token = $resp['json']['access_token'] ?? null;
            if ($token) {
                @file_put_contents($this->token_cache, json_encode(['token' => $token, 'expires' => time() + 89 * 24 * 3600]));
                return $token;
            }
            log_message('error', 'Billing_client: oauth token mint failed: ' . substr($resp['body'], 0, 200));
        }
        return $this->cfg('billing_access_token') ?: null;   // static fallback
    }

    // ---- http ---------------------------------------------------------------
    private function request($method, $path, $payload = [])
    {
        $token = $this->get_token();
        if (! $token) {
            return ['success' => false, 'data' => null, 'error' => 'no_token'];
        }
        $r = $this->http($method, rtrim($this->cfg('billing_api_url'), '/') . $path, $payload, $token);
        if ($r['status'] < 200 || $r['status'] >= 300 || $r['error']) {
            log_message('error', 'Billing_client: ' . $method . ' ' . $path . ' -> ' . $r['status'] . ' ' . substr($r['body'], 0, 300));
            return ['success' => false, 'data' => $r['json'], 'error' => 'http_' . $r['status']];
        }
        return ['success' => true, 'data' => $r['json'], 'error' => null];
    }

    private function http($method, $url, $payload, $token)
    {
        $ch = curl_init($url);
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if ($token) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        ];
        if (strtoupper($method) !== 'GET') {
            $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
        }
        curl_setopt_array($ch, $opts);
        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);
        return ['body' => (string) $body, 'status' => $status, 'error' => $err, 'json' => json_decode((string) $body, true)];
    }

    // ---- config with talent/.env fallback -----------------------------------
    private function cfg($key)
    {
        $val = $this->CI->config->item($key, 'shulesoft_auth');
        if (($val === '' || $val === null) && isset($this->env_map[$key])) {
            $val = $this->talent_env($this->env_map[$key]);
        }
        return $val;
    }

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
        return $this->talent_env[$key] ?? '';
    }
}
