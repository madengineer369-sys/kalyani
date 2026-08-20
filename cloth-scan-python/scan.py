import cv2
import mediapipe as mp
import numpy as np
import time
import json
import os
import urllib.request

from mediapipe.tasks import python
from mediapipe.tasks.python import vision

# Model configuration
MODEL_FILENAME = "pose_landmarker_full.task"
MODEL_URL = "https://storage.googleapis.com/mediapipe-models/pose_landmarker/pose_landmarker_full/float16/1/pose_landmarker_full.task"

# Colors for UI (BGR format)
COLOR_WHITE = (245, 245, 245)
COLOR_GOLD = (0, 198, 255)       # Premium gold-ish yellow
COLOR_DARK = (20, 20, 20)
COLOR_GREEN = (0, 230, 115)      # Safe / aligned green
COLOR_RED = (80, 80, 255)        # Warning / alert red
COLOR_GRAY = (120, 120, 120)

def download_model_if_missing():
    """Download the MediaPipe Pose Landmarker model task file if it does not exist."""
    current_dir = os.path.dirname(os.path.abspath(__file__))
    model_path = os.path.join(current_dir, MODEL_FILENAME)
    
    if not os.path.exists(model_path):
        print(f"\n[INFO] Downloading body pose detection model (~14MB)...")
        print(f"Source: {MODEL_URL}")
        try:
            # Add User-Agent header for urllib to avoid potential request blockings
            opener = urllib.request.build_opener()
            opener.addheaders = [('User-agent', 'Mozilla/5.0')]
            urllib.request.install_opener(opener)
            
            urllib.request.urlretrieve(MODEL_URL, model_path)
            print("[SUCCESS] Model downloaded successfully!")
        except Exception as e:
            print(f"[ERROR] Failed to download model: {e}")
            print("Please download it manually and place it in the same directory as scan.py.")
    return model_path

def calculate_distance_2d(p1, p2, width, height):
    """Calculate distance in pixels on the 2D image plane."""
    x1, y1 = int(p1.x * width), int(p1.y * height)
    x2, y2 = int(p2.x * width), int(p2.y * height)
    return np.sqrt((x1 - x2)**2 + (y1 - y2)**2)

def get_clothing_sizes(shoulder, chest, waist, hips):
    """Map real-world body measurements to clothing sizes."""
    # Sizing logic based on standard fashion charts (in cm)
    
    # 1. T-shirt / Shirt Size Recommendation
    shirt_size = "M"
    if chest < 88:
        shirt_size = "XS"
    elif chest < 96:
        shirt_size = "S"
    elif chest < 104:
        shirt_size = "M"
    elif chest < 112:
        shirt_size = "L"
    elif chest < 124:
        shirt_size = "XL"
    else:
        shirt_size = "XXL"
        
    # 2. Pants / Jeans Size Recommendation (Waist size in inches)
    waist_inches = waist / 2.54
    pants_size = int(round(waist_inches))
    # Round to nearest even size
    if pants_size % 2 != 0:
        pants_size += 1
    # Clamp pants size between 28 and 42 for standard sizes
    pants_size = max(28, min(42, pants_size))
    
    return shirt_size, f"{pants_size} (Waist)"

def draw_styled_box(img, x, y, w, h, title, value, unit="", bg_color=COLOR_DARK, text_color=COLOR_WHITE):
    """Draw a semi-transparent card with key-value information."""
    # Draw background box
    overlay = img.copy()
    cv2.rectangle(overlay, (x, y), (x + w, y + h), bg_color, -1)
    cv2.rectangle(overlay, (x, y), (x + w, y + h), COLOR_GOLD, 1)
    cv2.addWeighted(overlay, 0.75, img, 0.25, 0, img)
    
    # Draw text labels
    cv2.putText(img, title, (x + 10, y + 20), cv2.FONT_HERSHEY_SIMPLEX, 0.4, COLOR_GOLD, 1, cv2.LINE_AA)
    cv2.putText(img, f"{value} {unit}", (x + 10, y + 45), cv2.FONT_HERSHEY_SIMPLEX, 0.6, text_color, 2, cv2.LINE_AA)

def main():
    print("=" * 60)
    print("      BLACK STONE - SMART BODY MEASUREMENT CAMERA SCANNER      ")
    print("=" * 60)
    print("This tool estimates body proportions using your webcam and pose landmarks.")
    print("To convert pixels to physical size, we need your height as a reference.")
    
    # Check and download model
    model_path = download_model_if_missing()
    
    # Get user height
    try:
        user_height_cm = float(input("\nEnter your height in cm (e.g., 175): ").strip())
    except ValueError:
        print("Invalid input. Using default height of 175 cm.")
        user_height_cm = 175.0
        
    print("\nStarting camera... Stand 2-3 meters back so your full body is visible.")
    print("Press 'q' to Quit, 's' to Save measurements, 'c' to Re-calibrate height.")
    print("=" * 60)

    # Initialize Pose Landmarker using modern Tasks API
    base_options = python.BaseOptions(model_asset_path=model_path)
    options = vision.PoseLandmarkerOptions(
        base_options=base_options,
        output_segmentation_masks=False,
        running_mode=vision.RunningMode.IMAGE
    )

    # Pose connections indices (standard 33 landmarks mapping)
    connections = [
        (11, 12),  # shoulder line
        (11, 23), (12, 24),  # shoulders to hips
        (23, 24),  # hips line
        (11, 13), (13, 15),  # left arm
        (12, 14), (14, 16),  # right arm
        (23, 25), (25, 27),  # left leg
        (24, 26), (26, 28)   # right leg
    ]

    # Start webcam capture (try indices 0, 1, 2, 3)
    cap = None
    active_idx = -1
    for camera_idx in [0, 1, 2, 3]:
        print(f"[INFO] Testing camera index {camera_idx}...")
        cap = cv2.VideoCapture(camera_idx)
        if cap.isOpened():
            success, test_frame = cap.read()
            if success:
                active_idx = camera_idx
                print(f"[SUCCESS] Camera opened successfully on index {camera_idx}!")
                break
            else:
                cap.release()
        else:
            if cap is not None:
                cap.release()
            cap = None

    if cap is None or not cap.isOpened():
        print("[ERROR] Could not open any webcam. Please verify camera connections & permissions.")
        return

    # Create window and bring to top
    window_name = "BLACK STONE Body Scan"
    cv2.namedWindow(window_name, cv2.WINDOW_NORMAL)
    try:
        cv2.setWindowProperty(window_name, cv2.WND_PROP_TOPMOST, 1.0)
    except Exception:
        pass


    # Stabilization buffers for readings
    history_len = 15
    shoulder_history = []
    chest_history = []
    waist_history = []
    hip_history = []
    arm_history = []
    inseam_history = []
    
    # Snapshot mode variables
    snapshot_mode = False
    snapshot_timer = 0
    saved_measurements = None

    with vision.PoseLandmarker.create_from_options(options) as landmarker:
        while cap.isOpened():
            success, frame = cap.read()
            if not success:
                print("Ignoring empty camera frame.")
                continue

            # Flip horizontally for natural mirror view
            frame = cv2.flip(frame, 1)
            h, w, c = frame.shape
            
            # Convert BGR to RGB for MediaPipe processing
            rgb_frame = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
            mp_image = mp.Image(image_format=mp.ImageFormat.SRGB, data=rgb_frame)
            
            # Detect Pose
            detection_result = landmarker.detect(mp_image)
            
            # Guide Overlay: Draw guide boxes to help align the user
            # Draw central box where user should align their body
            guide_w = int(w * 0.4)
            guide_h = int(h * 0.85)
            guide_x = (w - guide_w) // 2
            guide_y = (h - guide_h) // 2
            
            is_aligned = False
            status_text = "ALIGN YOUR BODY INSIDE GREEN BOX"
            status_color = COLOR_RED
            
            if detection_result.pose_landmarks and len(detection_result.pose_landmarks) > 0:
                landmarks = detection_result.pose_landmarks[0]
                
                # Key landmark points
                nose = landmarks[0]
                l_shoulder = landmarks[11]
                r_shoulder = landmarks[12]
                l_hip = landmarks[23]
                r_hip = landmarks[24]
                l_wrist = landmarks[15]
                r_wrist = landmarks[16]
                l_heel = landmarks[29]
                r_heel = landmarks[30]
                l_ankle = landmarks[27]
                r_ankle = landmarks[28]
                
                # Check alignment: Make sure shoulder joints and ankle joints are visible and inside frame
                l_shoulder_px = (int(l_shoulder.x * w), int(l_shoulder.y * h))
                r_shoulder_px = (int(r_shoulder.x * w), int(r_shoulder.y * h))
                
                # Is body within the guide box coordinates (visibility threshold 0.5)
                # Note: Tasks API landmarks have visibility attribute
                if (nose.visibility > 0.5 and l_heel.visibility > 0.5 and r_heel.visibility > 0.5):
                    # Check if ankles/nose are within the guide horizontal limits
                    nose_x_px = int(nose.x * w)
                    l_heel_x_px = int(l_heel.x * w)
                    r_heel_x_px = int(r_heel.x * w)
                    
                    if (guide_x < nose_x_px < guide_x + guide_w and 
                        guide_x < l_heel_x_px < guide_x + guide_w and 
                        guide_x < r_heel_x_px < guide_x + guide_w):
                        is_aligned = True
                        status_text = "BODY DETECTED: STAND STILL"
                        status_color = COLOR_GREEN

                # Draw skeleton connections manually using thin cyan lines
                for start_idx, end_idx in connections:
                    p_start = landmarks[start_idx]
                    p_end = landmarks[end_idx]
                    if p_start.visibility > 0.5 and p_end.visibility > 0.5:
                        cv2.line(frame, 
                                 (int(p_start.x * w), int(p_start.y * h)), 
                                 (int(p_end.x * w), int(p_end.y * h)), 
                                 (230, 230, 0), 1, cv2.LINE_AA)

                # Draw landmark points
                for lnd in landmarks:
                    if lnd.visibility > 0.5:
                        cv2.circle(frame, (int(lnd.x * w), int(lnd.y * h)), 3, COLOR_WHITE, -1)

                # Calculation of Height in pixels
                # Feet position (average of left and right heels/ankles)
                feet_y_px = (l_heel.y + r_heel.y) / 2 * h
                # Top of head approximation (offsetting above nose by the shoulder-nose offset)
                shoulder_mid_y = (l_shoulder.y + r_shoulder.y) / 2
                nose_shoulder_dist = abs(shoulder_mid_y - nose.y)
                head_crown_y_px = (nose.y - (nose_shoulder_dist * 0.6)) * h
                
                height_px = feet_y_px - head_crown_y_px
                
                if height_px > 50: # Avoid division by zero/extreme jumps
                    # Pixel-to-CM Conversion Ratio
                    px_to_cm = user_height_cm / height_px
                    
                    # --- Shoulder Width ---
                    # Skeletal shoulder distance
                    shoulder_width_px = calculate_distance_2d(l_shoulder, r_shoulder, w, h)
                    shoulder_width_cm = shoulder_width_px * px_to_cm * 1.08  # Scaling factor to account for bone-to-outer shoulder skin width
                    
                    # --- Chest Circumference ---
                    # Chest line is estimated below shoulders (approx. 1/3 distance between shoulder mid-line and hip mid-line)
                    hip_mid_y = (l_hip.y + r_hip.y) / 2
                    chest_y = shoulder_mid_y + (hip_mid_y - shoulder_mid_y) * 0.33
                    # Estimate flat front width (using chest level and pose chest width approximation)
                    chest_width_px = shoulder_width_px * 0.95
                    chest_circ_cm = (chest_width_px * px_to_cm) * 2.3  # Standard conversion factor for flat width to full circumference
                    
                    # --- Waist Circumference ---
                    # Waist level is estimated at approx. 2/3 distance from shoulders to hips
                    waist_y = shoulder_mid_y + (hip_mid_y - shoulder_mid_y) * 0.72
                    # In standard pose, waist width is slightly narrower than shoulders/hips
                    waist_width_px = calculate_distance_2d(l_hip, r_hip, w, h) * 0.9
                    waist_circ_cm = (waist_width_px * px_to_cm) * 2.3
                    
                    # --- Hip Circumference ---
                    hip_width_px = calculate_distance_2d(l_hip, r_hip, w, h)
                    hip_circ_cm = (hip_width_px * px_to_cm) * 2.4
                    
                    # --- Sleeve Length (Shoulder to wrist through elbow) ---
                    # Left Arm (Elbow is landmark 13)
                    l_sleeve_px = (calculate_distance_2d(l_shoulder, landmarks[13], w, h) + 
                                   calculate_distance_2d(landmarks[13], l_wrist, w, h))
                    # Right Arm (Elbow is landmark 14)
                    r_sleeve_px = (calculate_distance_2d(r_shoulder, landmarks[14], w, h) + 
                                   calculate_distance_2d(landmarks[14], r_wrist, w, h))
                    sleeve_length_cm = ((l_sleeve_px + r_sleeve_px) / 2) * px_to_cm
                    
                    # --- Leg Inseam (Crotch/Hip to Ankle) ---
                    # Left Leg (Knee is 25, Ankle is 27)
                    l_leg_px = (calculate_distance_2d(l_hip, landmarks[25], w, h) + 
                                 calculate_distance_2d(landmarks[25], l_ankle, w, h))
                    # Right Leg (Knee is 26, Ankle is 28)
                    r_leg_px = (calculate_distance_2d(r_hip, landmarks[26], w, h) + 
                                 calculate_distance_2d(landmarks[26], r_ankle, w, h))
                    inseam_length_cm = (((l_leg_px + r_leg_px) / 2) * px_to_cm) * 0.82  # Inseam is shorter than total leg length (crotch to floor)

                    # Stabilize values using rolling average
                    if is_aligned and not snapshot_mode:
                        shoulder_history.append(shoulder_width_cm)
                        chest_history.append(chest_circ_cm)
                        waist_history.append(waist_circ_cm)
                        hip_history.append(hip_circ_cm)
                        arm_history.append(sleeve_length_cm)
                        inseam_history.append(inseam_length_cm)
                        
                        if len(shoulder_history) > history_len:
                            shoulder_history.pop(0)
                            chest_history.pop(0)
                            waist_history.pop(0)
                            hip_history.pop(0)
                            arm_history.pop(0)
                            inseam_history.pop(0)

                    # Get active readings (either smoothed or final snapshot)
                    if not snapshot_mode and len(shoulder_history) > 0:
                        curr_shoulder = np.mean(shoulder_history)
                        curr_chest = np.mean(chest_history)
                        curr_waist = np.mean(waist_history)
                        curr_hip = np.mean(hip_history)
                        curr_arm = np.mean(arm_history)
                        curr_inseam = np.mean(inseam_history)
                    elif snapshot_mode and saved_measurements:
                        curr_shoulder = saved_measurements["shoulder_width"]
                        curr_chest = saved_measurements["chest_circ"]
                        curr_waist = saved_measurements["waist_circ"]
                        curr_hip = saved_measurements["hip_circ"]
                        curr_arm = saved_measurements["sleeve_length"]
                        curr_inseam = saved_measurements["inseam"]
                    else:
                        curr_shoulder = shoulder_width_cm
                        curr_chest = chest_circ_cm
                        curr_waist = waist_circ_cm
                        curr_hip = hip_circ_cm
                        curr_arm = sleeve_length_cm
                        curr_inseam = inseam_length_cm

                    # Fetch sizing mappings
                    shirt_sz, pant_sz = get_clothing_sizes(curr_shoulder, curr_chest, curr_waist, curr_hip)
                    
                    # --- VISUAL HUD RENDER ---
                    # Draw chest level tracking line
                    c_y_px = int(chest_y * h)
                    cv2.line(frame, (guide_x, c_y_px), (guide_x + guide_w, c_y_px), (255, 120, 0), 1, cv2.LINE_AA)
                    cv2.putText(frame, "CHEST", (guide_x + 5, c_y_px - 4), cv2.FONT_HERSHEY_SIMPLEX, 0.35, (255, 120, 0), 1, cv2.LINE_AA)
                    
                    # Draw waist level tracking line
                    w_y_px = int(waist_y * h)
                    cv2.line(frame, (guide_x, w_y_px), (guide_x + guide_w, w_y_px), (0, 165, 255), 1, cv2.LINE_AA)
                    cv2.putText(frame, "WAIST", (guide_x + 5, w_y_px - 4), cv2.FONT_HERSHEY_SIMPLEX, 0.35, (0, 165, 255), 1, cv2.LINE_AA)
                    
                    # Draw hip level tracking line
                    h_y_px = int(hip_mid_y * h)
                    cv2.line(frame, (guide_x, h_y_px), (guide_x + guide_w, h_y_px), (0, 100, 255), 1, cv2.LINE_AA)
                    cv2.putText(frame, "HIP", (guide_x + 5, h_y_px - 4), cv2.FONT_HERSHEY_SIMPLEX, 0.35, (0, 100, 255), 1, cv2.LINE_AA)
                    
                    # Render Measurement Info Boxes (HUD Left Column)
                    col1_x = 20
                    draw_styled_box(frame, col1_x, 80, 180, 55, "SHOULDER WIDTH", f"{curr_shoulder:.1f}", "cm")
                    draw_styled_box(frame, col1_x, 145, 180, 55, "CHEST CIRC.", f"{curr_chest:.1f}", "cm")
                    draw_styled_box(frame, col1_x, 210, 180, 55, "WAIST CIRC.", f"{curr_waist:.1f}", "cm")
                    draw_styled_box(frame, col1_x, 275, 180, 55, "HIP CIRC.", f"{curr_hip:.1f}", "cm")
                    
                    # HUD Right Column
                    col2_x = w - 200
                    draw_styled_box(frame, col2_x, 80, 180, 55, "SLEEVE LENGTH", f"{curr_arm:.1f}", "cm")
                    draw_styled_box(frame, col2_x, 145, 180, 55, "LEG INSEAM", f"{curr_inseam:.1f}", "cm")
                    
                    # recommended clothing sizes (Highlight box)
                    draw_styled_box(frame, col2_x, 210, 180, 55, "SHIRT SIZE", shirt_sz, "", bg_color=(40, 20, 20), text_color=COLOR_GOLD)
                    draw_styled_box(frame, col2_x, 275, 180, 55, "PANTS SIZE", pant_sz, "", bg_color=(40, 20, 20), text_color=COLOR_GOLD)
                    
                    # Trigger snapshot after 3 seconds of solid alignment
                    if is_aligned and not snapshot_mode:
                        if snapshot_timer == 0:
                            snapshot_timer = time.time()
                        elif time.time() - snapshot_timer > 3.0:
                            snapshot_mode = True
                            saved_measurements = {
                                "height": user_height_cm,
                                "shoulder_width": curr_shoulder,
                                "chest_circ": curr_chest,
                                "waist_circ": curr_waist,
                                "hip_circ": curr_hip,
                                "sleeve_length": curr_arm,
                                "inseam": curr_inseam,
                                "shirt_size": shirt_sz,
                                "pants_size": pant_sz
                            }
                            print("\n[SUCCESS] Body scan locked! Measurements are frozen.")
                            print("Saved measurements structure:")
                            print(json.dumps(saved_measurements, indent=4))
                    else:
                        if not snapshot_mode:
                            snapshot_timer = 0
                else:
                    snapshot_timer = 0

            # Draw Guide Box on Frame
            cv2.rectangle(frame, (guide_x, guide_y), (guide_x + guide_w, guide_y + guide_h), status_color, 2)
            
            # Display header brand bar
            brand_overlay = frame.copy()
            cv2.rectangle(brand_overlay, (0, 0), (w, 60), COLOR_DARK, -1)
            cv2.addWeighted(brand_overlay, 0.9, frame, 0.1, 0, frame)
            cv2.putText(frame, "BLACK STONE // BODY MEASUREMENT HUB", (20, 36), cv2.FONT_HERSHEY_DUPLEX, 0.6, COLOR_GOLD, 1, cv2.LINE_AA)
            
            # Display status banner at the bottom
            status_overlay = frame.copy()
            cv2.rectangle(status_overlay, (0, h - 50), (w, h), COLOR_DARK, -1)
            cv2.addWeighted(status_overlay, 0.9, frame, 0.1, 0, frame)
            
            if snapshot_mode:
                cv2.putText(frame, "SCAN LOCK ACTIVE (Press 'c' to Release & Re-scan)", (20, h - 20), cv2.FONT_HERSHEY_SIMPLEX, 0.5, COLOR_GOLD, 1, cv2.LINE_AA)
            else:
                cv2.putText(frame, status_text, (20, h - 20), cv2.FONT_HERSHEY_SIMPLEX, 0.5, status_color, 1, cv2.LINE_AA)
                
            # Draw countdown indicator if aligning
            if is_aligned and not snapshot_mode and snapshot_timer > 0:
                elapsed = time.time() - snapshot_timer
                remaining = max(0, 3.0 - elapsed)
                cv2.putText(frame, f"Locking in {remaining:.1f}s...", (w - 200, h - 20), cv2.FONT_HERSHEY_SIMPLEX, 0.5, COLOR_GREEN, 1, cv2.LINE_AA)

            # Show frame
            cv2.imshow("BLACK STONE Body Scan", frame)
            
            # Key bindings
            key = cv2.waitKey(1) & 0xFF
            
            # 'q' to Quit
            if key == ord('q'):
                break
                
            # 'c' to Clear/Recalibrate
            elif key == ord('c'):
                snapshot_mode = False
                snapshot_timer = 0
                saved_measurements = None
                shoulder_history.clear()
                chest_history.clear()
                waist_history.clear()
                hip_history.clear()
                arm_history.clear()
                inseam_history.clear()
                print("\n[INFO] Scan reset. Standing by for calibration...")
                
            # 's' to Save to JSON
            elif key == ord('s'):
                if saved_measurements:
                    output_path = os.path.join(os.path.dirname(__file__), "measurements.json")
                    with open(output_path, "w") as f:
                        json.dump(saved_measurements, f, indent=4)
                    print(f"\n[INFO] Measurements successfully saved to: {output_path}")
                else:
                    print("\n[WARNING] Cannot save: Scan is not locked yet. Align yourself to freeze values first.")

    # Cleanup
    cap.release()
    cv2.destroyAllWindows()
    print("\n[INFO] Camera released. Program exited successfully.")

if __name__ == "__main__":
    main()
