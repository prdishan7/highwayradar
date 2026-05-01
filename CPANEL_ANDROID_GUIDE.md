# cPanel Backend + Android Build Guide

## 1) Deploy backend to cPanel
1. In cPanel, create a MySQL database and user, then grant all privileges.
2. Import SQL from `setup-database.sql` (or tables from `docs/DB_SCHEMA.md`).
3. Upload the `api/` folder to your public web root (for example `public_html/api`).
4. Edit `api/config.php`:
   - `db.host`, `db.name`, `db.user`, `db.pass`
   - `jwt_secret` (set a strong secret)
   - `firebase_db_url` (your RTDB URL)
5. Confirm `.htaccess` inside `api/` is uploaded so route rewriting works.

## 2) Verify live API before mobile build
Test these URLs in browser/Postman:
- `https://your-domain.com/api/status`
- `https://your-domain.com/api/auth/login` (POST)

Expected:
- `status` returns JSON
- `login` returns token with valid credentials

## 3) Configure app for cPanel backend
In `app/`, create `.env.production` (copy from `.env.production.example`):

```env
VITE_API_BASE=https://your-domain.com/api
VITE_FIREBASE_DATABASE_URL=https://your-project-default-rtdb.region.firebasedatabase.app/
```

Notes:
- Use HTTPS for Android production.
- `/api` only works in web dev with Vite proxy, not in Android app.

## 4) Build Android app
From `app/`:

```bash
npm install
npm run android
npm run android:open
```

This will:
1. Build web assets (`dist/`)
2. Sync assets/plugins into `app/android`
3. Open Android Studio

## 5) Generate APK/AAB in Android Studio
1. `Build` -> `Generate Signed Bundle / APK`
2. Choose:
   - `Android App Bundle (AAB)` for Play Store
   - `APK` for direct install/testing
3. Create/select keystore and finish wizard.

## 6) If API calls fail on device
- Recheck `VITE_API_BASE` in `.env.production`
- Run again: `npm run android`
- Confirm backend endpoint works directly over HTTPS
- Check server logs in cPanel (`Errors`)
