<?php
/**
 * Nexus Klíma - Contact Form Backend
 * Enhanced with CSRF protection, rate limiting, and security features
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

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting: Max 3 submissions per IP per hour
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
if ($_SESSION[$rate_limit_key]['count'] >= 3) {
    header("Location: index.html?status=ratelimit#contact");
    exit;
}

// Only process POST requests
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: index.html");
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: index.php?status=error&type=csrf#contact");
    exit;
}

// Honeypot check (hidden field)
if (!empty($_POST['website'])) {
    // Bot detected, but redirect silently to avoid revealing it's a honeypot
    header("Location: index.php?status=success#contact");
    exit;
}

// Collect and sanitize input
$name    = htmlspecialchars(strip_tags(trim($_POST["name"])), ENT_QUOTES, 'UTF-8');
$email   = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
$phone   = htmlspecialchars(strip_tags(trim($_POST["phone"] ?? "")), ENT_QUOTES, 'UTF-8');
$service = isset($_POST["service"]) ? htmlspecialchars(strip_tags(trim($_POST["service"])), ENT_QUOTES, 'UTF-8') : "Nincs megadva";
$message = htmlspecialchars(strip_tags(trim($_POST["message"] ?? "")), ENT_QUOTES, 'UTF-8');

// Enhanced validation
$errors = [];

if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
    $errors[] = "name";
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    $errors[] = "email";
}

// Phone validation (Hungarian format)
if (!empty($phone)) {
    $phone_clean = preg_replace('/[^0-9+]/', '', $phone);
    if (strlen($phone_clean) < 9 || strlen($phone_clean) > 15) {
        $errors[] = "phone";
    }
}

if (!empty($errors)) {
    header("Location: index.php?status=error&fields=" . implode(',', $errors) . "#contact");
    exit;
}

// Increment rate limit counter
$_SESSION[$rate_limit_key]['count']++;

// Email configuration
$recipient = "info@nexusklima.hu";
$subject   = "=?UTF-8?B?" . base64_encode("Webes árajánlat kérés - " . $name) . "?=";

// Prepare email body
$email_body = "Új érdeklődés érkezett a weboldalról:\n\n";
$email_body .= "Név: " . $name . "\n";
$email_body .= "Email: " . $email . "\n";
$email_body .= "Telefonszám: " . ($phone ? $phone : "Nincs megadva") . "\n";
$email_body .= "Szolgáltatás: " . $service . "\n\n";
$email_body .= "Üzenet:\n" . ($message ? $message : "Nincs üzenet") . "\n\n";
$email_body .= "---\n";
$email_body .= "IP cím: " . $ip_address . "\n";
$email_body .= "Időpont: " . date('Y-m-d H:i:s') . "\n";
$email_body .= "User-Agent: " . substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 200) . "\n";
$email_body .= "--\nEz az üzenet automatikusan generálódott a nexus-klima.hu weboldalon.";

// Headers with proper encoding
$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "From: Nexus Klíma <web@nexusklima.hu>\r\n";
$headers .= "Reply-To: " . $name . " <" . $email . ">\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

// Send email
if (mail($recipient, $subject, $email_body, $headers)) {
    
    // --- HUBSPOT INTEGRATION ---
    if (defined('HUBSPOT_TOKEN')) {
        $hubspot_token = HUBSPOT_TOKEN;
        $hubspot_url = "https://api.hubapi.com/crm/v3/objects/contacts";
        
        // Map form service values to HubSpot dropdown internal values/labels
        $service_raw = $_POST["service"] ?? "";
        $hs_service_mapping = [
            'klimatelepites' => 'Telepítés',
            'eves_karbantartas' => 'Éves karbantartás',
            'mely_tisztitas' => 'Mély tisztítás',
            'egyeb' => 'Egyéb'
        ];
        $hs_service_value = $hs_service_mapping[$service_raw] ?? 'Egyéb';
        
        $hubspot_data = [
            "properties" => [
                "email" => $email,
                "firstname" => $name,
                "phone" => $phone,
                "szolgaltatas_tipusa" => $hs_service_value,
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
        // Timeout beállítása, hogy ne akassza meg a formot ha lassú a Hubspot
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
        
        $response = curl_exec($ch);
        curl_close($ch);
    }
    // ---------------------------

    // Regenerate CSRF token after successful submission
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header("Location: index.php?status=success#contact");
} else {
    header("Location: index.php?status=error&type=mail#contact");
}
exit;
?>
