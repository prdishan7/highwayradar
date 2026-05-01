# Deployment Guidelines (highway gbbs)

## ESP32 firmware
- Arduino IDE or PlatformIO.
- Library: **Firebase ESP Client**.
- Flash `firmware/esp32-sensor/esp32-sensor.ino` and `firmware/esp32-sos/esp32-sos.ino`.
- Set Wi‑Fi + Firebase RTDB URL in the sketches.

## Firebase RTDB
- Enable Realtime Database.
- Apply `firebase/database.rules.json` (only `live/sensor` and `alerts/sos` paths).
- No auth/users stored here; it only carries ESP32 sensor + SOS data.

## PHP + MySQL API (cPanel)
1. Create MySQL DB; run schema in `docs/DB_SCHEMA.md`.
2. Upload `api/` folder to hosting (e.g., `/public_html/api`).
3. Edit `api/config.php` with DB credentials, JWT secret, and RTDB URL.
4. Ensure `.htaccess` is allowed so `/api/*` routes hit `api/index.php`.
5. Verify `/api/auth/login` works (seeded admin: `admin@gmail.com / admin123`).

## Web/Mobile app
```bash
cd app
npm install
echo "VITE_API_BASE=/api" > .env.local
npm run dev        # web dev server
npm run build      # production build (dist/)
npx cap sync android   # for Android packaging
```

## High-availability notes
- Keep JWT secret rotated per environment.
- Set PHP `upload_max_filesize` >= 6M; compression happens on the client to ~200KB.
- Cron/uptime monitor `/api/status` to ensure DB + RTDB reachability.
