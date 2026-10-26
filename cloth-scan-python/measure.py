import numpy as np
import mediapipe as mp

def get_distance(point1, point2, image_width, image_height):
    x1, y1 = int(point1.x * image_width), int(point1.y * image_height)
    x2, y2 = int(point2.x * image_width), int(point2.y * image_height)
    return np.linalg.norm([x2 - x1, y2 - y1])

def extract_measurements(landmarks, frame_shape):
    image_height, image_width, _ = frame_shape

    shoulder_width = get_distance(
        landmarks[mp.solutions.pose.PoseLandmark.LEFT_SHOULDER],
        landmarks[mp.solutions.pose.PoseLandmark.RIGHT_SHOULDER],
        image_width, image_height
    )

    hip_width = get_distance(
        landmarks[mp.solutions.pose.PoseLandmark.LEFT_HIP],
        landmarks[mp.solutions.pose.PoseLandmark.RIGHT_HIP],
        image_width, image_height
    )

    return {
        "shoulder": shoulder_width,
        "hip": hip_width
    }
