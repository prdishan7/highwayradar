# Troubleshooting Guide

## "Failed to Fetch" Error

This error typically means the frontend cannot connect to the API. Check the following:

### 1. Verify WAMP is Running
- Open WAMP Server
- Ensure Apache and MySQL services are running (green icons)
- If not green, click "Start All Services"

### 2. Test API Directly
Open your browser and visit:
- `http://localhost/highway/api/status`

You should see JSON response. If you see an error, check:
- Database exists and is accessible
- Database credentials in `api/config.php` are correct

### 3. Check Database Setup
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Verify database `highway_gbbs` exists
3. If not, run `setup-database.sql` in phpMyAdmin
4. Check database credentials in `api/config.php`:
   ```php
   'user' => 'root',  // WAMP default
   'pass' => '',      // WAMP default (usually empty)
   ```

### 4. Verify Vite Dev Server
- Make sure you're running `npm run dev` in the `app` directory
- Check the terminal for any errors
- The dev server should be running on `http://localhost:5173`

### 5. Check Browser Console
- Open browser Developer Tools (F12)
- Check the Console tab for errors
- Check the Network tab to see if API requests are being made
- Look for CORS errors or 404 errors

### 6. Restart Services
If issues persist:
1. Stop WAMP services
2. Start WAMP services again
3. Restart the Vite dev server (stop with Ctrl+C, then `npm run dev` again)

## Highway Status Stuck in Loading

This usually means:
1. The API endpoint `/api/status` is not responding
2. Database connection is failing
3. The `highway_status` table doesn't exist

**Solution:**
1. Check if database tables exist (run `setup-database.sql`)
2. Test API directly: `http://localhost/highway/api/status`
3. Check browser console for errors
4. Check WAMP Apache error logs

## Database Connection Errors

If you see database connection errors:

1. **Check MySQL is running** in WAMP
2. **Verify database exists:**
   ```sql
   SHOW DATABASES;
   ```
3. **Check credentials** in `api/config.php`:
   - Default WAMP: user=`root`, password=`` (empty)
   - If you changed MySQL password, update `api/config.php`
4. **Create database if missing:**
   ```sql
   CREATE DATABASE highway_gbbs;
   ```
5. **Import schema:** Run `setup-database.sql` in phpMyAdmin

## CORS Errors

If you see CORS errors in the browser console:
- The API already has CORS headers configured
- The Vite proxy should handle this automatically
- If issues persist, check `api/util.php` - the `cors()` function should be called

## Common Issues

### Issue: "Undefined array key 0"
- **Fixed:** This was a routing bug that has been fixed
- Refresh your browser to clear any cached errors

### Issue: API returns 404
- Check that `.htaccess` file exists in `api/` directory
- Verify Apache mod_rewrite is enabled in WAMP
- Check Apache error logs

### Issue: Frontend shows "Failed to fetch"
- Verify WAMP Apache is running
- Test API directly in browser: `http://localhost/highway/api/status`
- Check Vite dev server is running
- Restart both services if needed

## Quick Diagnostic Commands

1. **Test API status endpoint:**
   - Browser: `http://localhost/highway/api/status`
   - Should return JSON with status information

2. **Test API login endpoint:**
   - Use Postman or browser dev tools
   - POST to `http://localhost/highway/api/auth/login`
   - Body: `{"email":"admin@gmail.com","password":"admin123"}`

3. **Check database:**
   - phpMyAdmin: `http://localhost/phpmyadmin`
   - Select `highway_gbbs` database
   - Verify tables exist: `users`, `incidents`, `highway_status`, `audit_log`

## Still Having Issues?

1. Check WAMP Apache error logs
2. Check browser console for detailed error messages
3. Verify all files are in the correct location
4. Ensure no firewall is blocking localhost connections
5. Try accessing the API directly without the frontend to isolate the issue


 
