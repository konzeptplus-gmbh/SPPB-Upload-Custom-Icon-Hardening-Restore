# SP Page Builder `uploadCustomIcon` Hardening Package

**Creator:** Agon Partners Innovation AG  
**Target extension:** SP Page Builder Pro 6.6.2  
**Package type:** Joomla file-extension installer wrapper  
**Supported Joomla versions:** Joomla 4.x, Joomla 5.x, Joomla 6.x  
**License:** GPL-2.0-or-later  
**Status:** Security hardening overlay / temporary defensive patch

## Overview

This repository contains a Joomla-installable hardening overlay for the SP Page Builder custom icon upload workflow, specifically the `asset.uploadCustomIcon` path.

The purpose of the package is to reduce the risk of arbitrary file upload, ZIP traversal, unsafe file extraction, and web-executable payload placement in the custom icon ZIP upload process.

This package is **not** a full replacement for vendor updates. Keep Joomla, SP Page Builder, Helix/Spectrum templates, and all third-party extensions updated. If a site was already compromised, this package does **not** clean existing backdoors, rogue users, modified templates, or stolen credentials.

## Distributed packages

Place release ZIPs in a `dist/` directory or attach them to the GitLab release.

| File | Purpose | SHA-256 |
|---|---|---|
| `files_sppb_uploadcustomicon_hardening_1.2.0_j4_j5_j6_with_deinstaller.zip` | Main Joomla installer. Installs the hardening files and includes safe restore-on-uninstall support. | `cfdbe3a4dcb9f47fcb5b7eaeb06168382f74b23ae7af0701dbe6954ff53a8efd` |
| `files_sppb_uploadcustomicon_hardening_restore_1.0.0_j4_j5_j6.zip` | Standalone restore/deinstaller package. Use this when the older `1.1.0` package was already installed or when a separate restore package is preferred. | `c79103b3f8bf8ed102a8da6e526fce72f0c37bc532ade7b5563ece9acd077300` |

Verify release files after download:

```bash
sha256sum files_sppb_uploadcustomicon_hardening_1.2.0_j4_j5_j6_with_deinstaller.zip
sha256sum files_sppb_uploadcustomicon_hardening_restore_1.0.0_j4_j5_j6.zip
```

Expected output:

```text
cfdbe3a4dcb9f47fcb5b7eaeb06168382f74b23ae7af0701dbe6954ff53a8efd  files_sppb_uploadcustomicon_hardening_1.2.0_j4_j5_j6_with_deinstaller.zip
c79103b3f8bf8ed102a8da6e526fce72f0c37bc532ade7b5563ece9acd077300  files_sppb_uploadcustomicon_hardening_restore_1.0.0_j4_j5_j6.zip
```

## Installation

Install from the Joomla administrator panel:

```text
System → Install → Extensions → Upload Package File
```

Upload:

```text
files_sppb_uploadcustomicon_hardening_1.2.0_j4_j5_j6_with_deinstaller.zip
```

After installation:

1. Clear Joomla cache.
2. Clear PHP OPcache if enabled.
3. Test SP Page Builder custom icon upload on a staging site first.
4. Keep existing WAF/Admin Tools rules in place until the site has been verified clean.

## Files changed by the hardening package

The installer writes the following files into the Joomla installation:

```text
/components/com_sppagebuilder/controllers/asset.php
/components/com_sppagebuilder/helpers/icon-upload-security.php
/administrator/components/com_sppagebuilder/editor/traits/IconsTrait.php
```

The helper file is new:

```text
/components/com_sppagebuilder/helpers/icon-upload-security.php
```

The two existing SP Page Builder files are backed up before replacement.

## Security hardening included

The hardening package adds defensive checks to the custom icon upload flow:

- Requires ZIP packages to pass magic-header validation.
- Validates the ZIP central directory before extraction.
- Rejects path traversal entries such as `../file`.
- Rejects absolute paths and Windows drive paths.
- Rejects NUL/control characters in ZIP entry names.
- Rejects server-executable files such as `.php`, `.phtml`, `.phar`, `.cgi`, `.pl`, `.asp`, `.aspx`, `.jsp`, `.cfm`, `.sh`, `.cmd`, `.bat`, and `.exe`.
- Rejects sensitive web-server configuration files such as `.htaccess`, `.user.ini`, `php.ini`, and `web.config`.
- Rejects ZIP entries marked as symlinks.
- Rejects encrypted ZIP entries.
- Enforces limits for file count, per-file uncompressed size, and total uncompressed size.
- Uses unique temporary ZIP filenames instead of predictable temporary names.
- Avoids Joomla upload calls with unsafe file handling enabled.
- Sanitizes custom icon font-family names.
- Sanitizes CSS icon prefixes.
- Copies only allow-listed icon-font assets into the public media directory.
- Copies only expected font and CSS files into `/media/com_sppagebuilder/assets/iconfont/`.
- Adds a best-effort `.htaccess` file under the icon-font media folder to deny PHP execution on Apache/LiteSpeed.

## Joomla manifest information

The main package manifest is located at the ZIP root:

```text
files_sppb_uploadcustomicon_hardening.xml
```

Main package metadata:

```xml
<extension type="file" version="4.0" method="upgrade">
    <name>files_sppb_uploadcustomicon_hardening</name>
    <element>files_sppb_uploadcustomicon_hardening</element>
    <author>Agon Partners Innovation AG</author>
    <creationDate>2026-07-03</creationDate>
    <copyright>Copyright (C) 2026 Agon Partners Innovation AG. Distributed under GPL-2.0-or-later.</copyright>
    <license>GNU General Public License version 2 or later</license>
    <version>1.2.0</version>
    <description>Joomla 4.x, 5.x, and 6.x installer wrapper for the SP Page Builder 6.6.2 uploadCustomIcon hardening patch. Version 1.2.0 adds safe restore-on-uninstall support.</description>
    <scriptfile>script.php</scriptfile>
</extension>
```

## Backup locations

The main installer stores original-file backups here:

```text
/administrator/components/com_sppagebuilder/backups/agon_sppb_uploadcustomicon_hardening/
```

The deinstaller creates a safety backup of the currently installed hardening state here:

```text
/administrator/components/com_sppagebuilder/backups/agon_sppb_uploadcustomicon_hardening_deinstaller/
```

Backups are intentionally left in place after restore/uninstall.

## Uninstall / restore

### Option 1: Built-in deinstaller from version 1.2.0

If version `1.2.0` is installed, use Joomla's extension management UI:

```text
System → Manage → Extensions
```

Search for:

```text
files_sppb_uploadcustomicon_hardening
```

Then uninstall it.

The uninstall routine restores only files that still match the known hardening payload hash. If a file was modified later by a vendor update or a manual edit, the deinstaller skips that file instead of overwriting newer changes.

### Option 2: Standalone restore package

Use the standalone restore package when:

- The older `1.1.0` package is already installed.
- The main package was removed but the hardening files remain.
- A separate restore step is preferred.

Install this ZIP via Joomla's extension installer:

```text
files_sppb_uploadcustomicon_hardening_restore_1.0.0_j4_j5_j6.zip
```

## Post-install smoke tests

### Normal upload test

1. Log in as a Joomla Super User.
2. Open SP Page Builder.
3. Upload a normal IcoMoon, Fontello, or IcoFont custom icon ZIP.
4. Confirm the icon set appears and can be selected.

### Malicious ZIP rejection tests

Confirm upload is rejected for ZIP files containing any of the following:

```text
shell.php
shell.phtml
payload.phar
.htaccess
.user.ini
php.ini
web.config
../traversal.css
../../shell.php
```

Expected result:

```text
File not supported.
```

or an equivalent SP Page Builder upload error.

### Media folder check

Confirm the icon font media folder does not contain executable files:

```bash
find media/com_sppagebuilder/assets/iconfont -type f \
  \( -iname '*.php' -o -iname '*.phtml' -o -iname '*.phar' -o -iname '*.cgi' -o -iname '*.pl' -o -iname '*.shtml' \)
```

Expected result:

```text
No output
```

### Unauthenticated endpoint check

Confirm unauthenticated access to the upload task is rejected:

```text
index.php?option=com_sppagebuilder&task=asset.uploadCustomIcon
```

A public, unauthenticated request must not be able to upload files.

## Operational notes

This package hardens the custom icon upload implementation. It does not perform incident response.

For sites previously exposed to exploitation, also perform the following checks:

- Search for rogue Super User accounts.
- Check for unknown accounts using unusual local domains such as `@secure.local`.
- Search for unexpected PHP files in `images/`, `media/`, `tmp/`, `cache/`, `templates/`, `plugins/`, and `components/`.
- Rotate Joomla Super User passwords.
- Rotate database, FTP/SFTP/SSH, hosting panel, and API credentials.
- Clear all Joomla sessions.
- Reinstall or verify the integrity of Joomla core and all third-party extensions.
- Keep Admin Tools or equivalent WAF/server protection rules enabled until the site is confirmed clean.

## Recommended Admin Tools compensating controls

For sites using Akeeba Admin Tools Professional, keep these controls enabled while the vendor extension and site integrity are being verified:

- Server protection via `.htaccess Maker`, NginX Conf Maker, or `web.config Maker`.
- WAF deny rule for `option=com_sppagebuilder` and `task=asset.uploadCustomIcon` if front-end custom icon uploads are not required.
- No PHP execution exceptions for `images`, `media`, `cache`, `tmp`, `templates`, `plugins`, or `components` directories unless absolutely required and scoped to exact files.
- PHP File Change Scanner scheduled daily or weekly.
- Administrator secret URL parameter and login alert emails.
- Repeat-offender auto-ban after false positives have been checked.

## Compatibility

Supported:

```text
Joomla 4.x
Joomla 5.x
Joomla 6.x
```

Rejected:

```text
Joomla 3.x
Joomla 7.x and later until explicitly tested
```

The package checks the runtime Joomla version in the installer script:

```text
JVERSION >= 4.0.0 && JVERSION < 7.0.0 if a clown already makes a J7 for testing.. you never know
```

## Known limitations

- This package is an overlay on top of SP Page Builder Pro 6.6.2 files.
- A future SP Page Builder update may overwrite the patched files.
- If the vendor ships an updated implementation, compare the vendor changes before reapplying this patch.
- The restore process is intentionally conservative and may skip files changed after installation.
- This package does not clean existing malware or compromised users.

## Suggested GitLab repository layout

```text
.
├── README.md
├── dist/
│   ├── files_sppb_uploadcustomicon_hardening_1.2.0_j4_j5_j6_with_deinstaller.zip
│   └── files_sppb_uploadcustomicon_hardening_restore_1.0.0_j4_j5_j6.zip
└── docs/
    └── testing.md
```

## Release checklist

Before tagging a release:

- [ ] Confirm package ZIPs are present in `dist/`.
- [ ] Verify SHA-256 checksums.
- [ ] Install on Joomla 4 staging site.
- [ ] Install on Joomla 5 staging site.
- [ ] Install on Joomla 6 staging site.
- [ ] Test normal custom icon upload.
- [ ] Test malicious ZIP rejection.
- [ ] Test uninstall/restore behaviour.
- [ ] Confirm no unexpected PHP files are copied into `/media/com_sppagebuilder/assets/iconfont/`.
- [ ] Confirm backups are created under `/administrator/components/com_sppagebuilder/backups/`.

## References

- SP Page Builder custom icon upload incident note: `https://mysites.guru/blog/sp-page-builder-zero-day-uploadcustomicon-rce/`
- Joomla extension manifest documentation: `https://manual.joomla.org/docs/4.4/building-extensions/install-update/installation/manifest/`
- Joomla security documentation: `https://manual.joomla.org/docs/next/security/common-vulnerabilities/`
- Joomla CSRF protection documentation: `https://manual.joomla.org/docs/next/security/csrf-protection/`

## Changelog

### 1.2.0

- Added safe restore-on-uninstall support to the main installer.
- Added conservative hash-based restore behaviour.
- Added deinstaller safety backup location.
- Kept Joomla 4.x, 5.x, and 6.x compatibility.

### 1.1.0

- Rebuilt as a Joomla file-extension installer wrapper.
- Added Joomla 4.x, 5.x, and 6.x compatibility.
- Added manifest, installer script, metadata, and copy information.

### 1.0.0

- Initial hardening payload for SP Page Builder Pro 6.6.2 `uploadCustomIcon` workflow.
