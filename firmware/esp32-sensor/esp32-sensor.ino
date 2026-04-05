#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>

// WiFi
#define WIFI_SSID "ishan"
#define WIFI_PASSWORD "ishan123"

// Firebase RTDB (REST)
const char *FIREBASE_DB_URL =
  "https://highway-123e3-default-rtdb.asia-southeast1.firebasedatabase.app";

// Pins (ESP32-WROOM-32)
const int PIN_SOIL_ADC  = 34; // ADC1_CH6 (analog only)
const int PIN_RAIN_ADC  = 35; // ADC1_CH7 (analog only)
const int PIN_STATUS_LED = 2; // optional
const int PIN_BUZZER = 25;    // passive buzzer

// Buzzer config
const int BUZZER_CHANNEL = 0;
const int BUZZER_FREQ = 2800;               // loud alarm tone
const int BUZZER_RESOLUTION = 8;
const unsigned long BUZZER_MS = 5000;       // ring for 5 seconds
unsigned long buzzerUntil = 0;
bool buzzerOn = false;
bool wasHigh = false;

// Thresholds & weights (loaded from RTDB)
struct Thresholds {
  int soilMin = 1200;
  int soilMax = 3200;
  int rainMin = 800;
  int rainMax = 3200;
  int activeThreshold = 3000;
  float wS = 0.5;
  float wR = 0.5;
  int lowThreshold = 40;
  int highThreshold = 70;
} thresholds;

unsigned long lastConfigSync = 0;
unsigned long lastLoop = 0;
const unsigned long CONFIG_SYNC_MS = 5000;
const unsigned long LOOP_MS = 0;          // no intentional sensor loop delay

int mapClamp(int val, int inMin, int inMax, int outMin, int outMax) {
  if (inMax <= inMin) return outMin;
  if (val < inMin) val = inMin;
  if (val > inMax) val = inMax;
  long mapped = (long)(val - inMin) * (outMax - outMin) / (inMax - inMin) + outMin;
  return (int)mapped;
}

void syncThresholds() {
  // REST read omitted in this version (keep defaults)
}

void setup() {
  Serial.begin(115200);
  pinMode(PIN_STATUS_LED, OUTPUT);
  digitalWrite(PIN_STATUS_LED, LOW);
  pinMode(PIN_BUZZER, OUTPUT);
  ledcSetup(BUZZER_CHANNEL, BUZZER_FREQ, BUZZER_RESOLUTION);
  ledcAttachPin(PIN_BUZZER, BUZZER_CHANNEL);
  ledcWrite(BUZZER_CHANNEL, 0);

  analogReadResolution(12);
  analogSetAttenuation(ADC_11db);

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  while (WiFi.status() != WL_CONNECTED) {
    Serial.print(".");
    delay(300);
  }
  Serial.println("\nWiFi connected");

  Serial.println("Firebase REST: initialized");

  // Immediate first publish
  lastLoop = 0;
}

void loop() {
  unsigned long now = millis();

  if (now - lastConfigSync >= CONFIG_SYNC_MS) {
    lastConfigSync = now;
    syncThresholds();
  }

  if (now - lastLoop < LOOP_MS) return;
  lastLoop = now;

  int soilRaw = analogRead(PIN_SOIL_ADC);
  int rainRaw = analogRead(PIN_RAIN_ADC);

  int soilScore = 100 - mapClamp(soilRaw, thresholds.soilMin, thresholds.soilMax, 0, 100);
  int rainScore = 100 - mapClamp(rainRaw, thresholds.rainMin, thresholds.rainMax, 0, 100);

  bool soilActive = soilRaw < thresholds.activeThreshold;
  bool rainActive = rainRaw < thresholds.activeThreshold;

  String riskLevel = "LOW";
  float riskIndex = 0;
  if (soilActive && rainActive) {
    riskLevel = "HIGH";
    riskIndex = 100;
  } else if (soilActive || rainActive) {
    riskLevel = "MEDIUM";
    riskIndex = 50;
  } else {
    riskLevel = "LOW";
    riskIndex = 0;
  }

  String payload = "{";
  payload += "\"soil\":" + String(soilRaw) + ",";
  payload += "\"rain\":" + String(rainRaw) + ",";
  payload += "\"riskIndex\":" + String(riskIndex, 2) + ",";
  payload += "\"riskLevel\":\"" + riskLevel + "\",";
  payload += "\"timestampMs\":" + String((unsigned long)millis());
  payload += "}";

  if (WiFi.status() == WL_CONNECTED) {
    WiFiClientSecure client;
    client.setInsecure();
    HTTPClient http;
    String url = String(FIREBASE_DB_URL) + "/live/sensor.json";
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

  // Trigger a 5-second alarm when entering HIGH risk.
  bool isHigh = (riskLevel == "HIGH");
  if (isHigh && !wasHigh) {
    buzzerUntil = now + BUZZER_MS;
  }
  wasHigh = isHigh;

  if (now < buzzerUntil) {
    if (!buzzerOn) {
      ledcWriteTone(BUZZER_CHANNEL, BUZZER_FREQ);
      buzzerOn = true;
    }
  } else if (buzzerOn) {
    ledcWrite(BUZZER_CHANNEL, 0);
    buzzerOn = false;
  }

  digitalWrite(PIN_STATUS_LED, riskLevel == "HIGH" ? HIGH : LOW);
  Serial.print("Soil: "); Serial.print(soilRaw);
  Serial.print(" Rain: "); Serial.print(rainRaw);
  Serial.print(" RiskIndex: "); Serial.print(riskIndex);
  Serial.print(" Level: "); Serial.println(riskLevel);
}
 
