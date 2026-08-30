<?php

declare(strict_types=1);

/** The size and shape every ordinary application API request may carry. */
final class APIRequestBody
{
    public const MAX_BYTES = 24 * 1024;

    /** Whether a valid JSON body is one object whose fields are all scalar or null. */
    public static function hasOnlyTopLevelFields(string $body): bool
    {
        if ($body === '') {
            return true;
        }

        try {
            $payload = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Endpoint validation already reports malformed input as missing
            // fields; this shared layer is only the nesting boundary.
            return true;
        }

        if (!is_object($payload)) {
            return false;
        }

        foreach (get_object_vars($payload) as $value) {
            if (is_array($value) || is_object($value)) {
                return false;
            }
        }

        return true;
    }
}
