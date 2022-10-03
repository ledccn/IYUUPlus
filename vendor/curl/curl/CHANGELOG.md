# CHANGELOG

All notable changes to this project will be documented in this file. This project adheres to [Semantic Versioning](http://semver.org/).

## 2.4.0 (29. August 2022)

+ [#84](https://github.com/php-mod/curl/pull/84) Added `.gitattributes`.
+ [#85](https://github.com/php-mod/curl/pull/85) Added `CURLOPT_CUSTOMREQUEST` for get and post methods.
+ [#91](https://github.com/php-mod/curl/pull/91) Added `$asJson` param for `put()` and `patch()` requests.
+ [#61](https://github.com/php-mod/curl/issues/61) Adjust user agent version to latest version.
+ [#74](https://github.com/php-mod/curl/pull/75) Fixed PHPDoc Block, Added more Unit Tests.
+ Added GitHub Actions Tests (from 5.6 - 8.1)

## 2.3.3 (30. November 2021)

+ Support php 8.1

## 2.3.2 (11. April 2021)

+ Fix a security issue in the test files. Thanks to Erwan from wpscan.com.

## 2.3.1 (21. January 2021)

+ Supports PHP8

## 2.3.0 (19. March 2019)

+ add asJson option (#67)

## 2.2.0 (4. December 2018)

+ Added some getters.

## 2.1.0 (17. November 2018)

+ CurlFile fix
+ This is not tested, but we are facing the same problem with CurlFile Uploads (https://github.com/php-mod/curl/issues/46) - This *should* do the trick.
+ Update README.md
+ cs fix

## 2.0.0 (15. November 2018)

+ Drop php 5.3, 5.4 and 5.5 support.
+ Use Gitlab CI instead of Travis CI.
