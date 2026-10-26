import cv2
from measurement.pose_detector import PoseDetector
from measurement.measurement_utils import extract_measurements

def main():
    cap = cv2.VideoCapture(0)
    pose = PoseDetector()

    while cap.isOpened():
        ret, frame = cap.read()
        if not ret:
            break

        landmarks = pose.detect(frame)
        if landmarks:
            measurements = extract_measurements(landmarks, frame.shape)
            print("Shoulder width:", measurements['shoulder'])
            print("Hip width:", measurements['hip'])

        cv2.imshow("Virtual Fitting Room", frame)
        if cv2.waitKey(1) & 0xFF == ord('q'):
            break

    cap.release()
    cv2.destroyAllWindows()

if __name__ == "__main__":
    main()
