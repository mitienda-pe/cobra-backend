<?php

/**
 * Format a phone number to E.164 format
 * Example: +51999999999
 * 
 * @param string $phone Phone number to format
 * @param string $defaultCountryCode Default country code to use if not provided (e.g., '51' for Peru)
 * @return string|null Formatted phone number or null if invalid
 */
function format_phone_e164($phone, $defaultCountryCode = '51')
{
    if (empty($phone)) {
        return null;
    }

    // Remove all non-digit characters except +
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // If phone starts with +, keep it as is
    if (strpos($phone, '+') === 0) {
        return $phone;
    }
    
    // If phone starts with country code (e.g., 51999999999)
    if (strlen($phone) >= 11 && substr($phone, 0, 2) === $defaultCountryCode) {
        return '+' . $phone;
    }
    
    // If phone is just the local number, add country code
    return '+' . $defaultCountryCode . $phone;
}

/**
 * Validate if a phone number is in E.164 format
 * 
 * @param string $phone Phone number to validate
 * @return bool True if valid, false otherwise
 */
function is_valid_e164($phone)
{
    return !empty($phone) && preg_match('/^\+[1-9]\d{1,14}$/', $phone);
}
