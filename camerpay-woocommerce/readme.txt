=== CamerPay for WooCommerce ===
Contributors: camerpay
Tags: cameroun, mobile money, orange money, mtn momo, paypal, stripe, paiement, woocommerce
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Acceptez Orange Money, MTN MoMo, cartes bancaires et PayPal au Cameroun via CamerPay.

== Description ==

CamerPay est une passerelle de paiement specialisee pour le Cameroun et l'Afrique centrale. Ce plugin officiel ajoute CamerPay comme methode de paiement dans WooCommerce.

Vos clients peuvent payer avec :

* **Orange Money** (USSD ou Web)
* **MTN MoMo** (Collections)
* **Cartes bancaires** Visa, MasterCard (via Stripe)
* **PayPal**

= Caracteristiques =

* Integration en 5 minutes (cle API + Bearer Token)
* Mode sandbox pour tester sans argent reel
* Webhook signe HMAC-SHA256 pour validation cote serveur
* Remboursements automatiques depuis l'admin WooCommerce
* Logs detailles pour debug
* Compatible HPOS (High-Performance Order Storage)

= Pre-requis =

1. Un compte marchand CamerPay (gratuit) : https://camerpay.biz/register
2. KYC valide (au moins niveau 1) pour encaisser en production
3. Cle API generee depuis votre dashboard CamerPay
4. WooCommerce 6.0 ou plus

== Installation ==

1. Telechargez le fichier ZIP du plugin
2. Dans WordPress : Plugins > Ajouter > Televerser un plugin
3. Selectionnez le ZIP et activez
4. Allez dans WooCommerce > Reglages > Paiements
5. Activez CamerPay et collez votre cle API
6. (Optionnel) Activez le mode sandbox pour tester
7. (Important) Collez le secret HMAC pour valider les webhooks

== Configuration webhook ==

Le webhook est automatiquement disponible a l'URL :

`https://votre-boutique.com/?wc-api=camerpay_webhook`

Dans votre dashboard CamerPay (https://camerpay.biz/client/api), configurez :
- URL webhook = l'URL ci-dessus
- Secret HMAC = la meme valeur que celle saisie dans le plugin

== FAQ ==

= Quels sont les frais ? =

Les frais de transaction dependent de votre plan CamerPay :
- Plan Starter : 3% par transaction (100 tx/mois max)
- Plan Business : 1.5% par transaction (illimite)
- Plan Pro : 1% par transaction (BYOK possible)

Consultez https://camerpay.biz/tarifs pour les details.

= Le paiement Mobile Money fonctionne-t-il a l'etranger ? =

Orange Money et MTN MoMo via CamerPay fonctionnent UNIQUEMENT depuis les numeros camerounais (+237 6XX...). Pour les clients etrangers, utilisez Stripe ou PayPal.

= Combien de temps pour recevoir les fonds ? =

Apres KYC valide, vous pouvez demander un retrait MoMo via /client/payouts. Traitement sous 24h ouvrees.

== Changelog ==

= 1.0.0 =
* Initial release
* Gateway WooCommerce
* Webhook HMAC-SHA256
* Refund process_refund
* Mode sandbox toggle
* Debug logs

== Support ==

Pour toute question : support@camerpay.biz
Documentation API : https://camerpay.biz/documentation
