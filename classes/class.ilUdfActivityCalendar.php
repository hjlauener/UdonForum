<?php

declare(strict_types=1);

/**
 * "Aktivitäts-Kalender" of an Udon Forum as SVG.
 *
 * One square per day, one column per week (Monday on top), from the week of
 * the first contribution up to today. The more posts and replies on a day,
 * the darker the square (four levels in the colour of the forum). Days with
 * activity can be clicked (see the script in ilObjUdonForumGUI); they carry
 * the day as data-udf-day="YYYY-MM-DD". Longer periods are split into rows
 * of at most one year.
 */
class ilUdfActivityCalendar
{
    public const TYPE_POST = 'post';
    public const TYPE_REPLY = 'reply';

    private const WIDTH = 960;
    private const LEFT = 34;
    private const TOP = 20;
    private const GAP = 3;
    private const MIN_CELL = 11;
    private const MAX_CELL = 26;
    private const MAX_WEEKS_PER_ROW = 53;
    private const EMPTY_COLOR = '#ebedf0';

    /** @var array<string, array{0: int, 1: int}> day => [posts, replies] */
    private array $counts = [];
    private ?DateTimeImmutable $first = null;
    private DateTimeImmutable $today;

    /**
     * @param array<int, array{0: int, 1: string}> $items [timestamp, TYPE_*]
     * @param string $color colour of the forum
     * @param int    $now   current time (today is marked and is the last day)
     */
    public function __construct(array $items, private string $color, int $now)
    {
        $this->today = self::day($now);
        foreach ($items as [$time, $type]) {
            $key = self::day($time)->format('Y-m-d');
            $this->counts[$key] ??= [0, 0];
            $this->counts[$key][$type === self::TYPE_POST ? 0 : 1]++;
            $day = self::day($time);
            if ($this->first === null || $day < $this->first) {
                $this->first = $day;
            }
        }
    }

    /**
     * Midnight of the day of a timestamp (local time zone).
     */
    public static function day(int $time): DateTimeImmutable
    {
        return (new DateTimeImmutable('@' . $time))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->setTime(0, 0);
    }

    /**
     * @return array<string, array{0: int, 1: int}> day (YYYY-MM-DD) => [posts, replies]
     */
    public function counts(): array
    {
        ksort($this->counts);
        return $this->counts;
    }

    /**
     * Busiest day and its number of contributions, or null without activity.
     *
     * @return array{0: string, 1: int}|null
     */
    public function busiestDay(): ?array
    {
        $best = null;
        foreach ($this->counts() as $day => [$posts, $replies]) {
            if ($best === null || $posts + $replies > $best[1]) {
                $best = [$day, $posts + $replies];
            }
        }
        return $best;
    }

    /**
     * Level 0 (nothing) to 4 (as busy as the busiest day).
     */
    public static function level(int $count, int $max): int
    {
        if ($count <= 0 || $max <= 0) {
            return 0;
        }
        return max(1, min(4, (int) ceil(4 * $count / $max)));
    }

    public function levelColor(int $level): string
    {
        return match ($level) {
            0 => self::EMPTY_COLOR,
            1 => ilUdfColors::mix($this->color, '#ffffff', 0.75),
            2 => ilUdfColors::mix($this->color, '#ffffff', 0.5),
            3 => ilUdfColors::mix($this->color, '#ffffff', 0.22),
            default => $this->color,
        };
    }

    /**
     * Rows of the calendar: Monday of the first week and number of weeks.
     *
     * @return array<int, array{0: DateTimeImmutable, 1: int}>
     */
    public function rows(): array
    {
        $first = $this->first ?? $this->today;
        if ($first > $this->today) {
            $first = $this->today;
        }
        $start = $first->modify('-' . ((int) $first->format('N') - 1) . ' days');
        $end = $this->today->modify('+' . (7 - (int) $this->today->format('N')) . ' days');
        $weeks = (int) round(($end->getTimestamp() - $start->getTimestamp()) / (7 * 86400));
        $weeks = max(1, $weeks);

        $rows = [];
        // the last row is the full one, older weeks are in the rows above
        $count = (int) ceil($weeks / self::MAX_WEEKS_PER_ROW);
        $monday = $start;
        for ($i = 0; $i < $count; $i++) {
            $n = $i === 0 ? $weeks - ($count - 1) * self::MAX_WEEKS_PER_ROW : self::MAX_WEEKS_PER_ROW;
            $rows[] = [$monday, $n];
            $monday = $monday->modify('+' . (7 * $n) . ' days');
        }
        return $rows;
    }

    /**
     * @param string[]                      $weekdays     short names Monday..Sunday
     * @param string[]                      $months       short names January..December
     * @param callable(DateTimeImmutable, int, int): string $tooltip      day, posts, replies
     * @param callable(DateTimeImmutable): string           $tooltip_none day without activity
     */
    public function toSvg(string $title, array $weekdays, array $months, callable $tooltip, callable $tooltip_none): string
    {
        $rows = $this->rows();
        $max_weeks = max(array_map(static fn(array $r): int => $r[1], $rows));
        $cell = (int) max(self::MIN_CELL, min(self::MAX_CELL, floor((self::WIDTH - self::LEFT) / $max_weeks) - self::GAP));
        $step = $cell + self::GAP;
        $max = max(array_map(static fn(array $c): int => $c[0] + $c[1], $this->counts ?: [[0, 0]]));
        $today = $this->today->format('Y-m-d');

        $html = '';
        foreach ($rows as [$monday, $weeks]) {
            $width = self::LEFT + $weeks * $step;
            $height = self::TOP + 7 * $step;
            $svg = '<svg class="udf-calendar" viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height . '"'
                . ' xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' . self::esc($title) . '">';

            foreach ([0, 2, 4] as $row) {
                $svg .= '<text x="' . (self::LEFT - 6) . '" y="' . (self::TOP + $row * $step + $cell - 2) . '" text-anchor="end"'
                    . ' font-size="10" fill="#777" font-family="inherit">' . self::esc($weekdays[$row] ?? '') . '</text>';
            }

            // month names above the week in which the month begins
            $labels = [];
            for ($w = 0; $w < $weeks; $w++) {
                $week_start = $monday->modify('+' . (7 * $w) . ' days');
                for ($d = 0; $d < 7; $d++) {
                    $date = $week_start->modify('+' . $d . ' days');
                    if ($date->format('j') === '1' || ($w === 0 && $d === 0)) {
                        $labels[$w] = $months[(int) $date->format('n') - 1] ?? '';
                        break;
                    }
                }
            }
            $keys = array_keys($labels);
            if (count($keys) > 1 && $keys[0] === 0 && $keys[1] < 3) {
                unset($labels[0]); // the first month is cut off: no label squeezed in
            }
            foreach ($labels as $w => $label) {
                $svg .= '<text x="' . (self::LEFT + $w * $step) . '" y="' . (self::TOP - 7) . '" font-size="10" fill="#777" font-family="inherit">'
                    . self::esc($label) . '</text>';
            }

            for ($w = 0; $w < $weeks; $w++) {
                for ($d = 0; $d < 7; $d++) {
                    $date = $monday->modify('+' . (7 * $w + $d) . ' days');
                    $key = $date->format('Y-m-d');
                    if ($key > $today) {
                        continue; // future
                    }
                    [$posts, $replies] = $this->counts[$key] ?? [0, 0];
                    $level = self::level($posts + $replies, $max);
                    $text = $level > 0 ? $tooltip($date, $posts, $replies) : $tooltip_none($date);
                    $svg .= '<rect x="' . (self::LEFT + $w * $step) . '" y="' . (self::TOP + $d * $step) . '"'
                        . ' width="' . $cell . '" height="' . $cell . '" rx="2" fill="' . $this->levelColor($level) . '"';
                    if ($key === $today) {
                        $svg .= ' stroke="' . ilUdfColors::mix($this->color, '#000000', 0.38) . '" stroke-width="1.5"';
                    }
                    if ($level > 0) {
                        $svg .= ' class="udf-cal-cell" data-udf-day="' . $key . '" tabindex="0" role="button"'
                            . ' aria-label="' . self::esc($text) . '"';
                    }
                    $svg .= '><title>' . self::esc($text) . '</title></rect>';
                }
            }
            $html .= $svg . '</svg>';
        }
        return $html;
    }

    private static function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
