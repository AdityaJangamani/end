<?php
/**
 * PHP Client for Flask API — Re-integrated to make this a strict AIML project.
 * This forwards the employee data to the Python/Flask ML server running on port 5000.
 */

function call_flask_api($endpoint, $data) {
    $url = "http://127.0.0.1:5000" . $endpoint;
    $ch = curl_init($url);
    $payload = json_encode($data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);
    // Set a short timeout so the page doesn't hang forever if the ML server is off
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
    
    $result = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpcode >= 200 && $httpcode < 300 && $result) {
        return json_decode($result, true);
    }
    
    // Return null if the Flask server is down
    return null;
}

function predict_salary($data) {
    $res = call_flask_api('/predict_salary', $data);
    if ($res && isset($res['predicted_salary'])) {
        return ['predicted_salary' => $res['predicted_salary']];
    }
    return ['predicted_salary' => 0]; // fallback
}

function predict_attrition($data) {
    $res = call_flask_api('/predict_attrition', $data);
    if ($res && isset($res['attrition_risk'])) {
        return ['attrition_risk' => $res['attrition_risk']];
    }
    return ['attrition_risk' => 0];
}

function predict_promotion($data) {
    $res = call_flask_api('/predict_promotion', $data);
    if ($res && isset($res['promotion_probability'])) {
        return ['promotion_probability' => $res['promotion_probability']];
    }
    return ['promotion_probability' => 0];
}

function predict_category($data) {
    $res = call_flask_api('/predict_intelligent', $data);
    if ($res && isset($res['category'])) {
        return ['category' => $res['category']];
    }
    return ['category' => 'Unknown'];
}

function analyze_productivity($data) {
    $res = call_flask_api('/analyze_productivity', $data);
    if ($res && isset($res['productivity_score'])) {
        return ['productivity_score' => $res['productivity_score']];
    }
    return ['productivity_score' => 0];
}
?>
