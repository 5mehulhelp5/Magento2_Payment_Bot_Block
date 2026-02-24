# Mage2 Module Genaker BlockPaymentBot

Blocks bot abuse on payment endpoints (guest-carts payment-information, totals-information). Uses Redis for counters and block state; configurable via Magento Admin or ENV variables.

## Main Functionalities

- **Rate limiting** — Limit payment attempts per IP/cart within a configurable time window; block for a configurable duration when exceeded.
- **Magento Admin config** — Enable/disable, IP whitelist, behind proxy/CDN, require form check, block time, record time, block count, per-path Bot Rules.
- **Admin Blocked IPs page** — System → Blocked IPs: view currently blocked IPs with reason, URL, counter, expiry.
- **CLI Commands**:
  - `php bin/magento genaker:blockbot:show-blocked-ips` — List blocked IPs and display configuration (IP whitelist, bot rules).
  - `php bin/magento genaker:blockbot:check-db-integrity` — Scan database for suspicious content patterns (XSS, injection attempts, etc.).
- **IP whitelist** — Whitelisted IPs are never blocked.
- **Behind trusted proxy** — Optional use of `X-Forwarded-For`, `Fastly-Client-IP`, `CF-Connecting-IP` when enabled in config (avoids spoofing when not behind proxy).
- **Bot Rules** — Per-path regex rules with custom request count and block time (e.g. payment-information, register, contact).

## How to test

Send POST requests to:

```
{domain}/rest/default/V1/guest-carts/{cartId}/payment-information
```

With the same cart ID multiple times; after the configured limit (default 20) the request will be blocked for the block time (default 2 minutes).

For **test mode only** (e.g. with `PEST` env set), you can use GET with `?bot_test=1`:

```
https://domain.com/rest/default/V1/guest-carts/GKxNF6em8IzxaZlk78YR3soEYby/payment-information?bot_test=1
```

## ENV variables (override Admin config)

- **MAGE_BOT_BLOCK_TIME** — Block duration in minutes. Default 2.
- **MAGE_BOT_RECORD_TIME** — Time window in minutes for the counter. Default 2.
- **MAGE_BOT_BLOCK_COUNT** — Request limit per IP/cart in that window. Default 20.

Optional: [Genaker/mage-dotenv](https://github.com/Genaker/mage-dotenv) to load ENV from a file.

## Magento Admin config

**Stores → Configuration → Checkout → Block Payment Bot**

- **Enable** — Turn protection on/off.
- **IP Whitelist** — IPs never blocked (Name + IP per row).
- **Behind Trusted Proxy / CDN** — Use proxy headers for client IP only when behind a trusted proxy.
- **Require Form Check Parameter** — Validate `form_check` in payment payload (recommended when frontend is installed).
- **Bot Block Time (minutes)** — How long to block after limit. Default 2.
- **Bot Record Time (minutes)** — Window for counting attempts. Default 2.
- **Bot Block Count** — Max attempts in that window. Default 20.
- **Bot Rules** — Per-path overrides (path regex, request count, block time). Examples: payment-information, register, contact.

**System → Blocked IPs** — View active blocked IPs.

## Database Integrity Check

The module includes a database integrity scanner that checks for suspicious patterns in database content (XSS, injection attempts, etc.).

### CLI Command

```bash
php bin/magento genaker:blockbot:check-db-integrity
```

This command scans the following tables by default:
- `cms_page` (content, title fields)
- `cms_block` (content field)
- `core_config_data` (value field)

### Detected Patterns

The scanner looks for suspicious patterns such as:
- `eval(atob(` — Base64-encoded eval attempts
- `eval(String.fromCharCode(` — Character code-based eval
- `eval(decodeURI(` — URI-decoded eval attempts
- `javascript:` — JavaScript protocol URLs
- `onerror=eval` / `onload=eval` — Event handler injection
- `unescape(` — Unescape function usage

### Configuration

The tables and patterns can be configured via Magento Admin:
- **Stores → Configuration → Checkout → Block Payment Bot → Integrity Tables Config** — JSON configuration for tables to scan
- **Stores → Configuration → Checkout → Block Payment Bot → Integrity Patterns** — JSON configuration for regex patterns to detect
- **Stores → Configuration → Checkout → Block Payment Bot → Integrity Recent Only** — Enable to scan only recent records
- **Stores → Configuration → Checkout → Block Payment Bot → Integrity Recent Days** — Number of days for recent-only scan (default: 30)

## Installation

\* = in production please use the `--keep-generated` option

### Type 1: Zip file

- Unzip in `app/code/Genaker`
- `php bin/magento module:enable Genaker_BlockPaymentBot`
- `php bin/magento setup:upgrade`\*
- `php bin/magento cache:flush`

### Type 2: Composer

- Add the repository, then: `composer require genaker/module-blockpaymentbot`
- `php bin/magento module:enable Genaker_BlockPaymentBot`
- `php bin/magento setup:upgrade`\*
- `php bin/magento cache:flush`

## Specifications

- **Observer:** `core_abstract_load_before` → `Genaker\BlockPaymentBot\Observer\Webapi\Core\AbstractLoadBefore`
- **Requirement:** phpRedis. If Redis is not configured, the module does not break the site but protection is disabled.

## Testing (curl)

```bash
curl -i -X POST https://www.MYDOMAIN.com/rest/default/V1/guest-carts/GKxNF6em8IzxaZlk78YR3soEYby/payment-information
```

- First requests (up to limit): normal response or validation errors.
- After limit: `Bye!` (HTTP 511).

---

`genaker/module-blockpaymentbot`
