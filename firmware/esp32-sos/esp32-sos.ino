#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>

// WiFi
#define WIFI_SSID "ishan"
#define WIFI_PASSWORD "ishan123"

// Firebase RTDB (REST)
const char *FIREBASE_DB_URL =
  "https://highway-123e3-default-rtdb.asia-southeast1.firebasedatabase.app";

// Pins
const int PIN_SOS_BUTTON = 0; // BOOT button on most ESP32-WROOM-32 dev boards
const int PIN_STATUS_LED = 2;

bool pressed = false;
unsigned long pressStart = 0;
const unsigned long LONG_PRESS_MS = 3000;

void sendJsonToFirebase(const String &path, const String &payload) {
  if (WiFi.status() != WL_CONNECTED) return;

  WiFiClientSecure client;
  client.setInsecure();

  HTTPClient http;
  String url = String(FIREBASE_DB_URL) + path + ".json";
  http.begin(client, url);
  http.addHeader("Content-Type", "application/json");
  int code = http.PUT(payload);
  Serial.print("Firebase update: ");
  Serial.println(code);
  String body = http.getString();
  if (body.length()) {
    Serial.print("Firebase response: ");
    Serial.println(body);
  }
  http.end();
}

void setup() {
  Serial.begin(115200);
  delay(200);

  pinMode(PIN_SOS_BUTTON, INPUT_PULLUP);
  pinMode(PIN_STATUS_LED, OUTPUT);
  digitalWrite(PIN_STATUS_LED, LOW);

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  Serial.print("WiFi: connecting");
  while (WiFi.status() != WL_CONNECTED) {
    delay(300);
    Serial.print(".");
  }
  Serial.println();
  Serial.print("WiFi: connected, IP=");
  Serial.println(WiFi.localIP());

  Serial.println("Firebase REST: initialized");
}

void loop() {
  bool isDown = (digitalRead(PIN_SOS_BUTTON) == LOW);
  unsigned long now = millis();

  if (isDown && !pressed) {
    pressed = true;
    pressStart = now;
    Serial.println("SOS button: pressed");
  }

  if (!isDown && pressed) {
    pressed = false;
    Serial.println("SOS button: released");
  }

  if (pressed && (now - pressStart >= LONG_PRESS_MS)) {
    pressed = false;
    Serial.println("SOS: long press detected, sending alert");
    String payload = "{";
    payload += "\"active\":true,";
    payload += "\"level\":\"HIGH\",";
    payload += "\"timestampMs\":" + String((unsigned long)millis());
    payload += "}";
    sendJsonToFirebase("/alerts/sos", payload);
    digitalWrite(PIN_STATUS_LED, HIGH);
    delay(500);
    digitalWrite(PIN_STATUS_LED, LOW);
  }
}
