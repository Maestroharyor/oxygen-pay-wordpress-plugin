<?php
function generate_unique_number($length = 16)
{
    if ($length <= 0) {
        throw new InvalidArgumentException("Length must be a positive integer.");
    }

    // Calculate the number of bytes needed to generate the desired length
    $numBytes = ceil($length / 2);

    // Generate random bytes
    $randomBytes = random_bytes($numBytes);

    // Convert the random bytes to a hexadecimal string
    $uniqueCode = bin2hex($randomBytes);

    // Truncate the string to the desired length
    $uniqueCode = substr($uniqueCode, 0, $length);

    return $uniqueCode;
}
