#include <Wire.h>
#include "MAX30105.h"
#include "heartRate.h"
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <Adafruit_MPU6050.h>
#include <Adafruit_Sensor.h>
#include <HardwareSerial.h>  // Changed from SoftwareSerial to HardwareSerial
#include <Adafruit_GFX.h>
#include <Adafruit_SH110X.h>

// I2C pins definition
#define I2C_SDA 21
#define I2C_SCL 22

// GSM Module setup
#define GSM_RX 19  // Changed from 4 to 19
#define GSM_TX 18  // Changed from 5 to 18
HardwareSerial gsmSerial(2);  // Use UART2

// OLED Display setup
#define SCREEN_WIDTH 128
#define SCREEN_HEIGHT 64
#define OLED_RESET -1
#define SCREEN_ADDRESS 0x3C
Adafruit_SH1106G display(SCREEN_WIDTH, SCREEN_HEIGHT, &Wire, OLED_RESET);

// Vital signs ranges for paralyzed patients
#define TEMP_MIN 35.5
#define TEMP_MAX 37.5
#define HR_MIN 60
#define HR_MAX 100
#define SPO2_MIN 95
#define SPO2_MAX 100

// Direction messages
const char* DIRECTION_MESSAGES[] = {
  "I am thirsty",      // EAST
  "I need assistance", // WEST
  "I am hungry",       // NORTH
  "I need fresh air"   // SOUTH
};

// WiFi credentials
const char* ssid = "honic desk";
const char* password = "1234567890";

// API endpoint - updated to the correct path
const char* apiUrl = "http://192.168.137.1/paralizeweb/src/api/receive_data.php";

// Device ID - this should match the device_id in your database
const char* deviceId = "ESP32_001";

// Temperature sensor setup
#define TEMP_SENSOR_PIN 17
OneWire oneWire(TEMP_SENSOR_PIN);
DallasTemperature tempSensor(&oneWire);
float temperature = 0.0;

// Temperature calibration factors
#define TEMP_CALIBRATION_OFFSET 0.5  // Adjust this value based on your sensor's accuracy
#define TEMP_CALIBRATION_FACTOR 1.0   // Adjust this value if needed (1.0 = no adjustment)

// MPU6050 setup
Adafruit_MPU6050 mpu;
float calibX = 0;
float calibY = 0;
float orientationX = 0;
float orientationY = 0;
String currentDirection = "UNKNOWN";

MAX30105 particleSensor;

const byte RATE_SIZE = 4; //Increase this for more averaging. 4 is good.
byte rates[RATE_SIZE]; //Array of heart rates
byte rateSpot = 0;
long lastBeat = 0; //Time at which the last beat occurred

float beatsPerMinute;
int beatAvg;

// SpO2 calculation variables
#define SPO2_SAMPLE_SIZE 25
#define SPO2_BUFFER_SIZE 100
#define SPO2_AVG_SIZE 5  // Number of SpO2 readings to average
long irBuffer[SPO2_BUFFER_SIZE];
long redBuffer[SPO2_BUFFER_SIZE];
int bufferIndex = 0;
int spo2Value = 0;
int spo2Values[SPO2_AVG_SIZE];  // Array to store SpO2 values for averaging
int spo2AvgIndex = 0;
int spo2Avg = 0;
bool spo2Calculated = false;
bool spo2AvgCalculated = false;

// Data sending variables
unsigned long lastDataSendTime = 0;
const unsigned long DATA_SEND_INTERVAL = 10000; // Send data every 10 seconds
bool wifiConnected = false;
long irValue = 0;  // Moved to global scope
long redValue = 0; // Also moved redValue for consistency
unsigned long lastPrintTime = 0;  // Add timer for printing
const unsigned long PRINT_INTERVAL = 1000;  // Print every 1 second

// Global variables
String tiltMessage = "";  // Add this with other global variables at the top

// Improved SpO2 calculation function
int calculateSpO2() {
  // Find min and max values in the buffer
  long minIR = 999999;
  long maxIR = 0;
  long minRed = 999999;
  long maxRed = 0;
  
  // Calculate mean values for noise reduction
  long sumIR = 0;
  long sumRed = 0;
  
  for (int i = 0; i < SPO2_BUFFER_SIZE; i++) {
    sumIR += irBuffer[i];
    sumRed += redBuffer[i];
    
    if (irBuffer[i] < minIR) minIR = irBuffer[i];
    if (irBuffer[i] > maxIR) maxIR = irBuffer[i];
    if (redBuffer[i] < minRed) minRed = redBuffer[i];
    if (redBuffer[i] > maxRed) maxRed = redBuffer[i];
  }
  
  long meanIR = sumIR / SPO2_BUFFER_SIZE;
  long meanRed = sumRed / SPO2_BUFFER_SIZE;
  
  // Calculate AC and DC components with noise filtering
  long irAC = 0;
  long redAC = 0;
  
  // Use RMS (Root Mean Square) for AC calculation to reduce noise
  for (int i = 0; i < SPO2_BUFFER_SIZE; i++) {
    irAC += (irBuffer[i] - meanIR) * (irBuffer[i] - meanIR);
    redAC += (redBuffer[i] - meanRed) * (redBuffer[i] - meanRed);
  }
  
  irAC = sqrt(irAC / SPO2_BUFFER_SIZE);
  redAC = sqrt(redAC / SPO2_BUFFER_SIZE);
  
  long irDC = meanIR;
  long redDC = meanRed;
  
  // Calculate ratio with error checking
  float ratio = 0;
  if (irAC > 0 && redAC > 0 && irDC > 0 && redDC > 0) {
    ratio = (float)(redAC * irDC) / (float)(irAC * redDC);
  }
  
  // Convert ratio to SpO2 percentage using improved empirical formula
  // This formula is based on research papers and may need fine-tuning
  int spo2 = 0;
  
  if (ratio > 0) {
    // More accurate formula with calibration factors
    spo2 = 110 - (25 * ratio);
    
    // Apply additional calibration based on ratio ranges
    if (ratio < 0.5) {
      spo2 = 100;  // Very high SpO2
    } else if (ratio > 1.5) {
      spo2 = 80;   // Very low SpO2
    }
  }
  
  // Constrain to valid range
  if (spo2 > 100) spo2 = 100;
  if (spo2 < 0) spo2 = 0;
  
  return spo2;
}

// Calculate average SpO2
int calculateSpO2Average() {
  int sum = 0;
  int validReadings = 0;
  
  for (int i = 0; i < SPO2_AVG_SIZE; i++) {
    if (spo2Values[i] > 0) {
      sum += spo2Values[i];
      validReadings++;
    }
  }
  
  if (validReadings > 0) {
    return sum / validReadings;
  } else {
    return 0;
  }
}

// Connect to WiFi
void connectToWiFi() {
  Serial.println("Connecting to WiFi...");
  WiFi.begin(ssid, password);
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nWiFi connected");
    Serial.print("IP address: ");
    Serial.println(WiFi.localIP());
    wifiConnected = true;
  } else {
    Serial.println("\nWiFi connection failed");
    wifiConnected = false;
  }
}

// Read temperature from DS18B20
float readTemperature() {
  tempSensor.requestTemperatures();
  float rawTemp = tempSensor.getTempCByIndex(0);
  
  // Check if reading is valid
  if (rawTemp == DEVICE_DISCONNECTED_C) {
    Serial.println("Error: Could not read temperature data");
    return 0.0;
  }
  
  // Apply calibration
  float calibratedTemp = (rawTemp * TEMP_CALIBRATION_FACTOR) + TEMP_CALIBRATION_OFFSET;
  
  // Print calibration info for debugging
  Serial.print("Raw Temp: ");
  Serial.print(rawTemp);
  Serial.print("°C, Calibrated Temp: ");
  Serial.print(calibratedTemp);
  Serial.println("°C");
  
  return calibratedTemp;
}

// Function to send SMS alert
void sendSMSAlert(const char* message) {
  gsmSerial.println("AT+CMGF=1");  // Set SMS text mode
  delay(1000);
  gsmSerial.println("AT+CMGS=\"+1234567890\"");  // Replace with actual phone number
  delay(1000);
  gsmSerial.println(message);
  delay(1000);
  gsmSerial.write(26);  // Ctrl+Z to send
  delay(1000);
}

// Function to check vital signs and generate alerts
String checkVitalSigns() {
  String alertMessage = "";
  
  // Check temperature
  if (temperature < TEMP_MIN) {
    alertMessage += "Low Temperature Alert: " + String(temperature) + "°C\n";
  } else if (temperature > TEMP_MAX) {
    alertMessage += "High Temperature Alert: " + String(temperature) + "°C\n";
  }
  
  // Check heart rate
  if (beatAvg < HR_MIN) {
    alertMessage += "Low Heart Rate Alert: " + String(beatAvg) + " BPM\n";
  } else if (beatAvg > HR_MAX) {
    alertMessage += "High Heart Rate Alert: " + String(beatAvg) + " BPM\n";
  }
  
  // Check SpO2
  if (spo2Avg < SPO2_MIN) {
    alertMessage += "Low SpO2 Alert: " + String(spo2Avg) + "%\n";
  } else if (spo2Avg > SPO2_MAX) {
    alertMessage += "High SpO2 Alert: " + String(spo2Avg) + "%\n";
  }
  
  return alertMessage;
}

// Function to determine vital sign status
String getVitalSignStatus(float value, float min, float max, bool hasFinger) {
  if (!hasFinger) return "no_finger";
  if (value < min) return "low";
  if (value > max) return "high";
  return "normal";
}

// Send data to API
void sendDataToAPI() {
  // Check if finger is detected
  if (irValue < 50000) {
    Serial.println("\n=== No Finger Detected - Skipping Data Send ===");
    return;  // Exit function without sending any data
  }

  // Only proceed if finger is detected
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin("http://192.168.137.1/paralizeweb/src/api/receive_data.php");
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    
    // Prepare data
    String postData = "device_id=" + String(deviceId);
    
    // Add heart rate and its status
    if (beatAvg > 0) {
      postData += "&heart_rate=" + String(beatAvg);
      postData += "&heart_rate_status=" + getVitalSignStatus(beatAvg, HR_MIN, HR_MAX, true);
    }
    
    // Add SpO2 and its status
    if (spo2Avg > 0) {
      postData += "&spo2=" + String(spo2Avg);
      postData += "&spo2_status=" + getVitalSignStatus(spo2Avg, SPO2_MIN, SPO2_MAX, true);
    }
    
    // Add temperature and its status
    if (temperature > 0) {
      postData += "&temperature=" + String(temperature, 1);
      postData += "&temperature_status=" + getVitalSignStatus(temperature, TEMP_MIN, TEMP_MAX, true);
    }
    
    // Add tilt message if available
    if (tiltMessage != "") {
      postData += "&tilt_message=" + tiltMessage;
      tiltMessage = ""; // Clear the message after sending
    }
    
    int httpResponseCode = http.POST(postData);
    
    if (httpResponseCode > 0) {
      String response = http.getString();
      Serial.println("HTTP Response code: " + String(httpResponseCode));
      Serial.println("Response: " + response);
    } else {
      Serial.println("Error sending data. HTTP Response code: " + String(httpResponseCode));
    }
    
    http.end();
  } else {
    Serial.println("WiFi not connected");
  }
}

// Add this new function after the existing functions
void checkAndSendOrientationSMS() {
  static String lastDirection = "FLAT";
  static unsigned long lastSMSTime = 0;
  const unsigned long SMS_COOLDOWN = 30000;  // 30 seconds cooldown between SMS
  
  // Read MPU6050 orientation
  sensors_event_t a, g, temp;
  mpu.getEvent(&a, &g, &temp);
  
  float rawX = atan2(a.acceleration.y, sqrt(a.acceleration.x * a.acceleration.x + a.acceleration.z * a.acceleration.z)) * 180 / PI;
  float rawY = atan2(a.acceleration.x, sqrt(a.acceleration.y * a.acceleration.y + a.acceleration.z * a.acceleration.z)) * 180 / PI;
  
  float currentX = rawX - calibX;
  float currentY = rawY - calibY;
  
  // Normalize to -180 ~ 180 degrees
  if (currentX > 180) currentX -= 360;
  if (currentX < -180) currentX += 360;
  if (currentY > 180) currentY -= 360;
  if (currentY < -180) currentY += 360;
  
  // Determine current direction
  String currentDirection = "FLAT";
  String message = "";
  
  if (currentY > 30) {
    currentDirection = "EAST";
    message = DIRECTION_MESSAGES[0];  // "I am thirsty"
  } else if (currentY < -30) {
    currentDirection = "WEST";
    message = DIRECTION_MESSAGES[1];  // "I need assistance"
  } else if (currentX > 30) {
    currentDirection = "NORTH";
    message = DIRECTION_MESSAGES[2];  // "I am hungry"
  } else if (currentX < -30) {
    currentDirection = "SOUTH";
    message = DIRECTION_MESSAGES[3];  // "I need fresh air"
  }
  
  // Send SMS if direction changed and cooldown period has passed
  if (currentDirection != "FLAT" && 
      currentDirection != lastDirection && 
      (millis() - lastSMSTime > SMS_COOLDOWN)) {
    
    Serial.print("Sending SMS for direction change: ");
    Serial.println(message);
    
    // Send SMS
    gsmSerial.println("AT+CMGF=1");  // Set SMS text mode
    delay(1000);
    gsmSerial.println("AT+CMGS=\"+1234567890\"");  // Replace with actual phone number
    delay(1000);
    gsmSerial.println(message);
    delay(1000);
    gsmSerial.write(26);  // Ctrl+Z to send
    delay(1000);
    
    lastSMSTime = millis();
    lastDirection = currentDirection;
  }
}

void updateDisplay() {
  static unsigned long lastDisplayUpdate = 0;
  static unsigned long lastStatusUpdate = 0;
  static int statusIndex = 0;
  const unsigned long DISPLAY_UPDATE_INTERVAL = 1000;
  const unsigned long STATUS_UPDATE_INTERVAL = 5000;  // 5 seconds for status updates
  
  if (millis() - lastDisplayUpdate < DISPLAY_UPDATE_INTERVAL) return;
  
  display.clearDisplay();
  
  // Title
  display.setTextSize(1);
  display.setTextColor(SH110X_WHITE);
  display.setCursor(0, 0);
  display.print("Vital Signs");
  display.drawLine(0, 8, 128, 8, SH110X_WHITE);
  
  // Left column - HR and SpO2
  display.setTextSize(1);
  display.setCursor(0, 12);
  display.print("HR:");
  display.setTextSize(1.5);
  display.setCursor(0, 22);
  if (irValue < 50000) {
    display.print("NA");
  } else if (beatAvg > 0) {
    display.print(beatAvg);
    display.print(" BPM");
  } else {
    display.print("Wait");
  }
  
  display.setTextSize(1);
  display.setCursor(0, 36);
  display.print("SpO2:");
  display.setTextSize(1);
  display.setCursor(0, 44);
  if (irValue < 50000) {
    display.print("NA");
  } else if (spo2Avg > 0) {
    display.print(spo2Avg);
    display.print(" %");
  } else {
    display.print("Wait");
  }
  
  // Right column - Temp and Status
  display.setTextSize(1);
  display.setCursor(64, 12);
  display.print("Temp:");
  display.setTextSize(1.5);
  display.setCursor(64, 22);
  if (temperature > 0) {
    display.print(temperature, 1);
    display.print(" C");
  } else {
    display.print("Wait");
  }
  
  display.setTextSize(1);
  display.setCursor(64, 36);
  display.print("Status:");
  display.setTextSize(1);
  display.setCursor(64, 44);
  
  // Update status message every 5 seconds
  if (millis() - lastStatusUpdate >= STATUS_UPDATE_INTERVAL) {
    lastStatusUpdate = millis();
    statusIndex = (statusIndex + 1) % 3;
  }
  
  if (irValue < 50000) {
    display.print("No Finger");
  } else {
    String statusMessage = "";
    switch(statusIndex) {
      case 0:  // HR status (60-100 BPM)
        if (beatAvg >= HR_MIN && beatAvg <= HR_MAX) {
          statusMessage = "HR Good";
        } else {
          statusMessage = "HR Bad";
        }
        break;
      case 1:  // SpO2 status (95-100%)
        if (spo2Avg >= SPO2_MIN && spo2Avg <= SPO2_MAX) {
          statusMessage = "SpO2 Good";
        } else {
          statusMessage = "SpO2 Bad";
        }
        break;
      case 2:  // Temperature status (35.5-37.5°C)
        if (temperature >= TEMP_MIN && temperature <= TEMP_MAX) {
          statusMessage = "Temp Good";
        } else {
          statusMessage = "Temp Bad";
        }
        break;
    }
    display.print(statusMessage);
  }
  
  display.display();
  lastDisplayUpdate = millis();
}

void setupDisplay() {
  display.begin(SCREEN_ADDRESS, true);
  display.clearDisplay();
  display.setTextColor(SH110X_WHITE);
  
  // Show startup message
  display.setTextSize(1);
  display.setCursor(0, 10);
  display.print("Initializing...");
  display.setCursor(0, 20);
  display.print("Please wait...");
  display.display();
  delay(2000);
}

void setup()
{
  Serial.begin(115200);
  Serial.println("Initializing...");

  // Initialize I2C
  Wire.begin(I2C_SDA, I2C_SCL);
  
  // Initialize OLED display
  setupDisplay();
  
  // Initialize temperature sensor
  tempSensor.begin();
  
  // Initialize MPU6050 with retry
  Serial.println("Initializing MPU6050...");
  int mpuInitAttempts = 0;
  while (!mpu.begin() && mpuInitAttempts < 5) {
    Serial.println("Failed to find MPU6050 chip, retrying...");
    delay(1000);
    mpuInitAttempts++;
  }
  
  if (!mpu.begin()) {
    Serial.println("Failed to find MPU6050 chip after multiple attempts");
    Serial.println("Please check the following:");
    Serial.println("1. Wiring connections (SDA->GPIO21, SCL->GPIO22)");
    Serial.println("2. Power supply (3.3V)");
    Serial.println("3. I2C pull-up resistors (4.7kΩ)");
    while (1) {
      delay(10);
    }
  }
  
  Serial.println("MPU6050 Found!");

  // Configure MPU6050
  mpu.setAccelerometerRange(MPU6050_RANGE_8_G);
  mpu.setGyroRange(MPU6050_RANGE_500_DEG);
  mpu.setFilterBandwidth(MPU6050_BAND_21_HZ);

  // MPU6050 Calibration
  Serial.println("Point to NORTH and keep flat... Wait 5 seconds");
  delay(5000);

  sensors_event_t a, g, temp;
  mpu.getEvent(&a, &g, &temp);

  // Print raw values for debugging
  Serial.println("Raw accelerometer values:");
  Serial.print("X: "); Serial.print(a.acceleration.x);
  Serial.print(" Y: "); Serial.print(a.acceleration.y);
  Serial.print(" Z: "); Serial.println(a.acceleration.z);

  calibX = atan2(a.acceleration.y, sqrt(a.acceleration.x * a.acceleration.x + a.acceleration.z * a.acceleration.z)) * 180 / PI;
  calibY = atan2(a.acceleration.x, sqrt(a.acceleration.y * a.acceleration.y + a.acceleration.z * a.acceleration.z)) * 180 / PI;

  Serial.print("Calibration X: ");
  Serial.println(calibX);
  Serial.print("Calibration Y: ");
  Serial.println(calibY);
  Serial.println("Calibration done.\n");
  
  // Connect to WiFi
  connectToWiFi();

  // Initialize MAX30105 sensor
  if (!particleSensor.begin(Wire, I2C_SPEED_FAST)) {
    Serial.println("MAX30105 was not found. Please check wiring/power. ");
    while (1);
  }
  
  Serial.println("Place your index finger on the sensor with steady pressure.");

  particleSensor.setup();
  particleSensor.setPulseAmplitudeRed(0x0A);
  particleSensor.setPulseAmplitudeGreen(0);
  
  // Initialize SpO2 buffers
  for (int i = 0; i < SPO2_BUFFER_SIZE; i++) {
    irBuffer[i] = 0;
    redBuffer[i] = 0;
  }
  
  // Initialize SpO2 average buffer
  for (int i = 0; i < SPO2_AVG_SIZE; i++) {
    spo2Values[i] = 0;
  }

  // Initialize GSM module
  Serial.println("Initializing GSM module...");
  gsmSerial.begin(9600, SERIAL_8N1, GSM_RX, GSM_TX);  // Initialize with correct pins
  delay(1000);
  
  // Clear any pending data
  while(gsmSerial.available()) {
    gsmSerial.read();
  }
  
  // Check if GSM module is responding
  Serial.println("Checking GSM module...");
  gsmSerial.println("AT");
  delay(1000);
  
  bool gsmFound = false;
  unsigned long startTime = millis();
  while (millis() - startTime < 5000) {  // Wait up to 5 seconds for response
    if (gsmSerial.available()) {
      String response = gsmSerial.readString();
      Serial.print("GSM Response: ");
      Serial.println(response);
      
      // Check for any response, not just "OK"
      if (response.length() > 0) {
        gsmFound = true;
        Serial.println("GSM module found and responding!");
        break;
      }
    }
    delay(100);
  }
  
  if (!gsmFound) {
    Serial.println("ERROR: GSM module not responding!");
    Serial.println("Please check:");
    Serial.println("1. Power supply (MUST be 3.3V, NOT 5V)");
    Serial.println("2. TX/RX connections (TX->GPIO18, RX->GPIO19)");
    Serial.println("3. SIM card insertion");
    Serial.println("4. GSM module power LED");
    Serial.println("5. Voltage regulator if using 5V power supply");
    while (1) {
      delay(1000);  // Stop execution if GSM not found
    }
  }
  
  // Initialize GSM module
  Serial.println("Configuring GSM module...");
  
  // Set SMS text mode
  gsmSerial.println("AT+CMGF=1");
  delay(1000);
  while(gsmSerial.available()) {
    Serial.write(gsmSerial.read());
  }
  
  // Set SMS notification mode
  gsmSerial.println("AT+CNMI=2,2,0,0,0");
  delay(1000);
  while(gsmSerial.available()) {
    Serial.write(gsmSerial.read());
  }
  
  // Check network registration
  Serial.println("Checking network registration...");
  gsmSerial.println("AT+COPS?");
  delay(1000);
  while(gsmSerial.available()) {
    Serial.write(gsmSerial.read());
  }
  
  Serial.println("GSM module initialized.");
}

void loop()
{
  // Check and send SMS based on orientation (independent of other conditions)
  checkAndSendOrientationSMS();

  // Read MPU6050 orientation
  sensors_event_t a, g, temp;
  mpu.getEvent(&a, &g, &temp);
  
  float rawX = atan2(a.acceleration.y, sqrt(a.acceleration.x * a.acceleration.x + a.acceleration.z * a.acceleration.z)) * 180 / PI;
  float rawY = atan2(a.acceleration.x, sqrt(a.acceleration.y * a.acceleration.y + a.acceleration.z * a.acceleration.z)) * 180 / PI;
  
  orientationX = rawX - calibX;
  orientationY = rawY - calibY;
  
  // Normalize to -180 ~ 180 degrees
  if (orientationX > 180) orientationX -= 360;
  if (orientationX < -180) orientationX += 360;
  if (orientationY > 180) orientationY -= 360;
  if (orientationY < -180) orientationY += 360;
  
  // Determine direction
  if (orientationY > 30) {
    currentDirection = "EAST";
  } else if (orientationY < -30) {
    currentDirection = "WEST";
  } else if (orientationX > 30) {
    currentDirection = "NORTH";
  } else if (orientationX < -30) {
    currentDirection = "SOUTH";
  } else {
    currentDirection = "FLAT";
  }

  irValue = particleSensor.getIR();
  redValue = particleSensor.getRed();

  // Store values in SpO2 buffer
  irBuffer[bufferIndex] = irValue;
  redBuffer[bufferIndex] = redValue;
  bufferIndex = (bufferIndex + 1) % SPO2_BUFFER_SIZE;

  if (checkForBeat(irValue) == true)
  {
    //We sensed a beat!
    long delta = millis() - lastBeat;
    lastBeat = millis();

    beatsPerMinute = 60 / (delta / 1000.0);

    if (beatsPerMinute < 255 && beatsPerMinute > 20)
    {
      rates[rateSpot++] = (byte)beatsPerMinute;
      rateSpot %= RATE_SIZE;

      beatAvg = 0;
      for (byte x = 0 ; x < RATE_SIZE ; x++)
        beatAvg += rates[x];
      beatAvg /= RATE_SIZE;
    }
  }

  // Calculate SpO2 every SPO2_SAMPLE_SIZE samples
  if (bufferIndex % SPO2_SAMPLE_SIZE == 0) {
    spo2Value = calculateSpO2();
    spo2Calculated = true;
    
    // Store SpO2 value for averaging
    spo2Values[spo2AvgIndex] = spo2Value;
    spo2AvgIndex = (spo2AvgIndex + 1) % SPO2_AVG_SIZE;
    
    // Calculate average SpO2
    spo2Avg = calculateSpO2Average();
    spo2AvgCalculated = true;
  }

  // Read temperature periodically
  static unsigned long lastTempRead = 0;
  if (millis() - lastTempRead > 2000) {
    temperature = readTemperature();
    lastTempRead = millis();
  }

  // Update display every second
  if (millis() - lastPrintTime >= PRINT_INTERVAL) {
    updateDisplay();
    
    if (irValue < 50000) {
      Serial.println("No finger detected!");
      spo2Calculated = false;
      spo2AvgCalculated = false;
    }
    
    lastPrintTime = millis();  // Update last print time
  }
  
  // Send data to API at regular intervals
  if (millis() - lastDataSendTime > DATA_SEND_INTERVAL) {
    sendDataToAPI();
    lastDataSendTime = millis();
  }
  
  // Check WiFi connection periodically
  if (WiFi.status() != WL_CONNECTED) {
    wifiConnected = false;
  }
} 