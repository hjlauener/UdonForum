<?php

declare(strict_types=1);

/**
 * "Perlenkette" of an Udon Forum as SVG.
 *
 * One row per person on a common time axis. Every contribution is a pearl at
 * the time it was written: a filled pearl for an opened post, a ring for a
 * reply. The pearls of a person are connected by a line (the string).
 * The rows are sorted by the number of pearls (most first), then by name.
 */
class ilUdfPearlChart
{
    public const TYPE_POST = 'post';
    public const TYPE_REPLY = 'reply';

    private const WIDTH = 960;
    private const LABEL_WIDTH = 210;
    private const RIGHT = 24;
    private const ROW_HEIGHT = 34;
    private const AXIS_HEIGHT = 34;
    private const PEARL_RADIUS = 7;

    private string $color;
    private string $color_dark;
    private string $color_pale;

    /**
     * @param array<int, array{label: string, items: array<int, array{0: int, 1: string, 2: string}>}> $rows
     *        user id => [label, items: [timestamp, TYPE_*, tooltip]]
     * @param string $color colour of the forum (pearls and lines)
     */
    public function __construct(private array $rows, string $color = '#c95100')
    {
        $this->color = $color;
        $this->color_dark = ilUdfColors::mix($color, '#000000', 0.18);
        $this->color_pale = ilUdfColors::mix($color, '#ffffff', 0.95);
    }

    /**
     * Rows sorted by number of pearls (descending), then by name.
     *
     * @return array<int, array{label: string, items: array<int, array{0: int, 1: string, 2: string}>}>
     */
    public function sortedRows(): array
    {
        $rows = $this->rows;
        uasort($rows, static fn(array $a, array $b): int => [count($b['items']), mb_strtolower($a['label'])]
            <=> [count($a['items']), mb_strtolower($b['label'])]);
        return $rows;
    }

    /**
     * Time axis: start, end and tick marks with labels, depending on the period.
     *
     * @return array{0: int, 1: int, 2: array<int, string>} [start, end, timestamp => label]
     */
    public function timeAxis(): array
    {
        $times = [];
        foreach ($this->rows as $row) {
            foreach ($row['items'] as $item) {
                $times[] = $item[0];
            }
        }
        if ($times === []) {
            return [0, 1, []];
        }
        $min = min($times);
        $max = max($times);
        $span = max($max - $min, 3600);

        // a little space before the first and after the last pearl
        $start = $min - (int) ($span * 0.04);
        $end = $max + (int) ($span * 0.04);
        $span = $end - $start;

        if ($span <= 3 * 86400) {
            $step = $span <= 86400 ? 3 * 3600 : 6 * 3600;
            $format = 'd.m. H:i';
            $first = (int) (ceil($start / $step) * $step);
        } elseif ($span <= 60 * 86400) {
            $days = max(1, (int) ceil($span / 86400 / 12));
            $step = $days * 86400;
            $format = 'd.m.';
            $first = (int) strtotime('tomorrow', $start);
        } elseif ($span <= 400 * 86400) {
            $weeks = max(1, (int) ceil($span / (7 * 86400) / 12));
            $step = $weeks * 7 * 86400;
            $format = 'd.m.';
            $first = (int) strtotime('next monday', $start);
        } else {
            $ticks = [];
            $months = max(1, (int) ceil($span / (30 * 86400) / 12));
            $t = (int) strtotime(date('Y-m-01', $start) . ' +1 month');
            while ($t <= $end) {
                $ticks[$t] = date('m.Y', $t);
                $t = (int) strtotime('+' . $months . ' month', $t);
            }
            return [$start, $end, $ticks];
        }

        $ticks = [];
        for ($t = $first; $t <= $end; $t += $step) {
            $ticks[$t] = date($format, $t);
        }
        if ($ticks === []) {
            // very short period (e.g. only one contribution): one label in the middle
            $mid = (int) (($start + $end) / 2);
            $ticks[$mid] = date('d.m.Y H:i', $mid);
        }
        return [$start, $end, $ticks];
    }

    /**
     * @param string $count_label     e.g. "%s Perlen"
     * @param string $count_label_one e.g. "1 Perle"
     */
    public function toSvg(string $title, string $count_label, string $count_label_one): string
    {
        $rows = $this->sortedRows();
        [$start, $end, $ticks] = $this->timeAxis();
        $x0 = self::LABEL_WIDTH;
        $x1 = self::WIDTH - self::RIGHT;
        $height = self::AXIS_HEIGHT * 2 + count($rows) * self::ROW_HEIGHT;
        $x = static fn(int $t): float => $x0 + ($t - $start) / max($end - $start, 1) * ($x1 - $x0);

        $svg = '<svg class="udf-pearls" viewBox="0 0 ' . self::WIDTH . ' ' . $height . '"'
            . ' xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' . self::esc($title) . '">'
            . '<title>' . self::esc($title) . '</title>';

        // time axis (top and bottom) with grid lines
        $bottom = $height - self::AXIS_HEIGHT;
        foreach ($ticks as $t => $label) {
            $tx = self::num($x($t));
            $svg .= '<line x1="' . $tx . '" y1="' . self::AXIS_HEIGHT . '" x2="' . $tx . '" y2="' . $bottom . '" stroke="#eee" stroke-width="1"/>'
                . '<text x="' . $tx . '" y="' . (self::AXIS_HEIGHT - 12) . '" text-anchor="middle" font-size="11" fill="#777" font-family="inherit">' . self::esc($label) . '</text>'
                . '<text x="' . $tx . '" y="' . ($bottom + 20) . '" text-anchor="middle" font-size="11" fill="#777" font-family="inherit">' . self::esc($label) . '</text>';
        }
        $svg .= '<line x1="' . $x0 . '" y1="' . self::AXIS_HEIGHT . '" x2="' . $x1 . '" y2="' . self::AXIS_HEIGHT . '" stroke="#ccc"/>'
            . '<line x1="' . $x0 . '" y1="' . $bottom . '" x2="' . $x1 . '" y2="' . $bottom . '" stroke="#ccc"/>';

        $i = 0;
        foreach ($rows as $row) {
            $y = self::AXIS_HEIGHT + $i * self::ROW_HEIGHT + self::ROW_HEIGHT / 2;
            $items = $row['items'];
            usort($items, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
            $count = count($items);

            if ($i % 2 === 1) {
                $svg .= '<rect x="0" y="' . self::num($y - self::ROW_HEIGHT / 2) . '" width="' . self::WIDTH . '" height="' . self::ROW_HEIGHT . '" fill="' . $this->color_pale . '"/>';
            }
            $svg .= '<text x="10" y="' . self::num($y + 4) . '" font-size="13" fill="#222" font-family="inherit">'
                . self::esc(self::shorten($row['label'], 22))
                . ' <tspan fill="' . $this->color_dark . '" font-weight="700">' . self::esc($count === 1 ? $count_label_one : sprintf($count_label, $count)) . '</tspan></text>';

            // the string: connects the pearls in time order
            if ($count > 1) {
                $points = implode(' ', array_map(static fn(array $it): string => self::num($x($it[0])) . ',' . self::num($y), $items));
                $svg .= '<polyline points="' . $points . '" fill="none" stroke="' . $this->color . '" stroke-opacity="0.45" stroke-width="2"/>';
            }

            foreach ($items as [$t, $type, $tooltip]) {
                $is_post = $type === self::TYPE_POST;
                $svg .= '<circle class="udf-pearl" cx="' . self::num($x($t)) . '" cy="' . self::num($y) . '" r="' . self::PEARL_RADIUS . '"'
                    . ' fill="' . ($is_post ? $this->color : '#fff') . '" stroke="' . $this->color . '" stroke-width="' . ($is_post ? '1.5' : '2.5') . '">'
                    . '<title>' . self::esc($tooltip) . '</title></circle>';
            }
            $i++;
        }

        return $svg . '</svg>';
    }

    private static function shorten(string $text, int $length): string
    {
        return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
    }

    private static function num(float $value): string
    {
        return number_format($value, 1, '.', '');
    }

    private static function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
