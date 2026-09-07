<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Server-to-server billing webhook — confirms assessment payments (port of
 * talent's BillingWebhookController). HMAC-SHA256 signature, idempotent:
 * a paid event marks the order paid and creates the assessment attempt.
 * No session/auth (called by the billing platform).
 */
class Assessment_webhook extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->model('attempt_model');
        $this->load->model('assessment_model');
        $this->load->library('billing_client');
    }

    public function index()
    {
        $raw = file_get_contents('php://input');
        $sig = $this->input->get_request_header('X-Webhook-Signature');

        if (! $this->billing_client->verify_webhook($raw, $sig)) {
            log_message('error', 'Assessment_webhook: invalid signature from ' . $this->input->ip_address());
            return $this->reply(401, ['success' => false, 'error' => 'invalid_signature']);
        }

        $body = json_decode($raw, true) ?: [];
        $event = $body['event'] ?? $body['type'] ?? null;
        $invoice_id = $this->resolve_invoice_id($body);
        if (! $invoice_id) {
            return $this->reply(200, ['success' => false, 'error' => 'no_invoice_id']);
        }

        $payment = $this->attempt_model->get_payment_by_invoice($invoice_id);
        if (! $payment) {
            log_message('error', 'Assessment_webhook: no order for invoice ' . $invoice_id);
            return $this->reply(200, ['success' => false, 'error' => 'no_matching_order']);
        }

        $success = ['payment.success', 'invoice.paid', 'payment.completed'];
        $failure = ['payment.failed', 'invoice.cancelled', 'invoice.voided'];
        $status  = $body['data']['status'] ?? $body['invoice']['status'] ?? null;

        if (in_array($event, $success, true) || $status === 'paid') {
            if ($payment['status'] !== 'paid') {   // idempotent
                $this->attempt_model->update_payment($payment['id'], ['status' => 'paid', 'gateway' => $body['gateway'] ?? '', 'paid_at' => date('Y-m-d H:i:s')]);
                if (! $this->attempt_model->active_attempt($payment['candidate_id'], $payment['assessment_id'])) {
                    $this->attempt_model->create_attempt($this->attempt_model->get_payment($payment['id']),
                        $this->assessment_model->get_assessment($payment['assessment_id']));
                }
            }
            return $this->reply(200, ['success' => true]);
        }

        if (in_array($event, $failure, true)) {
            if ($payment['status'] !== 'paid') {
                $this->attempt_model->update_payment($payment['id'], ['status' => 'failed']);
            }
            return $this->reply(200, ['success' => true]);
        }

        log_message('info', 'Assessment_webhook: unhandled event ' . $event . ' for invoice ' . $invoice_id);
        return $this->reply(200, ['success' => true]);
    }

    private function resolve_invoice_id($body)
    {
        $v = $body['invoice']['id'] ?? $body['data']['invoice_id'] ?? $body['data']['invoice']['id']
            ?? $body['invoice_id'] ?? $body['payment']['invoice_id'] ?? null;
        return $v !== null ? (string) $v : null;
    }

    private function reply($code, $data)
    {
        $this->output->set_status_header($code)->set_content_type('application/json')->set_output(json_encode($data));
    }
}
