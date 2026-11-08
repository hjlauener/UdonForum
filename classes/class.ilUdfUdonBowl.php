<?php

declare(strict_types=1);

/**
 * Udon bowl instead of karma: every ▲ a person receives on their posts is a
 * noodle in their bowl. The bowl fills up in four ranks:
 *
 * - Nudel-Lehrling  (0–4 noodles)
 * - Brühe-Kenner    (5–14)
 * - Tempura-Meister (15–39)
 * - Udon-Sensei     (40 and more)
 *
 * Texts: rank names from the language file (udon_rank_<key>).
 */
class ilUdfUdonBowl
{
    public const RANK_APPRENTICE = 'apprentice';
    public const RANK_BROTH = 'broth';
    public const RANK_TEMPURA = 'tempura';
    public const RANK_SENSEI = 'sensei';

    /** minimum number of noodles per rank */
    public const RANKS = [
        self::RANK_APPRENTICE => 0,
        self::RANK_BROTH => 5,
        self::RANK_TEMPURA => 15,
        self::RANK_SENSEI => 40,
    ];

    public static function rank(int $noodles): string
    {
        $rank = self::RANK_APPRENTICE;
        foreach (self::RANKS as $key => $minimum) {
            if ($noodles >= $minimum) {
                $rank = $key;
            }
        }
        return $rank;
    }

    /**
     * How full the bowl is: 0 = empty, 1..4 = the four ranks (1 = first noodles).
     */
    public static function level(int $noodles): int
    {
        if ($noodles <= 0) {
            return 0;
        }
        return array_search(self::rank($noodles), array_keys(self::RANKS), true) + 1;
    }

    /**
     * Small bowl (inline SVG) that fills up with the noodles.
     */
    public static function icon(int $noodles, int $size = 18): string
    {
        $level = self::level($noodles);
        $noodle = '#f2c94c';
        $parts = '';

        // noodles: the fuller the bowl, the higher the pile
        if ($level >= 1) {
            $parts .= '<path d="M5.2 11.4c1.1-1 2.2 1 3.3 0s2.2 1 3.3 0 2.2 1 3.3 0 2.2 1 3.3 0" stroke="' . $noodle . '" stroke-width="1.5" fill="none" stroke-linecap="round"/>';
        }
        if ($level >= 2) {
            $parts .= '<path d="M6.4 9.6c1-.9 2 .9 2.9 0s2 .9 2.9 0 2 .9 2.9 0 1.8.9 2.5 0" stroke="' . $noodle . '" stroke-width="1.5" fill="none" stroke-linecap="round"/>';
        }
        if ($level >= 3) {
            // tempura shrimp on top
            $parts .= '<path d="M9 8.6c.6-2.2 3.2-3.4 5.6-2.4.5.2.4.9-.1 1-1.9.3-3.4 1.2-4.4 2.2-.5.5-1.3 0-1.1-.8z" fill="#e8590c"/>';
        }
        if ($level >= 4) {
            // chopsticks and steam: the master's bowl
            $parts .= '<path d="M14.5 9.2 20.8 2.6M16.4 9.6l5.4-5.4" stroke="#7a4b1f" stroke-width="1.3" stroke-linecap="round"/>'
                . '<path d="M6.2 6.6c-.8-1 .8-1.6 0-2.8M8.6 6c-.8-1 .8-1.6 0-2.8" stroke="#adb5bd" stroke-width="1" fill="none" stroke-linecap="round"/>';
        }

        $bowl = '<path d="M2.6 11.8h18.8c-.4 4.4-4.5 7.6-9.4 7.6s-9-3.2-9.4-7.6z" fill="#c95100"/>'
            . '<path d="M4.6 14.2h14.8" stroke="#fff" stroke-opacity=".45" stroke-width=".9"/>'
            . '<rect x="9" y="19" width="6" height="1.9" rx=".6" fill="#a94208"/>';

        return '<svg class="udf-bowl-icon" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '"'
            . ' aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">' . $parts . $bowl . '</svg>';
    }
}
