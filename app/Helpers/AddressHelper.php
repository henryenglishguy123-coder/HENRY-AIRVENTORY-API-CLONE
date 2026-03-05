<?php

namespace App\Helpers;

class AddressHelper
{
    /**
     * Splits a long address into multiple lines based on character limits.
     *
     * @param  string|null  $address  Full address string
     * @param  int  $limit  Maximum characters per line
     * @param  int  $maxLines  Maximum number of lines to return
     * @return array Array of address lines
     */
    public static function splitAddress(?string $address, int $limit = 50, int $maxLines = 3): array
    {
        if (empty($address)) {
            return array_fill(0, $maxLines, '');
        }

        $address = trim($address);
        $lines = [];

        // If it's already multi-line, use those as starting point
        $inputLines = preg_split('/\r\n|\r|\n/', $address);

        foreach ($inputLines as $inputLine) {
            $inputLine = trim($inputLine);
            if (empty($inputLine)) {
                continue;
            }

            // If a single line is too long, split it by comma or space
            if (strlen($inputLine) > $limit) {
                $words = preg_split('/(, )|(\s+)/', $inputLine, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                $currentLine = '';

                foreach ($words as $word) {
                    if (strlen($currentLine.$word) <= $limit) {
                        $currentLine .= $word;
                    } else {
                        if (! empty($currentLine)) {
                            $lines[] = trim($currentLine, ', ');
                        }
                        $currentLine = $word;
                    }
                }
                if (! empty($currentLine)) {
                    $lines[] = trim($currentLine, ', ');
                }
            } else {
                $lines[] = $inputLine;
            }
        }

        // Ensure we have exactly $maxLines results
        $result = array_slice($lines, 0, $maxLines);
        while (count($result) < $maxLines) {
            $result[] = '';
        }

        return $result;
    }

    /**
     * Truncates a string to a given limit while trimming whitespace.
     */
    public static function truncate(?string $string, int $limit = 50): ?string
    {
        if ($string === null) return null;
        $string = trim($string);
        if (strlen($string) <= $limit) return $string;
        return substr($string, 0, $limit);
    }
}
