<?php

namespace Tests\Concerns;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

/**
 * Snapshots the real `storage/installed` marker before each test and restores
 * it afterwards.
 *
 * Installer-related tests toggle this file to simulate installed / not-installed
 * states. Without this guard they would delete or overwrite the marker of the
 * live installation the test suite runs against, leaving the real app thinking
 * it is not installed. The #[Before]/#[After] hooks run independently of the
 * test's own setUp()/tearDown(), so the original state is always captured and
 * restored exactly — including its original contents.
 */
trait PreservesInstalledMarker
{
    private ?string $preservedInstalledContents = null;

    private bool $preservedInstalledExisted = false;

    protected function installedMarkerPath(): string
    {
        // Resolved relative to the project root rather than via storage_path(),
        // because the #[Before]/#[After] hooks run before the Laravel
        // application is bootstrapped in setUp().
        return dirname(__DIR__, 2).'/storage/installed';
    }

    #[Before]
    protected function snapshotInstalledMarker(): void
    {
        $path = $this->installedMarkerPath();
        $this->preservedInstalledExisted = file_exists($path);
        $this->preservedInstalledContents = $this->preservedInstalledExisted
            ? (file_get_contents($path) ?: '')
            : null;
    }

    #[After]
    protected function restoreInstalledMarker(): void
    {
        $path = $this->installedMarkerPath();

        if ($this->preservedInstalledExisted) {
            file_put_contents($path, $this->preservedInstalledContents ?? '');
        } elseif (file_exists($path)) {
            unlink($path);
        }
    }
}
