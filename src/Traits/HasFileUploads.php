<?php

namespace StalkArtisan\LaravelFileUploader\Traits;

use StalkArtisan\LaravelFileUploader\Models\File;
use StalkArtisan\LaravelFileUploader\Facades\FileUploader;
use StalkArtisan\LaravelFileUploader\Exceptions\InvalidFileException;
use Illuminate\Support\Str;

trait HasFileUploads
{
    public function uploadFile(mixed $source, string $fileType = 'any', ?int $maxSize = null, ?string $folder = null, ?string $customName = null): ?File
    {
        try {
            $file = FileUploader::handle($source, $fileType, $maxSize, $folder, get_class($this), $this->getKey());
            
            if ($customName) {
                $file->update(['original_name' => $customName]);
            }
            
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
    
    /**
     * Upload file with module-based folder structure
     */
    public function uploadFileToModule(mixed $source, string $module, string $fileType = 'any', ?int $maxSize = null, ?string $customName = null): ?File
    {
        $folder = $this->buildModuleFolderPath($module);
        return $this->uploadFile($source, $fileType, $maxSize, $folder, $customName);
    }
    
    /**
     * Build folder path based on module and user ID
     */
    private function buildModuleFolderPath(string $module): string
    {
        $userId = $this->getKey();
        $module = Str::slug($module);
        
        return "users/{$userId}/{$module}";
    }
    
    /**
     * Get files by module
     */
    public function getFilesByModule(string $module)
    {
        $folder = $this->buildModuleFolderPath($module);
        return $this->files()->where('file_path', 'like', "%{$folder}%")->get();
    }
}