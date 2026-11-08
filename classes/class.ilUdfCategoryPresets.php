<?php

declare(strict_types=1);

/**
 * Category presets of the plugin administration ("Kategorie-Vorgaben") and
 * parsing of category lists.
 *
 * The presets are stored as text in the ILIAS settings (module "xudf"),
 * one preset per line:  Name: Category 1; Category 2; Category 3
 * They are offered when a new forum is created.
 */
class ilUdfCategoryPresets
{
    private const SETTINGS_MODULE = 'xudf';
    private const SETTINGS_KEY = 'category_presets';
    private const MAX_CATEGORIES = 50;
    private const MAX_TITLE_LENGTH = 255;

    public static function loadText(): string
    {
        return (string) (new ilSetting(self::SETTINGS_MODULE))->get(self::SETTINGS_KEY, '');
    }

    public static function saveText(string $text): void
    {
        (new ilSetting(self::SETTINGS_MODULE))->set(self::SETTINGS_KEY, self::normalizeText($text));
    }

    /**
     * @return array<int, array{name: string, categories: string[]}>
     */
    public static function load(): array
    {
        return self::parsePresets(self::loadText());
    }

    /**
     * One preset per line: "Name: Category 1; Category 2". Without colon the
     * categories themselves are the name. Empty lines are ignored.
     *
     * @return array<int, array{name: string, categories: string[]}>
     */
    public static function parsePresets(string $text): array
    {
        $presets = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $pos = mb_strpos($line, ':');
            $name = $pos !== false ? trim(mb_substr($line, 0, $pos)) : '';
            $categories = self::parseList($pos !== false ? mb_substr($line, $pos + 1) : $line);
            if ($categories === []) {
                continue;
            }
            $presets[] = [
                'name' => $name !== '' ? $name : implode('; ', $categories),
                'categories' => $categories,
            ];
        }
        return $presets;
    }

    /**
     * "Allgemeines; Fragen;; fragen ; Prüfung" => ["Allgemeines", "Fragen", "Prüfung"]
     * (trimmed, without empty entries and duplicates, at most 50).
     *
     * @return string[]
     */
    public static function parseList(string $list): array
    {
        $result = [];
        $seen = [];
        foreach (explode(';', $list) as $title) {
            $title = trim((string) preg_replace('/\s+/u', ' ', $title));
            if ($title === '') {
                continue;
            }
            $title = mb_substr($title, 0, self::MAX_TITLE_LENGTH);
            $key = mb_strtolower($title);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $title;
            if (count($result) >= self::MAX_CATEGORIES) {
                break;
            }
        }
        return $result;
    }

    /**
     * Stored form: every valid preset on its own line as "Name: A; B; C".
     */
    public static function normalizeText(string $text): string
    {
        return implode("\n", array_map(
            static fn(array $p): string => $p['name'] . ': ' . implode('; ', $p['categories']),
            self::parsePresets($text)
        ));
    }
}
