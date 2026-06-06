# CamerPay for WooCommerce

[![License: GPL v2](https://img.shields.io/badge/License-GPL_v2+-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WooCommerce: 6.0+](https://img.shields.io/badge/WooCommerce-6.0%2B-purple.svg)](https://woocommerce.com)
[![PHP: 7.4+](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)

Plugin officiel WooCommerce pour [CamerPay](https://camerpay.biz) — passerelle de paiement Mobile Money, cartes et PayPal au Cameroun.

## Caractéristiques

- ✅ Installation en 5 minutes (clé API pré-remplie depuis le dashboard CamerPay)
- ✅ Mode sandbox pour tester sans argent réel
- ✅ **Vérification HMAC-SHA256 native** sur webhooks (anti-spoofing)
- ✅ Remboursements depuis l'admin WooCommerce
- ✅ Compatible HPOS (High-Performance Order Storage)
- ✅ Multi-fichier (architecture WP standard)

## Pré-requis

- WordPress 5.8 ou supérieur
- WooCommerce 6.0 ou supérieur (testé jusqu'à 9.4)
- PHP 7.4 ou supérieur
- Un compte marchand CamerPay : [s'inscrire gratuitement](https://camerpay.biz/register)

## Installation

### Méthode 1 — Téléchargement depuis votre dashboard (recommandé)

1. Connectez-vous à votre [dashboard CamerPay > Intégrations](https://camerpay.biz/client/integrations)
2. Trouvez la carte WooCommerce et cliquez **Télécharger**
3. Le ZIP contient le plugin **+ vos credentials pré-remplis**
4. Dans WordPress : Extensions → Ajouter → Téléverser une extension
5. Téléversez le ZIP, cliquez "Installer maintenant" puis "Activer"

### Méthode 2 — Installation manuelle

1. Téléchargez la dernière release depuis [GitHub Releases](https://github.com/camerpay/woocommerce-plugin/releases)
2. WordPress > Extensions > Téléverser
3. Activez
4. WooCommerce → Réglages → Paiements → CamerPay
5. Renseignez Clé API et Secret HMAC depuis [camerpay.biz/client/api](https://camerpay.biz/client/api)

## Configuration webhook

URL webhook automatique :

```
https://votre-boutique.com/?wc-api=camerpay_webhook
```

À renseigner dans votre dashboard CamerPay > API & webhooks.

## Documentation complète

[camerpay.biz/docs/integrations/woocommerce](https://camerpay.biz/docs/integrations/woocommerce)

## Build le ZIP localement

```bash
php build-zip.php
```

## Support

- Email : contact@camerpay.biz
- Issues : https://github.com/camerpay/woocommerce-plugin/issues
- Documentation : https://camerpay.biz/docs

## Licence

[GPL-2.0+](LICENSE)
