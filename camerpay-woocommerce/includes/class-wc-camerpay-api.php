<?php
if (!defined('ABSPATH')) exit;

/**
 * Client API CamerPay : appels POST /api/payment/initiate
 * et POST /api/payment/{uuid}/refund.
 *
 * Documentation API : https://camerpay.biz/documentation
 */
class WC_CamerPay_API {

    /** @var string Base URL prod ou sandbox */
    private $base_url;

    /** @var string Bearer token Sanctum recupere depuis /client/api */
    private $api_key;

    /** @var bool Mode sandbox actif ? */
    private $is_sandbox;

    public function __construct($api_key, $is_sandbox = false) {
        $this->api_key    = $api_key;
        $this->is_sandbox = (bool) $is_sandbox;
        $this->base_url   = 'https://camerpay.biz/api';
    }

    /**
     * Initie une transaction de paiement.
     *
     * @param array $order_data {
     *     @type float  $amount               Montant en XAF
     *     @type string $currency             Code ISO 4217 (XAF par defaut)
     *     @type string $payment_method       orange_money|mtn_momo|stripe|paypal (optionnel)
     *     @type string $customer_email
     *     @type string $customer_phone
     *     @type string $customer_name
     *     @type string $merchant_invoice_id
     *     @type string $merchant_callback_url
     *     @type string $merchant_return_url
     * }
     * @return array|WP_Error {
     *     @type bool   $success
     *     @type string $transaction_uuid
     *     @type string $pay_url       URL vers laquelle rediriger le client
     *     @type string $status
     * }
     */
    public function initiate_payment($order_data) {
        $body = wp_json_encode([
            'amount'                => (float) $order_data['amount'],
            'currency'              => $order_data['currency'] ?? 'XAF',
            'payment_method'        => $order_data['payment_method'] ?? null,
            'customer_email'        => $order_data['customer_email'] ?? null,
            'customer_phone'        => $order_data['customer_phone'] ?? null,
            'customer_name'         => $order_data['customer_name'] ?? null,
            'merchant_invoice_id'   => $order_data['merchant_invoice_id'],
            'merchant_callback_url' => $order_data['merchant_callback_url'],
            'merchant_return_url'   => $order_data['merchant_return_url'],
            'source'                => 'woocommerce',
        ]);

        $response = wp_remote_post($this->base_url . '/payment/initiate', [
            'method'  => 'POST',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'X-Camerpay-Sdk' => 'woocommerce/' . CAMERPAY_VERSION,
            ],
            'body'    => $body,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('http_error', $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($data['success'])) {
            $msg = $data['message'] ?? $data['error'] ?? 'Erreur API CamerPay (HTTP ' . $code . ')';
            return new WP_Error('camerpay_api_error', $msg, $data);
        }

        return $data;
    }

    /**
     * Recupere le statut d'une transaction (utile pour les retries
     * webhook ou les pages thank-you qui veulent confirmer).
     */
    public function get_transaction_status($uuid) {
        $response = wp_remote_get($this->base_url . '/payment/' . $uuid . '/status', [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept'        => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('http_error', $response->get_error_message());
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Initie un remboursement total ou partiel.
     */
    public function refund($uuid, $amount = null, $reason = '') {
        $body = wp_json_encode(array_filter([
            'amount' => $amount,
            'reason' => $reason,
        ]));

        $response = wp_remote_post($this->base_url . '/payment/' . $uuid . '/refund', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
            'body'    => $body,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('http_error', $response->get_error_message());
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }
}
