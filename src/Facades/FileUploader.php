<?php

namespace StalkArtisan\LaravelFileUploader\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \StalkArtisan\LaravelFileUploader\Models\File upload(\Illuminate\Http\UploadedFile $file, string $fileType = 'any', ?int $maxSize = null, ?string $folder = null, ?string $fileableType = null, ?int $fileableId = null)
 * @method static \StalkArtisan\LaravelFileUploader\Models\File uploadFromUrl(string $url, string $fileType = 'any', ?int $maxSize = null, ?string $folder = null, ?string $fileableType = null, ?int $fileableId = null)
 * @method static \StalkArtisan\LaravelFileUploader\Models\File handle(mixed $source, string $fileType = 'any', ?int $maxSize = null, ?string $folder = null, ?string $fileableType = null, ?int $fileableId = null)
 * @method static bool validateFile(\Illuminate\Http\UploadedFile $file, string $fileType = 'any', ?int $maxSize = null)
 * @method static bool validateUrl(string $url, string $fileType = 'any', ?int $maxSize = null)
 * @method static bool delete(\StalkArtisan\LaravelFileUploader\Models\File $file)
 * @method static array getFileTypeConfig(string $fileType)
 */
class FileUploader extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \StalkArtisan\LaravelFileUploader\Contracts\FileUploaderInterface::class;
    }
}