# Laravel File Uploader

A Laravel package for handling file uploads from both local files and URLs, with support for multiple file types (images, videos, PDFs, documents, Excel files, or any file). Files are saved to Laravel's public storage, and metadata is stored in the database for persistent retrieval. The package provides a flexible API, robust validation, and polymorphic relationships for easy integration with any model.

## Features
- **Dual Input Methods**: Upload files from a local device or provide a URL to download files.
- **Supported File Types**: Images (jpg, png, gif, webp, bmp, svg), videos (mp4, mkv, avi, mov), PDFs, documents (doc, docx, ppt, pptx), Excel files (xls, xlsx, csv), or any supported type.
- **Configurable File Size**: Default max size of 5MB, with the ability to override in the controller.
- **Validation**: Robust validation for file types, sizes, and URL accessibility.
- **Storage**: Saves files to Laravel's public storage (`storage/app/public/files` by default).
- **Database Integration**: Stores file metadata in a `files` table with polymorphic relations for easy retrieval.
- **Trait-Based Integration**: Use the `HasFileUploads` trait to add upload functionality to any model.
- **Error Handling**: Clear error messages for invalid files, URLs, or unsupported formats.

## Requirements
- PHP ^8.1
- Laravel ^9.0, ^10.0, ^11.0, or ^12.0

## Installation
1. Install the package via Composer:
   ```bash
   composer require stalkdeveloper/laravel-file-uploader
   ```

2. Publish the configuration file:
   ```bash
   php artisan vendor:publish --tag=file-uploader-config
   ```

3. Publish and run migrations:
   ```bash
   php artisan vendor:publish --tag=file-uploader-migrations
   php artisan migrate
   ```

   This creates a `files` table to store file metadata.

4. Ensure the storage symlink is created:
   ```bash
   php artisan storage:link
   ```

## Configuration
The configuration file is located at `config/file-uploader.php`. Key options include:
- **`storage.disk`**: Storage disk (default: `public`).
- **`storage.path`**: Storage directory (default: `files`).
- **`validation.file.max_size`**: Default max file size (5MB, in KB).
- **`validation.file.mimes`**: Allowed file extensions by type (image, video, pdf, document, excel, any).
- **`naming.strategy`**: File naming strategy (`random`, `original`, `timestamp`).

Example configuration:
```php
return [
    'storage' => [
        'disk' => 'public',
        'path' => 'files',
        'url_path' => 'storage/files',
    ],
    'validation' => [
        'file' => [
            'max_size' => 5120, // 5MB
            'mimes' => [
                'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'],
                'video' => ['mp4', 'mkv', 'avi', 'mov'],
                // ...
            ],
        ],
        // ...
    ],
];
```

## Package Structure
The package follows a modular structure to ensure maintainability and extensibility:

```
src/
├── Contracts/
│   └── FileUploaderInterface.php       # Interface defining file uploader methods
├── config/
│   └── file-uploader.php              # Configuration file for storage, validation, and naming
├── database/
│   └── migrations/2024_01_01_000000_create_files_table.php  # Migration for the files table
├── Exceptions/
│   └── InvalidFileException.php        # Custom exception for file validation errors
├── Facades/
│   └── FileUploader.php                # Facade for easy access to the uploader service
├── Models/
│   └── File.php                       # Eloquent model for file metadata
├── Providers/
│   └── FileUploaderServiceProvider.php # Service provider for package registration
├── Services/
│   └── FileUploaderService.php         # Core logic for file uploads and validation
└── Traits/
    └── HasFileUploads.php             # Trait for adding upload functionality to models
```

## Usage
### 1. Add the Trait to Your Model
Add the `HasFileUploads` trait to any model (e.g., `User`):
```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use StalkArtisan\LaravelFileUploader\Traits\HasFileUploads;

class User extends Authenticatable
{
    use HasFileUploads;
}
```

### 2. Create a Form
In your Blade view (e.g., `resources/views/profile/edit.blade.php`):
```html
<form method="POST" action="{{ route('profile.update.file') }}" enctype="multipart/form-data">
    @csrf
    <div>
        <label>
            <input type="radio" name="upload_type" value="file" checked> Upload File
        </label>
        <label>
            <input type="radio" name="upload_type" value="url"> Provide URL
        </label>
    </div>
    <div id="file-upload">
        <input type="file" name="file">
        <select name="file_type">
            <option value="any">Any</option>
            <option value="image">Image</option>
            <option value="video">Video</option>
            <option value="pdf">PDF</option>
            <option value="document">Document</option>
            <option value="excel">Excel</option>
        </select>
        <input type="number" name="max_size" placeholder="Max size in KB (optional)">
    </div>
    <div id="url-upload" style="display: none;">
        <input type="url" name="file_url" placeholder="https://example.com/file.pdf">
        <select name="file_type">
            <option value="any">Any</option>
            <option value="image">Image</option>
            <option value="video">Video</option>
            <option value="pdf">PDF</option>
            <option value="document">Document</option>
            <option value="excel">Excel</option>
        </select>
        <input type="number" name="max_size" placeholder="Max size in KB (optional)">
    </div>
    <button type="submit">Submit</button>
</form>

<script>
    document.querySelectorAll('input[name="upload_type"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.getElementById('file-upload').style.display = radio.value === 'file' ? 'block' : 'none';
            document.getElementById('url-upload').style.display = radio.value === 'url' ? 'block' : 'none';
        });
    });
</script>
```

### 3. Handle Uploads in Controller
In your controller (e.g., `app/Http/Controllers/ProfileController.php`):
```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use StalkArtisan\LaravelFileUploader\Facades\FileUploader;

class ProfileController extends Controller
{
    public function updateFile(Request $request)
    {
        $request->validate([
            'file' => 'required_without:file_url|file',
            'file_url' => 'required_without:file|url|active_url',
            'file_type' => 'required|in:any,image,video,pdf,document,excel',
            'max_size' => 'nullable|integer|min:1024',
        ]);

        try {
            $fileType = $request->file_type ?? 'any';
            $maxSize = $request->max_size; // Override default if provided
            $source = $request->hasFile('file') ? $request->file('file') : $request->file_url;

            $file = auth()->user()->uploadFile($source, $fileType, $maxSize);
            return response()->json(['message' => 'File uploaded successfully', 'url' => $file->url]);
        } catch (\StalkArtisan\LaravelFileUploader\Exceptions\InvalidFileException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
```

### 4. Display Files
In your Blade view (e.g., `resources/views/profile/show.blade.php`):
```html
@if (auth()->user()->file_url)
    @if (in_array(auth()->user()->files()->latest()->first()->file_type, ['image']))
        <img src="{{ auth()->user()->file_url }}" alt="Uploaded File">
    @else
        <a href="{{ auth()->user()->file_url }}" target="_blank">View File</a>
    @endif
@else
    <p>No file uploaded</p>
@endif
```

### 5. Define Routes
In `routes/web.php` or `api.php`:
```php
Route::post('/profile/update-file', [App\Http\Controllers\ProfileController::class, 'updateFile'])->name('profile.update.file');
```

## API Usage
The package provides a facade (`FileUploader`) for direct use:
```php
use StalkArtisan\LaravelFileUploader\Facades\FileUploader;

// Upload a local file
$file = FileUploader::upload($request->file('file'), 'pdf', 10240); // 10MB override

// Upload from URL
$file = FileUploader::uploadFromUrl('https://example.com/document.pdf', 'pdf');

// Delete a file
FileUploader::delete($file);
```

## Database Schema
The `files` table stores:
- `id`: Primary key
- `original_name`: Original file name
- `file_name`: Stored file name
- `file_path`: Storage path
- `file_size`: Size in bytes
- `mime_type`: MIME type
- `extension`: File extension
- `file_type`: Category (image, video, pdf, document, excel, any)
- `source_type`: Source (upload or url)
- `source_url`: URL if downloaded
- `disk`: Storage disk
- `fileable_id`, `fileable_type`: Polymorphic relation
- `created_at`, `updated_at`: Timestamps

## Contributing
Contributions are welcome! Please submit a pull request or open an issue on [GitHub](https://github.com/stalkdeveloper/laravel-file-uploader).

## License
This package is open-sourced software licensed under the [MIT License](LICENSE).