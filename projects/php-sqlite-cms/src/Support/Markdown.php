<?php

declare(strict_types=1);

namespace Cms\Support;

/**
 * Deliberately small Markdown subset renderer.
 *
 * It supports the constructs a blog editor needs (headings, emphasis, links,
 * images, lists, quotes, fenced code, tables and horizontal rules) without
 * pulling in a Composer dependency. All raw HTML is escaped, so untrusted
 * author input can never inject markup.
 */
final class Markdown
{
    public static function toHtml(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $codeBlocks = [];
        $markdown = self::extractCodeBlocks($markdown, $codeBlocks);
        $markdown = htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = [];
        $lines = explode("\n", $markdown);
        $count = count($lines);
        $index = 0;
        $inUl = false;
        $inOl = false;

        $closeLists = static function () use (&$inUl, &$inOl, &$html): void {
            if ($inUl) {
                $html[] = '</ul>';
                $inUl = false;
            }
            if ($inOl) {
                $html[] = '</ol>';
                $inOl = false;
            }
        };

        while ($index < $count) {
            $line = $lines[$index];

            // Tables: a header row followed by a separator row.
            if (
                $index + 1 < $count
                && str_contains($line, '|')
                && preg_match('/^\s*\|?[\s:|-]+\|[\s:|-]*$/', $lines[$index + 1]) === 1
            ) {
                $closeLists();
                $header = self::splitTableRow($line);
                $index += 2;
                $rows = [];
                while ($index < $count && str_contains($lines[$index], '|')) {
                    $rows[] = self::splitTableRow($lines[$index]);
                    $index++;
                }
                $html[] = self::renderTable($header, $rows);
                continue;
            }

            if (preg_match('/^\s*$/', $line) === 1) {
                $closeLists();
                $index++;
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m) === 1) {
                $closeLists();
                $level = strlen($m[1]);
                $text = self::inline(trim($m[2]));
                $id = Str::slug(strip_tags($text));
                $html[] = sprintf('<h%d id="%s">%s</h%d>', $level, $id, $text, $level);
                $index++;
                continue;
            }

            if (preg_match('/^\s*(?:---|\*\*\*|___)\s*$/', $line) === 1) {
                $closeLists();
                $html[] = '<hr>';
                $index++;
                continue;
            }

            if (preg_match('/^\s*>\s?(.*)$/', $line) === 1) {
                $closeLists();
                $buffer = [];
                while ($index < $count && preg_match('/^\s*>\s?(.*)$/', $lines[$index], $m) === 1) {
                    $buffer[] = trim($m[1]);
                    $index++;
                }
                $html[] = '<blockquote><p>' . self::inline(implode(' ', $buffer)) . '</p></blockquote>';
                continue;
            }

            if (preg_match('/^\s*[-*+]\s+(.*)$/', $line, $m) === 1) {
                if ($inOl) {
                    $html[] = '</ol>';
                    $inOl = false;
                }
                if (!$inUl) {
                    $html[] = '<ul>';
                    $inUl = true;
                }
                $html[] = '<li>' . self::inline(trim($m[1])) . '</li>';
                $index++;
                continue;
            }

            if (preg_match('/^\s*\d+[.)]\s+(.*)$/', $line, $m) === 1) {
                if ($inUl) {
                    $html[] = '</ul>';
                    $inUl = false;
                }
                if (!$inOl) {
                    $html[] = '<ol>';
                    $inOl = true;
                }
                $html[] = '<li>' . self::inline(trim($m[1])) . '</li>';
                $index++;
                continue;
            }

            $closeLists();
            $paragraph = [$line];
            $index++;
            while (
                $index < $count
                && preg_match('/^\s*$/', $lines[$index]) !== 1
                && preg_match('/^(#{1,6}\s|\s*[-*+]\s|\s*\d+[.)]\s|\s*>|\s*(?:---|\*\*\*|___)\s*$)/', $lines[$index]) !== 1
            ) {
                $paragraph[] = $lines[$index];
                $index++;
            }
            $html[] = '<p>' . self::inline(implode(' ', array_map('trim', $paragraph))) . '</p>';
        }

        $closeLists();

        $output = implode("\n", $html);

        // Restore fenced code blocks.
        foreach ($codeBlocks as $token => $block) {
            $escaped = htmlspecialchars($block['code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $language = htmlspecialchars($block['language'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $class = $language === '' ? 'code' : 'code language-' . $language;
            $replacement = '<pre class="code-block"><code class="' . $class . '">' . $escaped . '</code></pre>';
            $output = str_replace('<p>' . $token . '</p>', $replacement, $output);
            $output = str_replace($token, $replacement, $output);
        }

        return $output;
    }

    /** Inline-level formatting: links, images, code spans, emphasis. */
    private static function inline(string $text): string
    {
        $text = preg_replace_callback(
            '/!\[([^\]]*)\]\(([^)\s]+)\)/',
            static fn (array $m): string => sprintf(
                '<img src="%s" alt="%s" loading="lazy">',
                $m[2],
                $m[1]
            ),
            $text
        ) ?? $text;

        $text = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)\s]+)\)/',
            static fn (array $m): string => sprintf(
                '<a href="%s" rel="noopener">%s</a>',
                $m[2],
                $m[1]
            ),
            $text
        ) ?? $text;

        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text) ?? $text;
        $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/~~([^~]+)~~/', '<del>$1</del>', $text) ?? $text;

        return $text;
    }

    /**
     * Pull fenced blocks out of the document before escaping so their contents
     * survive verbatim. Each block is replaced by a unique token.
     *
     * @param array<string, array{code: string, language: string}> $blocks
     */
    private static function extractCodeBlocks(string $markdown, array &$blocks): string
    {
        return preg_replace_callback(
            '/^```([a-zA-Z0-9_+-]*)\n(.*?)^```\s*$/ms',
            static function (array $m) use (&$blocks): string {
                $token = 'CODEBLOCKTOKEN' . count($blocks) . 'X';
                $blocks[$token] = ['language' => $m[1], 'code' => rtrim($m[2], "\n")];

                return $token;
            },
            $markdown
        ) ?? $markdown;
    }

    /** @return list<string> */
    private static function splitTableRow(string $row): array
    {
        $row = trim($row);
        $row = trim($row, '|');
        $cells = explode('|', $row);

        return array_map(static fn (string $cell): string => trim($cell), $cells);
    }

    /** @param list<list<string>> $rows */
    private static function renderTable(array $header, array $rows): string
    {
        $html = '<div class="table-wrap"><table><thead><tr>';
        foreach ($header as $cell) {
            $html .= '<th>' . self::inline($cell) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . self::inline($cell) . '</td>';
            }
            $html .= '</tr>';
        }

        return $html . '</tbody></table></div>';
    }
}
