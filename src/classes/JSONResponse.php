<?php

declare(strict_types=1);

class JSONResponse
{
    public int $statusCode = 200;
    public mixed $response = null;
    public ?string $error = null;

    /**
     * Which inputs were refused and why, keyed by field name.
     *
     * A bare message leaves somebody hunting for which of five boxes it is
     * about, which is bad enough looking at the screen and hopeless not
     * looking at it. Named, the client can put each message under the box it
     * belongs to - and say all of them at once, so nobody submits five times
     * to be told five things.
     *
     * @var array<string, string>|null
     */
    public ?array $fields = null;

    public static function success(mixed $response = null, int $status_code = 200): self
    {
        $json = new self();
        $json -> response = $response;
        $json -> statusCode = $status_code;

        return $json;
    }

    public static function error(string $error, int $status_code = 400): self
    {
        $json = new self();
        $json -> error = $error;
        $json -> statusCode = $status_code;

        return $json;
    }

    /**
     * A refusal that knows which inputs caused it.
     *
     * The summary is what a client with no form to mark up falls back to, and
     * what anything older than this keeps showing - so an endpoint can start
     * naming fields without breaking whatever is already reading it.
     *
     * @param array<string, string> $fields
     */
    public static function fieldErrors(array $fields, ?string $summary = null, int $status_code = 422): self
    {
        $json = self::error($summary ?? implode(' ', $fields), $status_code);
        $json -> fields = $fields;

        return $json;
    }

    /** The common case: one input, one reason. */
    public static function fieldError(string $field, string $reason, int $status_code = 422): self
    {
        return self::fieldErrors([$field => $reason], $reason, $status_code);
    }

    public function send(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($this -> statusCode);
        header('Content-Type: application/json');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        $body = [
            'error' => $this -> error,
            'response' => $this -> response,
        ];

        // Only when there are any, so every other response keeps the shape it
        // has always had.
        if ($this -> fields !== null) {
            $body['fields'] = $this -> fields;
        }

        echo json_encode($body);

        exit;
    }
}
