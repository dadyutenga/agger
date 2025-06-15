# ESP32 Heart Rate and SpO2 Monitor

This project uses an ESP32 WROOM-DA and MAX30105 sensor to monitor heart rate and blood oxygen levels using optical sensing technology.

## Hardware Requirements

- ESP32 WROOM-DA development board
- MAX30105 Heart Rate and SpO2 Sensor
- Connecting wires
- Rubber band (for securing the sensor to finger)

## Wiring Instructions

Connect the MAX30105 sensor to your ESP32 as follows:
- 5V → 3.3V (ESP32's 3.3V pin)
- GND → GND
- SDA → GPIO21 (ESP32's default SDA pin)
- SCL → GPIO22 (ESP32's default SCL pin)
- INT → Not connected

## Software Requirements

1. Arduino IDE
2. ESP32 board support package
3. Required Libraries:
   - Wire.h (built-in)
   - MAX30105.h
   - spo2_algorithm.h

## Installation Steps

1. Install Arduino IDE
2. Add ESP32 board support:
   - Open Arduino IDE
   - Go to File → Preferences
   - Add this URL to Additional Boards Manager URLs:
     ```
     https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json
     ```
   - Go to Tools → Board → Boards Manager
   - Search for "esp32"
   - Install "ESP32 by Espressif Systems"

3. Install required libraries:
   - Go to Tools → Manage Libraries
   - Search for and install:
     - MAX30105
     - spo2_algorithm

4. Select your board:
   - Go to Tools → Board → ESP32 Arduino
   - Select "ESP32 Dev Module"

5. Select the correct port:
   - Go to Tools → Port
   - Select the COM port where your ESP32 is connected

6. Upload the code:
   - Open `esp32_heart_rate_monitor.ino`
   - Click the Upload button

## Usage

1. After uploading, open the Serial Monitor (115200 baud)
2. Place your index finger on the sensor
3. Use a rubber band to secure the sensor to your finger
4. Keep your finger steady and wait for readings
5. The Serial Monitor will display:
   - IR value
   - Current BPM (Beats Per Minute)
   - Average BPM
   - SpO2 level
   - SpO2 validity indicator

## Features

- Real-time heart rate monitoring
- Blood oxygen (SpO2) level measurement
- Built-in LED indicator that blinks with each heartbeat
- Averaged readings for more stable results
- Finger detection warning

## Troubleshooting

If you see "MAX30105 was not found" error:
1. Check your wiring connections
2. Verify power supply (should be 3.3V)
3. Ensure I2C connections are correct
4. Try using a different I2C address if needed

## Notes

- For best results, keep your finger steady on the sensor
- The sensor works best with consistent pressure
- Readings may take a few seconds to stabilize
- Normal heart rate range is typically between 60-100 BPM
- Normal SpO2 range is typically between 95-100%

# Patient Monitoring System

A web-based system for monitoring patients' vital signs and facilitating communication between doctors and caretakers.

## Features

1. **User Authentication**
   - Doctor login with username/password
   - Caretaker login with device-specific credentials
   - Session management and secure access control

2. **Dashboard**
   - Overview of all patients (for doctors)
   - Real-time vital signs monitoring
   - Patient status indicators
   - Quick access to patient details

3. **Patient Management**
   - Register new patients
   - Assign caretakers
   - Upload patient photos
   - Track patient location and contact information

4. **Vital Signs Monitoring**
   - Real-time heart rate monitoring
   - SpO2 level tracking
   - Temperature monitoring
   - Automated alerts for critical values

5. **Analysis**
   - Statistical analysis of vital signs
   - Patient health trends
   - Alert history and patterns
   - Customizable date ranges for reports

6. **Private Chat System**
   - Secure communication between doctors and caretakers
   - Real-time messaging
   - Unread message indicators
   - Message history
   - Patient-specific conversations

## Installation

1. Clone the repository to your web server directory
2. Import the database schema using `setup_database.php`
3. Configure database connection in `config.php`
4. Ensure proper permissions for file uploads
5. Access the system through your web browser

## Database Structure

The system uses MySQL with the following main tables:
- `doctors`: Doctor credentials and information
- `patients`: Patient records and details
- `vital_signs`: Real-time monitoring data
- `caretaker_credentials`: Caretaker access management
- `messages`: Private chat system data
- `alerts`: System alerts and notifications

## Usage

### For Doctors
1. Login with doctor credentials
2. Access dashboard to view all patients
3. Register new patients and assign caretakers
4. Monitor vital signs and receive alerts
5. Communicate with caretakers through chat
6. View analysis and generate reports

### For Caretakers
1. Login with provided device credentials
2. View assigned patient's details
3. Monitor vital signs in real-time
4. Communicate with the assigned doctor
5. Receive alerts for critical values

## Chat System

The private chat system enables secure communication between doctors and caretakers:

### Features
- Real-time messaging
- Unread message indicators
- Message history
- Automatic user mapping (caretakers can only chat with their patient's doctor)
- Mobile-responsive interface

### Privacy
- Messages are private between doctor and caretaker
- Caretakers can only communicate with their assigned doctor
- Doctors see all caretakers for their patients
- Message history is preserved for reference

## Security

- Password hashing using PHP's password_hash()
- Prepared SQL statements to prevent injection
- Session-based authentication
- Input validation and sanitization
- Access control based on user roles

## Support

For technical support or questions, please contact the system administrator.

## License

[Your License Information] 