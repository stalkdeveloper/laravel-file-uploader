<?php

namespace StalkArtisan\LaravelFileUploader\Contracts;

use Illuminate\Http\UploadedFile;
use StalkArtisan\LaravelFileUploader\Models\File;

interface FileUploaderInterface
{
    public function upload(UploadedFile $file, string $fileType = 'any', ?int $maxSize = null, ?string $folder = null, ?string $fileableType = null, ?int $fileableId = null): File;
    
    public function uploadFromUrl(string $url, string $fileType = 'any', ?int $maxSize = null, ?string $folder = null, ?string $fileableType = null, ?int $fileableId = null): File;
    
    public function handle(mixed $source, string $fileType = 'any', ?int $maxSize = null, ?string $folder = null, ?string $fileableType = null, ?int $fileableId = null): File;
    
    public function validateFile(UploadedFile $file, string $fileType = 'any', ?int $maxSize = null): bool;
    
    public function validateUrl(string $url, string $fileType = 'any', ?int $maxSize = null): bool;
    
    public function delete(File $file): bool;
    
    public function getFileTypeConfig(string $fileType): array;
}