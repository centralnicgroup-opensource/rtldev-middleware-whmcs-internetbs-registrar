# Internet.bs WHMCS Registrar Module

The Internet.bs Registrar Module is shipped with WHMCS but we have own version of this Registrar Module which should be used instead of the WHMCS built-in Module.

## Supported Features

- Registrar TLD Sync / Pricing Import (WHMCS 7.10)
- Domain & Transfer Synchronization
- Internationl Domain Names (IDNs)
- Additional Domain Fields
- Premium Domains
- Domain Registration
- Domain Renewal
- Domain Transfer
- Domain Management
- WHOIS Update
- Nameserver Registration & Management
- Get EPP Code
- DNS Management
- Email Forwarding
- URL Forwarding
- WHOIS Privacy / ID Protection

## Installation and Migration from older versions

To install our module please download the latest version from [here](https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs-internetbs-registrar/archive/refs/heads/master.zip),
unpack it locally and then:

1. Copy the **ibs** folder to whmcs modules/registrars
2. Copy trademarkClaim.php to root of your WHMCS installation(needed for new gTLDs)
3. Copy trademarkClaim.tpl to the your current template directory(needed for new gTLDs)
4. Copy the file itterms.html to the root of your WHMCS installation (needed for .it domains)
5. Optionally copy the files in the **lang** folder to root of your WHMCS installation (Currently we only bundle the Italian transaction. See [below](#localization) for details on adding your own.)
6. Login to whmcs Admin area and activate the module.
7. Fill in your API key and password, chose whether you want to use the live or the test environment and save. If using in test mode please use testapi/testpass for API key/password.
8. NOTE: If you plan on configuring the cron job to synchronize expiration dates of your
   domains (useful when you have pending transfers or have autorenewal enabled for your
   domains in your account on our system) when the “SyncNextDueDate” checkbox is not
   checked the script will only update the status and the expiration date. If you check the
   “SyncNextDueDate” it will also update the Next Due Date which determines the time
   when WHMCS will issue a new invoice for the domain. **This only applies if you are
   using our custom sync script and not the WHMCS built in sync**.
9. If you are upgrading from a previous verson of our module or swirtchng from the one shipped with WHMCS please also execute the SQL queries in the file upgrade.sql
10. If you want to register .it, .fr, .be, etc., please configure your additional domain fields as explained [here](#additionalfields).
11. **IMPORTANT NOTE**: If you want to enable premium domains in whmcs, you need to make sure that you define an exchange rate between USD and your currency in Admin the menu Setup→Payments→Currencies. Our module explicitly sets USD when checking the price for premium names and USD price will be sent back to us when confirming the price for the premium name at registration time. Also you need to enable premiu domains for your API KEY in your account in the reseller settings page (need to change “Allow premium domains operations” from NO to YES).

<a name="additionalfields"></a>

## Additional Fields

WHMCS provides a way to define additional fields that are needed for some TLDs. We add those for our module via file WHMCSROOT/resources/domains/additionalfields.php. Initially, this file is not present and has to be created as follows:

```php
<?php
include(ROOTDIR."/modules/registrars/ibs/ibs_additionaldomainfields.php");
```

Unfortunately we do not yet have support for all TLDs that have additional fields in our WHMCS module but plan to add it in future releases.

From the TLDs that require additional data we currently support: .co.uk, .org.uk, .me.uk, .uk, .eu, .be, .asia, .fr, .re, .pm, .tf, .wf, .yt, .it, .de, .nl, .tel, .us

<a name="localization"></a>

## Localization support

Our module is in English, and we provide an Italian translation as well.
You can find it in the lang/overrides folder, and you need to copy the file italian.php to `WHMCSROOT/lang/overrides/`.

## Notes

The price sync feature supports only the following currencies: USD, EUR, GBP, CAD, AUD and JPY.
If your default currency is not one of the above then the prices are pulled from our API in USD and you need to define exchange rates between USD and your default currency in order to use this feature.

## Developer Corner

Install composer and then care about installing the below global dependencies.

```bash
composer global require squizlabs/php_codesniffer
```

Then, before committing, run:

```bash
phpcbf --standard=PSR12 -q --ignore=node_modules,vendor,templates_c .
```

This automatically uses CodeSniffer to format the source code in PSR12 code style.
