<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('file_name');
            $table->string('file_path');
            $table->integer('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('extension')->nullable();
            $table->string('file_type')->default('any'); // image, video, pdf, document, excel, any
            $table->enum('source_type', ['upload', 'url'])->default('upload');
            $table->text('source_url')->nullable();
            $table->string('disk')->default('public');
            $table->nullableMorphs('fileable');
            $table->timestamps();
            
            $table->index('file_path');
            $table->index('source_type');
            $table->index(['fileable_id', 'fileable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};