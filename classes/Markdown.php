<?php

require_once __DIR__ . '/Parsedown.php';

/**
 * Lightweight Markdown rendering helper shared across frontend templates.
 */
class Markdown
{
    /**
     * @var Parsedown|null
     */
    private static $parser = null;

    /**
     * Convert Markdown text to safe HTML output.
     */
    public static function toHtml(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $parser = self::getParser();
        return $parser->text($text);
    }

    /**
     * Convert Markdown text to plain text, optionally limiting length.
     */
    public static function toPlainText(?string $text, int $limit = 0): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $plain = trim(strip_tags(self::toHtml($text)));
        if ($limit > 0 && mb_strlen($plain) > $limit) {
            return mb_substr($plain, 0, $limit);
        }

        return $plain;
    }

    private static function getParser(): Parsedown
    {
        if (self::$parser === null) {
            self::$parser = new Parsedown();
        }

        return self::$parser;
    }
}
