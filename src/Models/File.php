<?php

namespace StalkArtisan\LaravelFileUploader\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class File extends Model
{
    protected $table = 'files';
    
    protected $fillable = [
        'original_name',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'extension',
        'file_type',
        'source_type',
        'source_url',
        'disk',
        'fileable_id',  /* foreign_key modelID */
        'fileable_type' /* Model class User/Post */
    ];
    
    protected $casts = [
        'file_size' => 'integer',
    ];
    
    public function url(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getUrl()
        );
    }
    
    public function getUrl()
    {
        $disk = $this->disk ?: config('file-uploader.storage.disk', 'public');
        return \Storage::disk($disk)->url($this->file_path);
    }
    
    public function getFullPath()
    {
        $disk = $this->disk ?: config('file-uploader.storage.disk', 'public');
        return \Storage::disk($disk)->path($this->file_path);
    }
    
    public function isFromUrl(): bool
    {
        return $this->source_type === 'url';
    }
    
    public function isFromUpload(): bool
    {
        return $this->source_type === 'upload';
    }
}