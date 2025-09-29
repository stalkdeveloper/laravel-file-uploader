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
    public function upload(UploadedFile $file, string $fileType = 'any', ?int $maxSize = null, ?string $folder = null, ?string $fileableType = null, ?int $fileableId = null): File
    {
        $this->validateFile($file, $fileType, $maxSize);
        
        $config = config('file-uploader');
        $disk = $config['storage']['disk'];
        
        // Build the storage path with folder structure
        $storagePath = $this->buildStoragePath($config['storage']['path'], $folder);
        
        $fileName = $this->generateFileName($file);
        $filePath = $file->storeAs($storagePath, $fileName, ['disk' => $disk]);
        
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
            'fileable_type' => $fileableType,
            'fileable_id' => $fileableId,
        ]);
    }
    
    public function uploadFromUrl(string $url, string $fileType = 'any', ?int $maxSize = null, ?string $folder = null, ?string $fileableType = null, ?int $fileableId = null): File
    {
        \Log::info("Starting URL upload: {$url}");
        
        // Simple URL validation first
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw InvalidFileException::invalidUrl($url);
        }
        
        $config = config('file-uploader');
        $timeout = $config['validation']['url']['timeout'];
        
        try {
            \Log::info("Making HTTP request to: {$url}");
            
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'User-Agent' => $config['validation']['url']['user_agent'],
                    'Accept' => '*/*',
                ])
                ->get($url);
                
            \Log::info("HTTP Response Status: " . $response->status());
            
            if (!$response->successful()) {
                \Log::error("HTTP request failed", [
                    'status' => $response->status(),
                    'url' => $url
                ]);
                throw InvalidFileException::downloadFailed($url);
            }
            
            $content = $response->body();
            $contentSize = strlen($content);
            
            \Log::info("Downloaded content size: " . $contentSize . " bytes");
            
            // Get file type config
            $fileTypeConfig = $this->getFileTypeConfig($fileType);
            $maxSizeKB = $maxSize ?: $fileTypeConfig['max_size'];
            $maxSizeBytes = $maxSizeKB * 1024;
            
            if ($contentSize > $maxSizeBytes) {
                \Log::error("File size exceeded", [
                    'content_size' => $contentSize,
                    'max_size' => $maxSizeBytes
                ]);
                throw InvalidFileException::sizeExceeded($maxSizeKB);
            }
            
            // Validate MIME type from content
            $mimeType = $this->getMimeTypeFromContent($content);
            \Log::info("Detected MIME type: " . $mimeType);
            
            if (!$this->isValidMimeType($mimeType, $fileType)) {
                \Log::error("Invalid MIME type", [
                    'mime_type' => $mimeType,
                    'file_type' => $fileType
                ]);
                throw InvalidFileException::invalidMimeType($mimeType);
            }
            
            // Create temporary file
            $tempPath = tempnam(sys_get_temp_dir(), 'laravel_file_');
            file_put_contents($tempPath, $content);
            
            $extension = $this->getExtensionFromMimeType($mimeType);
            $originalName = $this->extractFilenameFromUrl($url) ?: "file.{$extension}";
            
            \Log::info("Creating UploadedFile", [
                'temp_path' => $tempPath,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'extension' => $extension
            ]);
            
            $uploadedFile = new UploadedFile(
                $tempPath,
                $originalName,
                $mimeType,
                null,
                true
            );
            
            $file = $this->upload($uploadedFile, $fileType, $maxSize, $folder, $fileableType, $fileableId);
            $file->update([
                'source_type' => 'url',
                'source_url' => $url,
            ]);
            
            // Clean up temporary file
            unlink($tempPath);
            
            \Log::info("URL upload completed successfully", [
                'file_id' => $file->id,
                'file_path' => $file->file_path
            ]);
            
            return $file;
            
        } catch (InvalidFileException $e) {
            \Log::error("InvalidFileException in uploadFromUrl", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Exception $e) {
            \Log::error("Exception in uploadFromUrl", [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw InvalidFileException::downloadFailed($url . " - " . $e->getMessage());
        }
    }
    
    public function handle(mixed $source, string $fileType = 'any', ?int $maxSize = null, ?string $folder = null, ?string $fileableType = null, ?int $fileableId = null): File
    {
        if ($source instanceof UploadedFile) {
            return $this->upload($source, $fileType, $maxSize, $folder, $fileableType, $fileableId);
        }
        
        if (is_string($source) && filter_var($source, FILTER_VALIDATE_URL)) {
            return $this->uploadFromUrl($source, $fileType, $maxSize, $folder, $fileableType, $fileableId);
        }
        
        throw InvalidFileException::invalidFile();
    }
    
    public function validateFile(UploadedFile $file, string $fileType = 'any', ?int $maxSize = null): bool
    {
        $fileTypeConfig = $this->getFileTypeConfig($fileType);
        
        if (!$file->isValid()) {
            throw InvalidFileException::invalidFile();
        }
        
        $maxSizeKB = $maxSize ?: $fileTypeConfig['max_size'];
        $maxSizeBytes = $maxSizeKB * 1024;
        
        if ($file->getSize() > $maxSizeBytes) {
            throw InvalidFileException::sizeExceeded($maxSizeKB);
        }
        
        $mimeType = $file->getMimeType();
        if (!$this->isValidMimeType($mimeType, $fileType)) {
            throw InvalidFileException::invalidMimeType($mimeType);
        }
        
        return true;
    }
    
    public function validateUrl(string $url, string $fileType = 'any', ?int $maxSize = null): bool
    {
        // Simple URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw InvalidFileException::invalidUrl($url);
        }
        
        $allowedSchemes = ['http', 'https'];
        $scheme = parse_url($url, PHP_URL_SCHEME);
        
        if (!in_array($scheme, $allowedSchemes)) {
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
    
    public function getFileTypeConfig(string $fileType): array
    {
        $config = config('file-uploader.file_types');
        
        if (!isset($config[$fileType])) {
            throw new \Exception("Unsupported file type: {$fileType}");
        }
        
        return $config[$fileType];
    }
    
    /**
     * Build storage path with folder structure
     */
    private function buildStoragePath(string $basePath, ?string $folder = null): string
    {
        $path = $basePath;
        
        if ($folder) {
            // Clean the folder path (remove slashes, dots, etc.)
            $cleanFolder = $this->cleanFolderPath($folder);
            $path = $basePath . '/' . $cleanFolder;
        }
        
        return $path;
    }
    
    /**
     * Clean folder path to prevent directory traversal
     */
    private function cleanFolderPath(string $folder): string
    {
        // Remove any dangerous characters and normalize path
        $folder = str_replace(['..', '\\', '//'], '', $folder);
        $folder = trim($folder, '/');
        $folder = preg_replace('/[^a-zA-Z0-9_\-\/]/', '_', $folder);
        
        return $folder;
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
        if (empty($content)) {
            throw new \Exception("Empty content cannot have MIME type");
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $content);
        finfo_close($finfo);
        
        return $mimeType ?: 'application/octet-stream';
    }
    
    private function isValidMimeType(string $mimeType, string $fileType): bool
    {
        $fileTypeConfig = $this->getFileTypeConfig($fileType);
        $allowedExtensions = $fileTypeConfig['mimes'];
        
        $mimeMap = $this->getMimeMap();
        
        $allowedMimeTypes = [];
        foreach ($allowedExtensions as $ext) {
            if (isset($mimeMap[$ext])) {
                $allowedMimeTypes = array_merge($allowedMimeTypes, (array)$mimeMap[$ext]);
            }
        }
        
        $allowedMimeTypes = array_unique($allowedMimeTypes);
        
        \Log::info("Checking MIME type", [
            'mime_type' => $mimeType,
            'file_type' => $fileType,
            'allowed_mime_types' => $allowedMimeTypes
        ]);
        
        return in_array($mimeType, $allowedMimeTypes);
    }
    
    private function extractFilenameFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $filename = basename($path) ?: 'downloaded_file';
        
        // If no extension, try to get from Content-Disposition or use default
        if (!pathinfo($filename, PATHINFO_EXTENSION)) {
            $filename .= '.tmp';
        }
        
        return $filename;
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
            'video/webm' => 'webm',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/csv' => 'csv',
            'text/plain' => 'txt',
            'application/rtf' => 'rtf',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            'application/zip' => 'zip',
            'application/x-rar-compressed' => 'rar',
        ];
        
        return $mimeMap[$mimeType] ?? 'bin';
    }
    
    private function getMimeMap(): array
    {
        return [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'bmp' => ['image/bmp'],
            'svg' => ['image/svg+xml'],
            'mp4' => ['video/mp4'],
            'mkv' => ['video/x-matroska'],
            'avi' => ['video/x-msvideo'],
            'mov' => ['video/quicktime'],
            'webm' => ['video/webm'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'ppt' => ['application/vnd.ms-powerpoint'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            'csv' => ['text/csv'],
            'txt' => ['text/plain'],
            'rtf' => ['application/rtf'],
            'mp3' => ['audio/mpeg'],
            'wav' => ['audio/wav'],
            'ogg' => ['audio/ogg'],
            'zip' => ['application/zip'],
            'rar' => ['application/x-rar-compressed'],
        ];
    }
}