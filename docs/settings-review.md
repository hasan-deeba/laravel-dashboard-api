# Application settings — review

**Status:** the settings feature merged in PR #2 and the default-language removal merged in PR #3.
This follow-up removes the primary-color setting and adds optional Markdown privacy/terms text in
English and Arabic. Validation is recorded below. **No deployment or live preview has been performed.**
Implementation branch: `arena/c6fcca39-laravel-dashboard-api` (the fixed Arena session branch).

## What was added

- **One shared project icon** (`branding.app_icon`) returned to dashboard, web, and mobile clients. This is project branding, not a personal/user avatar.
- Optional shared **logo**, **favicon**, and project name. (The primary-color setting was removed —
  clients keep their own bundled theme colors.)
- **Default language is not a settings field.** It is controlled by `APP_LOCALE` in `config/app.php` (default `en`, fallback `en`). A supported `Accept-Language` header still overrides it for that request. Timezone handling is intentionally left as it was before this feature.
- Support **email**, **phone**, and **website URL**.
- **Privacy policy** and **terms**: an optional **URL** *and* optional **Markdown text** per language
  (English/Arabic). Clients show the text when present and otherwise open the link.
- Client **maintenance mode** and **registration switch**, enforced server-side. CMS/dashboard routes remain available during client maintenance.
- Independent **iOS** and **Android** release policies: minimum supported version, `lock_for_update`, store URL, and translated update message.

## Legal documents (link + text)

Each document is independent and completely optional:

| Field | Type | Notes |
|---|---|---|
| `legal.privacy_policy_url` | URL | http/https, max 2048 |
| `legal.privacy_policy_text` | Markdown, `{en, ar}` | max 50000 per language |
| `legal.terms_url` | URL | http/https, max 2048 |
| `legal.terms_text` | Markdown, `{en, ar}` | max 50000 per language |

- Both languages of the text are nullable, so a document can be English-only, Arabic-only, or empty.
- A single-language patch merges (`{ar: "…"}` keeps `en`); `{ar: null}` clears only Arabic; sending
  `null` for the whole field clears both languages.
- Expected client behavior: show the text if it exists (fallback to English), otherwise open the link;
  if both exist, show the text with a "view full page" link; if neither exists, hide the entry.
- Markdown is rendered by clients, so active HTML (`script`, `iframe`, `object`, `embed`, `style`,
  `link`, `meta`, `form`, `input`, `button`) is rejected with a 422 on that language key.
- The builder exposes these as `type: "TRANSLATABLE_MARKDOWN"` with `format: "markdown"`.

## Update-lock behavior

When a platform is locked, only installed versions **below** the configured version must update:

| Configured minimum | Installed version | Update required |
|---|---:|---:|
| `1.10.0` | `1.9.0` | yes |
| `1.10.0` | `1.10.0` | no |
| `1.10.0` | `1.10.1` | no |
| `1.10.0` | `2.0.0` | no |

Versions use stable numeric `MAJOR.MINOR.PATCH` values. Comparison is numeric, so `1.10.0` is newer than `1.9.0`. Defaults are unlocked with a null minimum version to avoid accidental lockouts. Enabling a lock requires both a minimum version and a store URL.

## Endpoints

| Method | Path | Access | Purpose |
|---|---|---|---|
| GET | `/api/settings` | public | Public branding/support/legal/availability settings and optional update decision |
| GET | `/cms/settings` | CMS auth + `settings.view` | Current settings for the dashboard form |
| GET | `/cms/settings/builder` | CMS auth + `settings.view` | Grouped, typed, translated form metadata |
| POST | `/cms/settings` | CMS auth + `settings.edit` | Partial settings update; JSON or multipart for images |

Public startup/update check:

```http
GET /api/settings?platform=ios&app_version=1.9.0
Accept-Language: en
```

The response uses the client envelope `{ "message": ..., "data": ... }`. The `data.update` fragment looks like:

```json
{
  "required": true,
  "current_version": "1.9.0",
  "required_version": "1.10.0",
  "store_url": "https://apps.apple.com/app/example",
  "message": "Please update the app to continue."
}
```

Without `platform` and `app_version`, `data.update` is `null`; the endpoint does not guess that an unknown client is current. Invalid or incomplete version context returns validation errors. Public settings responses are marked `no-store` so startup checks see fresh lock/maintenance changes. The public API omits `branding.app_name`; each legal Markdown setting is returned as a single string (or `null`) selected by `Accept-Language` (`ar` or `en`). The CMS settings endpoint continues to expose both locales for editing.

Example partial dashboard update:

```json
{
  "branding": {
    "app_name": { "en": "HeroDash", "ar": "هيرو داش" }
  },
  "apps": {
    "ios": {
      "version": "1.10.0",
      "lock_for_update": true,
      "store_url": "https://apps.apple.com/app/example"
    },
    "android": {
      "version": "2.4.0",
      "lock_for_update": false,
      "store_url": "https://play.google.com/store/apps/details?id=example"
    }
  }
}
```

Image fields accept `jpg`, `jpeg`, `png`, or `webp`, up to 4 MB and 4096×4096. Send multipart fields such as `branding[app_icon]`. Send `null` to remove an image. Stored values are relative public-disk paths; API responses expose URLs. Replacement/removal cleans up the old managed file after the database commit.

Boolean settings expect real JSON booleans from the dashboard (`true` / `false`). The previous string-to-boolean normalization was removed because the React dashboard will always send booleans.

## Client integration still required outside this repository

This repository provides the backend API and dashboard form metadata; it does not contain the external dashboard settings screen or the native app startup code.

Native/web clients must:

1. Call `/api/settings` before login and on startup/resume, including their platform and installed version.
2. If `data.update.required` is true, block normal navigation and show the returned message/store link until the app is updated.
3. Respect maintenance responses (`503`) and the returned registration switch. The API also blocks client routes during maintenance and blocks new registrations server-side when registration is disabled.
4. Use `branding.app_icon` as the shared in-app/project icon. A native home-screen launcher icon is bundled at build time and cannot be replaced remotely by an API setting; changing that requires a native build/store release.

An API flag alone cannot stop an already-installed native app from opening; the native startup gate is essential.

## Review checklist

- [ ] Shared project icon/logo/favicon and branding fields match the desired dashboard form (no color field).
- [ ] Privacy/terms behave as desired: optional link + optional Markdown text, per language, text preferred by clients.
- [ ] iOS/Android minimum-version lock semantics are correct.
- [ ] Maintenance should block all client routes, including authenticated client routes, while leaving CMS and public settings available.
- [ ] Registration switch should block only new registrations, not existing-client login.
- [ ] Config-based default language (`APP_LOCALE`) and supported-language override are acceptable; no locale field appears in the settings builder.
- [ ] External dashboard/native teams can consume the builder and startup-check contracts above.

## Validation performed locally

Focused tests passed in the PHP-WASM sandbox:

- `tests/Feature/Settings/ApplicationSettingsTest.php`: **20 tests, 197 assertions — OK**
- `tests/Feature/Clients/ClientAuthTest.php`: **23 tests, 89 assertions — OK**
- PHP syntax check: **139 files, 0 errors**
- Scratch-database migration: **OK**
- `SettingsSeeder` run twice on a fresh scratch database: **OK**, **22 rows** for **22 registry keys**, no duplicates, **0 color rows**, **0 locale rows**
- Settings routes registered: **4**

The temporary PHP-WASM migration override used for testing was removed. The legacy full test suite was not run because it contains pre-existing unrelated starter-kit assumptions.

## Deployment steps — only after explicit approval

1. Review and approve the local edits.
2. Commit/push from the fixed Arena branch when authorized.
3. Run `php artisan migrate --force`.
4. Run `php artisan db:seed --class=SettingsSeeder --force` (idempotent; does not overwrite saved values). `migrate --seed` also includes it through `DatabaseSeeder`.
5. Run `php artisan storage:link` so public image URLs resolve.
6. If configuration is cached, rebuild the cache after deploying the registry.
7. Optional cleanup: an old `branding.primary_color` row from a previous deployment is ignored by the
   API (only registry keys are read). Delete it if you want a tidy table:
   `Setting::where('key', 'branding.primary_color')->delete();`
8. Assign existing `settings.view` / `settings.edit` permissions as needed. Avoid casually rerunning `RolePermissionSeeder` because it syncs role permissions.

No secrets belong in this settings table; only the fixed public registry keys are accepted.
