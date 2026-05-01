# Fix Database Connection and Routing Issues

## Step 1: Run Diagnostic Tool

Open this URL in your browser:
```
http://localhost/highway/diagnose-issues.php
```

This will show you exactly what's wrong:
- Database connection status
- Missing tables
- Configuration issues
- File structure problems

## Step 2: Test Database Connection

Open this URL:
```
http://localhost/highway/api/test-connection.php
```

This will return JSON showing:
- If config loads
- If database connects
- Which tables exist
- Routing information

## Step 3: Fix Database Issues

### If Database Doesn't Exist:

1. **Open phpMyAdmin:**
   - Go to: `http://localhost/phpmyadmin`

2. **Create Database:**
   - Click "New" in left sidebar
   - Database name: `highway_gbbs`
   - Collation: `utf8mb4_unicode_ci`
   - Click "Create"

3. **Import Schema:**
   - Select `highway_gbbs` database
   - Click "SQL" tab
   - Copy contents of `setup-database.sql`
   - Paste and click "Go"

### If Database Connection Fails:

1. **Check WAMP MySQL:**
   - WAMP icon should show MySQL as GREEN
   - If not, click "Start All Services"

2. **Check Credentials in `api/config.php`:**
   ```php
   'user' => 'root',  // Default WAMP user
   'pass' => '',      // Default WAMP password (usually empty)
   ```

3. **If you changed MySQL password:**
   - Update `api/config.php` with correct password
   - Or reset MySQL password in WAMP

## Step 4: Fix Routing Issues

### Test Direct API Access:

1. **Test Status Endpoint:**
   ```
   http://localhost/highway/api/status
   ```
   Should return JSON with status information.

2. **Test Login Endpoint (use browser dev tools or Postman):**
   ```
   POST http://localhost/highway/api/auth/login
   Body: {"email":"admin@gmail.com","password":"admin123"}
   ```

### If Direct API Works but Frontend Doesn't:

1. **Restart Vite Dev Server:**
   ```bash
   # Stop current server (Ctrl+C)
   cd app
   npm run dev
   ```

2. **Check Proxy Configuration:**
   - File: `app/vite.config.js`
   - Should proxy `/api` to `http://localhost/highway/api`
   - Check Vite terminal for proxy logs

3. **Test Proxy:**
   ```
   http://localhost:5173/api/status
   ```
   Should return same JSON as direct API.

4. **Clear Browser Cache:**
   - Press `Ctrl+Shift+R` (hard refresh)
   - Or clear cache completely

## Step 5: Check Browser Console

1. **Open Developer Tools:** `F12`
2. **Go to Console tab**
3. **Look for:**
   - `[API]` log messages (shows requests)
   - Red error messages
   - Network errors

4. **Go to Network tab:**
   - Try logging in
   - Check if `/api/auth/login` request appears
   - Check status code (200 = success, 404/500 = error)
   - Click on request to see response

## Step 6: Common Issues and Fixes

### Issue: "Database connection failed"
**Fix:**
- Create database in phpMyAdmin
- Run `setup-database.sql`
- Check credentials in `api/config.php`
- Ensure MySQL is running in WAMP

### Issue: "Failed to fetch" / "Failed to connect"
**Fix:**
- Restart Vite dev server
- Check WAMP Apache is running (green)
- Test direct API: `http://localhost/highway/api/status`
- Check browser console for CORS errors

### Issue: "404 Not found" on API endpoints
**Fix:**
- Check `.htaccess` exists in `api/` folder
- Verify mod_rewrite is enabled in Apache
- Check Apache error logs in WAMP

### Issue: Status shows "Error loading status"
**Fix:**
- Check database connection (use test-connection.php)
- Verify `highway_status` table exists
- Check browser console for API errors

## Step 7: Verify Everything Works

After fixing issues, test:

1. ✅ Database connection: `http://localhost/highway/api/test-connection.php`
2. ✅ Status endpoint: `http://localhost/highway/api/status`
3. ✅ Via proxy: `http://localhost:5173/api/status`
4. ✅ Login: Try logging in with `admin@gmail.com` / `admin123`

## Still Having Issues?

1. **Check WAMP Apache Error Logs:**
   - WAMP menu → Apache → Error logs
   - Look for PHP errors

2. **Check Vite Terminal:**
   - Look for proxy errors
   - Check if requests are being proxied

3. **Check Browser Network Tab:**
   - See actual HTTP requests
   - Check response codes and messages

4. **Run Diagnostic:**
   - `http://localhost/highway/diagnose-issues.php`
   - Shows comprehensive system status


