<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

trait CsvTrait
{
    const string DIR = 'csv';
    const string FILE_PREFIX = 'export_';
    const string FILE_SUFFIX = '.csv.gz';
    protected $disk;


    /**
     * Get the most recent CSV file path from the storage disk.
     */
    public function getLastFile(): ?string
    {
        $this->disk    = Storage::disk($this::DISK);
        $files         = $this->disk->files($this::DIR);
        $pattern       = $this::FILE_PREFIX . '*' . $this::FILE_SUFFIX;
        $matchingFiles = array_filter($files, function ($file) use ($pattern) {
            return fnmatch($pattern, basename($file));
        });
        $files = collect($matchingFiles)->map(function ($file) {
            return [
                'path'     => $file,
                'modified' => $this->disk->lastModified($file),
            ];
        })->sortByDesc('modified');
        return $files->first()['path'];
    }

    /**
     * Read and parse a CSV file from storage.
     *
     * @return string The file contents.
     */
    public function getFileContent(string $path): string
    {
        $contents = $this->disk->get($path);
        if ($contents === false || $contents === null) {
            throw new RuntimeException("Failed to read: {$path}");
        }
        return $contents;
    }

    /**
     * Safe gzip decompress with errors surfaced.
     * @return string The decompressed string.
     */
    public function decompressGzip(string $binary): string
    {
        $out = @gzdecode($binary);
        if ($out === false) {
            throw new RuntimeException('Gzip decompress failed: data may be corrupted or not gzip.');
        }
        return $out;
    }


}
