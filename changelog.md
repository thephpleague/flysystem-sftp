# Changelog

## 1.1.0 - 2022-01-04

### Added

* Added `usePingForConnectivityCheck` flag to use `->ping()` for connectivity checks.

## 1.0.22 - 2019-10-16

* Trim extra slashes while setting connection root (#93)

## 1.0.21 - 2019-09-19

* Support double authentication (#63)
* Added marker interface to some exceptions (#86)

## 1.0.20 - 2019-06-07

* From #77 / Bugfix: Directories or files named '0' do not show up in listContents

## 1.0.19 - 2019-04-25

* Fixed casing of `privateKey` property

## 1.0.18 - 2019-01-07

* Throw an Exception if can't connect to check Host Fingerprint

## 1.0.17 - 2018-10-14

* Don't return visibility when not in scope

## 1.0.16 - 2018-07-08

### Altered

* Stat cache is always disabled.

## 1.0.15 - 2017-11-16

### Fixed

* Added missing `path` to read and readStream response.
* Upgraded phpunit and lost support for php <=5.5

## 1.0.14 - 2017-07-11

### Fixed

* Prevent private key exposure in php log when open_basedir restriction is in effect.

## 1.0.13 - 2016-12-08

### Fixed

* Undefined index when object type is not defined

## 1.0.12 - 2016-10-17

### Improved

* This adapter now uses the username/password getters so it uses the safe storage mechanism in the main package (1.0.29)

## 1.0.11 - 2016-10-17

### Improved 

* Added a fingerprint verification.

## 1.0.9 - 2016-02-19

### Fixed

* The absolute path is now stores for better external referencing.
* When a private key is given is ca no longer trigger a warning with an open basedir restriction.

## 1.0.8 - 2015-12-08

### Fixed

* Added .gitattributes for smaller distributions.

## 1.0.7 - 2015-01-25

### Fixed

* Updated phpseclib to v 2.0.0
* Allow the connection to be injected.

## 1.0.6 - 2015-09-20

### Fixed

* [isConnected] Missing function added.

## 1.0.5 - 2015-05-26 

### Fixed

* [readStream] This method no longer uses a polyfill.
