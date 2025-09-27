<?php

namespace StalkArtisan\LaravelFileUploader\Exceptions;

use Exception;

class InvalidFileException extends Exception
{
    protected $message = 'Invalid file or unsupported file format';

    public static function invalidFile(string $message = null): self
    {
        return new static($message ?: 'The uploaded file is invalid');
    }

    public static function invalidUrl(string $url): self
    {
        return new static("Invalid URL: {$url}");
    }

    public static function downloadFailed(string $url): self
    {
        return new static("Failed to download file from URL: {$url}");
    }

    public static function sizeExceeded(int $maxSize): self
    {
        return new static("File size exceeds maximum limit of {$maxSize} KB");
    }

    public static function invalidMimeType(string $mimeType): self
    {
        return new static("Unsupported MIME type: {$mimeType}");
    }
    
    public function __construct($message = null, $code = 0, Exception $previous = null)
    {
        parent::__construct($message ?: $this->message, $code, $previous);
    }
}