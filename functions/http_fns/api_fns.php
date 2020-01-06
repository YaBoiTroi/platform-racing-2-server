<?php

/**
 * Validates an API request against the origin IP and API key sent.
 *
 * @param string ip The user's IP.
 * @param string key The passed API key.
 * @param bool override_ip Boolean value to determine if the function should override the IP validation step.
 *
 * @throws Exception if the user is not determined to be privileged.
 * @return void
 */
function validate_api_request(string $ip, string $key, bool $override_ip = false)
{
    global $API_ALLOWED_IPS, $API_KEY;

    if (((is_empty($ip) || !in_array($ip, $API_ALLOWED_IPS)) && $override_ip === false) || $key !== $API_KEY) {
        throw new Exception('Access denied.');
    }
}
