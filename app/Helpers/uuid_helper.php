<?php

/**
 * Generate a short UUID (6 characters) that is unique and url-safe
 *
 * @return string
 */
function generate_short_uuid($length = 6)
{
    // Use random_bytes() for secure random data
    $bytes = random_bytes(ceil($length / 2));
    
    // Convert to hexadecimal
    $hex = bin2hex($bytes);
    
    // Return the first $length characters
    return substr($hex, 0, $length);
}

/**
 * Generate a random 6-character UUID using base62 characters (a-zA-Z0-9)
 * This ensures the UUID is shorter, url-safe, and visually distinguishable
 *
 * @return string
 */
function generate_base62_uuid($length = 6)
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $uuid = '';
    
    // Generate random bytes
    $bytes = random_bytes($length);
    
    // Convert random bytes to characters from our character set
    for ($i = 0; $i < $length; $i++) {
        $uuid .= $chars[ord($bytes[$i]) % 62]; // 62 is the length of our character set
    }
    
    return $uuid;
}

/**
 * Verify if a UUID is already in use in a specific table and column
 *
 * @param string $uuid UUID to check
 * @param string $table Table name
 * @param string $column Column name
 * @return boolean True if UUID exists, false otherwise
 */
function uuid_exists($uuid, $table, $column)
{
    $db = \Config\Database::connect();
    $query = $db->table($table)->where($column, $uuid)->get();
    
    return $query->getNumRows() > 0;
}

/**
 * Generate a guaranteed unique UUID for a specific table and column
 *
 * @param string $table Table name
 * @param string $column Column name
 * @param int $length UUID length
 * @return string Unique UUID
 */
function generate_unique_uuid($table, $column, $length = 6)
{
    $uuid = generate_base62_uuid($length);
    
    // Keep generating until we find a unique one
    while (uuid_exists($uuid, $table, $column)) {
        $uuid = generate_base62_uuid($length);
    }
    
    return $uuid;
}