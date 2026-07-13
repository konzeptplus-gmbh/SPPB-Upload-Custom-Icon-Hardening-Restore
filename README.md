# SP Page Builder uploadCustomIcon hardening standalone restore package

Creator: Agon Partners Innovation AG  
Version: 1.0.0  
Supported Joomla versions: 4.x, 5.x, 6.x

This is a Joomla-installable restore package for sites where the SP Page Builder uploadCustomIcon hardening package was already installed.

Install this ZIP only when you want to revert the hardening files back to the previous SP Page Builder files saved by the hardening installer.

## What it restores

It searches for backup manifests under:

`/administrator/components/com_sppagebuilder/backups/agon_sppb_uploadcustomicon_hardening/`

It selects the newest suitable pre-hardening backup and restores only these known files:

- `/components/com_sppagebuilder/controllers/asset.php`
- `/components/com_sppagebuilder/helpers/icon-upload-security.php`
- `/administrator/components/com_sppagebuilder/editor/traits/IconsTrait.php`

The helper-created `/media/com_sppagebuilder/assets/iconfont/.htaccess` is removed only if it still exactly matches the hardening-generated file.

## Safety behavior

The restore is intentionally conservative:

- It restores an original file only if the current file still matches the hardening payload hash recorded in the backup manifest.
- It deletes a hardening-created file only if that current file still matches the recorded payload hash.
- If a file was changed by a later vendor update or manual edit, the package skips that file and reports it instead of overwriting it.
- Before changing anything, it creates a pre-restore safety backup under:

`/administrator/components/com_sppagebuilder/backups/agon_sppb_uploadcustomicon_hardening_deinstaller/`

Install on staging first.
