<?php
// src/Repositories/JsonFileStore.php

namespace App\Repositories;

use RuntimeException;

/**
 * The stand-in for MySQL while the database is deferred.
 *
 * A collection is one JSON file holding a flat array of records. Reads and
 * writes for a single file are protected with flock(); operations that touch
 * two collections at once (a hold must consult reservations before it can be
 * granted) run inside JsonFileStore::withLock() instead — see HoldManager.
 *
 * When MySQL arrives, this class disappears and the repositories behind it are
 * rewritten with PDO. Nothing above the repository layer knows this exists.
 */
class JsonFileStore
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->ensureFile();
    }

    public function path(): string
    {
        return $this->path;
    }

    /** All records in the collection. Shared lock, safe to call anywhere. */
    public function read(): array
    {
        $handle = fopen($this->path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Unable to open data file: {$this->path}");
        }

        try {
            flock($handle, LOCK_SH);
            $raw = stream_get_contents($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Overwrite the whole collection.
     *
     * Only call this while holding the booking lock, or from inside
     * atomicUpdate() — a bare write outside a critical section is how you lose
     * somebody else's booking.
     */
    public function replace(array $records): void
    {
        $handle = $this->openExclusive();

        try {
            $this->writeAndFlush($handle, $records);
        } finally {
            $this->release($handle);
        }
    }

    /**
     * Read, mutate, write — one critical section, so the read cannot go stale
     * before the write lands. `$mutator` receives the records and returns
     * [newRecords, result].
     */
    public function atomicUpdate(callable $mutator): mixed
    {
        $handle = $this->openExclusive();

        try {
            $raw = stream_get_contents($handle);
            $decoded = json_decode((string) $raw, true);
            $records = is_array($decoded) ? $decoded : [];

            [$records, $result] = $mutator($records);

            $this->writeAndFlush($handle, $records);

            return $result;
        } finally {
            $this->release($handle);
        }
    }

    /**
     * Run a callback while holding an exclusive lock on a lock file.
     *
     * This is the booking-critical section: hold creation and reservation
     * creation both take this same lock, so a slot cannot be granted twice
     * even when two requests arrive in the same instant.
     */
    public static function withLock(string $lockPath, callable $callback): mixed
    {
        $dir = dirname($lockPath);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Unable to create lock directory: {$dir}");
        }

        $handle = fopen($lockPath, 'c');

        if ($handle === false) {
            throw new RuntimeException("Unable to open lock file: {$lockPath}");
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new RuntimeException("Unable to acquire lock: {$lockPath}");
        }

        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function ensureFile(): void
    {
        $dir = dirname($this->path);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Unable to create data directory: {$dir}");
        }

        if (!file_exists($this->path)) {
            file_put_contents($this->path, "[]");
        }
    }

    /** @return resource */
    private function openExclusive()
    {
        $handle = fopen($this->path, 'c+');

        if ($handle === false) {
            throw new RuntimeException("Unable to open data file: {$this->path}");
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            throw new RuntimeException("Unable to lock data file: {$this->path}");
        }

        return $handle;
    }

    /** @param resource $handle */
    private function writeAndFlush($handle, array $records): void
    {
        $encoded = json_encode(
            array_values($records),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, $encoded === false ? '[]' : $encoded);
        fflush($handle);
    }

    /** @param resource $handle */
    private function release($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
