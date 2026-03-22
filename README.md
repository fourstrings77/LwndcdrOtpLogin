# LwndcdrOtpLogin

A Shopware 6 plugin that adds optional two-factor authentication (2FA) to the storefront customer login via **TOTP** (authenticator apps) or **Email OTP**.

## What it solves

By default, Shopware's storefront login is single-factor — username and password only. This plugin adds a voluntary second step: after a successful login, customers who have enabled 2FA are redirected to an OTP verification page before accessing their account. Customers who have not set up 2FA are not affected.

## Features

- **TOTP (RFC 6238)** — works with any authenticator app (Google Authenticator, Authy, etc.). A QR code is shown during setup.
- **Email OTP** — a one-time code is sent to the customer's registered email address at each login.
- **Customer-controlled** — customers enable and manage 2FA themselves from their account page (`/account/otp`). No admin intervention required.
- **Rate limiting** — the verification endpoint is protected against brute-force attacks (5 attempts per 15 minutes per IP).
- **Secure storage** — TOTP secrets are encrypted at rest using OpenSSL; email OTP codes are hashed before persistence and expire after a configurable TTL (default: 10 minutes). Codes are single-use.
- **Shopware-native** — uses Shopware's EntityRepository, Migration system, MailService, RateLimiter, and Twig template inheritance throughout. No raw SQL, no custom frameworks.

## Requirements

- Shopware `~6.7.0`
- PHP 8.1+

## Installation

```bash
# Copy the plugin into your Shopware custom/plugins directory, then:
php bin/console plugin:refresh
php bin/console plugin:install --activate OtpLogin
php bin/console database:migrate --all
php bin/console cache:clear
```

## Configuration

Go to **Admin → Extensions → My extensions → OtpLogin → Configure**:

| Setting | Description | Default |
|---|---|---|
| TOTP Encryption Key | A strong, random passphrase used to encrypt TOTP secrets at rest. **Do not change this after customers have set up TOTP.** | — |
| Email OTP expiry (minutes) | How long an emailed OTP code remains valid. | 10 |

## How it works

1. Customer logs in normally.
2. `CustomerLoginSubscriber` intercepts the `CustomerLoginEvent`. If the customer has TOTP or Email OTP enabled, an `otp_pending` session state is set and — for Email OTP — a code is sent.
3. The customer is redirected to `/otp/verify` to enter their code.
4. On success the session state is cleared and the customer lands on their account page.
5. Any storefront request while `otp_pending` is active is redirected back to `/otp/verify`, except for the verify route itself and `/logout`.

TOTP takes priority when both methods are active.

## Routes

| Route | Description |
|---|---|
| `GET /account/otp` | Customer OTP settings page (login required) |
| `POST /account/otp/email/toggle` | Enable / disable Email OTP |
| `POST /account/otp/totp/setup` | Start TOTP setup (generates QR code) |
| `POST /account/otp/totp/confirm` | Confirm TOTP setup with a test code |
| `POST /account/otp/totp/disable` | Disable TOTP and wipe the stored secret |
| `GET/POST /otp/verify` | OTP verification page shown after login |

## Out of scope

This plugin is intentionally focused. The following are not implemented:

- Backup / recovery codes
- Admin UI for resetting customer OTP
- SMS OTP
- Mandatory 2FA enforcement

## License

MIT