<?php

namespace StalkArtisan\LaravelFileUploader\Providers;

use Illuminate\Support\ServiceProvider;
use StalkArtisan\LaravelFileUploader\Contracts\FileUploaderInterface;
use StalkArtisan\LaravelFileUploader\Services\FileUploaderService;

class FileUploaderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FileUploaderInterface::class, FileUploaderService::class);
        $this->app->alias(FileUploaderInterface::class, 'file-uploader');

        $this->mergeConfigFrom(
            __DIR__.'/../config/file-uploader.php', 'file-uploader'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/file-uploader.php' => config_path('file-uploader.php'),
        ], 'file-uploader-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'file-uploader-migrations');
    }
}