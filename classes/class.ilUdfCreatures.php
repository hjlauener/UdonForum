<?php

declare(strict_types=1);

/**
 * "1000 ways Forum": four creatures from Japanese folklore, each standing
 * for a different way of taking part in a discussion.
 *
 * - Hakutaku (白澤): brings facts, definitions and sources
 * - Kitsune  (狐):   questions, asks for reasons, finds gaps
 * - Tanuki   (狸):   brings examples and makes things concrete
 * - Kodama   (木霊): connects, summarises and mediates
 *
 * The course/group admins (lecturers) do not take part in the random
 * distribution: they have the role "Chef" (大将, taishō), with several admins
 * numbered "Chef 1", "Chef 2", ...
 *
 * Texts (name, role, hint) come from the language files:
 * creature_<key>, creature_<key>_role, creature_<key>_hint.
 */
class ilUdfCreatures
{
    public const HAKUTAKU = 'hakutaku';
    public const KITSUNE = 'kitsune';
    public const TANUKI = 'tanuki';
    public const KODAMA = 'kodama';
    /** role of the lecturers (course/group admins), not distributed randomly */
    public const CHEF = 'chef';

    public const ALL = [self::HAKUTAKU, self::KITSUNE, self::TANUKI, self::KODAMA];

    private const COLORS = [
        self::HAKUTAKU => '#1971c2',
        self::KITSUNE => '#d9480f',
        self::TANUKI => '#8d5a2b',
        self::KODAMA => '#2f9e44',
        self::CHEF => '#212529',
    ];

    private const KANJI = [
        self::HAKUTAKU => '白澤',
        self::KITSUNE => '狐',
        self::TANUKI => '狸',
        self::KODAMA => '木霊',
        self::CHEF => '大将',
    ];

    /**
     * One of the four creatures that are distributed among the members.
     */
    public static function isValid(string $creature): bool
    {
        return in_array($creature, self::ALL, true);
    }

    /**
     * Numbers of the chefs: with one admin no number (0), with several
     * 1, 2, ... in the order of their user accounts (stable).
     *
     * @param int[] $admin_ids
     * @return array<int, int> user id => number (0 = only one chef)
     */
    public static function chefNumbers(array $admin_ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $admin_ids)));
        sort($ids);
        if (count($ids) === 1) {
            return [$ids[0] => 0];
        }
        $numbers = [];
        foreach ($ids as $i => $id) {
            $numbers[$id] = $i + 1;
        }
        return $numbers;
    }

    public static function color(string $creature): string
    {
        return self::COLORS[$creature] ?? '#555555';
    }

    public static function kanji(string $creature): string
    {
        return self::KANJI[$creature] ?? '';
    }

    /**
     * Small round icon (inline SVG) of a creature.
     */
    public static function icon(string $creature, int $size = 18): string
    {
        $color = self::color($creature);
        $glyph = match ($creature) {
            // three eyes: the all-knowing white beast
            self::HAKUTAKU => '<path d="M6 15c0-4 2.7-7 6-7s6 3 6 7c0 1.7-1.3 3-3 3H9c-1.7 0-3-1.3-3-3z" fill="#fff"/>'
                . '<path d="M7.5 8.5 6 5.5l3 1.6M16.5 8.5 18 5.5l-3 1.6" fill="#fff"/>'
                . '<circle cx="9.6" cy="13.2" r="1.1" fill="' . $color . '"/><circle cx="14.4" cy="13.2" r="1.1" fill="' . $color . '"/>'
                . '<ellipse cx="12" cy="10.4" rx="0.8" ry="1.1" fill="' . $color . '"/>',
            // fox head with pointed ears
            self::KITSUNE => '<path d="M5.5 5.5l3.6 3.1h5.8l3.6-3.1-.6 6.3L12 19l-5.9-7.2z" fill="#fff"/>'
                . '<path d="M9.3 12.2l1.4.9M14.7 12.2l-1.4.9" stroke="' . $color . '" stroke-width="1.3" stroke-linecap="round"/>'
                . '<circle cx="12" cy="16.4" r="1" fill="' . $color . '"/>',
            // round raccoon dog face with eye mask and the leaf on its head
            self::TANUKI => '<path d="M12 3.6c1.9.6 2.6 2.1 2.2 3.6-1.6-.2-2.6-1.4-2.2-3.6z" fill="#8fd16a"/>'
                . '<circle cx="12" cy="13" r="6.2" fill="#fff"/>'
                . '<ellipse cx="9.4" cy="12.6" rx="2" ry="1.5" fill="' . $color . '"/><ellipse cx="14.6" cy="12.6" rx="2" ry="1.5" fill="' . $color . '"/>'
                . '<circle cx="9.4" cy="12.6" r=".6" fill="#fff"/><circle cx="14.6" cy="12.6" r=".6" fill="#fff"/>'
                . '<ellipse cx="12" cy="15.8" rx="1" ry=".7" fill="' . $color . '"/>',
            // tree spirit: crown of a tree with a calm face
            self::KODAMA => '<path d="M12 4.5c3.3 0 5.8 2.4 5.8 5.4 0 2.6-1.9 4.7-4.4 5.2V19h-2.8v-3.9c-2.5-.5-4.4-2.6-4.4-5.2 0-3 2.5-5.4 5.8-5.4z" fill="#fff"/>'
                . '<circle cx="10.2" cy="9.8" r=".9" fill="' . $color . '"/><circle cx="13.8" cy="9.8" r=".9" fill="' . $color . '"/>'
                . '<path d="M10.6 12.3c.8.6 2 .6 2.8 0" stroke="' . $color . '" stroke-width="1" fill="none" stroke-linecap="round"/>',
            // golden crown
            self::CHEF => '<path d="M5.2 15.6 4.4 8.2l4.2 3.3L12 5.6l3.4 5.9 4.2-3.3-.8 7.4z" fill="#f2c94c"/>'
                . '<rect x="5.2" y="16.6" width="13.6" height="2.2" rx=".6" fill="#f2c94c"/>'
                . '<circle cx="12" cy="12.6" r="1" fill="' . $color . '"/>',
            default => '',
        };

        return '<svg class="udf-creature-icon" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '"'
            . ' aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">'
            . '<circle cx="12" cy="12" r="12" fill="' . $color . '"/>' . $glyph . '</svg>';
    }

    /**
     * Assigns a creature to every member who has none yet: always one of the
     * creatures that are least common so far (random among those), so that
     * the four roles stay evenly distributed.
     *
     * @param int[]                $member_ids
     * @param array<int, string>   $current user id => creature
     * @return array<int, string> new assignments (user id => creature)
     */
    public static function assignMissing(array $member_ids, array $current): array
    {
        $counts = array_fill_keys(self::ALL, 0);
        foreach ($current as $user_id => $creature) {
            if (isset($counts[$creature]) && in_array($user_id, $member_ids, true)) {
                $counts[$creature]++;
            }
        }

        $missing = array_values(array_filter($member_ids, static fn(int $id): bool => !isset($current[$id])));
        shuffle($missing);

        $new = [];
        foreach ($missing as $user_id) {
            $min = min($counts);
            $candidates = array_keys(array_filter($counts, static fn(int $c): bool => $c === $min));
            $creature = $candidates[random_int(0, count($candidates) - 1)];
            $new[$user_id] = $creature;
            $counts[$creature]++;
        }
        return $new;
    }

    /**
     * New random, even distribution for all members ("Neu mischen").
     *
     * @param int[] $member_ids
     * @return array<int, string> user id => creature
     */
    public static function reshuffle(array $member_ids): array
    {
        $ids = array_values($member_ids);
        shuffle($ids);
        $creatures = self::ALL;
        shuffle($creatures);

        $result = [];
        foreach ($ids as $i => $user_id) {
            $result[$user_id] = $creatures[$i % count($creatures)];
        }
        return $result;
    }
}
