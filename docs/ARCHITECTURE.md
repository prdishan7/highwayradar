# System Architecture (highway gbbs)

```mermaid
flowchart LR
  subgraph Edge[ESP32 Edge Layer]
    E1[ESP32-1 Sensors] -->|RTDB Write| FB[(Firebase RTDB)]
    E2[ESP32-2 SOS] -->|RTDB Write| FB
  end

  subgraph API[PHP + MySQL API]
    API1[/users, incidents, status/]
    DB[(MySQL)]
    API1 --> DB
  end

  subgraph App[Vue + Ionic + Capacitor]
    U1[Admin]
    U2[Driver]
    U3[Local Resident]
    U1 -->|JWT| API1
    U2 -->|JWT| API1
    U3 -->|JWT| API1
    AppRT[RTDB listeners] --> FB
  end

  API1 -. polls .-> FB
  AppRT -. read-only .-> FB
```

## Data flow
1. ESP32 sensor node writes `/live/sensor`; SOS node writes `/alerts/sos`.
2. Web/mobile app listens to RTDB for live sensor + SOS only.
3. Users authenticate against PHP API (JWT). Roles and accounts live in MySQL.
4. Incident reports (with base64 images) post to `/api/incidents`.
5. `/api/status` computes highway status from MySQL incidents + RTDB sensor risk + SOS; admin override persists in `highway_status`.
6. Local notifications fire on SOS or high/closed incidents.

## Deployment
- cPanel-friendly PHP 8 + MySQL hosting for `/api`.
- RTDB used only for ESP32 writes and app reads (`live/sensor`, `alerts/sos`).
- Vue/Ionic app served from any CDN or via Capacitor Android bundle.
 
