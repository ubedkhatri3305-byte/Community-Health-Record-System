<?php
/**
 * notify.php — shared notification helper
 * Include this file, then call: notify($mysqli, $user_id, $message)
 *
 * Sends:
 *  1. In-app notification (notifications table)
 *  2. Email  — configure SMTP section below when ready
 *  3. SMS    — configure Twilio section below when ready
 */

/* =====================================================================
   IN-APP NOTIFICATION
   ===================================================================== */
function notify($mysqli, $user_id, $message) {

    /* 1. Insert into notifications table */
    $stmt = $mysqli->prepare(
        "INSERT INTO notifications (user_id, message) VALUES (?, ?)"
    );
    $stmt->bind_param("is", $user_id, $message);
    $stmt->execute();

    /* 2. Fetch user email + phone for external channels */
    $u = $mysqli->prepare("SELECT email, phone FROM users WHERE id=? LIMIT 1");
    $u->bind_param("i", $user_id);
    $u->execute();
    $user = $u->get_result()->fetch_assoc();

    if ($user) {
        notify_email($user['email'], "CHR Notification", $message);
        notify_sms($user['phone'], $message);
    }
}

/* =====================================================================
   EMAIL — configure your SMTP details here
   Set $EMAIL_ENABLED = true and fill in credentials to activate.
   ===================================================================== */
function notify_email($to, $subject, $body) {

    $EMAIL_ENABLED = false;          // ← set to true when you add credentials

    /* --- Gmail SMTP example (fill these in) --- */
    $SMTP_HOST = 'smtp.gmail.com';
    $SMTP_PORT = 587;
    $SMTP_USER = 'your_gmail@gmail.com';   // ← your Gmail address
    $SMTP_PASS = 'your_app_password';       // ← Gmail App Password
    $FROM_NAME = 'CHR Health System';

    if (!$EMAIL_ENABLED || empty($to)) return;

    /* Simple socket-based SMTP sender (no library needed) */
    try {
        $socket = fsockopen($SMTP_HOST, $SMTP_PORT, $errno, $errstr, 10);
        if (!$socket) return;

        $read = function() use ($socket) {
            $data = '';
            while ($str = fgets($socket, 515)) {
                $data .= $str;
                if (substr($str, 3, 1) === ' ') break;
            }
            return $data;
        };

        $send = function($cmd) use ($socket) {
            fwrite($socket, $cmd . "\r\n");
        };

        $read(); // 220 banner
        $send("EHLO localhost"); $read();
        $send("STARTTLS");       $read();
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $send("EHLO localhost"); $read();
        $send("AUTH LOGIN");     $read();
        $send(base64_encode($SMTP_USER)); $read();
        $send(base64_encode($SMTP_PASS)); $read();
        $send("MAIL FROM:<{$SMTP_USER}>"); $read();
        $send("RCPT TO:<{$to}>"); $read();
        $send("DATA"); $read();

        $headers  = "From: {$FROM_NAME} <{$SMTP_USER}>\r\n";
        $headers .= "To: {$to}\r\n";
        $headers .= "Subject: {$subject}\r\n";
        $headers .= "Content-Type: text/plain; charset=utf-8\r\n\r\n";
        $send($headers . $body . "\r\n.");
        $read();
        $send("QUIT");
        fclose($socket);
    } catch (Exception $e) {
        // silently fail — in-app notification already saved
    }
}

/* =====================================================================
   SMS via Twilio — configure your credentials here
   Set $SMS_ENABLED = true and fill in credentials to activate.
   ===================================================================== */
function notify_sms($phone, $message) {

    $SMS_ENABLED   = false;              // ← set to true when you add credentials

    $ACCOUNT_SID   = 'ACxxxxxxxxxxxxxxxx';   // ← Twilio Account SID
    $AUTH_TOKEN    = 'your_auth_token';       // ← Twilio Auth Token
    $FROM_NUMBER   = '+1234567890';           // ← Twilio phone number

    if (!$SMS_ENABLED || empty($phone)) return;

    /* Clean phone: ensure it starts with + */
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (substr($phone, 0, 1) !== '+') $phone = '+91' . $phone; // default India

    $url  = "https://api.twilio.com/2010-04-01/Accounts/{$ACCOUNT_SID}/Messages.json";
    $data = http_build_query([
        'To'   => $phone,
        'From' => $FROM_NUMBER,
        'Body' => $message,
    ]);

    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Authorization: Basic " . base64_encode("{$ACCOUNT_SID}:{$AUTH_TOKEN}") . "\r\n"
                       . "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $data,
            'ignore_errors' => true,
        ]
    ]);

    @file_get_contents($url, false, $ctx); // fire and forget
}
