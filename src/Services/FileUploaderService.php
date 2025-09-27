<?php

namespace StalkArtisan\LaravelFileUploader\Services;

use StalkArtisan\LaravelFileUploader\Contracts\FileUploaderInterface;
use StalkArtisan\LaravelFileUploader\Exceptions\InvalidFileException;
use StalkArtisan\LaravelFileUploader\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploaderService implements FileUploaderInterface
{
    public function upload(UploadedFile $file, string $fileType = 'any', ?int $maxSize = null): File
    {
        $this->validateFile($file, $fileType, $maxSize);
        
        $config = config('file-uploader');
        $disk = $config['storage']['disk'];
        $path = $config['storage']['path'];
        
        $fileName = $this->generateFileName($file);
        $filePath = $file->storeAs($path, $fileName, ['disk' => $disk]);
        
        return File::create([
            'original_name' => $file->getClientOriginalName(),
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'extension' => strtolower($file->getClientOriginalExtension()),
            'file_type' => $fileType,
            'source_type' => 'upload',
            'disk' => $disk,
        ]);
    }
    
    public function uploadFromUrl(string $url, string $fileType = 'any', ?int $maxSize = null): File
    {
        $this->validateUrl($url, $fileType, $maxSize);
        
        $config = config('file-uploader');
        $timeout = $config['validation']['url']['timeout'];
        
        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'User-Agent' => $config['validation']['url']['user_agent']
                ])
                ->get($url);
                
            if (!$response->successful()) {
                throw InvalidFileException::downloadFailed($url);
            }
            
            $content = $response->body();
            $contentSize = strlen($content);
            
            // Validate content size
            $maxSize = $maxSize ?? $config['validation']['url']['max_size'] * 1024;
            if ($contentSize > $maxSize) {
                throw InvalidFileException::sizeExceeded($maxSize / 1024);
            }
            
            // Validate MIME type
            $mimeType = $this->getMimeTypeFromContent($content);
            if (!$this->isValidMimeType($mimeType, $fileType, 'url')) {
                throw InvalidFileException::invalidMimeType($mimeType);
            }
            
            // Create temporary file
            $tempPath = tempnam(sys_get_temp_dir(), 'laravel_file_');
            file_put_contents($tempPath, $content);
            
            $extension = $this->getExtensionFromMimeType($mimeType);
            $originalName = $this->extractFilenameFromUrl($url) ?: "file.{$extension}";
            
            $uploadedFile = new UploadedFile(
                $tempPath,
                $originalName,
                $mimeType,
                null,
                true
            );
            
            $file = $this->upload($uploadedFile, $fileType, $maxSize);
            $file->update([
                'source_type' => 'url',
                'source_url' => $url,
            ]);
            
            // Clean up temporary file
            unlink($tempPath);
            
            return $file;
            
        } catch (InvalidFileException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw InvalidFileException::downloadFailed($url);
        }
    }
    
    public function handle(mixed $source, string $fileType = 'any', ?int $maxSize = null): File
    {
        if ($source instanceof UploadedFile) {
            return $this->upload($source, $fileType, $maxSize);
        }
        
        if (is_string($source) && filter_var($source, FILTER_VALIDATE_URL)) {
            return $this->uploadFromUrl($source, $fileType, $maxSize);
        }
        
        throw InvalidFileException::invalidFile();
    }
    
    public function validateFile(UploadedFile $file, string $fileType = 'any', ?int $maxSize = null): bool
    {
        $config = config('file-uploader.validation.file');
        
        if (!$file->isValid()) {
            throw InvalidFileException::invalidFile();
        }
        
        $maxSize = $maxSize ?? $config['max_size'] * 1024;
        if ($file->getSize() > $maxSize) {
            throw InvalidFileException::sizeExceeded($maxSize / 1024);
        }
        
        $mimeType = $file->getMimeType();
        if (!$this->isValidMimeType($mimeType, $fileType, 'file')) {
            throw InvalidFileException::invalidMimeType($mimeType);
        }
        
        return true;
    }
    
    public function validateUrl(string $url, string $fileType = 'any', ?int $maxSize = null): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw InvalidFileException::invalidUrl($url);
        }
        
        $allowedSchemes = ['http', 'https'];
        $scheme = parse_url($url, PHP_URL_SCHEME);
        
        if (!in_array($scheme, $allowedSchemes)) {
            throw InvalidFileException::invalidUrl($url);
        }
        
        // Quick header check
        $headers = @get_headers($url, 1);
        if (!$headers || strpos($headers[0], '200') === false) {
            throw InvalidFileException::invalidUrl($url);
        }
        
        $contentType = $headers['Content-Type'] ?? '';
        if (!is_string($contentType)) {
            $contentType = end($contentType);
        }
        
        if (!$this->isValidContentType($contentType, $fileType)) {
            throw InvalidFileException::invalidUrl($url);
        }
        
        return true;
    }
    
    public function delete(File $file): bool
    {
        try {
            Storage::disk($file->disk)->delete($file->file_path);
            return $file->delete();
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function generateFileName(UploadedFile $file): string
    {
        $config = config('file-uploader.naming');
        $extension = strtolower($file->getClientOriginalExtension());
        
        return match ($config['strategy']) {
            'original' => Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $extension,
            'timestamp' => time() . '_' . Str::random(10) . '.' . $extension,
            default => Str::random($config['length']) . '.' . $extension,
        };
    }
    
    private function getMimeTypeFromContent(string $content): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $content);
        finfo_close($finfo);
        
        return $mimeType;
    }
    
    private function isValidMimeType(string $mimeType, string $fileType, string $source = 'file'): bool
    {
        $config = config("file-uploader.validation.{$source}");
        $allowedMimes = $config['mimes'][$fileType] ?? $config['mimes']['any'];
        
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4',
            'mkv' => 'video/x-matroska',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'csv' => 'text/csv',
        ];
        
        $allowedMimeTypes = array_intersect_key($mimeMap, array_flip($allowedMimes));
        
        return in_array($mimeType, $allowedMimeTypes);
    }
    
    private function isValidContentType(string $contentType, string $fileType): bool
    {
        $allowedTypes = [
            'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'],
            'video' => ['video/mp4', 'video/x-matroska', 'video/x-msvideo', 'video/quicktime'],
            'pdf' => ['application/pdf'],
            'document' => ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                          'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                          'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'excel' => ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'],
            'any' => [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml',
                'video/mp4', 'video/x-matroska', 'video/x-msvideo', 'video/quicktime',
                'application/pdf',
                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/csv'
            ]
        ];
        
        foreach ($allowedTypes[$fileType] ?? $allowedTypes['any'] as $type) {
            if (strpos($contentType, $type) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function extractFilenameFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        return basename($path) ?: '';
    }
    
    private function getExtensionFromMimeType(string $mimeType): string
    {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/svg+xml' => 'svg',
            'video/mp4' => 'mp4',
            'video/x-matroska' => 'mkv',
            'video/x-msvideo' => 'avi',
            'video/quicktime' => 'mov',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/csv' => 'csv',
        ];
        
        return $mimeMap[$mimeType] ?? 'bin';
    }
}