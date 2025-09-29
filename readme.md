# Laravel File Uploader
A comprehensive Laravel package for handling file uploads from both local files and URLs, with support for multiple file types, folder organization, and polymorphic relationships. Files are saved to Laravel's storage with customizable folder structures, and metadata is stored in the database for persistent retrieval.

## Features
- **Dual Input Methods**: Upload files from local device or download from URLs
- **Multiple File Types**: Images, videos, PDFs, documents, Excel files, audio, archives, or any file type
- **Folder Organization**: Organize files in custom folder structures
- **Polymorphic Relationships**: Files can belong to any model (Users, Posts, Products, etc.)
- **Configurable Limits**: Customizable file size limits and validation rules
- **Robust Validation**: Comprehensive validation for file types, sizes, and URL accessibility
- **Security**: Path sanitization and MIME type verification
- **Flexible Naming**: Multiple file naming strategies (random, original, timestamp)
- **Error Handling**: Clear error messages for invalid files or URLs

## Supported File Types
| Category   | Supported Formats                              |
|------------|-----------------------------------------------|
| Images     | jpg, jpeg, png, gif, webp, bmp, svg           |
| Videos     | mp4, mkv, avi, mov, webm                      |
| Documents  | pdf, doc, docx, xls, xlsx, ppt, pptx, txt, rtf|
| Excel      | xls, xlsx, csv                                |
| Audio      | mp3, wav, ogg, aac, flac                      |
| Archives   | zip, rar, 7z, tar, gz                         |
| Any        | All supported formats combined                 |

## Requirements
- PHP ^8.1
- Laravel ^9.0, ^10.0, ^11.0, or ^12.0

## Installation
Install the package via Composer:

```bash
composer require stalkdeveloper/laravel-file-uploader
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=file-uploader-config
```

Publish and run migrations:

```bash
php artisan vendor:publish --tag=file-uploader-migrations
php artisan migrate
```

Create storage symlink (if using public disk):

```bash
php artisan storage:link
```

## Configuration
The configuration file is located at `config/file-uploader.php`. Key options:

```php
return [
    'storage' => [
        'disk' => 'public',      // Storage disk
        'path' => 'files',       // Base storage directory
        'url_path' => 'storage/files',
    ],
    
    'defaults' => [
        'max_size' => 5120,      // 5MB default
        'file_type' => 'any',
    ],
    
    'file_types' => [
        'image' => [
            'mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'],
            'max_size' => 5120,  // 5MB
        ],
        'video' => [
            'mimes' => ['mp4', 'mkv', 'avi', 'mov', 'webm'],
            'max_size' => 51200, // 50MB
        ],
        // ... more file types
    ],
    
    'validation' => [
        'url' => [
            'timeout' => 60,
            'user_agent' => 'Laravel File Uploader/1.0',
        ],
    ],
    
    'database' => [
        'table' => 'files',
        'model' => \StalkArtisan\LaravelFileUploader\Models\File::class,
    ],
    
    'naming' => [
        'strategy' => 'random', // random, original, timestamp
        'length' => 40,
    ],
];
```
## Usage
### 1. Add Trait to Your Model
```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use StalkArtisan\LaravelFileUploader\Traits\HasFileUploads;

class User extends Authenticatable
{
    use HasFileUploads;
}

class Post extends Model
{
    use HasFileUploads;
}
```

### 2. Basic File Upload
#### Using the Trait (Recommended)
```php
// Upload local file
$file = $user->uploadFile($request->file('avatar'), 'image');

// Upload from URL
$file = $user->uploadFile('https://example.com/image.jpg', 'image');

// With custom size limit (10MB)
$file = $user->uploadFile($request->file('document'), 'pdf', 10240);
```

#### Using Facade Directly
```php
use StalkArtisan\LaravelFileUploader\Facades\FileUploader;

$file = FileUploader::upload($request->file('file'), 'image');
$file = FileUploader::uploadFromUrl('https://example.com/file.pdf', 'pdf');
```

### 3. Advanced Usage with Folder Structure
```php
// Upload to specific folder
$file = $user->uploadFile(
    $request->file('avatar'), 
    'image', 
    null, 
    'users/'.$user->id.'/avatars'
);

// Upload with custom name
$file = $user->uploadFile(
    $request->file('document'), 
    'pdf', 
    null, 
    'documents',
    'custom-filename'
);

// Module-based organization
$file = $user->uploadFileToModule(
    $request->file('profile_picture'), 
    'profile', 
    'image'
);
```

### 4. Fileable Relationships
```php
// For Post model with fileable relationship
$file = FileUploader::upload(
    $request->file('image'), 
    'image', 
    null, 
    'posts/'.$postId.'/images',
    'App\Models\Post',
    $postId
);

// Get files for a model
$userFiles = $user->files;
$postImages = $post->files;

// Get files by module
$profileImages = $user->getFilesByModule('profile');
```

### 5. Complete Form Example
```html
<form method="POST" action="{{ route('files.upload') }}" enctype="multipart/form-data">
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
        <input type="file" name="file" required>
        <input type="text" name="folder" placeholder="Folder path (optional)">
        <input type="text" name="custom_name" placeholder="Custom file name (optional)">
        <select name="file_type">
            <option value="any">Any Type</option>
            <option value="image">Image</option>
            <option value="video">Video</option>
            <option value="pdf">PDF</option>
            <option value="document">Document</option>
            <option value="excel">Excel</option>
        </select>
        <input type="number" name="max_size" placeholder="Max size in KB (optional)">
    </div>

    <div id="url-upload" style="display: none;">
        <input type="url" name="file_url" placeholder="https://example.com/file.jpg" required>
        <input type="text" name="folder" placeholder="Folder path (optional)">
        <input type="text" name="custom_name" placeholder="Custom file name (optional)">
        <select name="file_type">
            <option value="any">Any Type</option>
            <option value="image">Image</option>
            <option value="video">Video</option>
            <option value="pdf">PDF</option>
            <option value="document">Document</option>
            <option value="excel">Excel</option>
        </select>
        <input type="number" name="max_size" placeholder="Max size in KB (optional)">
    </div>

    <button type="submit">Upload</button>
</form>

<script>
    document.querySelectorAll('input[name="upload_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const isFileUpload = this.value === 'file';
            document.getElementById('file-upload').style.display = isFileUpload ? 'block' : 'none';
            document.getElementById('url-upload').style.display = isFileUpload ? 'none' : 'block';
        });
    });
</script>
```

### 6. Controller Implementation
```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FileController extends Controller
{
    public function upload(Request $request)
    {
        $uploadType = $request->input('upload_type', 'file');
        
        if ($uploadType === 'file') {
            $validated = $request->validate([
                'file' => 'required|file',
                'folder' => 'nullable|string',
                'custom_name' => 'nullable|string',
                'file_type' => 'required|in:any,image,video,pdf,document,excel',
                'max_size' => 'nullable|integer|min:1',
            ]);
            
            $source = $request->file('file');
            $folder = $request->folder;
            $customName = $request->custom_name;
            $fileType = $request->file_type;
            $maxSize = $request->max_size;
        } else {
            $validated = $request->validate([
                'file_url' => 'required|url',
                'folder' => 'nullable|string',
                'custom_name' => 'nullable|string',
                'file_type' => 'required|in:any,image,video,pdf,document,excel',
                'max_size' => 'nullable|integer|min:1',
            ]);
            
            $source = $request->input('file_url');
            $folder = $request->folder;
            $customName = $request->custom_name;
            $fileType = $request->file_type;
            $maxSize = $request->max_size;
        }

        try {
            $file = auth()->user()->uploadFile($source, $fileType, $maxSize, $folder, $customName);
            
            return response()->json([
                'success' => true,
                'file' => [
                    'id' => $file->id,
                    'url' => $file->url,
                    'path' => $file->file_path,
                    'original_name' => $file->original_name,
                    'file_type' => $file->file_type,
                    'size' => $file->file_size,
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
    
    public function delete($fileId)
    {
        $file = \StalkArtisan\LaravelFileUploader\Models\File::findOrFail($fileId);
        
        // Check ownership or use policies
        if ($file->fileable_id !== auth()->id()) {
            abort(403);
        }
        
        \StalkArtisan\LaravelFileUploader\Facades\FileUploader::delete($file);
        
        return response()->json(['success' => true]);
    }
}
```

### 7. Displaying Files
```blade
{{-- Display user's files --}}
@foreach($user->files as $file)
    <div class="file-item">
        @if($file->isImage())
            <img src="{{ $file->url }}" alt="{{ $file->original_name }}" style="max-width: 200px;">
        @elseif($file->isVideo())
            <video controls style="max-width: 200px;">
                <source src="{{ $file->url }}" type="{{ $file->mime_type }}">
            </video>
        @else
            <a href="{{ $file->url }}" target="_blank" class="file-link">
                📄 {{ $file->original_name }}
            </a>
        @endif
        
        <small>
            {{ round($file->file_size / 1024, 2) }} KB • 
            {{ $file->created_at->format('M j, Y') }}
        </small>
        
        <form action="{{ route('files.delete', $file->id) }}" method="POST" class="d-inline">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
        </form>
    </div>
@endforeach
```

## API Reference
### FileUploader Facade Methods
| Method | Description |
|--------|-------------|
| `upload(UploadedFile $file, string $fileType, ?int $maxSize, ?string $folder, ?string $fileableType, ?int $fileableId)` | Upload a file |
| `uploadFromUrl(string $url, string $fileType, ?int $maxSize, ?string $folder, ?string $fileableType, ?int $fileableId)` | Upload from URL |
| `handle(mixed $source, string $fileType, ?int $maxSize, ?string $folder, ?string $fileableType, ?int $fileableId)` | Handle both file and URL |
| `validateFile(UploadedFile $file, string $fileType, ?int $maxSize)` | Validate file |
| `validateUrl(string $url, string $fileType, ?int $maxSize)` | Validate URL |
| `delete(File $file)` | Delete file |
| `getFileTypeConfig(string $fileType)` | Get file type configuration |

### HasFileUploads Trait Methods
| Method | Description |
|--------|-------------|
| `uploadFile(mixed $source, string $fileType, ?int $maxSize, ?string $folder, ?string $customName)` | Upload file for model |
| `uploadFileToModule(mixed $source, string $module, string $fileType, ?int $maxSize, ?string $customName)` | Upload to module folder |
| `files()` | Get all files for model |
| `getFilesByModule(string $module)` | Get files by module |
| `deleteFiles()` | Delete all model files |

## Database Schema
The `files` table includes:

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `original_name` | string | Original file name |
| `file_name` | string | Stored file name |
| `file_path` | string | Storage path |
| `file_size` | integer | File size in bytes |
| `mime_type` | string | MIME type |
| `extension` | string | File extension |
| `file_type` | string | Category (image, video, pdf, etc.) |
| `source_type` | enum | Source (upload/url) |
| `source_url` | text | Original URL (for URL downloads) |
| `disk` | string | Storage disk |
| `fileable_id` | bigint | Polymorphic relation ID |
| `fileable_type` | string | Polymorphic relation type |
| `created_at`, `updated_at` | timestamp | Timestamps |

## Error Handling
The package throws `InvalidFileException` with specific messages:
- Invalid file provided. - General file validation failure
- Invalid URL: {url} - URL validation failed
- Failed to download file from URL: {url} - URL download failed
- File size exceeds maximum limit of {size} KB - File too large
- Unsupported MIME type: {mime_type} - Invalid file type

## Testing
Create a test route to verify installation:

```php
// routes/web.php
Route::get('/test-upload', function() {
    return view('test-upload-form');
});

Route::post('/test-upload', function(\Illuminate\Http\Request $request) {
    try {
        $file = auth()->user()->uploadFile(
            $request->file('file') ?? $request->input('file_url'),
            $request->file_type ?? 'any',
            $request->max_size,
            $request->folder,
            $request->custom_name
        );
        
        return back()->with('success', 'File uploaded: ' . $file->url);
    } catch (\Exception $e) {
        return back()->with('error', $e->getMessage());
    }
});
```

## Security Features
- **Path Sanitization**: Prevents directory traversal attacks
- **MIME Type Verification**: Validates file content, not just extension
- **Size Limits**: Configurable maximum file sizes
- **URL Validation**: Ensures only http/https URLs are accepted
- **File Type Restrictions**: Whitelist-based file type validation

## Contributing
Contributions are welcome! Please:
- Fork the repository
- Create a feature branch
- Make your changes
- Add tests
- Submit a pull request

## License
This package is open-sourced software licensed under the [MIT License](LICENSE).

## Support
For issues and questions:
- Create an issue on [GitHub](https://github.com/stalkdeveloper/laravel-file-uploader)
- Email: sunnyk.kongu@gmail.com

Happy uploading! 🚀