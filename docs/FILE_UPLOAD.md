# File Upload Endpoint Documentation

## Overview

Secure file upload endpoint that validates files against user-specific allowed file types.

## Endpoint

```
POST /upload
```

## Authentication

**Required**: User must be logged in (session-based authentication)

## Request

- **Content-Type**: `multipart/form-data`
- **Body Parameter**: `file` (the file to upload)

### Example using cURL

```bash
curl -X POST http://localhost/upload \
  -H "Cookie: PHPSESSID=your-session-id" \
  -F "file=@/path/to/your/document.pdf"
```

### Example using JavaScript (Fetch API)

```javascript
const formData = new FormData();
formData.append("file", fileInput.files[0]);

fetch("http://localhost/upload", {
  method: "POST",
  body: formData,
  credentials: "include", // Important for session cookies
})
  .then((response) => response.json())
  .then((data) => console.log(data))
  .catch((error) => console.error("Error:", error));
```

## Security Features

### 1. **File Type Validation**

- Files are validated against the user's `allowedFileTypes` field
- Both extension and MIME type are checked
- Example: If user's `allowedFileTypes` is "jpg,png,pdf", only these types can be uploaded

### 2. **File Size Limit**

- Maximum file size: **10MB** (configurable in FileController.php)
- Empty files are rejected

### 3. **Filename Sanitization**

- Special characters are removed
- Unique timestamp and random string added to prevent conflicts
- Original filename is preserved in response

### 4. **User Isolation**

- Files are stored in user-specific directories: `uploads/{user_id}/`
- Each user's files are separated for security and organization

### 5. **MIME Type Verification**

- Server validates that MIME type matches file extension
- Prevents malicious files with fake extensions

## Supported File Types

The endpoint supports common file types including:

- **Images**: jpg, jpeg, png, gif, webp
- **Documents**: pdf, doc, docx, txt, csv
- **Spreadsheets**: xls, xlsx
- **Archives**: zip
- **Media**: mp3, mp4

_Note: Actual allowed types depend on each user's `allowedFileTypes` setting_

## Response Format

### Success Response (201 Created)

```json
{
  "message": "File uploaded successfully",
  "file": {
    "original_name": "document.pdf",
    "stored_name": "document_1707580800_a1b2c3d4e5f6g7h8.pdf",
    "size": 524288,
    "extension": "pdf",
    "mime_type": "application/pdf",
    "uploaded_at": "2026-02-10 15:30:00"
  }
}
```

### Error Responses

#### Unauthorized (401)

```json
{
  "error": "Unauthorized. Please login first."
}
```

#### Invalid File Type (400)

```json
{
  "error": "File type \"exe\" is not allowed. Allowed types: jpg, png, pdf"
}
```

#### File Too Large (400)

```json
{
  "error": "File size exceeds maximum allowed size of 10MB"
}
```

#### No File Uploaded (400)

```json
{
  "error": "No file uploaded"
}
```

#### MIME Type Mismatch (400)

```json
{
  "error": "File MIME type does not match extension"
}
```

## User Configuration

### Setting Allowed File Types

When registering or updating a user, set the `allowedFileTypes` field:

```php
// Example: Allow images and PDFs
$user = User::create([
    'username' => 'john_doe',
    'password' => password_hash('secret', PASSWORD_BCRYPT),
    'allowedFileTypes' => 'jpg,jpeg,png,pdf'
]);
```

**Format**: Comma-separated file extensions (without dots)

### Default Allowed Types

New users are registered with default types: `jpg,png,pdf`

## Error Handling

The endpoint handles various upload errors:

- `UPLOAD_ERR_INI_SIZE`: File exceeds PHP's upload_max_filesize
- `UPLOAD_ERR_FORM_SIZE`: File exceeds form's MAX_FILE_SIZE
- `UPLOAD_ERR_PARTIAL`: File partially uploaded
- `UPLOAD_ERR_NO_TMP_DIR`: Missing temporary folder
- `UPLOAD_ERR_CANT_WRITE`: Failed to write to disk
- `UPLOAD_ERR_EXTENSION`: PHP extension stopped upload

## Security Best Practices

1. **Always validate on server-side** - Never trust client-side validation alone
2. **Use HTTPS** in production to encrypt file uploads
3. **Set appropriate permissions** on upload directory (755 recommended)
4. **Regular cleanup** - Implement file retention policies
5. **Virus scanning** - Consider adding antivirus scanning for production use
6. **Rate limiting** - Implement rate limits to prevent abuse

## Configuration

### Modify Maximum File Size

Edit `FileController.php`:

```php
private const MAX_FILE_SIZE = 10485760; // 10MB in bytes
```

### Change Upload Directory

Edit `FileController.php`:

```php
private const UPLOAD_DIR = __DIR__ . '/../../uploads/';
```

### Add Custom MIME Types

Edit the `isValidMimeType()` method in `FileController.php` to add new mappings.

## Testing

### Test with Postman

1. Create a POST request to `http://localhost/upload`
2. Set Authorization: Use cookies from login endpoint
3. In Body tab, select "form-data"
4. Add key "file" of type "File"
5. Select a file to upload
6. Send request

### Test Authentication Flow

1. Register: `POST /users/register`
2. Login: `POST /users/login` (saves session)
3. Upload: `POST /upload` (uses session from login)

## Troubleshooting

### "Unauthorized" Error

- Ensure you're logged in first via `/users/login`
- Check that session cookies are being sent with the request

### "File type not allowed" Error

- Check user's `allowedFileTypes` field in database
- Verify file extension matches allowed types
- Remember: extensions are case-insensitive

### Upload Directory Permission Errors

```bash
# On Linux/Mac, ensure proper permissions
chmod 755 server/uploads/
```

### PHP Upload Limits

If files larger than 2MB fail, check PHP configuration:

```ini
; php.ini
upload_max_filesize = 10M
post_max_size = 12M
```

## Future Enhancements

- Add file download endpoint
- File listing for users
- File deletion capability
- Image thumbnail generation
- File metadata storage in database
- Cloud storage integration (AWS S3, Azure Blob, etc.)
