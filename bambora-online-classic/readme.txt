=== Bambora Online ePay ===
Contributors: bambora
Tags: woocommerce, woo commerce, payment, payment gateway, gateway, subscription, subscriptions, bambora, epay, integration, woocommerce bambora, woocommerce epay, woocommerce bambora online classic, psp
Requires at least: 4.0.0
Tested up to: 5.2.4
Stable tag: 5.0.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Integrates Bambora Online ePay payment gateway into your WooCommerce installation.

== Description ==
With Bambora Online ePay for WooCommerce, you are able to integrate the Bambora Online ePay payment window into your WooCommerce installation and start receiving secure online payments.

= Features =
* Receive payments securely through the Bambora Online ePay payment window
* Create and handle subscriptions
* Get an overview over the status for your payments directly from your WooCommerce order page.
* Capture your payments directly from your WooCommerce order page.
* Credit your payments directly from your WooCommerce order page.
* Delete your payments directly from your WooCommerce order page.
* Sign up, process, cancel, reactivate and change subscriptions
* Supports WooCommerce 2.6 and up.
* Supports WooCommerce Subscription 2.x

== Installation ==
1. Go to your WordPress administration page and log in. Example url: https://www.yourshop.com/wp-admin

2. In the left menu click **Plugins** -> **Add new**.

3. Click the **Upload plugin** button to open the **Add Plugins** dialog.

4. Click **Choose File** and browse to the folder where you saved the file from step 1. Select the file and click **Open**.

5. Click **Install Now**

6. Click **Activate Plugin** 

7. In the left menu click **WooCommerce** -> **Settings**.

8. Click the tab **Checkout** and select **ePay**

9. Enter and adjust the settings which are described in the **Settings** section.

10. Click **Save Changes** when done and you are ready to use Bambora Online ePay

<a href="https://woocommerce.wpguiden.dk/en/configuration">Click here for more information about **Settings**</a>

== Changelog ==
= 5.0.4 =
* Adds fix for declineurl using encoded version of & 

= 5.0.3 =
* Fix for unsupported date/time function in WC below 3.1 

= 5.0.2 =
* Adds hooks for payment actions like capture, refund and delete
* Code refactoring to comply with WordPress 5.x 

= 5.0.1 =
* Fix for Payment logoes not always displayed as payment description.
* Fix for ordernumber cleaning. It now only supports letters from a-z and all digits

= 5.0.0 =
* The payment window can no longer be displayed as an Overlay and will now always be displayed as Full Screen
* Adds support for multiple subscription sign-up

= 4.0.6 =
* Fix for Chrome Iframe redirect issue when using mobile payment window

= 4.0.5 =
* Adds "Payment Type ID" to order meta info

= 4.0.4 =
* Fix for skipping paymentwindow if switching subscriptions with amount beeing zero

= 4.0.3 = 
* Fix for invoice error if product quantity is 2 or larger

= 4.0.2 =
* Adds Payment Type and logo to Order detail page in the backoffice

= 4.0.1 =
* Fix for renewal order status not beeing set to failed when subscription payments fails

= 4.0.0 =
* Refactoring of module to comply with WooCommerce 3.x and WooCommerce Subscription 2.x standards
* Adds Change payment for subscriptions
* Adds Reactivation of failed subscriptions
* Adds Reactivation of canceled subscriptions
* Removed support for multiple-subscriptions
* Improved information flow
* Improved error handling
* Adds filter to callback url and callback completion
* Changed payment icons
* Code cleanup and performance enhancement
* Updates orders from earlier modules to comply with new standards
* Labels and Translations updated
* Adds support for WooCommerce Sequential Order Numbers
* Adds debug logging with access from module configuration page
* Refactoring of invoice lines
* Refactoring of order actions

= 3.1.5 =
* Fix for transaction id not being saved in Woocommerce 3.x
* Adds check for bambora subscription id

= 3.1.4 =
* Changed bambora Logo size and css

= 3.1.3 =
* Fix for using wrong Helper class

= 3.1.2 =
* Improved quality for Bambora logo
* Improved quality for Bambora payment type logoes
* Adds rounding option in module configuration
* Adds more language codes
* Refactoring of module css files

= 3.1.1 =
* Fixes bug where a failed subscription cancellation could cause an unhandled exception
* Updates translations

= 3.1.0 =
* Adds support for WooCommerce 3.0

= 3.0.2 =
* Fix for different property name between subscription and payment.asmx on pbsError
* Fix for refund keeps spinning and not reloading page.

= 3.0.1 =
* Fix for ePay callback keeps posting when order is completed

= 3.0.0 =
* Module name changed from woocommerce-gateway-epay-dk to bambora-online-classic
* Code refactored to comply with WordPress code standard
* Translations changed to fit the new module name

= 2.6.7 =
* Fix for wrong parameter pbsresponse

= 2.6.6 =
* Adds readme.txt for markedplace integration
* Adds support for nb_NO
* Fix for subscription_cancellation throwing exception

= 2.6.5 =
* Fix for WooCommerce Subscription deprecation and errors
* Removed support for WooCommerce Subscription 1.x
* Adds Cancelation of Subscriptions
* Adds check for missing module configuration settings
* Fix for ePay error get error messages
* Adds calculation of minor units based on currency
* Adds Mobile payment window toggle
* Removed translations of module configuration settings
* Adds better handling of invoice data
* Code refactoring

= 2.6.4 =
* Adds icons for available payment types to the checkout page description
* Updates the admin order ePay panel with new styling and additional information on the payment
* Adds CSS styling
* Fixes error handling on failed API calls
* Fixes various minor issues
* Adds support for WooCommerce Subscriptions 2.0+

= 2.6.3 =
* Improves compatibility with other payment gateway modules.

= 2.6.2 =
* Fixes potential Undefined Index error

= 2.6.1 =
* Fix for wrong rounding on soap request

= 2.6.0 = 
* Fix for Undefined index: enableinvoice and preg_replace(/e) deprication
* Fix for no translation of texts
* Fix for order update on callback with custom order status
* Fix for German language
* Add language Norwegian (Bookmål)
* Add extended cms info on payment request
* Add error description on failed subscriptions
* Code cleanup

= 2.5.0 =
* WooCommerce Subscription upgraded to 2.0
* Module structural changes and minor bug fixes