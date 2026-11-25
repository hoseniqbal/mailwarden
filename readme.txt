=== MailWarden: Order Email Delivery Checker for WooCommerce ===
Contributors: iqbal511138
Tags: woocommerce, order emails, email log, resend email, smtp
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See every email WooCommerce sent for an order, whether it worked and why not, and resend it in one click.

== Description ==

"I never got my order confirmation." Every store owner hears it, and WooCommerce gives you no way to check. Was the email sent? Did it fail? Was it the wrong address?

MailWarden answers that on the order screen itself.

= What it does =

* **Order emails box.** Every order shows which WooCommerce emails were sent, when, to which address, and whether WordPress handed them to the mail server. When one failed, you see the reason.
* **Resend in one click.** Pick an email (processing, completed, invoice, new order and more) and resend it. The resend is recorded too, with a note on the order.
* **Failure warning.** When several order emails fail within an hour, a warning appears at the top of every admin screen, so a broken mail setup does not go unnoticed for days.
* **Setup check.** Shows whether you send through an SMTP plugin, whether your From address matches your domain, and whether your domain has SPF and DMARC records. Each check says what to do next.
* **Test email** to any address, with the exact error when it fails.
* **Email log** with a "Failed only" filter.

MailWarden is not a general mail log. It focuses on WooCommerce order emails and works with any SMTP plugin.

= Privacy =

Only the email type, a shortened recipient (ja***@example.com), the subject and the result are stored. Message bodies are never stored. The setup check looks up your own domain's public DNS records (SPF and DMARC) from your server; nothing is sent to any other service.

== Installation ==

1. Install and activate MailWarden. WooCommerce must be active.
2. Open MailWarden in the admin menu and read the setup check.
3. Press "Send a test email".
4. Open any order: the Order emails box is below the order items.

== Frequently Asked Questions ==

= "Sent" means the customer got it? =

It means WordPress handed the email to your mail server without an error. Whether it reached the inbox depends on the mail service, which is why the setup check matters.

= Emails sent before I installed MailWarden? =

They are not listed. MailWarden records from the moment it is active.

= Can I resend an email that is turned off in WooCommerce? =

Only the invoice email. Turn the others on in WooCommerce, Settings, Emails first.

= Does it send data anywhere? =

No. The only outside lookup is a DNS query for your own domain's SPF and DMARC records, cached for 12 hours.

== Screenshots ==

1. Overview with the setup check.
2. Order emails box on the order screen.
3. Email log.

== Changelog ==

= 1.0.0 =
* First release: order email tracking, one-click resend, failure warning, setup check, test email, email log.

== Upgrade Notice ==

= 1.0.0 =
First release.
