# Internet.bs WHMCS Registrar Module

> DEPRECATION NOTICE: Find the new ibs module version now available for download over this [link](https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs/raw/main/whmcs-ibs-registrar-latest.zip). The new repository is https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs. Issue to be reported there. Some of the files got moved to the [archive branch](https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs/tree/archive/ibs) of that repository as they are not related to the registrar module itself.

Access to source code isn't at least for now not yet available. Report issues please in that repository. It is the successor of this one and the right place to address issues. Just ensure to mention "ibs" whenever you're reporting issues.
I did my best with refactoring the entire module - still, it might include a bug - we changed ~2.5k lines of code.

If that rewrite isn't patching your issue (I am sure it is not), please report it in the new repository.
Thanks so much!

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

To install our module please download the latest version from [here](https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs/raw/main/whmcs-ibs-registrar-latest.zip), unpack it locally and then:

1. Copy the **ibs** folder to whmcs modules/registrars
2. _DEPRECATED_: Copy trademarkClaim.php to root of your WHMCS installation(needed for new gTLDs)
3. _DEPRECATED_: Copy trademarkClaim.tpl to the your current template directory(needed for new gTLDs)
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

This part of the docs got moved to [here](https://centralnicgroup-public.github.io/rtldev-middleware-documentation/docs/internetbs/whmcs/whmcs-ibs-registrar#additional-fields).

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
