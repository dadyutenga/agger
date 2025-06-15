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

// I2C Bus declarations
TwoWire I2C_2 = TwoWire(1); // Use Wire1 for MAX30105
TwoWire I2C_3 = TwoWire(2); // Use Wire2 for MPU6050

Adafruit_MPU6050 mpu;

float calibX = 0;
float calibY = 0;

// WiFi credentials
const char* ssid = "honic desk";
const char* password = "1234567890";

// API endpoint - updated to the correct path
const char* apiUrl = "http://localhost/paralizeweb/src/api/receive_data.php";

// Device ID - this should match the device_id in your database
const char* deviceId = "ESP32_001";

// DS18B20 Temperature Sensor
#define ONE_WIRE_BUS 17 // DS18B20 connected to GPIO 17
OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature sensors(&oneWire);

MAX30105 particleSensor;

const byte RATE_SIZE = 4; //Increase this for more averaging. 4 is good.
byte rates[RATE_SIZE]; //Array of heart rates
byte rateSpot = 0;
long lastBeat = 0; //Time at which the last beat occurred

float beatsPerMinute;
int beatAvg;
float temperature = 0.0;

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

// Read temperature from DS18B20 using the provided method
float readTemperature() {
  // Request temperatures from all devices on the bus
  sensors.requestTemperatures();
  
  // Get temperature from the first sensor (index 0)
  float tempC = sensors.getTempCByIndex(0);
  
  // Check if reading was successful
  if (tempC != DEVICE_DISCONNECTED_C) {
    Serial.print("Temperature: ");
    Serial.print(tempC);
    Serial.println("°C");
    return tempC;
  } else {
    Serial.println("Error: Could not read temperature data");
    return 0.0;
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

// Send data to API
void sendDataToAPI() {
  if (!wifiConnected) {
    Serial.println("WiFi not connected. Attempting to reconnect...");
    connectToWiFi();
    if (!wifiConnected) return;
  }
  
  // Only send if we have valid data
  if (beatAvg > 0 && spo2Avg > 0) {
    Serial.println("Sending data to API...");
    
    HTTPClient http;
    http.begin(apiUrl);
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    
    // Create form data with actual temperature reading
    String httpRequestData = "device_id=" + String(deviceId) + 
                            "&heart_rate=" + String(beatAvg) + 
                            "&spo2=" + String(spo2Avg) + 
                            "&temperature=" + String(temperature);
    
    int httpResponseCode = http.POST(httpRequestData);
    
    if (httpResponseCode > 0) {
      String response = http.getString();
      Serial.print("HTTP Response code: ");
      Serial.println(httpResponseCode);
      Serial.print("Response: ");
      Serial.println(response);
    } else {
      Serial.print("Error code: ");
      Serial.println(httpResponseCode);
    }
    
    http.end();
  } else {
    Serial.println("No valid data to send");
  }
}

void setup()
{
  Serial.begin(115200);
  Serial.println("Initializing...");

  // Initialize I2C buses
  I2C_2.begin(18, 19); // SDA: 18, SCL: 19 for MAX30105
  I2C_3.begin(21, 22); // SDA: 21, SCL: 22 for MPU6050

  // Initialize DS18B20 temperature sensor
  sensors.begin();
  Serial.println("DS18B20 Temperature Sensor initialized");

  // Connect to WiFi
  connectToWiFi();

  // Initialize MAX30105 sensor with I2C_2
  if (!particleSensor.begin(I2C_2, I2C_SPEED_FAST))
  {
    Serial.println("MAX30105 was not found. Please check wiring/power. ");
    while (1);
  }
  
  Serial.println("Place your index finger on the sensor with steady pressure.");

  particleSensor.setup(); //Configure sensor with default settings
  particleSensor.setPulseAmplitudeRed(0x0A); //Turn Red LED to low to indicate sensor is running
  particleSensor.setPulseAmplitudeGreen(0); //Turn off Green LED
  
  // Initialize SpO2 buffers
  for (int i = 0; i < SPO2_BUFFER_SIZE; i++) {
    irBuffer[i] = 0;
    redBuffer[i] = 0;
  }
  
  // Initialize SpO2 average buffer
  for (int i = 0; i < SPO2_AVG_SIZE; i++) {
    spo2Values[i] = 0;
  }

  // Initialize MPU6050 with I2C_3
  while (!Serial)
    delay(10);

  Serial.println("MPU6050 Compass Calibration");

  if (!mpu.begin(0x68, &I2C_3)) {
    Serial.println("Failed to find MPU6050 chip");
    while (1) {
      delay(10);
    }
  }
  Serial.println("MPU6050 Found!");

  mpu.setAccelerometerRange(MPU6050_RANGE_8_G);
  mpu.setGyroRange(MPU6050_RANGE_500_DEG);
  mpu.setFilterBandwidth(MPU6050_BAND_21_HZ);

  delay(1000);

  Serial.println("Point to NORTH and keep flat... Wait 5 seconds");
  delay(5000);

  sensors_event_t a, g, temp;
  mpu.getEvent(&a, &g, &temp);

  calibX = atan2(a.acceleration.y, sqrt(a.acceleration.x * a.acceleration.x + a.acceleration.z * a.acceleration.z)) * 180 / PI;
  calibY = atan2(a.acceleration.x, sqrt(a.acceleration.y * a.acceleration.y + a.acceleration.z * a.acceleration.z)) * 180 / PI;

  Serial.print("Calibration X: ");
  Serial.println(calibX);
  Serial.print("Calibration Y: ");
  Serial.println(calibY);

  Serial.println("Calibration done.\n");
}

void loop()
{
  long irValue = particleSensor.getIR();
  long redValue = particleSensor.getRed();

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

  // Read temperature every 5 seconds
  static unsigned long lastTempReadTime = 0;
  if (millis() - lastTempReadTime > 5000) {
    temperature = readTemperature();
    lastTempReadTime = millis();
  }

  Serial.print("IR=");
  Serial.print(irValue);
  Serial.print(", RED=");
  Serial.print(redValue);
  Serial.print(", BPM=");
  Serial.print(beatsPerMinute);
  Serial.print(", Avg BPM=");
  Serial.print(beatAvg);

  if (spo2Calculated) {
    Serial.print(", SpO2=");
    Serial.print(spo2Value);
    Serial.print("%");
  }
  
  if (spo2AvgCalculated) {
    Serial.print(", Avg SpO2=");
    Serial.print(spo2Avg);
    Serial.print("%");
  }
  
  Serial.print(", Temperature=");
  Serial.print(temperature);
  Serial.print("°C");
  
  Serial.println();

  if (irValue < 50000) {
    Serial.println("No finger detected!");
    spo2Calculated = false;
    spo2AvgCalculated = false;
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

  sensors_event_t a, g, temp;
  mpu.getEvent(&a, &g, &temp);

  float rawX = atan2(a.acceleration.y, sqrt(a.acceleration.x * a.acceleration.x + a.acceleration.z * a.acceleration.z)) * 180 / PI;
  float rawY = atan2(a.acceleration.x, sqrt(a.acceleration.y * a.acceleration.y + a.acceleration.z * a.acceleration.z)) * 180 / PI;

  float correctedX = rawX - calibX;
  float correctedY = rawY - calibY;

  // Normalize to -180 ~ 180 degrees
  if (correctedX > 180) correctedX -= 360;
  if (correctedX < -180) correctedX += 360;
  if (correctedY > 180) correctedY -= 360;
  if (correctedY < -180) correctedY += 360;
// Direction detection
  if (correctedY > 30) {
    Serial.println("EAST");
  } else if (correctedY < -30) {
    Serial.println("WEST");
  } else if (correctedX > 30) {
    Serial.println("NORTH");
  } else if (correctedX < -30) {
    Serial.println("SOUTH");
  } else {
    Serial.println("FLAT / UNKNOWN");
  }

  Serial.println();
  delay(1000);
} 
