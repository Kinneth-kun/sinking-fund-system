<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendVerificationEmail(string $toEmail, string $toName, string $token): bool {
    $mail = new PHPMailer(true);
    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        // Content
        $verifyUrl = APP_URL . '/verify.php?token=' . urlencode($token);
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your Email - ' . APP_NAME;
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #1a1a2e, #16213e); padding: 30px; text-align: center;'>
                <h1 style='color: #e2c07d; margin: 0; font-size: 24px;'>💰 " . APP_NAME . "</h1>
            </div>
            <div style='background: #f9f9f9; padding: 30px;'>
                <h2 style='color: #333;'>Hello, {$toName}!</h2>
                <p style='color: #666;'>Please verify your email address to access the Sinking Funds System.</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$verifyUrl}' style='background: linear-gradient(135deg, #e2c07d, #c9a227); color: #1a1a2e; padding: 14px 35px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px;'>
                        ✉️ Verify Email Address
                    </a>
                </div>
                <p style='color: #999; font-size: 12px;'>This link expires in 24 hours. If you didn't request this, ignore this email.</p>
            </div>
        </div>";
        $mail->AltBody = "Verify your email: {$verifyUrl}";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}

function sendOtpEmail(string $toEmail, string $toName, string $code): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPDebug  = 0; // Set to 2 temporarily if you need to debug SMTP

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName); // ✅ Send to the user's Gmail

        $mail->isHTML(true);
        $mail->Subject = 'Your Login Code: ' . $code . ' — ' . APP_NAME;
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 560px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #1a1a2e, #16213e); padding: 28px; text-align: center; border-radius: 12px 12px 0 0;'>
                <h1 style='color: #e2c07d; margin: 0; font-size: 22px;'>&#128176; " . APP_NAME . "</h1>
                <p style='color: #8888aa; margin: 6px 0 0; font-size: 13px;'>Login Verification Code</p>
            </div>
            <div style='background: #f9f9f9; padding: 32px; border-radius: 0 0 12px 12px;'>
                <p style='color: #555; font-size: 14px; margin-bottom: 8px;'>Hello, <strong>{$toName}</strong>!</p>
                <p style='color: #555; font-size: 14px; margin-bottom: 24px;'>Use this code to complete your sign-in:</p>
                <div style='text-align: center; margin: 28px 0;'>
                    <div style='display: inline-block; background: #1a1a2e; color: #e2c07d; font-size: 44px; font-weight: 900; letter-spacing: 16px; padding: 22px 36px; border-radius: 14px; font-family: monospace;'>{$code}</div>
                </div>
                <p style='color: #e74c6a; font-size: 13px; text-align: center; margin-bottom: 16px;'>&#9200; This code expires in <strong>10 minutes</strong>.</p>
                <p style='color: #999; font-size: 12px; text-align:center;'>If you did not attempt to log in, you can safely ignore this email.</p>
            </div>
        </div>";
        $mail->AltBody = "Your login code for " . APP_NAME . " is: {$code}\n\nExpires in 10 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the full error so you can diagnose it
        error_log('[OTP Mailer ERROR] ' . $mail->ErrorInfo);
        error_log('[OTP Mailer] Tried to send to: ' . $toEmail . ' from: ' . SMTP_FROM_EMAIL);
        return false;
    }
}

function sendPasswordResetEmail(string $toEmail, string $toName, string $token): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $resetUrl = APP_URL . '/reset-password.php?token=' . urlencode($token);
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset - ' . APP_NAME;
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #1a1a2e, #16213e); padding: 30px; text-align: center;'>
                <h1 style='color: #e2c07d; margin: 0;'>💰 " . APP_NAME . "</h1>
            </div>
            <div style='background: #f9f9f9; padding: 30px;'>
                <h2>Password Reset Request</h2>
                <p>Click below to reset your password:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$resetUrl}' style='background: #e74c3c; color: white; padding: 14px 35px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Reset Password</a>
                </div>
                <p style='color: #999; font-size: 12px;'>Expires in 1 hour.</p>
            </div>
        </div>";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}