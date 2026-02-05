# highway gbbs

Geohazard Barrier & Broadcast System with ESP32 sensor + SOS nodes, a Vue + Ionic + Capacitor client, and a PHP + SQLite API (cPanel-friendly). Firebase Realtime Database is kept only for the ESP32 live sensor/SOS feed; all auth, roles, incidents, and highway status live in SQLite.

## Structure
- `api/` – PHP REST API (JWT auth, incidents, highway status)
- `app/` – Vue 3 + Ionic + Capacitor client
- `firmware/esp32-sensor/` – sensor node
- `firmware/esp32-sos/` – SOS button node
- `firebase/` – RTDB rules (sensor + SOS only)
- `docs/` – architecture, DB schema, deployment notes

## Quick start
1) **Database (SQLite)**
   - Run `setup-database.sql` against the SQLite file.
   - Default DB path is `api/data/highway.sqlite` (set in `api/config.php`).
   - Default admin is seeded automatically: `admin@gmail.com / admin123` (bcrypt).
2) **PHP API (cPanel)**  
   - Upload `api/` folder. Ensure `.htaccess` is allowed.  
   - Confirm `/api/auth/login` responds after DB credentials are set.
3) **Firebase RTDB**  
   - Create a project, enable Realtime Database.  
   - Set rules from `firebase/database.rules.json` (sensor + SOS paths only).  
   - Put the database URL in `VITE_FIREBASE_DATABASE_URL`.
4) **App**
   ```bash
   cd app
   npm install
   echo "VITE_API_BASE=/api" > .env.local
   npm run dev   # http://localhost:5173
   ```
   Android build:
   - Emulator API URL example: `VITE_API_BASE=http://10.0.2.2/highway/api`
   - Phone API URL example: `VITE_API_BASE=http://<your-lan-ip>/highway/api`
   - Run: `npm run android`
   - Open Android Studio: `npm run android:open`

## Behavior highlights
- JWT auth via PHP; roles: `admin`, `driver`, `local`.
- Incidents stored in SQLite with base64 images (client-compressed to ~150–200KB and watermarked).
- Highway status computed from incidents + RTDB sensor risk + SOS, with admin override.
- Push-style local notifications when SOS triggers or a high/closed incident appears.

See `docs/DEPLOYMENT.md` for cPanel notes and `docs/ARCHITECTURE.md` for updated diagrams.
For cPanel-to-Android end-to-end steps, see `CPANEL_ANDROID_GUIDE.md`.
 
