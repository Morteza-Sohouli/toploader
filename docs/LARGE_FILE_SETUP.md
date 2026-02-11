# Large File Upload Configuration (20GB Support)

## ✅ What's Been Done

The file upload endpoint has been configured to support files up to **20GB** with **RAR file type** support:

### Code Changes

- ✅ Maximum file size increased to 20GB (21,474,836,480 bytes)
- ✅ RAR MIME types added: `application/vnd.rar`, `application/x-rar-compressed`, `application/x-rar`
- ✅ Memory-efficient streaming upload (PSR-7's `moveTo()` doesn't load entire file into RAM)
- ✅ Human-readable file size formatting in error messages
- ✅ Apache configuration added to `.htaccess`

## ⚙️ Required Server Configuration

### For PHP (php.ini)

**Windows (XAMPP/WAMP):**

```
C:\xampp\php\php.ini  (or similar)
```

**Linux:**

```
/etc/php/8.x/apache2/php.ini
/etc/php/8.x/fpm/php.ini
```

**Required Settings:**

```ini
upload_max_filesize = 20G
post_max_size = 21G
memory_limit = 1G
max_execution_time = 3600
max_input_time = 3600
file_uploads = On
```

**After editing, restart the web server:**

```bash
# XAMPP
Restart Apache from XAMPP Control Panel

# Linux Apache
sudo systemctl restart apache2

# Linux Nginx + PHP-FPM
sudo systemctl restart php8.x-fpm
sudo systemctl restart nginx
```

### For Apache (already in .htaccess)

The `.htaccess` file has been updated with:

- `LimitRequestBody 21474836480` (20GB limit)
- PHP configuration overrides
- Security rules to prevent uploaded script execution

### For Nginx

If using Nginx, add to your server block:

```nginx
server {
    client_max_body_size 20G;
    client_body_timeout 3600s;
    client_header_timeout 3600s;

    location ~ \.php$ {
        fastcgi_read_timeout 3600s;
        # ... other fastcgi settings
    }
}
```

Then restart: `sudo systemctl restart nginx`

## 🔍 Verify Configuration

Create `phpinfo.php` in `server/public/`:

```php
<?php phpinfo(); ?>
```

Visit: `http://localhost/phpinfo.php`

Check these values:

- ✅ `upload_max_filesize`: Should show **20G** or **21474836480**
- ✅ `post_max_size`: Should show **21G** or higher
- ✅ `memory_limit`: Should show **1G** or higher
- ✅ `max_execution_time`: Should show **3600** or higher

**⚠️ Delete `phpinfo.php` after checking (security risk in production)**

## 💾 Disk Space Requirements

### Temporary Storage

During upload, PHP stores files in the system temp directory:

- **Windows:** `C:\Windows\Temp` or configured `upload_tmp_dir`
- **Linux:** `/tmp` or configured `upload_tmp_dir`

**Ensure temp directory has 20GB+ free space**

Check temp directory:

```bash
# Linux
df -h /tmp

# Windows PowerShell
Get-PSDrive C
```

### Upload Directory

Files are stored in `server/uploads/{user_id}/`

**Ensure sufficient disk space for all user uploads**

## 🚀 Testing Large File Uploads

### Using cURL

```bash
curl -X POST http://localhost/upload \
  -H "Cookie: PHPSESSID=your-session-id" \
  -F "file=@/path/to/large-file.rar" \
  --max-time 3600
```

### Using Postman

1. Set request timeout to 3600 seconds in Settings
2. POST to `/upload`
3. Body → form-data
4. Add `file` field (type: File)
5. Select large RAR file

### Monitor Upload Progress

Check PHP error log for issues:

- **XAMPP:** `C:\xampp\apache\logs\error.log`
- **Linux:** `/var/log/apache2/error.log` or `/var/log/nginx/error.log`

## 📊 Performance Considerations

### Memory Usage

The implementation uses **streaming**, so:

- ❌ Does NOT load 20GB into RAM
- ✅ Processes file in chunks
- ✅ Memory usage stays around 512MB-1GB regardless of file size

### Upload Time Estimates

| Connection Speed | 20GB Upload Time |
| ---------------- | ---------------- |
| 10 Mbps          | ~4.5 hours       |
| 100 Mbps         | ~27 minutes      |
| 1 Gbps           | ~2.7 minutes     |

**Ensure `max_execution_time` is set appropriately for your connection speed**

## 🔒 Security Considerations

### 1. User Allowed File Types

Users can only upload RAR if their `allowedFileTypes` includes "rar":

```php
// Example: Update user to allow RAR files
$user->allowedFileTypes = 'jpg,png,pdf,zip,rar';
$user->save();
```

### 2. File Execution Prevention

The `.htaccess` prevents execution of uploaded scripts:

```apache
RedirectMatch 403 ^/uploads/.*\.(php|phtml|php3|...)$
```

### 3. Storage Isolation

Each user's files are stored separately:

```
uploads/
  ├─ 1/  (user ID 1's files)
  ├─ 2/  (user ID 2's files)
  └─ 3/  (user ID 3's files)
```

## ⚠️ Common Issues & Solutions

### Issue: "File exceeds upload_max_filesize"

**Solution:** Check `php.ini` has `upload_max_filesize = 20G` and server is restarted

### Issue: Upload times out

**Solution:** Increase `max_execution_time` in `php.ini`

### Issue: "No space left on device"

**Solution:** Free up space in temp directory or upload directory

### Issue: Nginx "413 Request Entity Too Large"

**Solution:** Add `client_max_body_size 20G;` to Nginx config

### Issue: Upload stops at 2GB

**Solution:** May be a 32-bit PHP limitation. Use 64-bit PHP

## 🎯 Recommended Setup for Production

```ini
; php.ini for production large file handling
upload_max_filesize = 20G
post_max_size = 21G
memory_limit = 1G
max_execution_time = 7200    ; 2 hours for slower connections
max_input_time = 7200
file_uploads = On
upload_tmp_dir = /path/to/large/temp/directory
```

## 📝 Adding RAR Files for a User

```bash
# Example: Register user with RAR support
curl -X POST http://localhost/users/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "john_doe",
    "password": "securepass123",
    "allowedFileTypes": "jpg,png,pdf,zip,rar"
  }'
```

Or update existing user in database:

```sql
UPDATE users
SET allowedFileTypes = 'jpg,png,pdf,zip,rar'
WHERE id = 1;
```

## 🔧 Advanced: Progress Tracking

For large file uploads, consider implementing:

1. **Chunked uploads** (split 20GB into smaller chunks)
2. **Progress webhooks** (notify frontend of upload progress)
3. **Resume capability** (allow interrupted uploads to resume)

These features require additional implementation beyond the current endpoint.

## ✅ Verification Checklist

Before uploading 20GB files:

- [ ] PHP `upload_max_filesize` = 20G
- [ ] PHP `post_max_size` = 21G
- [ ] PHP `memory_limit` = 1G+
- [ ] PHP `max_execution_time` = 3600+
- [ ] Web server restarted after config changes
- [ ] Temp directory has 20GB+ free space
- [ ] Upload directory has sufficient space
- [ ] User's `allowedFileTypes` includes "rar"
- [ ] Tested with smaller file first
- [ ] Monitoring logs during upload

---

**Need help?** Check PHP error logs and server configuration files.
