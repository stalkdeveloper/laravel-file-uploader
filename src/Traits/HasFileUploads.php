<?php

namespace StalkArtisan\LaravelFileUploader\Traits;

use StalkArtisan\LaravelFileUploader\Models\File;
use StalkArtisan\LaravelFileUploader\Facades\FileUploader;
use StalkArtisan\LaravelFileUploader\Exceptions\InvalidFileException;

trait HasFileUploads
{
    public function uploadFile(mixed $source, string $fileType = 'any', ?int $maxSize = null): ?File
    {
        try {
            $file = FileUploader::handle($source, $fileType, $maxSize);
            $file->update([
                'fileable_id' => $this->getKey(),
                'fileable_type' => get_class($this),
            ]);
            
            return $file;
        } catch (InvalidFileException $e) {
            throw $e;
        }
    }
    
    public function files()
    {
        return $this->morphMany(File::class, 'fileable');
    }
    
    public function getFileUrlAttribute(): ?string
    {
        $file = $this->files()->latest()->first();
        return $file?->url;
    }
    
    public function deleteFiles(): bool
    {
        return $this->files->every(function (File $file) {
            return FileUploader::delete($file);
        });
    }
}