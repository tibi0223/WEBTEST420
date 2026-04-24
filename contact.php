<?php
/**
 * Nexus Klíma - Contact Form Backend
 * Updated to handle Lead Qualifier fields
 */

// Include config for secrets
require_once 'config.php';

// Start session for CSRF and rate limiting
session_start();

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("X-XSS-Protection: 1; mode=block");
header('Content-Type: application/json');

// Helper to send JSON response
function sendResponse($status, $message = '', $type = '', $fields = []) {
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'type' => $type,
        'fields' => $fields
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function cleanInput($value) {
    return htmlspecialchars(strip_tags(trim((string)$value)), ENT_QUOTES, 'UTF-8');
}

function cleanHeaderValue($value) {
    return preg_replace('/[\r\n]+/', ' ', trim((string)$value));
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting: max 10 submissions per IP per hour for the multi-step form.
$ip_address = $_SERVER['REMOTE_ADDR'];
$rate_limit_key = 'rate_limit_' . md5($ip_address);

if (!isset($_SESSION[$rate_limit_key])) {
    $_SESSION[$rate_limit_key] = ['count' => 0, 'time' => time()];
}

// Reset rate limit after 1 hour
if (time() - $_SESSION[$rate_limit_key]['time'] > 3600) {
    $_SESSION[$rate_limit_key] = ['count' => 0, 'time' => time()];
}

// Check rate limit
if ($_SESSION[$rate_limit_key]['count'] >= 10) {
    sendResponse('error', 'Túl sok kísérlet. Kérjük próbálja meg később.', 'ratelimit');
}

// Only process POST requests
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    sendResponse('error', 'Érvénytelen kérés.');
}

// Honeypot check (hidden field)
if (!empty($_POST['website'])) {
    sendResponse('success', 'Köszönjük! Üzenetét megkaptuk.');
}

// Validate CSRF token when the rendered page provides one.
if (!empty($_POST['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    sendResponse('error', 'Biztonsági ellenőrzés sikertelen. Kérjük frissítse az oldalt.', 'csrf');
}

// Collect and sanitize input
$name        = cleanInput($_POST["name"] ?? "");
$email       = filter_var(trim($_POST["email"] ?? ""), FILTER_SANITIZE_EMAIL);
$phone       = cleanInput($_POST["phone"] ?? "");
$postcode    = cleanInput($_POST["postcode"] ?? "");
$service     = cleanInput($_POST["service"] ?? "");
$property    = cleanInput($_POST["property"] ?? "");
$size        = cleanInput($_POST["size"] ?? "");
$goal        = cleanInput($_POST["goal"] ?? "");
$price_range = cleanInput($_POST["price_range"] ?? "");
$message     = cleanInput($_POST["message"] ?? "");

// Validation
$errors = [];
if (empty($name) || strlen($name) > 240) $errors[] = "name";
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "email";
if (empty($phone) || strlen(preg_replace('/[^\d+]/', '', $phone)) < 8) $errors[] = "phone";
if (!empty($postcode) && !preg_match('/^\d{4}$/', $postcode)) $errors[] = "postcode";

if (!empty($errors)) {
    sendResponse('error', 'Kérjük töltsön ki minden kötelező mezőt.', 'validation', $errors);
}

// Increment rate limit counter
$_SESSION[$rate_limit_key]['count']++;

// Email configuration
$recipient = "info@nexusklima.hu";
$subject   = "=?UTF-8?B?" . base64_encode("ÚJ ÁRAJÁNLAT KÉRÉS (Kalkulátor) - " . cleanHeaderValue($name)) . "?=";

// Prepare email body
$email_body = "Új részletes érdeklődés érkezett a kalkulátorból:\n\n";
$email_body .= "--- ÜGYFÉL ADATOK ---\n";
$email_body .= "Név: " . $name . "\n";
$email_body .= "Email: " . $email . "\n";
$email_body .= "Telefonszám: " . $phone . "\n";
$email_body .= "Irányítószám: " . $postcode . "\n\n";

$email_body .= "--- IGÉNYEK ---\n";
$email_body .= "Szolgáltatás: " . $service . "\n";
$email_body .= "Ingatlan típusa: " . $property . "\n";
$email_body .= "Alapterület: " . $size . "\n";
$email_body .= "Fő cél: " . $goal . "\n";
$email_body .= "Becsült árkalkuláció: " . $price_range . "\n\n";

if ($message) {
    $email_body .= "Üzenet:\n" . $message . "\n\n";
}

$email_body .= "--- RENDSZERADATOK ---\n";
$email_body .= "IP cím: " . $ip_address . "\n";
$email_body .= "Időpont: " . date('Y-m-d H:i:s') . "\n";
$email_body .= "--\nEz az üzenet automatikusan generálódott a nexus-klima.hu weboldalon.";

// Headers
$boundary = md5(time());
$headers = "MIME-Version: 1.0\r\n";
$headers .= "From: Nexus Klíma <web@nexusklima.hu>\r\n";
$headers .= "Reply-To: " . cleanHeaderValue($name) . " <" . cleanHeaderValue($email) . ">\r\n";

// Handle attachment if photo exists
if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE && $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    sendResponse('error', 'Hiba történt a fotó feltöltése közben. Kérjük próbálja újra.', 'upload');
}

if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
    $max_file_size = 10 * 1024 * 1024;
    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif'
    ];

    if ($_FILES['photo']['size'] > $max_file_size) {
        sendResponse('error', 'A feltöltött fotó legfeljebb 10 MB lehet.', 'upload');
    }

    $detected_type = null;
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected_type = $finfo->file($_FILES['photo']['tmp_name']);
    } elseif (function_exists('mime_content_type')) {
        $detected_type = mime_content_type($_FILES['photo']['tmp_name']);
    }
    if (!isset($allowed_types[$detected_type])) {
        sendResponse('error', 'Csak JPG, PNG, WEBP vagy GIF kép tölthető fel.', 'upload');
    }

    $headers .= "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"\r\n";
    
    $original_name = pathinfo($_FILES['photo']['name'], PATHINFO_FILENAME);
    $safe_name = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $original_name);
    $safe_name = trim($safe_name, '-') ?: 'helyszini-foto';
    $file_name = $safe_name . '.' . $allowed_types[$detected_type];
    $file_type = $detected_type;
    $file_tmp  = $_FILES['photo']['tmp_name'];
    
    $content = file_get_contents($file_tmp);
    $content = chunk_split(base64_encode($content));
    
    // Message body
    $body = "--" . $boundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $email_body . "\r\n";
    
    // Attachment
    $body .= "--" . $boundary . "\r\n";
    $body .= "Content-Type: " . $file_type . "; name=\"" . $file_name . "\"\r\n";
    $body .= "Content-Disposition: attachment; filename=\"" . $file_name . "\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= $content . "\r\n";
    $body .= "--" . $boundary . "--";
} else {
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body = $email_body;
}

// Send email
if (mail($recipient, $subject, $body, $headers)) {
    
    // HubSpot Integration
    if (defined('HUBSPOT_TOKEN')) {
        $hubspot_token = HUBSPOT_TOKEN;
        $hubspot_url = "https://api.hubapi.com/crm/v3/objects/contacts";
        
        $hubspot_data = [
            "properties" => [
                "email" => $email,
                "firstname" => $name,
                "phone" => $phone,
                "zip" => $postcode,
                "szolgaltatas_tipusa" => $service,
                "ingatlan_tipusa" => $property,
                "alapterulet" => $size,
                "klima_celja" => $goal,
                "becsult_ar" => $price_range,
                "lifecyclestage" => "lead"
            ]
        ];
        
        $ch = curl_init($hubspot_url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($hubspot_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $hubspot_token,
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
        curl_exec($ch);
        curl_close($ch);
    }

    sendResponse('success', 'Köszönjük! Az ajánlatkérést sikeresen rögzítettük.');
} else {
    sendResponse('error', 'Hiba történt az email küldése során.', 'mail');
}

