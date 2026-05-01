# Hardware Connections (ESP32-WROOM-32)

This document shows **exact pin mapping**. Only analog outputs are used for soil and rain sensors.

## ESP32-1: Sensor Monitoring Node

### Soil Moisture Sensor (Analog only)
- VCC -> 3.3V
- GND -> GND
- A0  -> GPIO34 (ADC1_CH6)
- D0  -> **Not used**

### Rainwater Sensor (Analog only)
- VCC -> 3.3V
- GND -> GND
- A0  -> GPIO35 (ADC1_CH7)
- D0  -> **Not used**

### Built-in Status LED (on most ESP32-WROOM-32 dev boards)
- Uses onboard LED on GPIO2
- No external wiring required

## ESP32-2: SOS Alert Node

### SOS Button (Built-in BOOT button, Long press > 3s)
- Uses onboard BOOT button (GPIO0)
- No external wiring required

### Built-in Status LED (on most ESP32-WROOM-32 dev boards)
- Uses onboard LED on GPIO2
- No external wiring required

## Power
- ESP32-WROOM-32 board: 5V via USB or 3.3V regulated supply
- Sensors: 3.3V recommended to match ESP32 ADC input range

## Grounding
- All sensor GNDs must connect to ESP32 GND (common ground)
