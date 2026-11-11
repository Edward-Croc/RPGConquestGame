<?php

/**
 * Decode a JSON payload that may be NULL.
 *
 * Passing NULL to json_decode is deprecated since PHP 8.1, and most payloads
 * here come from a nullable column or a missing config key. NULL and the empty
 * string decode identically — both yield NULL with JSON_ERROR_SYNTAX — so
 * callers that inspect json_last_error() keep the behaviour they had.
 *
 * @param string|null $json : the payload, typically a column or a config value
 * @param bool $associative : decode objects as associative arrays
 *
 * @return mixed : whatever json_decode returns, NULL when the payload is unusable
 */
function decodeJson(string|null $json, bool $associative = true): mixed
{
    return json_decode($json ?? '', $associative);
}
