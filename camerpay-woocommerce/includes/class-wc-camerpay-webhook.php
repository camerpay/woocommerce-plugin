<?php
if (!defined('ABSPATH')) exit;

/**
 * Handler webhook CamerPay -> WooCommerce.
 *
 * Endpoint : https://votre-boutique.com/?wc-api=camerpay_webhook
 *
 * CamerPay POSTe ici a chaque changement d'etat d'une transaction.
 * Body : form-encoded (PAS json) avec les champs :
 *   - uuid                  : UUID de la transaction
 *   - invoice_id            : merchant_invoice_id envoye lors de initiate
 *   - status                : completed | failed | pending | refunded
 *   - amount                : montant (decimal 2 char)
 *   - signature             : HMAC-SHA256(data, callback_secret)
 *                             data = "uuid|invoice_id|status|amount"
 *
 * Headers ajoutes :
 *   - X-CamerPay-Signature  : meme HMAC en header (dedoublonne)
 *   - Idempotency-Key
 *   - X-CamerPay-Event-Id
 *   - X-CamerPay-Event      : "transaction.{status}"
 */
class WC_CamerPay_Webhook {

    public function handle() {
        // Lit les options gateway (api_key + callback_secret)
        $gateway_settings = get_option('woocommerce_camerpay_settings', []);
        $api_key          = $gateway_settings['api_key'] ?? '';

        if (empty($api_key)) {
            status_header(503);
            exit('CamerPay non configure.');
        }

        // Body parse : form-encoded
        $uuid       = $_POST['uuid']       ?? '';
        $invoice_id = $_POST['invoice_id'] ?? '';
        $status     = $_POST['status']     ?? '';
        $amount     = $_POST['amount']     ?? '';
        $signature  = $_POST['signature']  ?? ($_SERVER['HTTP_X_CAMERPAY_SIGNATURE'] ?? '');

        if (empty($uuid) || empty($invoice_id) || empty($status) || empty($amount) || empty($signature)) {
            status_header(400);
            exit('Payload incomplet.');
        }

        // Verification HMAC. Le secret est :
        //   1. callback_secret defini par le marchand dans son dashboard CamerPay
        //      (visible et editable dans /client/api > Webhooks)
        //   2. A configurer cote WooCommerce dans options.camerpay.callback_secret
        $callback_secret = $gateway_settings['callback_secret'] ?? '';

        if (empty($callback_secret)) {
            // Fallback : si le marchand n'a pas configure de secret, on accepte
            // mais on logge. PAS recommande en prod.
            wc_get_logger()->warning('CamerPay webhook recu sans callback_secret configure', [
                'source' => 'camerpay',
                'uuid'   => $uuid,
            ]);
        } else {
            $expected_data = $uuid . '|' . $invoice_id . '|' . $status . '|' . $amount;
            $expected_sig  = hash_hmac('sha256', $expected_data, $callback_secret);

            if (!hash_equals($expected_sig, $signature)) {
                wc_get_logger()->error('CamerPay webhook : signature invalide', [
                    'source'   => 'camerpay',
                    'uuid'     => $uuid,
                    'expected' => substr($expected_sig, 0, 12) . '...',
                    'received' => substr($signature, 0, 12) . '...',
                ]);
                status_header(401);
                exit('Signature invalide.');
            }
        }

        // Retrouve la commande WC via _camerpay_uuid ou via le merchant_invoice_id
        // (format : "WC-{order_id}")
        $order = $this->find_order($uuid, $invoice_id);
        if (!$order) {
            wc_get_logger()->warning('CamerPay webhook : commande introuvable', [
                'source'     => 'camerpay',
                'uuid'       => $uuid,
                'invoice_id' => $invoice_id,
            ]);
            status_header(404);
            exit('Commande introuvable.');
        }

        // Mise a jour du status WC en fonction du status CamerPay
        switch ($status) {
            case 'completed':
                if (!$order->is_paid()) {
                    $order->payment_complete($uuid);
                    $order->add_order_note('Paiement CamerPay confirme. UUID : ' . $uuid . ' / Montant : ' . $amount);
                }
                break;

            case 'failed':
            case 'cancelled':
                $order->update_status('failed', 'Paiement CamerPay echoue. UUID : ' . $uuid);
                break;

            case 'refunded':
                $order->update_status('refunded', 'Paiement CamerPay rembourse. UUID : ' . $uuid);
                break;

            case 'pending':
            case 'processing':
                // pas de changement d'etat, juste une note
                $order->add_order_note('CamerPay : statut intermediaire = ' . $status);
                break;

            default:
                wc_get_logger()->warning('CamerPay webhook : status inconnu', [
                    'source' => 'camerpay',
                    'uuid'   => $uuid,
                    'status' => $status,
                ]);
        }

        status_header(200);
        exit('OK');
    }

    /**
     * Cherche la commande WC matchant l'UUID ou l'invoice_id CamerPay.
     */
    private function find_order($uuid, $invoice_id) {
        // 1. Recherche via meta _camerpay_uuid (le plus fiable)
        $orders = wc_get_orders([
            'meta_key'   => '_camerpay_uuid',
            'meta_value' => $uuid,
            'limit'      => 1,
        ]);
        if (!empty($orders)) {
            return reset($orders);
        }

        // 2. Fallback : parsing du invoice_id (format "WC-{id}")
        if (strpos($invoice_id, 'WC-') === 0) {
            $order_id = (int) substr($invoice_id, 3);
            $order = wc_get_order($order_id);
            if ($order) return $order;
        }

        return null;
    }
}
