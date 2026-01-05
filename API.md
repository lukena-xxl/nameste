# Image Upload API Documentation

## Overview

This application provides a robust temporary image upload handling system with automatic image processing, resizing, and thumbnail generation.

## Installation

1. Clone the repository
2. Install dependencies:
```bash
composer install
```

3. Configure your environment by copying `.env` to `.env.local` and setting:
```env
APP_ENV=dev
APP_SECRET=your-secret-key
```

## API Endpoint

### POST /api/images/upload

Upload an image file with automatic processing and thumbnail generation.

#### Request Parameters

- `file` (required): The image file to upload (multipart/form-data)
- `type` (optional): The image type configuration to use (default: `fish`)

#### Supported File Types

- image/jpeg
- image/png
- image/webp
- image/gif

#### Maximum File Size

10 MB (10,485,760 bytes)

#### Response

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": "/uploads/tmp/fish/uuid-filename.webp"
}
```

**Error Response (400 Bad Request):**
```json
{
  "success": false,
  "message": "Error description"
}
```

#### Example Usage

Using cURL:
```bash
curl -X POST http://localhost:8000/api/images/upload \
  -F "file=@/path/to/image.jpg" \
  -F "type=fish"
```

Using JavaScript (fetch):
```javascript
const formData = new FormData();
formData.append('file', fileInput.files[0]);
formData.append('type', 'fish');

fetch('/api/images/upload', {
  method: 'POST',
  body: formData
})
.then(response => response.json())
.then(data => {
  if (data.success) {
    // Use data.data as img.src or hidden input value
    console.log('Uploaded to:', data.data);
  } else {
    console.error('Error:', data.message);
  }
});
```

## Configuration

All configuration is in `config/services.yaml` under the `parameters` section.

### Global Upload Settings

```yaml
app.image.upload.allowed_mime_types: 'image/jpeg,image/png,image/webp,image/gif'
app.image.upload.allowed_max_size: 10485760 # 10MB in bytes
app.image.upload.tmp_dir: '%kernel.project_dir%/public/uploads/tmp'
```

### Type-Specific Settings (Example: fish)

```yaml
app.image.fish.upload.aspect_ratio: 1.5
app.image.fish.upload.width: 800
app.image.fish.upload.mime_type: 'image/webp'
app.image.fish.upload.quality: 85
app.image.fish.upload.thumbs.small.width: 150
app.image.fish.upload.thumbs.medium.width: 300
```

### Adding a New Type

To add support for a new image type (e.g., `product`), add these parameters:

```yaml
app.image.product.upload.aspect_ratio: 1.0
app.image.product.upload.width: 1000
app.image.product.upload.mime_type: 'image/webp'
app.image.product.upload.quality: 90
app.image.product.upload.thumbs.small.width: 100
app.image.product.upload.thumbs.large.width: 500
```

Then upload with `type=product`:
```bash
curl -X POST /api/images/upload -F "file=@image.jpg" -F "type=product"
```

## Image Processing

### Aspect Ratio Contain Logic

Images are processed using "contain" behavior:

1. A white canvas is created with dimensions: `targetWidth × (targetWidth / aspectRatio)`
2. The original image is resized to fit within these bounds without cropping
3. The resized image is centered on the white canvas
4. The same process is applied to each thumbnail

### Output Structure

Files are saved in the following structure:
```
public/uploads/tmp/
└── {type}/
    ├── {uuid}.{ext}                    # Main image
    └── thumbs/
        ├── small/
        │   └── {uuid}.{ext}
        └── medium/
            └── {uuid}.{ext}
```

Example:
```
public/uploads/tmp/
└── fish/
    ├── a1b2c3d4-e5f6-7890-abcd-ef1234567890.webp
    └── thumbs/
        ├── small/
        │   └── a1b2c3d4-e5f6-7890-abcd-ef1234567890.webp
        └── medium/
            └── a1b2c3d4-e5f6-7890-abcd-ef1234567890.webp
```

## Development

### Running the Development Server

```bash
php -S localhost:8000 -t public
```

Or with environment variables:
```bash
APP_ENV=dev APP_SECRET=secret php -S localhost:8000 -t public
```

### Testing Routes

```bash
php bin/console debug:router
```

## Security

- Server-side validation of MIME types and file sizes
- UUID-based unique filenames to prevent collisions and path traversal
- Directory permissions set to 0755 for security
- Files saved in a dedicated tmp directory
- Only allowed MIME types are accepted

## Architecture

### Components

1. **ImagesUploadController** (`src/Controller/Api/ImagesUploadController.php`)
   - Handles HTTP requests
   - Validates file presence
   - Delegates processing to the service
   - Returns JSON responses

2. **UploadImageService** (`src/Service/UploadImageService.php`)
   - Validates uploads (MIME type, size)
   - Processes images with Intervention Image library
   - Applies aspect ratio contain logic
   - Generates thumbnails
   - Creates unique filenames
   - Saves files to the configured directory

3. **Configuration** (`config/services.yaml`)
   - Global and type-specific parameters
   - Centralized configuration management

## Dependencies

- **Symfony 7.4**: PHP framework
- **Intervention Image 3.x**: Image processing library
- **Ramsey UUID 4.x**: UUID generation for unique filenames

## Troubleshooting

### "No file uploaded" error
Ensure the file input name is `file` in your form.

### "Invalid file type" error
Check that the uploaded file is one of the allowed MIME types.

### "File size exceeds maximum" error
The file is larger than 10MB. Compress or resize the image before uploading.

### Images not displaying
Check that the returned URL path matches your web server configuration and that the `public` directory is set as the document root.
