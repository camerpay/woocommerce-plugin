<?php
/**
 * Plugin Name: CamerPay for WooCommerce
 * Plugin URI:  https://camerpay.biz/integrations/woocommerce
 * Description: Acceptez les paiements Mobile Money (Orange Money, MTN MoMo), cartes (Visa/MasterCard via Stripe) et PayPal au Cameroun directement sur votre boutique WooCommerce.
 * Version:     1.0.0
 * Author:      CamerPay
 * Author URI:  https://camerpay.biz
 * License:     GPL-2.0+
 * Text Domain: camerpay
 * Domain Path: /languages
 *
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * WC tested up to:      9.4
 */

if (!defined('ABSPATH')) {
    exit; // Acces direct interdit
}

define('CAMERPAY_VERSION',       '1.0.0');
define('CAMERPAY_PLUGIN_FILE',   __FILE__);
define('CAMERPAY_PLUGIN_DIR',    plugin_dir_path(__FILE__));
define('CAMERPAY_PLUGIN_URL',    plugin_dir_url(__FILE__));

/**
 * Verifie que WooCommerce est actif. Sinon, affiche une notice admin
 * et ne charge pas le reste du plugin (evite un fatal error).
 */
function camerpay_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>CamerPay :</strong> WooCommerce est requis pour utiliser ce plugin. ';
            echo '<a href="' . esc_url(admin_url('plugin-install.php?tab=plugin-information&plugin=woocommerce')) . '">Installer WooCommerce</a>';
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

/**
 * Charge le plugin une fois que tous les autres plugins sont initialises
 * (pour s'assurer que WooCommerce est disponible).
 */
add_action('plugins_loaded', 'camerpay_init', 11);
function camerpay_init() {
    if (!camerpay_check_woocommerce()) {
        return;
    }

    // Classes du plugin
    require_once CAMERPAY_PLUGIN_DIR . 'includes/class-wc-camerpay-api.php';
    require_once CAMERPAY_PLUGIN_DIR . 'includes/class-wc-camerpay-gateway.php';
    require_once CAMERPAY_PLUGIN_DIR . 'includes/class-wc-camerpay-webhook.php';

    // Enregistre le gateway aupres de WooCommerce
    add_filter('woocommerce_payment_gateways', function($gateways) {
        $gateways[] = 'WC_CamerPay_Gateway';
        return $gateways;
    });

    // Endpoint webhook : https://votre-boutique.com/?wc-api=camerpay_webhook
    add_action('woocommerce_api_camerpay_webhook', [new WC_CamerPay_Webhook(), 'handle']);

    // i18n
    load_plugin_textdomain('camerpay', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

/**
 * Au moment de l'activation, verifier les pre-requis et stocker
 * la version (utile pour les migrations futures).
 */
register_activation_hook(__FILE__, function() {
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('CamerPay requiert PHP 7.4 ou superieur. Vous avez ' . PHP_VERSION);
    }
    update_option('camerpay_version', CAMERPAY_VERSION);

    // Pre-fill credentials si le ZIP a ete telecharge depuis le dashboard
    // CamerPay marchand (qui injecte camerpay-prefill.json). N'ecrase pas
    // les options existantes (preserve la config d'un re-install).
    $prefillPath = CAMERPAY_PLUGIN_DIR . 'camerpay-prefill.json';
    if (file_exists($prefillPath)) {
        $data = json_decode(file_get_contents($prefillPath), true);
        if (is_array($data)) {
            $existing = get_option('woocommerce_camerpay_settings', []);
            $merged = array_merge([
                'enabled'         => 'yes',
                'title'           => 'Paiement Mobile Money / Carte (CamerPay)',
                'description'     => 'Payez avec Orange Money, MTN MoMo, carte bancaire ou PayPal.',
                'api_key'         => $data['api_key'] ?? '',
                'callback_secret' => $data['callback_secret'] ?? '',
                'testmode'        => 'no',
                'debug'           => 'no',
            ], $existing);
            update_option('woocommerce_camerpay_settings', $merged);
        }
    }
});

/**
 * Lien direct vers les reglages depuis la liste des plugins.
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=camerpay') . '">' . __('Reglages', 'camerpay') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});
