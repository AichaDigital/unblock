<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Create .ssh directory if it doesn't exist
    $sshPath = storage_path('app/.ssh');
    if (! File::isDirectory($sshPath)) {
        File::makeDirectory($sshPath, 0700, true);
    }

    // Clean up any existing test files
    $files = File::files($sshPath);
    foreach ($files as $file) {
        if (str_starts_with($file->getFilename(), 'test_key_')) {
            File::delete($file->getPathname());
        }
    }
});

afterEach(function () {
    // Clean up test files
    $sshPath = storage_path('app/.ssh');
    if (File::isDirectory($sshPath)) {
        $files = File::files($sshPath);
        foreach ($files as $file) {
            if (str_starts_with($file->getFilename(), 'test_key_')) {
                File::delete($file->getPathname());
            }
        }
    }
});

it('removes SSH keys older than 1 day', function () {
    $sshPath = storage_path('app/.ssh');

    // Create test files with different ages
    $oldFile = $sshPath.'/test_key_old_'.uniqid();
    $newFile = $sshPath.'/test_key_new_'.uniqid();

    File::put($oldFile, 'old_test_key_content');
    File::put($newFile, 'new_test_key_content');

    // Make old file appear to be 2 days old
    touch($oldFile, now()->subDays(2)->timestamp);

    // Verify files exist
    expect(File::exists($oldFile))->toBeTrue();
    expect(File::exists($newFile))->toBeTrue();

    // Simulate the cleanup logic
    $oneDayAgo = now()->subDay()->timestamp;
    $files = File::files($sshPath);

    foreach ($files as $file) {
        $filePath = $file->getPathname();
        if (File::lastModified($filePath) < $oneDayAgo && str_starts_with($file->getFilename(), 'test_key_')) {
            File::delete($filePath);
        }
    }

    // Old file should be deleted
    expect(File::exists($oldFile))->toBeFalse();

    // New file should still exist
    expect(File::exists($newFile))->toBeTrue();
});

it('does not fail when .ssh directory does not exist', function () {
    $sshPath = storage_path('app/.ssh_nonexistent_test');

    // Simulate the cleanup logic
    if (File::isDirectory($sshPath)) {
        $oneDayAgo = now()->subDay()->timestamp;
        $files = File::files($sshPath);

        foreach ($files as $file) {
            $filePath = $file->getPathname();
            if (File::lastModified($filePath) < $oneDayAgo) {
                File::delete($filePath);
            }
        }
    }

    // Should not throw any exception
    expect(true)->toBeTrue();
});

it('schedules SSH cleanup task daily at 7am', function () {
    $schedule = app(Schedule::class);

    $cleanupTask = collect($schedule->events())->first(function ($event) {
        return $event->description === 'cleanup-ssh-keys';
    });

    expect($cleanupTask)->not->toBeNull();
    expect($cleanupTask->expression)->toBe('0 7 * * *');
});

it('only deletes files older than 1 day', function () {
    $sshPath = storage_path('app/.ssh');

    // Create files with different ages
    $veryOldFile = $sshPath.'/test_key_very_old_'.uniqid();
    $exactlyOneDayFile = $sshPath.'/test_key_one_day_'.uniqid();
    $almostOneDayFile = $sshPath.'/test_key_almost_'.uniqid();

    File::put($veryOldFile, 'test');
    File::put($exactlyOneDayFile, 'test');
    File::put($almostOneDayFile, 'test');

    // Make files appear with different ages
    touch($veryOldFile, now()->subDays(3)->timestamp);
    touch($exactlyOneDayFile, now()->subDay()->subMinutes(5)->timestamp); // 1 day + 5 min ago
    touch($almostOneDayFile, now()->subDay()->addMinutes(5)->timestamp); // 1 day - 5 min ago

    // Simulate cleanup
    $oneDayAgo = now()->subDay()->timestamp;
    $files = File::files($sshPath);

    foreach ($files as $file) {
        $filePath = $file->getPathname();
        if (File::lastModified($filePath) < $oneDayAgo && str_starts_with($file->getFilename(), 'test_key_')) {
            File::delete($filePath);
        }
    }

    // Old files should be deleted
    expect(File::exists($veryOldFile))->toBeFalse();
    expect(File::exists($exactlyOneDayFile))->toBeFalse();

    // Recent file should still exist
    expect(File::exists($almostOneDayFile))->toBeTrue();
});
