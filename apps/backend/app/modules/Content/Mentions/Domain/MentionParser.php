<?php

declare(strict_types=1);

namespace app\modules\Content\Mentions\Domain;

/** Parses canonical @handles without treating e-mail addresses as mentions. */
final class MentionParser
{
    /** Mirrors ThinkPHP's chsDash rule used by the username validators. */
    private const HANDLE_CHARS = '\\x{4e00}-\\x{9fef}\\x{3400}-\\x{4db5}\\x{20000}-\\x{2ebe0}A-Za-z0-9_-';

    private static function pattern(): string
    {
        return '~(?<![\\p{L}\\p{N}._%+\\-])@([' . self::HANDLE_CHARS . ']{3,12})(?![\\p{L}\\p{N}_-])~u';
    }

    private static function unicodeLength(string $value): int
    {
        if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
        preg_match_all('/./us', $value, $characters);
        return count($characters[0] ?? []);
    }

    private static function lowercase(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    /** @return array<int,array{username:string,start:int,end:int}> Unicode code-point offsets. */
    public function occurrences(string $text): array
    {
        if ($text === '') return [];
        preg_match_all(self::pattern(), $text, $matches, PREG_OFFSET_CAPTURE);
        $result = [];
        foreach ($matches[0] ?? [] as $index => $match) {
            $token = (string) ($match[0] ?? '');
            $byteOffset = (int) ($match[1] ?? -1);
            $username = (string) ($matches[1][$index][0] ?? '');
            if ($token === '' || $username === '' || $byteOffset < 0) continue;
            $start = self::unicodeLength(substr($text, 0, $byteOffset));
            $result[] = [
                'username' => $username,
                'start' => $start,
                'end' => $start + self::unicodeLength($token),
            ];
        }
        return $result;
    }

    /** @return string[] unique handles in source order */
    public function parse(array $texts): array
    {
        $handles = [];
        foreach ($texts as $text) {
            if (!is_string($text) || $text === '') continue;
            foreach ($this->occurrences($text) as $occurrence) {
                $handle = $occurrence['username'];
                $key = self::lowercase((string) $handle);
                if (!isset($handles[$key])) $handles[$key] = (string) $handle;
            }
        }

        return array_values($handles);
    }
}
