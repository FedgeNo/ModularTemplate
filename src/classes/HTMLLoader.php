<?php

declare(strict_types=1);

class HTMLLoader extends Div
{
    public function toDOM(): \DOMElement
    {
        $id = 'htmlloader-' . bin2hex(random_bytes(8));

        $html = implode('', $this -> contents);

        $loader = new \DOMDocument();

        // The meta charset is required: without it loadHTML assumes ISO-8859-1
        // and mangles any multibyte UTF-8 content (emoji, accented characters).
        $previous_setting = libxml_use_internal_errors(true);
        $loader -> loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="' . $id . '">' . $html . '</div></body></html>');
        libxml_use_internal_errors($previous_setting);

        $div = $loader -> getElementById($id);

        if (!$div instanceof \DOMElement) {
            throw new Exception('Failed to load HTML content');
        }

        $div -> removeAttribute('id');

        $element = self::$document -> importNode($div, true);

        if ($this -> id !== null) {
            $element -> setAttribute('id', $this -> id);
        }

        if ($this -> class !== null) {
            $element -> setAttribute('class', $this -> class);
        }

        foreach ($this -> attributes as $name => $value) {
            $element -> setAttribute($name, $value);
        }

        return $element;
    }
}
