<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * One-off billing maintenance. Reachable from CLI, or via the web ONLY by a
 * logged-in admin. Primes the Academy "Skills Assessment" product on the
 * ShuleSoft billing platform so checkout has a valid price_plan_id cached and
 * stays fast, and exposes a probe to diagnose auth/product issues.
 */
class Billing_setup extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('session');
        if (! is_cli() && $this->session->userdata('admin_login') != true) {
            show_404();
        }
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        $this->load->library('billing_client');
        $this->output->set_content_type('text/plain');
    }

    public function probe()
    {
        echo json_encode($this->billing_client->probe(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function prime()
    {
        $f = APPPATH . 'cache/billing_assessment_product.json';
        if (is_file($f)) {
            @unlink($f);
        }
        $p = $this->billing_client->get_or_create_assessment_product();
        echo 'Result: ' . json_encode($p) . "\n";
        echo ($p['tzs_price_plan_id'] ? "OK — TZS price plan #{$p['tzs_price_plan_id']} cached.\n" : "FAILED — check probe / logs.\n");
    }

    /**
     * Replays a signed "payment.success" webhook for a real invoice to prove the
     * inbound path works (signature verified, payment marked paid, attempt
     * created). The signature is computed server-side with the real secret.
     */
    public function simulate_webhook($invoice_id = '')
    {
        if (! $invoice_id) {
            echo "usage: billing_setup/simulate_webhook/<invoice_id>\n";
            return;
        }
        $payload = json_encode([
            'event'   => 'payment.success',
            'data'    => ['status' => 'paid'],
            'invoice' => ['id' => (string) $invoice_id],
            'gateway' => 'SIMULATED',
        ]);
        $sig = $this->billing_client->sign_body($payload);

        $ch = curl_init(site_url('assessment_webhook'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-Webhook-Signature: ' . $sig],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        echo "webhook HTTP {$code}: {$resp}\n";
    }
}
