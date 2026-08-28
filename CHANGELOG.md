# Changelog

All notable changes to this repository are documented here.

## [Unreleased]

### Fixed

- fix: the `webform_geonames` autocomplete endpoint now trims its input and rejects whitespace-only values, so an unauthenticated caller can no longer generate `watchdog` warning rows by sending a whitespace-only country.
- fix: restore the city autocomplete on the organisation registration form (`webform_geonames`). It resolved the selected country name to a code via the retired `restcountries.com/v3.1` API (removed, CORS-blocked), so no suggestions appeared. The country code is now resolved from Drupal core's `country_manager`, dropping the third-party dependency.

### Added

- feat: attach the city autocomplete to the organisation submission **edit** form too, not only the add form (`webform_geonames`).

### Changed

- change: `webform_geonames` now calls geonames over HTTPS (`https://secure.geonames.org`) using an OPERAS-owned account, replacing the plain-`http://` call on the former shared `jmartinos` account. No behaviour change for users; removes the third-party account dependency and the plaintext hop.
- refactor: `webform_geonames` now logs on every failure path (unresolved country, Geonames `status` error object, non-JSON response body, transport error) instead of returning an empty result silently.
- fix: the `webform_geonames` form_alter no longer writes a debug `watchdog` entry on every form build site-wide; the diagnostic log is gated to the organisation_registry submission forms, so it can no longer evict real warnings/errors under the default `dblog` `row_limit`.

## [1.3.0] – Drupal 11 compatibility declared

### Added

- feat: declare Drupal 11 compatibility on all six modules — `core_version_requirement` widened to `^10 || ^11`. `^9` is dropped: nothing we run is on Drupal 9.

### Changed

- chore: relicense the repository from MIT to GPL-3.0-or-later.
- chore: bump every module a minor version to reflect the new core-compatibility declaration.
  - `computed_address`: `1.1.0`
  - `email_protect`: `1.1.0`
  - `org_moderation_sync`: `1.1.0`
  - `organization_listing`: `1.1.0`
  - `organization_validation`: `1.2.0` (also carries the fixes below)
  - `webform_geonames`: `1.1.0`
- style: apply Drupal coding-standard fixes across the module code (phpcbf).
- refactor: remove a leftover debug `console.log` from `organization_validation` (`1.2.0`).
- ci: MegaLinter to v10, zizmor security scanning via the org-shared reusable workflow, ESLint flat config for v10, and GitHub Actions pinned to full-length commit SHAs.

### Fixed

- fix: correct owner-notification logic bugs in `organization_validation` (`1.2.0`).
- fix: delete unused `config/install/organization_validation.emails.yml` from
  `organization_validation` (`1.2.0`) — the module reads templates from the
  root-level file at runtime; the config/install copy was never used after
  module enable.

## [1.2.0] – Release automation and cleanup

### Added

- feat: GitHub Actions workflow triggered on tag push to automatically create a draft release and attach a modules archive. The draft can then be reviewed and published manually.

### Changed

- feat: remove unused code from `organization_validation` module (`1.1.1`).

### Fixed

- fix: remove accidental shared hosting artifacts (`.cagefs`, `.cl.selector`) from `email_protect` module (`1.0.1`).
- fix: remove accidental shared hosting artifacts (`.cagefs`, `.cl.selector`) from `webform_geonames` module (`1.0.1`).

## [1.1.1] – Per-module semver versioning and CI tooling

### Added

- feat: add Dependabot for automated dependency updates and MegaLinter for code quality checks.

### Changed

- chore: add `version` field to all module `.info.yml` files, enabling independent per-module semver.
  - `organization_validation`: `1.1.0` (reflects two post-import changes)
  - All other modules: `1.0.0` (unchanged since initial import)

## [1.1.0] – Update mail templates

### Changed

- feat: improve mail templates.

## [1.0.1] – Update contact points to use edch.eu

### Changed

- doc: point to new hostname.
- feat: use `no-reply@edch.eu` as fallback address.
- feat: update mail templates to use addresses under @edch.eu.

## [1.0.0] – Initial import

### Added

- **computed_address**: Computed address string for entities.
- **email_protect**: Obfuscates organisation contact email into a “Protected Email” computed value.
- **org_moderation_sync**: Syncs organisation moderation state with linked users.
- **organization_listing**: Public organisations table at `/organizations` with country filter/search.
- **webform_geonames**: City autocomplete for Webforms via GeoNames and restcountries lookups.
- **organization_validation**: Organisation validation during user registration, email templates, admin manageSelectedOrganisations endpoint, workflow event subscriber.

### Notes

- Drupal core 10 targeted; some modules compatible with 9 as indicated.
- Additional UI routes in `organization_validation` are present but currently commented out.
