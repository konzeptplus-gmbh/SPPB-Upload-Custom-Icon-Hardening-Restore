<?php
/**
 * Joomla 4.x / 5.x / 6.x standalone restore/deinstaller for SP Page Builder uploadCustomIcon hardening.
 *
 * Creator: Agon Partners Innovation AG
 * Package: files_sppb_uploadcustomicon_hardening_restore
 * Version: 1.0.0
 */
defined('_JEXEC') or die;

if (!class_exists('AgonSppbUploadcustomiconHardeningRestoreCore', false))
{
    class AgonSppbUploadcustomiconHardeningRestoreCore
    {
        /** @var array<string,bool> */
        protected $allowedTargets = array(
            'components/com_sppagebuilder/controllers/asset.php' => true,
            'components/com_sppagebuilder/helpers/icon-upload-security.php' => true,
            'administrator/components/com_sppagebuilder/editor/traits/IconsTrait.php' => true,
        );

        /** @var string|null */
        protected $restoreManifestPath = null;

        /** @var string|null */
        protected $restoreOperationBackupDir = null;

        public function preflight($type, $adapter)
        {
            if ($type === 'uninstall')
            {
                return true;
            }

            if (!defined('JVERSION') || version_compare(JVERSION, '4.0.0', '<') || version_compare(JVERSION, '7.0.0', '>='))
            {
                $this->message('This restore package supports Joomla 4.x, 5.x, and 6.x only.', 'error');
                return false;
            }

            if (!defined('JPATH_ROOT') || !defined('JPATH_ADMINISTRATOR'))
            {
                $this->message('Joomla path constants are not available. Restore cannot continue.', 'error');
                return false;
            }

            if (!is_dir(JPATH_ROOT . '/components/com_sppagebuilder') || !is_dir(JPATH_ADMINISTRATOR . '/components/com_sppagebuilder'))
            {
                $this->message('SP Page Builder was not found. Restore package cannot continue.', 'error');
                return false;
            }

            if ($this->findBestBackupManifest() === null)
            {
                $this->message('No suitable backup from files_sppb_uploadcustomicon_hardening was found. Nothing can be restored.', 'error');
                return false;
            }

            return true;
        }

        public function install($adapter)
        {
            return $this->restoreLatestBackup('standalone restore package install');
        }

        public function update($adapter)
        {
            return $this->restoreLatestBackup('standalone restore package update');
        }

        public function uninstall($adapter)
        {
            $this->message('Removed the standalone restore package metadata. No SP Page Builder files were changed during restore package uninstall.', 'notice');
            return true;
        }

        public function postflight($type, $adapter)
        {
            return true;
        }

        protected function restoreLatestBackup($reason)
        {
            $candidate = $this->findBestBackupManifest();

            if ($candidate === null)
            {
                $this->message('No suitable pre-hardening backup manifest was found. Nothing was restored.', 'error');
                return false;
            }

            $manifest = $candidate['manifest'];
            $manifestPath = $candidate['path'];
            $this->restoreManifestPath = $manifestPath;
            $this->message('Using restore manifest: /' . $this->toRelativePath($manifestPath), 'notice');

            if (!$this->createRestoreOperationBackup($manifest))
            {
                return false;
            }

            $changed = 0;
            $skipped = 0;
            $failed = 0;

            foreach ($manifest['files'] as $entry)
            {
                if (!isset($entry['target']) || !is_string($entry['target']) || !isset($this->allowedTargets[$entry['target']]))
                {
                    $this->message('Skipped unknown target from backup manifest.', 'warning');
                    $skipped++;
                    continue;
                }

                $target = JPATH_ROOT . '/' . $entry['target'];
                $targetDir = dirname($target);
                $existedBeforeHardening = !empty($entry['existed']);
                $sourceHash = isset($entry['source_sha256']) && is_string($entry['source_sha256']) ? $entry['source_sha256'] : null;
                $currentHash = is_file($target) ? hash_file('sha256', $target) : null;

                if ($existedBeforeHardening)
                {
                    $backup = $this->getBackupFilePath($entry);

                    if ($backup === null || !is_file($backup))
                    {
                        $this->message('Backup file missing for /' . $entry['target'] . '; skipped.', 'warning');
                        $skipped++;
                        continue;
                    }

                    if (isset($entry['previous_sha256']) && is_string($entry['previous_sha256']))
                    {
                        $backupHash = hash_file('sha256', $backup);

                        if (!hash_equals($entry['previous_sha256'], $backupHash))
                        {
                            $this->message('Backup checksum mismatch for /' . $entry['target'] . '; skipped.', 'error');
                            $failed++;
                            continue;
                        }
                    }

                    if (is_file($target) && $sourceHash !== null && !hash_equals($sourceHash, (string) $currentHash))
                    {
                        $this->message('Current file has changed since the hardening patch was installed; skipped restore for /' . $entry['target'] . ' to avoid overwriting newer vendor or manual changes.', 'warning');
                        $skipped++;
                        continue;
                    }

                    if (!is_dir($targetDir) && !$this->ensureDirectory($targetDir))
                    {
                        $this->message('Target directory cannot be created for /' . $entry['target'], 'error');
                        $failed++;
                        continue;
                    }

                    if (!$this->copyFile($backup, $target))
                    {
                        $this->message('Could not restore /' . $entry['target'], 'error');
                        $failed++;
                        continue;
                    }

                    $this->message('Restored /' . $entry['target'], 'message');
                    $changed++;
                    continue;
                }

                // File did not exist before hardening. Remove it only if it is still the exact hardening payload.
                if (!is_file($target))
                {
                    $this->message('Already absent: /' . $entry['target'], 'notice');
                    $skipped++;
                    continue;
                }

                if ($sourceHash === null || !hash_equals($sourceHash, (string) $currentHash))
                {
                    $this->message('Created file has changed since the hardening patch was installed; left in place: /' . $entry['target'], 'warning');
                    $skipped++;
                    continue;
                }

                if (!@unlink($target))
                {
                    $this->message('Could not remove hardening-created file: /' . $entry['target'], 'error');
                    $failed++;
                    continue;
                }

                $this->message('Removed hardening-created file: /' . $entry['target'], 'message');
                $changed++;
            }

            $this->removeIconfontHtaccess();

            if ($this->restoreOperationBackupDir !== null)
            {
                $this->message('The pre-restore state was backed up to: /' . $this->toRelativePath($this->restoreOperationBackupDir), 'notice');
            }

            if ($failed > 0)
            {
                $this->message('Restore completed with errors. Changed: ' . $changed . '; skipped: ' . $skipped . '; failed: ' . $failed . '.', 'error');
                return false;
            }

            $this->message('Restore completed. Changed: ' . $changed . '; skipped: ' . $skipped . '. Trigger: ' . $reason . '.', 'message');
            return true;
        }

        protected function findBestBackupManifest()
        {
            $base = JPATH_ADMINISTRATOR . '/components/com_sppagebuilder/backups/agon_sppb_uploadcustomicon_hardening';

            if (!is_dir($base))
            {
                return null;
            }

            $candidates = array();
            $handle = @opendir($base);

            if ($handle === false)
            {
                return null;
            }

            while (($name = readdir($handle)) !== false)
            {
                if ($name === '.' || $name === '..')
                {
                    continue;
                }

                $path = $base . '/' . $name . '/backup-manifest.json';

                if (!is_file($path))
                {
                    continue;
                }

                $json = @file_get_contents($path);
                $manifest = is_string($json) ? json_decode($json, true) : null;

                if (!is_array($manifest) || !$this->isValidBackupManifest($manifest))
                {
                    continue;
                }

                $score = $this->scoreBackupManifest($manifest);

                if ($score <= 0)
                {
                    continue;
                }

                $time = isset($manifest['created_utc']) && is_string($manifest['created_utc']) ? strtotime($manifest['created_utc']) : false;
                $time = $time !== false ? $time : @filemtime($path);
                $candidates[] = array(
                    'path' => $path,
                    'manifest' => $manifest,
                    'score' => $score,
                    'time' => (int) $time,
                );
            }

            closedir($handle);

            if (empty($candidates))
            {
                return null;
            }

            usort($candidates, function ($a, $b) {
                if ($a['time'] === $b['time'])
                {
                    return $b['score'] <=> $a['score'];
                }

                return $b['time'] <=> $a['time'];
            });

            return $candidates[0];
        }

        protected function isValidBackupManifest(array $manifest)
        {
            if (!isset($manifest['package']) || $manifest['package'] !== 'files_sppb_uploadcustomicon_hardening')
            {
                return false;
            }

            if (!isset($manifest['creator']) || $manifest['creator'] !== 'Agon Partners Innovation AG')
            {
                return false;
            }

            if (!isset($manifest['files']) || !is_array($manifest['files']))
            {
                return false;
            }

            foreach ($manifest['files'] as $entry)
            {
                if (!is_array($entry) || !isset($entry['target']) || !is_string($entry['target']) || !isset($this->allowedTargets[$entry['target']]))
                {
                    return false;
                }
            }

            return true;
        }

        protected function scoreBackupManifest(array $manifest)
        {
            $score = 0;

            foreach ($manifest['files'] as $entry)
            {
                $existed = !empty($entry['existed']);
                $source = isset($entry['source_sha256']) && is_string($entry['source_sha256']) ? $entry['source_sha256'] : null;
                $previous = isset($entry['previous_sha256']) && is_string($entry['previous_sha256']) ? $entry['previous_sha256'] : null;

                if ($existed && $source !== null && $previous !== null && !hash_equals($source, $previous))
                {
                    $score += 2;
                }
                elseif (!$existed && $source !== null)
                {
                    $score += 1;
                }
            }

            return $score;
        }

        protected function getBackupFilePath(array $entry)
        {
            if (!isset($entry['backup']) || !is_string($entry['backup']) || !$this->isSafeRelativePath($entry['backup']))
            {
                return null;
            }

            $path = JPATH_ROOT . '/' . str_replace('\\', '/', $entry['backup']);
            $real = realpath($path);
            $base = realpath(JPATH_ADMINISTRATOR . '/components/com_sppagebuilder/backups/agon_sppb_uploadcustomicon_hardening');

            if ($real === false || $base === false)
            {
                return null;
            }

            $real = rtrim(str_replace('\\', '/', $real), '/');
            $base = rtrim(str_replace('\\', '/', $base), '/') . '/';

            if (strpos($real . '/', $base) !== 0)
            {
                return null;
            }

            return $real;
        }

        protected function createRestoreOperationBackup(array $manifest)
        {
            $base = JPATH_ADMINISTRATOR . '/components/com_sppagebuilder/backups/agon_sppb_uploadcustomicon_hardening_deinstaller';
            $stamp = gmdate('Ymd-His');
            $suffix = function_exists('random_bytes') ? bin2hex(random_bytes(4)) : str_replace('.', '', uniqid('', true));
            $dir = $base . '/' . $stamp . '-' . $suffix;

            if (!$this->ensureDirectory($dir))
            {
                $this->message('Could not create pre-restore backup directory: /' . $this->toRelativePath($dir), 'error');
                return false;
            }

            $backupManifest = array(
                'package' => 'files_sppb_uploadcustomicon_hardening_restore',
                'operation' => 'pre-restore safety backup',
                'creator' => 'Agon Partners Innovation AG',
                'created_utc' => gmdate('c'),
                'restore_manifest' => $this->restoreManifestPath !== null ? $this->toRelativePath($this->restoreManifestPath) : null,
                'files' => array(),
            );

            foreach ($manifest['files'] as $entry)
            {
                if (!isset($entry['target']) || !is_string($entry['target']) || !isset($this->allowedTargets[$entry['target']]))
                {
                    continue;
                }

                $target = JPATH_ROOT . '/' . $entry['target'];
                $backup = $dir . '/' . $entry['target'];
                $row = array(
                    'target' => $entry['target'],
                    'existed' => is_file($target),
                    'backup' => null,
                    'sha256' => null,
                );

                if (is_file($target))
                {
                    if (!$this->ensureDirectory(dirname($backup)) || !@copy($target, $backup))
                    {
                        $this->message('Could not create pre-restore copy of /' . $entry['target'], 'error');
                        return false;
                    }

                    @chmod($backup, 0644);
                    $row['backup'] = $this->toRelativePath($backup);
                    $row['sha256'] = hash_file('sha256', $backup);
                }

                $backupManifest['files'][] = $row;
            }

            $htaccess = JPATH_ROOT . '/media/com_sppagebuilder/assets/iconfont/.htaccess';

            if (is_file($htaccess))
            {
                $backup = $dir . '/media/com_sppagebuilder/assets/iconfont/.htaccess';

                if (!$this->ensureDirectory(dirname($backup)) || !@copy($htaccess, $backup))
                {
                    $this->message('Could not create pre-restore copy of iconfont .htaccess.', 'error');
                    return false;
                }

                @chmod($backup, 0644);
                $backupManifest['files'][] = array(
                    'target' => 'media/com_sppagebuilder/assets/iconfont/.htaccess',
                    'existed' => true,
                    'backup' => $this->toRelativePath($backup),
                    'sha256' => hash_file('sha256', $backup),
                );
            }

            $json = json_encode($backupManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if ($json !== false)
            {
                @file_put_contents($dir . '/pre-restore-manifest.json', $json . "\n");
            }

            $this->restoreOperationBackupDir = $dir;
            return true;
        }

        protected function removeIconfontHtaccess()
        {
            $path = JPATH_ROOT . '/media/com_sppagebuilder/assets/iconfont/.htaccess';

            if (!is_file($path))
            {
                return;
            }

            $current = @file_get_contents($path);
            $expected = $this->expectedIconfontHtaccess();

            if (is_string($current) && hash_equals(hash('sha256', $expected), hash('sha256', $current)))
            {
                if (@unlink($path))
                {
                    $this->message('Removed hardening-created iconfont .htaccess.', 'message');
                }
                else
                {
                    $this->message('Could not remove iconfont .htaccess.', 'warning');
                }

                return;
            }

            if (is_string($current) && strpos($current, 'SP Page Builder custom icon upload hardening') !== false)
            {
                $this->message('Iconfont .htaccess contains the hardening marker but was modified; left it in place.', 'warning');
            }
        }

        protected function expectedIconfontHtaccess()
        {
            return <<<'HTACCESS'
# SP Page Builder custom icon upload hardening.
# This directory should serve static icon-font assets only.
<IfModule mod_authz_core.c>
    <FilesMatch "\.(?:php[0-9]?|phtml|phar|phps|shtml|cgi|pl)$">
        Require all denied
    </FilesMatch>
</IfModule>
<IfModule !mod_authz_core.c>
    <FilesMatch "\.(?:php[0-9]?|phtml|phar|phps|shtml|cgi|pl)$">
        Deny from all
    </FilesMatch>
</IfModule>
RemoveHandler .php .php2 .php3 .php4 .php5 .php6 .php7 .php8 .phtml .phar .phps .shtml .cgi .pl
RemoveType .php .php2 .php3 .php4 .php5 .php6 .php7 .php8 .phtml .phar .phps .shtml .cgi .pl
HTACCESS;
        }

        protected function isSafeRelativePath($path)
        {
            if (!is_string($path) || $path === '')
            {
                return false;
            }

            $path = str_replace('\\', '/', $path);

            if ($path[0] === '/' || preg_match('#^[a-z]:/#i', $path) || strpos($path, '://') !== false || preg_match('/[\x00-\x1F\x7F]/', $path))
            {
                return false;
            }

            foreach (explode('/', $path) as $part)
            {
                if ($part === '' || $part === '.' || $part === '..')
                {
                    return false;
                }
            }

            return true;
        }

        protected function copyFile($source, $target)
        {
            if (!$this->ensureDirectory(dirname($target)))
            {
                return false;
            }

            $tmp = dirname($target) . '/.' . basename($target) . '.tmp.' . str_replace('.', '', uniqid('', true));

            if (!@copy($source, $tmp))
            {
                return false;
            }

            @chmod($tmp, 0644);

            if (!@rename($tmp, $target))
            {
                @unlink($tmp);
                return false;
            }

            @chmod($target, 0644);
            return true;
        }

        protected function ensureDirectory($path)
        {
            if (is_dir($path))
            {
                return true;
            }

            return @mkdir($path, 0755, true) || is_dir($path);
        }

        protected function toRelativePath($path)
        {
            $root = defined('JPATH_ROOT') ? rtrim(str_replace('\\', '/', JPATH_ROOT), '/') : '';
            $path = str_replace('\\', '/', $path);

            if ($root !== '' && strpos($path, $root . '/') === 0)
            {
                return substr($path, strlen($root) + 1);
            }

            return $path;
        }

        protected function message($message, $type = 'message')
        {
            try
            {
                if (class_exists('Joomla\\CMS\\Factory'))
                {
                    \Joomla\CMS\Factory::getApplication()->enqueueMessage($message, $type);
                    return;
                }
            }
            catch (Throwable $e)
            {
                // Fall back to echo below.
            }

            echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "<br>\n";
        }
    }
}

// Legacy Joomla 4.0/4.1 fallback names. Joomla 4.2+ / 5 / 6 use the returned InstallerScriptInterface object below.
if (!class_exists('files_sppb_uploadcustomicon_hardening_restoreInstallerScript', false))
{
    class files_sppb_uploadcustomicon_hardening_restoreInstallerScript extends AgonSppbUploadcustomiconHardeningRestoreCore
    {
    }
}

if (!class_exists('FilesSppbUploadcustomiconHardeningRestoreInstallerScript', false))
{
    class FilesSppbUploadcustomiconHardeningRestoreInstallerScript extends AgonSppbUploadcustomiconHardeningRestoreCore
    {
    }
}

if (interface_exists('Joomla\\CMS\\Installer\\InstallerScriptInterface'))
{
    return new class () implements \Joomla\CMS\Installer\InstallerScriptInterface
    {
        /** @var AgonSppbUploadcustomiconHardeningRestoreCore */
        private $core;

        public function __construct()
        {
            $this->core = new AgonSppbUploadcustomiconHardeningRestoreCore();
        }

        public function install(\Joomla\CMS\Installer\InstallerAdapter $adapter): bool
        {
            return $this->core->install($adapter);
        }

        public function update(\Joomla\CMS\Installer\InstallerAdapter $adapter): bool
        {
            return $this->core->update($adapter);
        }

        public function uninstall(\Joomla\CMS\Installer\InstallerAdapter $adapter): bool
        {
            return $this->core->uninstall($adapter);
        }

        public function preflight(string $type, \Joomla\CMS\Installer\InstallerAdapter $adapter): bool
        {
            return $this->core->preflight($type, $adapter);
        }

        public function postflight(string $type, \Joomla\CMS\Installer\InstallerAdapter $adapter): bool
        {
            return $this->core->postflight($type, $adapter);
        }
    };
}
