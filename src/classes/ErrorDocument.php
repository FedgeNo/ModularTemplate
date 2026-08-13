<?php

declare(strict_types=1);

class ErrorDocument
{
    /**
     * An error page for the one case where the database cannot be read: the
     * code and the schema disagree, so anything that queries a table might be
     * querying one that has changed underneath it. The navigation is what does
     * that here - it asks whether there are unseen notifications - so this
     * sends the same page without it.
     */
    public static function send(int $status_code, string $title, string $message): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        http_response_code($status_code);

        $page = new Page(['title' => $title]);

        $page -> addContent(new Paragraph($message));

        $page -> addContent(new Anchor(ServerURL::absolute('/'), 'Back to Home'));

        $page -> send();
    }
}
