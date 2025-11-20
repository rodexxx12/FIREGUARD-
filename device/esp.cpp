#include <WiFi.h>
#include <WiFiManager.h>
#include <HTTPClient.h>
#include <DHT.h>
#include <Preferences.h>
#include <WiFiClientSecure.h>

// Sensor Pins
const int smokeAnalogPin = 32;
const int smokeDigitalPin = 27;
const int flamePin = 26;
const int smokeLedPin = 25;
const int flameLedPin = 33;
const int buzzerPin = 14;
const int dhtPin = 13;
const int powerSwitchPin = 15;    // Tactile switch pin
const int powerStatusLedPin = 2;  // Yellow LED - shows system is ON
const int offStatusLedPin = 4;    // Red LED - shows system is OFF

// DHT Sensor Settings
#define DHT_TYPE DHT11
DHT dht(dhtPin, DHT_TYPE);

// Server details
const char* serverUrl = "http://fireguard.bccbsis.com/device/smoke_api.php";

// Detection Settings
int smokeBaseline = 0;
const float smokeSensitivity = 1.5;
const int minSmokeChange = 300;
const unsigned long loggingInterval = 10000;
const unsigned long flameDebounceTime = 1000;
const int flameConfirmationCount = 8;
const int flameRejectionCount = 15;
const int stabilizationDelay = 30000;
const int calibrationDuration = 30000;
const unsigned long dhtReadInterval = 2000;

// Heat Index Settings
const float HEAT_INDEX_THRESHOLD = 27.0;
const float HEAT_ALARM_THRESHOLD = 32.0;

// Power Management Settings
const unsigned long debounceDelay = 50;    // Debounce time for power switch
const unsigned long shutdownHoldTime = 2000;  // Time to hold button for shutdown

// Detection variables
unsigned long lastLogTime = 0;
unsigned long lastFlameDetectionTime = 0;
unsigned long lastDhtReadTime = 0;
unsigned long systemStartTime = 0;
int currentSmokeValue = 0;
float currentTemperature = 0;
float currentHumidity = 0;
float currentHeatIndex = 0;
int flameDetectionCount = 0;
int flameRejectionCounter = 0;
bool confirmedFlameDetected = false;
bool smokeDetected = false;
bool alarmActive = false;
bool isCalibrated = false;
bool dangerousHeatDetected = false;

// Power Management variables
bool systemPoweredOn = false;
bool lastSwitchState = HIGH;
bool switchState = HIGH;
unsigned long lastDebounceTime = 0;
unsigned long buttonPressStartTime = 0;
bool buttonActive = false;
bool powerStateChanged = false;

// WiFiManager and Preferences
WiFiManager wifiManager;
Preferences preferences;

void setup() {
  Serial.begin(115200);
  delay(1000); // Give time for serial to initialize
  
  // Initialize watchdog timer
  esp_task_wdt_init(30, true); // 30 second timeout
  esp_task_wdt_add(NULL); // Add current task to watchdog
  
  // Initialize pins
  pinMode(smokeDigitalPin, INPUT_PULLUP);
  pinMode(flamePin, INPUT_PULLUP);
  pinMode(smokeLedPin, OUTPUT);
  pinMode(flameLedPin, OUTPUT);
  pinMode(buzzerPin, OUTPUT);
  pinMode(powerSwitchPin, INPUT_PULLUP);    // Tactile switch with pull-up
  pinMode(powerStatusLedPin, OUTPUT);       // Power status LED (Yellow)
  pinMode(offStatusLedPin, OUTPUT);         // Off status LED (Red)
  
  // Turn off all outputs initially
  digitalWrite(smokeLedPin, LOW);
  digitalWrite(flameLedPin, LOW);
  digitalWrite(buzzerPin, LOW);
  digitalWrite(powerStatusLedPin, LOW);
  digitalWrite(offStatusLedPin, LOW);

  // Configure ADC for better accuracy
  analogReadResolution(12);
  analogSetAttenuation(ADC_11db);
  
  // Initialize WiFi in station mode
  WiFi.mode(WIFI_STA);

  // Initialize DHT sensor
  dht.begin();

  systemStartTime = millis();
  
  // Initialize preferences
  preferences.begin("fireguard", false);
  
  // Check if system should start powered on
  systemPoweredOn = preferences.getBool("powerState", false);
  
  if (systemPoweredOn) {
    Serial.println("System starting in powered ON state");
    powerOnSequence();
  } else {
    Serial.println("System starting in powered OFF state");
    powerOffSequence();
  }
}

void powerOnSequence() {
  // Turn on power status LED (Yellow)
  digitalWrite(powerStatusLedPin, HIGH);
  digitalWrite(offStatusLedPin, LOW);  // Turn off red LED
  
  // Connect to WiFi using WiFiManager
  setupWiFi();

  // Warm-up period
  Serial.println("\nSensor Warm-up (30 seconds)...");
  unsigned long warmUpStart = millis();
  while(millis() - warmUpStart < stabilizationDelay) {
    delay(1000);
    Serial.print(".");
    digitalWrite(smokeLedPin, (millis()/500) % 2);
    readDHT();
    
    // Simple button check during warm-up (no state changes)
    if (digitalRead(powerSwitchPin) == LOW) {
      Serial.println("Button pressed during warm-up");
      delay(300); // Simple debounce
    }
  }
  digitalWrite(smokeLedPin, LOW);
  Serial.println("\nWarm-up complete");

  // Calibration
  calibrateSensor();

  Serial.println("\nSystem Ready");
  Serial.print("Baseline: ");
  Serial.println(smokeBaseline);
  printEnvironmentData();
  
  // Save power state
  preferences.putBool("powerState", true);
  preferences.end(); // Close preferences to save data
  systemPoweredOn = true;
}

void powerOffSequence() {
  // Turn off all outputs
  digitalWrite(smokeLedPin, LOW);
  digitalWrite(flameLedPin, LOW);
  digitalWrite(buzzerPin, LOW);
  digitalWrite(powerStatusLedPin, LOW);  // Turn off yellow LED
  digitalWrite(offStatusLedPin, HIGH);   // Turn on red LED
  noTone(buzzerPin);
  
  // Disconnect WiFi to save power
  WiFi.disconnect(true);
  WiFi.mode(WIFI_OFF);
  
  // Save power state
  preferences.putBool("powerState", false);
  preferences.end(); // Close preferences to save data
  systemPoweredOn = false;
  isCalibrated = false; // Reset calibration state
  
  Serial.println("System powered off. Press button to turn on.");
}

void checkPowerSwitch() {
  // Read the state of the switch
  int reading = digitalRead(powerSwitchPin);
  
  // Check if switch state changed (due to noise or pressing)
  if (reading != lastSwitchState) {
    lastDebounceTime = millis();
  }
  
  // If the reading has been stable for longer than the debounce delay
  if ((millis() - lastDebounceTime) > debounceDelay) {
    // If the switch state has changed
    if (reading != switchState) {
      switchState = reading;
      
      // If switch is pressed (LOW because of pull-up)
      if (switchState == LOW) {
        buttonPressStartTime = millis();
        buttonActive = true;
        Serial.println("Button pressed");
      } else {
        // Button released
        if (buttonActive) {
          // Short press - toggle power if not in alarm
          if (!alarmActive && (millis() - buttonPressStartTime < shutdownHoldTime)) {
            powerStateChanged = true;
            Serial.println("Power state change requested");
          }
          buttonActive = false;
        }
      }
    }
  }
  
  // Check for long press (for emergency shutdown even during alarm)
  if (buttonActive && (millis() - buttonPressStartTime >= shutdownHoldTime)) {
    Serial.println("Long press detected - forced shutdown");
    powerStateChanged = true;
    buttonActive = false;
  }
  
  lastSwitchState = reading;
}

void setupWiFi() {
  // Set a custom hostname for the device
  String hostname = "FireGuard-" + String(ESP.getEfuseMac(), HEX);
  WiFi.setHostname(hostname.c_str());
  
  // WiFiManager configuration
  wifiManager.setDebugOutput(false); // Reduce debug output
  wifiManager.setConfigPortalTimeout(180); // 3 minutes timeout
  wifiManager.setConnectTimeout(30); // 30 seconds to connect
  wifiManager.setConnectRetries(3);
  
  // Try to connect to stored WiFi or start configuration portal
  Serial.println("Attempting to connect to WiFi...");
  digitalWrite(smokeLedPin, HIGH);
  
  if (!wifiManager.autoConnect("FireGuard-Setup")) {
    Serial.println("Failed to connect and hit timeout");
    // Reset and try again
    ESP.restart();
  }

  digitalWrite(smokeLedPin, LOW);
  Serial.println("WiFi connected!");
  Serial.print("IP address: ");
  Serial.println(WiFi.localIP());
  Serial.print("SSID: ");
  Serial.println(WiFi.SSID());
}

void readDHT() {
  if (millis() - lastDhtReadTime >= dhtReadInterval) {
    float h = dht.readHumidity();
    float t = dht.readTemperature();
    
    if (isnan(h) || isnan(t)) {
      Serial.println("DHT read failed - checking connections");
      delay(100);
      return;
    }
    
    if (h < 0 || h > 100 || t < -20 || t > 80) {
      Serial.println("Invalid DHT readings - ignoring");
      return;
    }
    
    currentTemperature = t;
    currentHumidity = h;
    currentHeatIndex = calculateHeatIndex(t, h);
    printEnvironmentData();
    
    lastDhtReadTime = millis();
  }
}

void printEnvironmentData() {
  if (!isnan(currentTemperature)) {
    Serial.print("Environment - Temp: ");
    Serial.print(currentTemperature);
    Serial.print("°C, Humidity: ");
    Serial.print(currentHumidity);
    Serial.print("%, Heat Index: ");
    Serial.print(currentHeatIndex);
    Serial.println("°C");
    
    if (currentHeatIndex >= HEAT_INDEX_THRESHOLD) {
      Serial.println("Warning: High Heat Index!");
    }
  }
}

float calculateHeatIndex(float temperature, float humidity) {
  if (isnan(temperature)) return NAN;
  
  if (temperature < 20.0 || humidity < 40.0) {
    return temperature;
  }

  float heatIndex = 0.5 * (temperature + 61.0 + ((temperature - 68.0) * 1.2) + (humidity * 0.094));

  if (heatIndex >= 80.0) {
    heatIndex = -42.379 + 2.04901523 * temperature + 10.14333127 * humidity 
                - 0.22475541 * temperature * humidity - 0.00683783 * pow(temperature, 2) 
                - 0.05481717 * pow(humidity, 2) + 0.00122874 * pow(temperature, 2) * humidity 
                + 0.00085282 * temperature * pow(humidity, 2) - 0.00000199 * pow(temperature, 2) * pow(humidity, 2);
    
    if (humidity > 85 && temperature >= 80 && temperature <= 87) {
      heatIndex += ((humidity - 85) / 10) * ((87 - temperature) / 5);
    }
  }
  
  return heatIndex;
}

void checkHeatConditions() {
  if (!isnan(currentHeatIndex)) {
    if (currentHeatIndex >= HEAT_ALARM_THRESHOLD) {
      if (!dangerousHeatDetected) {
        dangerousHeatDetected = true;
        Serial.print("DANGEROUS HEAT DETECTED! Heat Index: ");
        Serial.print(currentHeatIndex);
        Serial.println("°C");
        digitalWrite(smokeLedPin, HIGH);
        digitalWrite(flameLedPin, HIGH);
        delay(200);
        digitalWrite(smokeLedPin, LOW);
        digitalWrite(flameLedPin, LOW);
      }
    } else {
      dangerousHeatDetected = false;
    }
  }
}

void calibrateSensor() {
  Serial.println("Calibrating - ensure clean air environment...");
  digitalWrite(smokeLedPin, HIGH);
  
  const int numReadings = 500;
  long sum = 0;
  int minReading = 4095;
  int maxReading = 0;
  
  for(int i = 0; i < numReadings; i++) {
    int reading = analogRead(smokeAnalogPin);
    sum += reading;
    if(reading < minReading) minReading = reading;
    if(reading > maxReading) maxReading = reading;
    
    delay(50);
    if(i % 50 == 0) {
      Serial.print(".");
      readDHT();
      
      // Simple button check during calibration (no state changes)
      if (digitalRead(powerSwitchPin) == LOW) {
        Serial.println("Button pressed during calibration");
        delay(300); // Simple debounce
      }
    }
  }
  
  smokeBaseline = (sum - minReading - maxReading) / (numReadings - 2);
  
  digitalWrite(smokeLedPin, LOW);
  
  Serial.print("\nCalibration Complete - Baseline: ");
  Serial.println(smokeBaseline);
  Serial.print("Range observed: ");
  Serial.print(minReading);
  Serial.print(" - ");
  Serial.println(maxReading);
  
  isCalibrated = true;
}

void sendToDatabase(int smokeValue, bool smokeDetectedFlag, bool flameDetected) {
  if(WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi disconnected, attempting to reconnect...");
    setupWiFi();
    if(WiFi.status() != WL_CONNECTED) {
      Serial.println("No WiFi - Data Not Sent");
      return;
    }
  }

  HTTPClient http;
  String url = String(serverUrl) + 
               "?value=" + String(smokeDetectedFlag ? smokeValue : 0) + 
               "&detected=" + (smokeDetectedFlag ? "1" : "0") + 
               "&flame_detected=" + (flameDetected ? "1" : "0") +
               "&temperature=" + String(currentTemperature) +
               "&humidity=" + String(currentHumidity) +
               "&heat_index=" + String(currentHeatIndex) +
               "&log=1";
  
  http.begin(url);
  http.setTimeout(10000); // Increased timeout
  http.addHeader("User-Agent", "FireGuard-ESP32/1.0");
  
  int httpCode = http.GET();
  if(httpCode == HTTP_CODE_OK) {
    String response = http.getString();
    Serial.println("Data Sent: " + response);
  } else {
    Serial.println("Send Error: " + String(httpCode));
    if(httpCode == HTTPC_ERROR_CONNECTION_REFUSED) {
      Serial.println("Connection refused - server may be down");
    } else if(httpCode == HTTPC_ERROR_CONNECTION_LOST) {
      Serial.println("Connection lost - retrying...");
    }
  }
  http.end();
}

bool checkSmoke() {
  static unsigned long lastCheck = 0;
  static int readings[10] = {0};
  static int idx = 0;
  
  if(millis() - lastCheck < 100) return smokeDetected;
  lastCheck = millis();
  
  if(!isCalibrated) return false;

  int currentReading = 0;
  for(int i = 0; i < 3; i++) {
    currentReading += analogRead(smokeAnalogPin);
    delay(2);
  }
  currentReading /= 3;
  currentSmokeValue = currentReading;
  
  readings[idx] = currentReading;
  idx = (idx + 1) % 10;

  long sum = 0;
  for(int i = 0; i < 10; i++) {
    sum += readings[i];
  }
  int smoothedValue = sum / 10;

  bool digitalState = (digitalRead(smokeDigitalPin) == LOW);
  
  int relativeThreshold = smokeBaseline * smokeSensitivity;
  int absoluteThreshold = smokeBaseline + minSmokeChange;
  
  bool analogTrigger = (smoothedValue > relativeThreshold) && 
                      (smoothedValue > absoluteThreshold);
  bool digitalTrigger = digitalState && (smoothedValue > smokeBaseline * 1.2);
  
  return (analogTrigger && digitalTrigger) || (smoothedValue > smokeBaseline * 2.0);
}

bool checkFlame() {
  static unsigned long lastCheck = 0;
  static bool lastState = false;
  static int flameReadings[10] = {0};
  static int flameIndex = 0;
  
  if(millis() - lastCheck < 200) return confirmedFlameDetected;
  lastCheck = millis();
  
  int reading = 0;
  for(int i = 0; i < 5; i++) {
    reading += digitalRead(flamePin);
    delay(1);
  }
  flameReadings[flameIndex] = (reading < 3) ? 1 : 0;
  flameIndex = (flameIndex + 1) % 10;
  
  int flameCount = 0;
  for(int i = 0; i < 10; i++) {
    flameCount += flameReadings[i];
  }
  
  bool currentState = (flameCount >= 7);
  
  if(currentState != lastState) {
    lastFlameDetectionTime = millis();
    lastState = currentState;
  }
  
  if(currentState) {
    if(millis() - lastFlameDetectionTime > flameDebounceTime) {
      flameDetectionCount++;
      if(flameDetectionCount >= flameConfirmationCount) {
        confirmedFlameDetected = true;
      }
    }
  } else {
    flameDetectionCount = 0;
    if(millis() - lastFlameDetectionTime > flameDebounceTime * 2) {
      confirmedFlameDetected = false;
    }
  }
  
  return confirmedFlameDetected;
}

void handleAlarm(bool smoke, bool flame) {
  digitalWrite(smokeLedPin, smoke ? HIGH : LOW);
  digitalWrite(flameLedPin, flame ? HIGH : LOW);

  if(flame || (smoke && currentTemperature > 50)) {
    if(millis() % 200 < 100) {
      tone(buzzerPin, 1000, 100);
    } else {
      noTone(buzzerPin);
    }
    alarmActive = true;
  } 
  else if(smoke) {
    if(millis() % 500 < 200) {
      tone(buzzerPin, 800, 200);
    } else {
      noTone(buzzerPin);
    }
    alarmActive = true;
  } 
  else if(dangerousHeatDetected) {
    if(millis() % 1000 < 100) {
      tone(buzzerPin, 600, 100);
    } else {
      noTone(buzzerPin);
    }
    alarmActive = true;
  }
  else if(alarmActive) {
    noTone(buzzerPin);
    alarmActive = false;
  }
}

void loop() {
  // Check the power switch
  checkPowerSwitch();
  
  // Handle power state change
  if (powerStateChanged) {
    powerStateChanged = false;
    
    if (systemPoweredOn) {
      Serial.println("Power OFF triggered");
      powerOffSequence();
    } else {
      Serial.println("Power ON triggered");
      powerOnSequence();
    }
    return;
  }
  
  // If system is powered off, enter low-power mode
  if (!systemPoweredOn) {
    // Keep red LED solid on to indicate system is off
    digitalWrite(offStatusLedPin, HIGH);
    delay(100);
    return;
  }
  
  if(!isCalibrated) {
    delay(100);
    return;
  }
  
  // Check WiFi connection periodically
  if (WiFi.status() != WL_CONNECTED) {
    static unsigned long lastWiFiCheck = 0;
    if (millis() - lastWiFiCheck > 30000) { // Check every 30 seconds
      Serial.println("WiFi disconnected, attempting to reconnect...");
      setupWiFi();
      lastWiFiCheck = millis();
    }
  }

  bool currentSmoke = checkSmoke();
  bool currentFlame = checkFlame();
  readDHT();
  checkHeatConditions();

  if(currentSmoke != smokeDetected) {
    smokeDetected = currentSmoke;
    
    if(smokeDetected) {
      Serial.print("SMOKE DETECTED! Value: ");
      Serial.print(currentSmokeValue);
      Serial.print(" (Baseline: ");
      Serial.print(smokeBaseline);
      Serial.println(")");
    } else {
      Serial.println("Smoke cleared");
    }
  }

  if(currentFlame != confirmedFlameDetected) {
    confirmedFlameDetected = currentFlame;
    
    if(confirmedFlameDetected) {
      Serial.println("FLAME DETECTED!");
    } else {
      Serial.println("Flame cleared");
    }
  }

  handleAlarm(smokeDetected, confirmedFlameDetected);

  if(millis() - lastLogTime >= loggingInterval) {
    sendToDatabase(currentSmokeValue, smokeDetected, confirmedFlameDetected);
    
    Serial.print("Log - Smoke: ");
    Serial.print(smokeDetected ? currentSmokeValue : 0);
    Serial.print(" (");
    Serial.print(smokeDetected ? "DETECTED" : "clear");
    Serial.print("), Flame: ");
    Serial.print(confirmedFlameDetected ? "DETECTED" : "clear");
    if (!isnan(currentTemperature)) {
      Serial.print(", Temp: ");
      Serial.print(currentTemperature);
      Serial.print("°C, Humidity: ");
      Serial.print(currentHumidity);
      Serial.print("%, Heat Index: ");
      Serial.print(currentHeatIndex);
      Serial.print("°C");
    }
    Serial.println();
    
    lastLogTime = millis();
  }

  // Feed watchdog timer
  esp_task_wdt_reset();
  
  delay(50);
}