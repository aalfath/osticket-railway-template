# Changelog

## v1.18.4-1 (2026-11-16)

First release of the Railway template.

- osTicket v1.18.4 (official release zip, sha256 `a4187df5f52dd41625e6b61b322245853542e12b5969a45f0a6d81f7ab8151a7`) on `php:8.4.27-apache-trixie@sha256:884f64b47174de2c0e813270fe7b24a18605729fcebe201302d98c38805530d0`.
- Unattended first install (no public web installer), config written from variables on every boot, automatic schema upgrades, built-in cron every 5 minutes, `/healthz.php` health check.
- Railway MySQL for all data.
