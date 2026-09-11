# Topload API Documentation

## Overview

Topload is a secure file upload service built with PHP, Slim Framework, and MySQL. The API provides user authentication and file upload functionality with strict type validation.

**Base URL:** `http://localhost:8080` (Docker) or `http://localhost` (local)

## Authentication

The API uses session-based authentication. Users must login to access protected endpoints.

### Session Management

- Sessions are maintained via HTTP cookies
- Include `credentials: 'include'` in frontend requests
- Sessions expire when browser closes or server restarts

---

## Endpoints

### 1. Health Check

**GET /**

Basic health check endpoint.

**Response:**

```json
"Welcome to Slim + Eloquent Project!"
```

---

### 2. User Registration

**POST /users/register**

Register a new user account.

**Request Body:**

```json
{
  "username": "string (required)",
  "password": "string (required, min 1 char)",
  "allowedFileTypes": "string (optional, comma-separated, default: 'jpg,png,pdf')"
}
```

**Success Response (201):**

```json
{
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "username": "john_doe",
    "allowedFileTypes": "jpg,png,pdf",
    "created_at": "2026-02-10T15:30:00.000000Z",
    "updated_at": "2026-02-10T15:30:00.000000Z"
  }
}
```

**Error Responses:**

- `400`: Username/password missing or user already exists

---

### 3. User Login

**POST /users/login**

Authenticate user and create session.

**Request Body:**

```json
{
  "username": "string (required)",
  "password": "string (required)"
}
```

**Success Response (200):**

```json
{
  "message": "Login successful",
  "user": {
    "id": 1,
    "username": "john_doe"
  }
}
```

**Error Responses:**

- `400`: Username/password missing
- `401`: Invalid credentials

---

### 4. User Logout

**POST /users/logout**

Destroy user session.

**Success Response (200):**

```json
{
  "message": "Logged out successfully"
}
```

---

### 5. Get Current User

**GET /users/me**

Get authenticated user's profile information.

**Authentication:** Required

**Success Response (200):**

```json
{
  "id": 1,
  "username": "john_doe",
  "allowedFileTypes": "jpg,png,pdf",
  "created_at": "2026-02-10T15:30:00.000000Z",
  "updated_at": "2026-02-10T15:30:00.000000Z"
}
```

**Error Responses:**

- `401`: Not authenticated

---

### 6. List All Users

**GET /users/**

Get list of all registered users.

**Success Response (200):**

```json
[
  {
    "id": 1,
    "username": "john_doe",
    "allowedFileTypes": "jpg,png,pdf",
    "created_at": "2026-02-10T15:30:00.000000Z",
    "updated_at": "2026-02-10T15:30:00.000000Z"
  }
]
```

---

### 7. File Upload

**POST /upload**

Upload a file with type validation based on user's allowed file types.

**Authentication:** Required

**Content-Type:** `multipart/form-data`

**Request Body:**

- `file`: File to upload (required)

**Supported File Types:**

- Images: `jpg`, `jpeg`, `png`, `gif`, `webp`
- Documents: `pdf`, `doc`, `docx`, `txt`, `csv`
- Spreadsheets: `xls`, `xlsx`
- Archives: `zip`, `rar`
- Media: `mp3`, `mp4`

**File Size Limit:** 30GB

**Success Response (201):**

```json
{
  "message": "File uploaded successfully",
  "file": {
    "id": 1,
    "hash": "a1b2c3d4e5f6...",
    "original_name": "document.pdf",
    "stored_name": "document_1707580800_a1b2c3d4e5f6g7h8.pdf",
    "size": 524288,
    "extension": "pdf",
    "mime_type": "application/pdf",
    "uploaded_at": "2026-02-10 15:30:00"
  }
}
```

**Error Responses:**

- `401`: Not authenticated
- `400`: No file uploaded, invalid file type, file too large, MIME type mismatch
- `404`: User not found
- `500`: File save failed

---

### 8. Serve File

New download links use a signed storage-relative path. Existing ID links remain supported.

**Canonical paths:**

- Native uploads: `GET /uploads/new/{yyyy}/{mm}/{dd}/{stored_name}?md5=...&expires=...`
- Legacy native uploads: `GET /uploads/{user_id}/{yyyy}/{mm}/{dd}/{stored_name}?md5=...&expires=...`
- WordPress uploads: `GET /wp-content/uploads/{relative_path}?md5=...&expires=...`

**Backward-compatible ID paths:**

- `GET /files/serve/{file_id}?md5=...&expires=...`
- `GET /files/serve?id={file_id}&md5=...&expires=...`

Download endpoints are public, but every request requires a valid signed URL.
The admin file-list response is an exception: its `download_url` contains only
the canonical URL, without `md5`, `expires`, or other authorization values, so
the downstream admin service can add its own credentials.

**Query Parameters:**

- `md5` (required): URL-safe signature for the exact download path
- `expires` (required): Signature expiry as a Unix timestamp

**Success Response:** File content with appropriate headers

**Error Responses:**

- `400`: Missing path/ID or signing parameters
- `403`: Invalid hash or access denied
- `404`: File not found

---

## Error Response Format

All error responses follow this format:

```json
{
  "error": "Error message description"
}
```

---

## File Type Validation

### How It Works

1. User registers with `allowedFileTypes` (comma-separated extensions)
2. During upload, system checks:
   - File extension matches user's allowed types
   - MIME type matches extension
   - File size ≤ 30GB
   - File is not empty

### Default Allowed Types

New users get: `jpg,png,pdf`

### Updating User File Types

```sql
UPDATE users SET allowedFileTypes = 'jpg,png,pdf,zip,rar' WHERE id = 1;
```

---

## JavaScript Examples

### Registration

```javascript
fetch("/users/register", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({
    username: "john_doe",
    password: "secure123",
    allowedFileTypes: "jpg,png,pdf,zip,rar",
  }),
});
```

### Login

```javascript
fetch("/users/login", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  credentials: "include",
  body: JSON.stringify({
    username: "john_doe",
    password: "secure123",
  }),
});
```

### File Upload

```javascript
const formData = new FormData();
formData.append("file", fileInput.files[0]);

fetch("/upload", {
  method: "POST",
  body: formData,
  credentials: "include",
})
  .then((response) => response.json())
  .then((data) => console.log(data));
```

### Get User Profile

```javascript
fetch("/users/me", {
  credentials: "include",
})
  .then((response) => response.json())
  .then((user) => console.log(user));
```

---

## cURL Examples

### Register User

```bash
curl -X POST http://localhost:8080/users/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "john_doe",
    "password": "secure123",
    "allowedFileTypes": "jpg,png,pdf,zip,rar"
  }'
```

### Login

```bash
curl -X POST http://localhost:8080/users/login \
  -H "Content-Type: application/json" \
  -c cookies.txt \
  -d '{
    "username": "john_doe",
    "password": "secure123"
  }'
```

### Upload File

```bash
curl -X POST http://localhost:8080/upload \
  -b cookies.txt \
  -F "file=@/path/to/file.pdf"
```

### Get Profile

```bash
curl -X GET http://localhost:8080/users/me \
  -b cookies.txt
```

---

## Database Schema

### Users Table

```sql
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  allowedFileTypes VARCHAR(255) DEFAULT 'jpg,png,pdf',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## Security Features

- **Session-based authentication** with middleware protection
- **File type validation** against user permissions
- **MIME type verification** to prevent extension spoofing
- **File size limits** (30GB max)
- **Storage isolation** - public paths are resolved only inside configured upload roots
- **Filename sanitization** with unique identifiers
- **Memory-efficient streaming** for large files
- **Apache security rules** preventing script execution in uploads

---

## Development Setup

### Docker (Recommended)

```bash
cd /path/to/topload
docker-compose up --build
```

**API URL:** `http://localhost:8080`

### Local Development

```bash
cd server
composer install
# Configure php.ini for 30GB uploads
# Start Apache/Nginx with PHP
```

**API URL:** `http://localhost`

### Environment Variables

Create `.env` file:

```
MYSQL_DATABASE=topload
MYSQL_USER=topload_user
MYSQL_PASSWORD=secure_password
MYSQL_ROOT_PASSWORD=root_password
```

---

## Testing

### Postman Collection

Import the following collection to test all endpoints:

```json
{
  "info": {
    "name": "Topload API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Register",
      "request": {
        "method": "POST",
        "header": [{ "key": "Content-Type", "value": "application/json" }],
        "body": {
          "mode": "raw",
          "raw": "{\"username\": \"testuser\", \"password\": \"testpass\", \"allowedFileTypes\": \"jpg,png,pdf,zip,rar\"}"
        },
        "url": { "raw": "{{base_url}}/users/register" }
      }
    },
    {
      "name": "Login",
      "request": {
        "method": "POST",
        "header": [{ "key": "Content-Type", "value": "application/json" }],
        "body": {
          "mode": "raw",
          "raw": "{\"username\": \"testuser\", \"password\": \"testpass\"}"
        },
        "url": { "raw": "{{base_url}}/users/login" }
      }
    },
    {
      "name": "Upload File",
      "request": {
        "method": "POST",
        "header": [],
        "body": {
          "mode": "formdata",
          "formdata": [{ "key": "file", "type": "file", "src": [] }]
        },
        "url": { "raw": "{{base_url}}/upload" }
      }
    }
  ],
  "variable": [{ "key": "base_url", "value": "http://localhost:8080" }]
}
```

---

## Rate Limiting

Currently no rate limiting implemented. Consider adding:

- Upload frequency limits per user
- File size limits per user per day
- Request rate limiting per IP

---

## Future Enhancements

- File download endpoints
- File listing/management
- User file quota system
- Progress tracking for large uploads
- Cloud storage integration
- Admin panel for user management
- File compression/decompression
- Thumbnail generation for images
- Virus scanning integration
