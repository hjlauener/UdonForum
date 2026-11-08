<?php

declare(strict_types=1);

/**
 * "Beteiligungs-Trichter" of an Udon Forum.
 *
 * Stages: members → visited the forum → read posts → voted → replied →
 * opened a post. Every member is counted on the highest stage reached and
 * on all stages below it, so the funnel always gets narrower. Clicking a
 * stage shows who stopped there (see the script in ilObjUdonForumGUI).
 */
class ilUdfFunnel
{
    public const MEMBERS = 'members';
    public const VISITED = 'visited';
    public const READ = 'read';
    public const VOTED = 'voted';
    public const REPLIED = 'replied';
    public const POSTED = 'posted';

    /** stages from wide to narrow */
    public const STAGES = [self::MEMBERS, self::VISITED, self::READ, self::VOTED, self::REPLIED, self::POSTED];

    /**
     * Highest stage of every member (index in STAGES).
     *
     * @param int[]                                $member_ids
     * @param array<string, array<int, true>>      $activity   stage => user id => true (VISITED .. POSTED)
     * @return array<int, int> user id => stage index
     */
    public static function levels(array $member_ids, array $activity): array
    {
        $levels = [];
        foreach ($member_ids as $user_id) {
            $level = 0;
            foreach (self::STAGES as $index => $stage) {
                if ($index > 0 && isset($activity[$stage][$user_id])) {
                    $level = $index;
                }
            }
            $levels[$user_id] = $level;
        }
        return $levels;
    }

    /**
     * Number of members on every stage (at least this far).
     *
     * @param array<int, int> $levels user id => stage index
     * @return int[] stage index => number
     */
    public static function counts(array $levels): array
    {
        $counts = array_fill(0, count(self::STAGES), 0);
        foreach ($levels as $level) {
            for ($i = 0; $i <= $level; $i++) {
                $counts[$i]++;
            }
        }
        return $counts;
    }

    /**
     * Colour of a stage: light at the wide top, the full forum colour at the bottom.
     */
    public static function stageColor(string $color, int $index): string
    {
        $last = count(self::STAGES) - 1;
        return ilUdfColors::mix($color, '#ffffff', 0.78 * ($last - $index) / $last);
    }

    /**
     * @param array<int, int>    $levels   user id => stage index
     * @param array<int, string> $names    user id => name (plain text)
     * @param string[]           $labels   stage => label
     * @param array<string, string> $texts keys: share (e.g. "%s %%"), drop_title, stopped, final, nobody
     */
    public static function toHtml(array $levels, array $names, string $color, array $labels, array $texts): string
    {
        $counts = self::counts($levels);
        $total = max($counts[0], 1);
        $last = count(self::STAGES) - 1;

        $html = '<div class="udf-funnel">';
        foreach (self::STAGES as $index => $stage) {
            $count = $counts[$index];
            $width = max(100 * $count / $total, 0.0);
            $background = self::stageColor($color, $index);

            // who stopped on this stage (on the last stage: everybody there)
            $people = array_keys(array_filter($levels, static fn(int $l): bool => $l === $index));
            usort($people, static fn(int $a, int $b): int => strcoll(mb_strtolower($names[$a] ?? ''), mb_strtolower($names[$b] ?? '')));

            $drop = $index > 0 ? $counts[$index - 1] - $count : 0;
            $html .= '<button type="button" class="udf-funnel-row" data-udf-stage="' . $stage . '" aria-expanded="false">'
                . '<span class="udf-funnel-label">' . self::esc($labels[$stage] ?? $stage) . '</span>'
                . '<span class="udf-funnel-track"><span class="udf-funnel-bar" style="width:' . number_format($width, 1, '.', '')
                . '%;background:' . $background . ';color:' . ilUdfColors::textOn($background) . '">' . $count . '</span></span>'
                . '<span class="udf-funnel-share">' . self::esc(sprintf($texts['share'], (string) round(100 * $count / $total))) . '</span>'
                . '<span class="udf-funnel-drop"' . ($drop > 0 ? ' title="' . self::esc(sprintf($texts['drop_title'], $drop)) . '">−' . $drop : '>') . '</span>'
                . '</button>';

            $html .= '<div class="udf-funnel-detail" data-udf-stage="' . $stage . '" hidden><strong>'
                . self::esc(sprintf($index === $last ? $texts['final'] : $texts['stopped'], count($people))) . '</strong> ';
            if ($people === []) {
                $html .= '<span class="udf-meta">' . self::esc($texts['nobody']) . '</span>';
            } else {
                $html .= '<span class="udf-funnel-names">' . implode('', array_map(
                    static fn(int $id): string => '<span class="udf-funnel-name">' . self::esc($names[$id] ?? '') . '</span>',
                    $people
                )) . '</span>';
            }
            $html .= '</div>';
        }
        return $html . '</div>';
    }

    private static function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
