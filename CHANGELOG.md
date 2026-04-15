# Changelog

All notable changes to this repository are documented here.

## [1.2.0] – Release automation and cleanup

### Added

- feat: GitHub Actions workflow triggered on tag push to automatically create a draft release and attach a modules archive. The draft can then be reviewed and published manually.

### Changed

- feat: remove unused code from `organization_validation` module (`1.1.1`).

### Fixed

- fix: remove accidental shared hosting artifacts (`.cagefs`, `.cl.selector`) from `email_protect` module (`1.0.1`).

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
