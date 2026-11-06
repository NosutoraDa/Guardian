# Guardian: Navigating Digital Deception

Guardian is a Laravel digital-safety app for checking suspicious links and files and asking questions about online scams.

## Features

- **Chatbot:** Uses the Gemini Developer API for digital-safety guidance. It has no local scripted-answer fallback.
- **Link checker:** Checks one HTTP(S) URL with VirusTotal after the user consents.
- **File scanner:** Scans files up to 10 MB with VirusTotal after the user consents. Public API uploads may be added to VirusTotal's community dataset.
- **Responsive interface:** Mobile and desktop layouts, animated mascot, radial tool menu, and a Three.js background.

## Requirements

- PHP 8.2+ with `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_sqlite`, `xml`, and `zip` extensions
- Composer 2
- The PHP ZIP extension or an installed `unzip`/7-Zip utility to extract Composer packages

## Install and run

In PowerShell, from the repository root:

```powershell
Set-Location .\guardian-laravel
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan serve
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000). The home page redirects to the Links tool.

## Configure API keys

Add your provider credentials to `guardian-laravel/.env`. Never put API keys in Blade templates, JavaScript, or files committed to Git. The `.env` file is ignored by Git. After changing environment values when config is cached, run this from `guardian-laravel`:

```powershell
php artisan config:clear
```

### Gemini chatbot

Create a Gemini API key in [Google AI Studio](https://aistudio.google.com/apikey), then add it to `guardian-laravel/.env`:

```dotenv
GEMINI_API_KEY=your_gemini_api_key
```

The app defaults to the stable `gemini-3.5-flash-lite` model. Optional settings (already present in `.env.example`) are:

```dotenv
GEMINI_API_URL=https://generativelanguage.googleapis.com/v1beta/models
GEMINI_MODEL=gemini-3.5-flash-lite
```

The browser sends questions to `POST /api/tanya`; Laravel calls Gemini server-side, keeping the key out of the browser. Gemini's free-tier availability and usage limits depend on the model, account, and region and can change. Google states that content submitted through unpaid Gemini API services may be used to improve its products, so do not send personal, confidential, or sensitive information. Google's current terms say the API is for professional or business use, not consumer use, and prohibit making an API client available to users under 18. Verify that your deployment is eligible and restricted to adults before enabling this integration.

### VirusTotal link checks

```dotenv
VIRUSTOTAL_API_KEY=your_virustotal_key_here
```

The Links page requires explicit consent. Submitted or queried URLs may be added to VirusTotal's dataset; do not check private, personal, confidential, or one-time URLs. The public API has strict quotas and terms, and may not be used in commercial products or business workflows without the appropriate permission. Confirm current [API quotas and terms](https://docs.virustotal.com/reference/public-vs-premium-api) before deployment. A result with no detections is not a guarantee of safety.

### VirusTotal file checks

The File page uses the same `VIRUSTOTAL_API_KEY` as link checks. The page requires explicit consent before uploading. Files submitted through VirusTotal's public API can be added to its dataset and made accessible to the community; do not upload sensitive, personal, or confidential files. The VirusTotal public API has strict quotas and is not permitted for commercial products or services without appropriate permission. Confirm current [API quotas and terms](https://docs.virustotal.com/reference/public-vs-premium-api) before deployment. Scan results are not a guarantee that a file is safe.

The chatbot is separate from both scanners: links and files are not sent through the chatbot.

## Pages and API routes

| Page | Route | Description |
| --- | --- | --- |
| Links | `/links` | Check a URL with VirusTotal |
| Files | `/files` | Scan a file with VirusTotal |
| File report | `/files/report/{sha256}` | View VirusTotal's engine results in Guardian |
| Chatbot | `/tanya` | Ask Gemini for digital-safety guidance |

| API endpoint | Description |
| --- | --- |
| `POST /api/links` | Submit a URL for checking |
| `POST /api/links/analysis` | Refresh a URL analysis |
| `POST /api/files` | Submit a file for scanning |
| `POST /api/files/analysis` | Refresh a file analysis |
| `POST /api/tanya` | Send a chatbot question |

## Run tests

From `guardian-laravel`:

```powershell
composer test
```

## Project structure

```text
guardian-laravel/
├── app/                 # Controllers and external-service integrations
├── config/              # Laravel and Guardian configuration
├── public/              # CSS, JavaScript, and web entry point
├── resources/views/     # Shared layout and tool pages
├── routes/web.php       # Web pages and API endpoints
├── tests/               # Feature tests
└── .env.example         # Environment-variable template (no secrets)
```
