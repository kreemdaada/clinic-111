# Legal Pages Setup (Impressum & Datenschutz)

DentalFinance exposes public legal pages at `/impressum` and `/datenschutz`. The content is rendered from Blade templates and operator data from `config/legal.php` (environment variables).

**Important:** The generated pages are a technical foundation. They must be reviewed legally before production publication. This document does not constitute legal advice.

## Environment variables

| Variable | Required for production | Description |
|---|---|---|
| `LEGAL_OPERATOR_NAME` | **Yes** | Full legal name of the operator (natural or legal person) |
| `LEGAL_BUSINESS_NAME` | No (default: `DentalFinance`) | Product or trade name |
| `LEGAL_ADDRESS_STREET` | **Yes** | Street and house number (service of process address) |
| `LEGAL_ADDRESS_POSTAL_CODE` | **Yes** | Postal code |
| `LEGAL_ADDRESS_CITY` | **Yes** | City |
| `LEGAL_ADDRESS_COUNTRY` | No (default: `Deutschland`) | Country |
| `LEGAL_EMAIL` | **Yes** | General contact e-mail (Impressum) |
| `LEGAL_PHONE` | No | Phone number (shown only if set) |
| `LEGAL_VAT_ID` | No | VAT ID (section shown only if set) |
| `LEGAL_COMMERCIAL_REGISTER` | No | Register court (section shown only if set) |
| `LEGAL_REGISTER_NUMBER` | No | Register number (section shown only if set) |
| `LEGAL_PRIVACY_EMAIL` | No | Separate privacy contact; falls back to `LEGAL_EMAIL` |
| `LEGAL_SUPERVISORY_AUTHORITY_NAME` | Recommended | Supervisory authority for data protection complaints |
| `LEGAL_SUPERVISORY_AUTHORITY_URL` | No | URL of the supervisory authority |
| `LEGAL_EDITORIAL_RESPONSIBLE_NAME` | No | Only if editorial content under § 18 MStV applies |
| `LEGAL_HOSTING_PROVIDER_NAME` | Recommended | Hosting provider name for privacy policy (e.g. IONOS) |
| `LEGAL_PRIVACY_LAST_UPDATED` | Recommended | Static update label (e.g. `Juni 2026`) — do not auto-update daily |

## Behaviour when values are missing

| Environment | Behaviour |
|---|---|
| `local`, `testing` | Placeholders like `[Platzhalter: LEGAL_OPERATOR_NAME]` for required display fields |
| `production` | Missing values are **not** replaced with invented data; optional sections are hidden; a warning may appear on Impressum if required fields are incomplete |

## Before production deployment

1. Set all **required** variables in the production environment.
2. Set `LEGAL_HOSTING_PROVIDER_NAME` once the hosting contract is confirmed.
3. Set `LEGAL_SUPERVISORY_AUTHORITY_NAME` (and URL) after determining the competent authority.
4. Review the privacy policy against the **actual** production stack (hosting, mail, backups, monitoring).
5. Ensure an **AVV (Auftragsverarbeitungsvertrag)** with the hosting provider is checked or signed where applicable.
6. Confirm `LEGAL_PRIVACY_LAST_UPDATED` reflects the publication date after legal review.
7. Have Impressum and Datenschutzerklärung reviewed by qualified legal counsel.

## Optional sections

These appear **only** when configured:

- VAT ID (`LEGAL_VAT_ID`)
- Commercial register (`LEGAL_COMMERCIAL_REGISTER`, `LEGAL_REGISTER_NUMBER`)
- Phone number (`LEGAL_PHONE`)
- Editorial responsibility (`LEGAL_EDITORIAL_RESPONSIBLE_NAME`)

Not included by default (by design):

- Consumer dispute resolution / EU-ODR links
- Generic liability, link, or copyright boilerplate from generators

## Patient data

DentalFinance is intended for practice financial data, not patient records. Users must not import identifiable patient data unless explicitly agreed as a supported feature. Operational and contractual measures should reinforce this.

## Third-party services

Only document providers that are **actually** used in production (hosting, mail, monitoring, analytics, CDN, CAPTCHA, etc.). If any of these change, update the privacy policy and this checklist.

Current application defaults (development):

- Session driver: `database` (see `SESSION_DRIVER`)
- Mail driver: often `log` in development (see `MAIL_MAILER`)
- CAPTCHA: disabled by default (`AUTH_CAPTCHA_ENABLED=false`)
- No bundled analytics or marketing trackers

## Contact (landing page footer)

The footer **Contact** link opens the user's e-mail client via `mailto:` when `LEGAL_EMAIL` is set. No contact form is used.

Set your domain mailbox before production, for example:

```env
LEGAL_EMAIL=contact@your-domain.de
```

| URL | Route name |
|---|---|
| `/impressum` | `legal.imprint` |
| `/datenschutz` | `legal.privacy` |

Both routes are public (no authentication middleware).

## Files

| Path | Purpose |
|---|---|
| `config/legal.php` | Operator configuration |
| `app/Support/Legal/LegalConfigPresenter.php` | Safe display logic |
| `app/Http/Controllers/Web/LegalPageController.php` | Controller |
| `resources/views/layouts/legal.blade.php` | Legal page layout |
| `resources/views/legal/imprint.blade.php` | Impressum |
| `resources/views/legal/privacy.blade.php` | Datenschutzerklärung |
| `tests/Feature/LegalPagesTest.php` | Feature tests |
