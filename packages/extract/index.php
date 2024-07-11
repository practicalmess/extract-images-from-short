<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Define the log file path
$log_file = './webhook-log.txt'; // Adjust path as needed

// Function to log messages
function log_message($message, $log_file) {
    $log_message = date('Y-m-d H:i:s') . " - " . $message . "\n";
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

// Log the script execution start
log_message("Script execution started.", $log_file);

// Retrieve the webhook payload
$payload = file_get_contents('php://input');

// Log payload retrieval
log_message("Payload retrieval attempt.", $log_file);

// Check if payload is empty or malformed
if (empty($payload)) {
    log_message("Invalid payload: empty payload.", $log_file);
    http_response_code(400);
    die('Invalid payload.');
}

// Decode JSON payload
$data = json_decode($payload, true);

// Log JSON decoding attempt
log_message("JSON decoding attempt.", $log_file);

// Check if JSON decoding failed
if (json_last_error() !== JSON_ERROR_NONE) {
    log_message("Failed to decode JSON payload: " . json_last_error_msg(), $log_file);
    http_response_code(400);
    die('Failed to decode JSON payload.');
}

// Log the incoming data
log_message("Webhook payload: " . print_r($data, true), $log_file);

// Check if the webhook is from WooCommerce (basic check)
if (isset($data['id']) && isset($data['date_created']) && isset($data['status'])) {
    // Log specific WooCommerce webhook data
    log_message("WooCommerce webhook triggered: " . print_r($data, true), $log_file);
    // WooCommerce REST API credentials from environment variables
    $consumer_key = getenv('WOOCOMMERCE_CONSUMER_KEY');
    $consumer_secret = getenv('WOOCOMMERCE_CONSUMER_SECRET');

    if (!$consumer_key || !$consumer_secret) {
        log_message("API keys are not set in environment variables.", $log_file);
        http_response_code(500);
        die('Internal Server Error: API keys are not set.');
    }

    // Regular expression pattern to match <img> tag and extract src attribute
    $pattern = '/<img[^>]+src=["\']([^"\']+)["\']/';

    // Perform the regular expression match
    if (preg_match($pattern, $data['short_description'], $matches)) {
        $src = $matches[1]; // Extracted src attribute value
        echo "Image Source: $src\n";
    } else {
        echo "No <img> tag found or src attribute not found\n";
    }

    // WooCommerce REST API URL
    $woocommerce_url = 'https://your-woocommerce-site.com/wp-json/wc/v3/products/';

    // Prepare the data to update the product
    $product_id = $data['id']; // Assuming the product ID is included in the webhook data
    $update_data = [
        'images' => [
            [
                'src' => $src,
                'position' => 0
            ]
        ]
    ];

    // Remove null values
    $update_data = array_filter($update_data, function($value) {
        return !is_null($value);
    });

    // Convert update data to JSON
    $update_data_json = json_encode($update_data);

    // Initialize cURL
    $ch = curl_init();

    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $woocommerce_url . $product_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $update_data_json);
    curl_setopt($ch, CURLOPT_USERPWD, $consumer_key . ':' . $consumer_secret);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($update_data_json)
    ]);

    // Execute cURL request
    $response = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        log_message("cURL error: " . curl_error($ch), $log_file);
    } else {
        // Log response
        log_message("Product updated successfully: " . $response, $log_file);
    }

    // Close cURL handle
    curl_close($ch);

    // Perform additional actions based on the webhook data
    // Example: Send notifications, update external systems, etc.
} else {
    // Log a message indicating this webhook is not from WooCommerce
    log_message("Non-WooCommerce webhook received.", $log_file);
}

$api_endpoint = '/wp-json/wc/v3/products/' . $data['id'];

$request = new WP_REST_Request('PUT', $api_endpoint);

// Log the script execution end
log_message("Script execution ended.", $log_file);

// Respond with a success message
http_response_code(200);
echo 'Webhook received successfully.';
?>
