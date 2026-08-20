# Black Stone Body Scanning Tool

This is a real-time body scanning and measurement tool using **MediaPipe Pose** and **OpenCV** to estimate clothing sizes (Shirt/T-shirt and Pants) using your webcam.

## Features
- **Real-Time Skeleton Tracking**: Overlays a clean skeletal structure on your body.
- **Auto-Calibration**: Asks for your height at startup to compute physical scale in centimeters.
- **Rolling Average Smoothing**: Dampens jitter from the camera to provide stable measurements.
- **Auto-Lock Snapshot**: Automatically locks and freezes the measurements once you stand aligned inside the guide box for 3 seconds.
- **Sizes Mapping**: Maps chest, waist, hip, and shoulder measurements to custom clothing sizes.
- **Export Data**: Press `s` on your keyboard to save the locked measurements to a `measurements.json` file.

---

## Installation & Setup

1. **Open your terminal** and navigate to the project directory:
   ```bash
   cd "BLACK-STONE/cloth-scan-python"
   ```

2. **Install Dependencies**:
   ```bash
   pip install -r requirements.txt
   ```

3. **Run the Script**:
   ```bash
   python scan.py
   ```

---

## How to Get Accurate Results

1. **Enter Your Height**: Input your height in centimeters (e.g., `175`) when prompted by the terminal.
2. **Camera Placement**: Position your camera at about hip height and angle it perpendicular to the floor.
3. **Step Back**: Stand 2 to 3 meters back from the camera so that your entire body (head to toes) is visible within the screen.
4. **Alignment Guide**: Center your body inside the green/red dashed guide rectangle on the screen.
5. **Stand Still**: Once aligned, the countdown will start. Stand straight and still for 3 seconds.
6. **Save Results**: Press `s` to save your measurement card to `measurements.json`. Press `c` to reset the scan and try again. Press `q` to quit the program.
