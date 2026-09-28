<?php
if (!defined('ABSPATH')) exit;

/**
 * Gateway de paiement CamerPay pour WooCommerce.
 *
 * Etend WC_Payment_Gateway pour s'integrer au checkout standard.
 * Methode : redirection vers une page de paiement CamerPay
 * (Mobile Money, cartes, PayPal selon ce que le client choisit).
 */
class WC_CamerPay_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'camerpay';
        $this->icon               = CAMERPAY_PLUGIN_URL . 'assets/images/camerpay-logo.png';
        $this->has_fields         = false; // pas de form custom, redirection externe
        $this->method_title       = 'CamerPay';
        $this->method_description = 'Accepter les paiements Mobile Money (Orange Money, MTN MoMo), cartes bancaires et PayPal au Cameroun.';

        $this->supports = ['products', 'refunds'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled     = $this->get_option('enabled');
        $this->api_key     = $this->get_option('api_key');
        $this->testmode    = 'yes' === $this->get_option('testmode');
        $this->debug       = 'yes' === $this->get_option('debug');

        // Hook : sauvegarde des reglages admin
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    /**
     * Champs admin (WooCommerce > Reglages > Paiements > CamerPay).
     */
    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => 'Activer/Desactiver',
                'type'    => 'checkbox',
                'label'   => 'Activer CamerPay',
                'default' => 'no',
            ],
            'title' => [
                'title'       => 'Titre',
                'type'        => 'text',
                'description' => 'Le titre affiche au client lors du checkout.',
                'default'     => 'Paiement Mobile Money / Carte (CamerPay)',
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'La description affichee sous le titre au moment du checkout.',
                'default'     => 'Payez avec Orange Money, MTN MoMo, carte bancaire ou PayPal. Vous serez redirige vers une page securisee CamerPay.',
                'desc_tip'    => true,
            ],
            'api_key' => [
                'title'       => 'Cle API (Bearer Token)',
                'type'        => 'password',
                'description' => 'Genere depuis votre dashboard CamerPay > <a href="https://camerpay.biz/client/api" target="_blank">API & webhooks</a>. Format : Bearer abc123...',
                'default'     => '',
            ],
            'callback_secret' => [
                'title'       => 'Secret webhook (HMAC)',
                'type'        => 'password',
                'description' => 'Secret HMAC utilise par CamerPay pour signer les webhooks. <strong>Obligatoire en production</strong>. Defini cote CamerPay dans <a href="https://camerpay.biz/client/api" target="_blank">API & webhooks</a>.',
                'default'     => '',
            ],
            'testmode' => [
                'title'       => 'Mode sandbox',
                'type'        => 'checkbox',
                'label'       => 'Activer le mode sandbox (transactions de test, aucun argent reel)',
                'default'     => 'no',
                'description' => 'En sandbox, les paiements sont simules. Utile pour tester l\'integration avant la mise en prod.',
            ],
            'debug' => [
                'title'   => 'Logs debug',
                'type'    => 'checkbox',
                'label'   => 'Enregistrer les appels API dans WooCommerce > Statut > Logs',
                'default' => 'no',
            ],
        ];
    }

    /**
     * Affiche un message admin si l'API key est manquante.
     */
    public function admin_options() {
        if (empty($this->api_key)) {
            echo '<div class="notice notice-warning inline"><p>';
            echo '<strong>CamerPay :</strong> ajoutez votre cle API pour activer les paiements. ';
            echo '<a href="https://camerpay.biz/client/api" target="_blank">Generer une cle</a>';
            echo '</p></div>';
        }

        if ($this->testmode) {
            echo '<div class="notice notice-info inline"><p>';
            echo '<strong>Mode sandbox actif</strong> - aucun argent reel ne sera encaisse.';
            echo '</p></div>';
        }

        parent::admin_options();
    }

    /**
     * Lance le paiement quand le client clique "Commander".
     *
     * Workflow :
     *  1. Cree une transaction CamerPay via /api/payment/initiate
     *  2. Recupere pay_url et redirige le client
     *  3. La commande WC reste en 'pending' jusqu'au webhook
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        $amount = (float) $order->get_total();
        if ($amount <= 0) {
            wc_add_notice('Erreur paiement CamerPay : montant invalide (0 XAF).', 'error');
            return ['result' => 'failure'];
        }

        $api = new WC_CamerPay_API($this->api_key, $this->testmode);

        $result = $api->initiate_payment([
            'amount'                => $amount,
            'currency'              => $order->get_currency() ?: 'XAF',
            'customer_email'        => $order->get_billing_email(),
            'customer_phone'        => $order->get_billing_phone(),
            'customer_name'         => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'merchant_invoice_id'   => 'WC-' . $order->get_id(),
            'merchant_callback_url' => home_url('?wc-api=camerpay_webhook'),
            'merchant_return_url'   => $this->get_return_url($order),
        ]);

        if (is_wp_error($result)) {
            $err_msg = $result->get_error_message();
            wc_add_notice('Erreur paiement CamerPay : ' . $err_msg, 'error');

            if ($this->debug) {
                $logger = wc_get_logger();
                $logger->error('initiate_payment failed', [
                    'source'   => 'camerpay',
                    'order_id' => $order_id,
                    'error'    => $err_msg,
                    'data'     => $result->get_error_data(),
                ]);
            }
            return ['result' => 'failure'];
        }

        // Conserve l'UUID CamerPay sur la commande pour matcher au webhook
        $order->update_meta_data('_camerpay_uuid', $result['transaction_uuid'] ?? '');
        $order->update_status('pending', 'En attente du paiement CamerPay (UUID : ' . ($result['transaction_uuid'] ?? '?') . ')');
        $order->save();

        if ($this->debug) {
            wc_get_logger()->info('Transaction CamerPay creee', [
                'source'   => 'camerpay',
                'order_id' => $order_id,
                'uuid'     => $result['transaction_uuid'] ?? null,
                'pay_url'  => $result['pay_url'] ?? null,
            ]);
        }

        return [
            'result'   => 'success',
            'redirect' => $result['pay_url'] ?? $result['redirect_url'] ?? $this->get_return_url($order),
        ];
    }

    /**
     * Remboursement depuis l'admin WC (bouton Refund sur la commande).
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);
        $uuid  = $order->get_meta('_camerpay_uuid');

        if (!$uuid) {
            return new WP_Error('camerpay_no_uuid', 'Transaction CamerPay introuvable pour cette commande.');
        }

        $api    = new WC_CamerPay_API($this->api_key, $this->testmode);
        $result = $api->refund($uuid, $amount, $reason);

        if (is_wp_error($result) || empty($result['success'])) {
            $err = is_wp_error($result) ? $result->get_error_message() : ($result['message'] ?? 'Echec remboursement');
            return new WP_Error('camerpay_refund_failed', $err);
        }

        $order->add_order_note('Remboursement CamerPay effectue : ' . number_format($amount, 0, ',', ' ') . ' XAF. Raison : ' . $reason);
        return true;
    }
}
