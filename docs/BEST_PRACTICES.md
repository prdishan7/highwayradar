# Best Practices

## Scalability
- Use lightweight payloads in RTDB
- Separate config, live data, and alerts
- Avoid large historical logs in RTDB (offload to storage if needed)

## Reliability
- Cache last thresholds on ESP32
- Reconnect WiFi and Firebase on failure
- Rate-limit writes (2s interval already used)

## Safety
- Use 3.3V sensors or level shifting for analog inputs
- Debounce SOS button and confirm long press
- Validate admin-only writes in Firebase rules

## Security
- Use Firebase Auth (no anonymous access)
- Restrict thresholds to admin role
- Rotate credentials regularly

