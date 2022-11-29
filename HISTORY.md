## [1.0.5](https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs-internetbs-registrar/compare/v1.0.4...v1.0.5) (2022-11-14)

### Bug Fixes

- **hook:** check if cart is empty before validating shopping cart ([cf8a0a3](https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs-internetbs-registrar/commit/cf8a0a3afd318420dfbc80cfc9583f3fb94bb715)), closes [#20](https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs-internetbs-registrar/issues/20)
- **php 8 support:** patching index access error in phone number reformatting function ([eae2815](https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs-internetbs-registrar/commit/eae281596c92c77eb5c5b12652afdc10edb81d93)), closes [#22](https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs-internetbs-registrar/issues/22)

## [1.0.4](https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs-internetbs-registrar/compare/v1.0.3...v1.0.4) (2022-07-11)

### Bug Fixes

- **hooks:** this is a fix to include_once the IBS module in the hooks file ([e11ce5f](https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs-internetbs-registrar/commit/e11ce5ff13cefecd3cca6222d00846546778b23e))

## [1.0.3](https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs-internetbs-registrar/compare/v1.0.2...v1.0.3) (2022-06-28)

### Bug Fixes

- **missing function:** causing fatal error, patched ([b5796ff](https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs-internetbs-registrar/commit/b5796ff97d3b0179423e6b13ef7b6b3bfef38d38))

## [1.0.2](https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs-internetbs-registrar/compare/v1.0.1...v1.0.2) (2022-06-10)

### Bug Fixes

- **cart & renewals:** cart timeouts, special renewal issues ([278f227](https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs-internetbs-registrar/commit/278f227324437257f6ef527b83c42f93a44acb6d))

## [1.0.1](https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs-internetbs-registrar/compare/v1.0.0...v1.0.1) (2021-11-25)

### Bug Fixes

- **registrar tld sync:** Modified the price sync to check the defaut currency of the WHMCS installation and, if that currency is supported by us, pull the prices in that currency ([3e66f13](https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs-internetbs-registrar/commit/3e66f1385753741b8487ef07b5cbb640a1471717))

# 1.0.0 (2021-08-23)

### Bug Fixes

- **upgrade:** fixed a few issues and added an upograde sql script to migrate from old or bundled module version to this new one ([a25964f](https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs-internetbs-registrar/commit/a25964fb8f2b654223ce1945810d229500deddc7))
- **whmcs.json:** fixed invalid json ([0c88c31](https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs-internetbs-registrar/commit/0c88c31173aa1b8a64b404970bace468fad64484))

### Features

- **release automation:** initial version ([fe5bb47](https://github.com/centralnicgroup-opensource/rtldev-middleware-whmcs-internetbs-registrar/commit/fe5bb47622f9059a0219c1636ba16d0a7067b3be))
