# nameste

nameste.com.ua - Image Upload API

## Quick Start

```bash
# Install dependencies
composer install

# Start development server
php -S localhost:8000 -t public

# Upload an image
curl -X POST http://localhost:8000/api/images/upload \
  -F "file=@path/to/image.jpg" \
  -F "type=fish"
```

## Features

- ✅ Robust image upload handling
- ✅ Automatic image resizing with aspect ratio preservation
- ✅ Thumbnail generation (configurable sizes)
- ✅ White background for contained images
- ✅ Server-side validation (MIME type, file size)
- ✅ UUID-based unique filenames
- ✅ Per-type configuration support
- ✅ WebP output format support

## Documentation

See [API.md](API.md) for complete API documentation and configuration guide.

## API Endpoint

**POST /api/images/upload**

Upload and process images with automatic thumbnail generation.

## Requirements

- PHP 8.3+
- Composer
- GD or Imagick extension (for image processing)

