<?php

declare(strict_types=1);

/**
 * "Bubble-Charts" of an Udon Forum as SVG.
 *
 * - Every topic (category) is a circle; the more posts, the larger the circle
 *   (the area grows with the number of posts).
 * - Related topics overlap: two topics are related when the same people are
 *   active in both (Jaccard similarity of the active people). The more people
 *   they share, the more the circles overlap.
 * - In the middle of every circle a small pie chart shows the share of
 *   posts (dark) and replies (light) of the topic.
 *
 * The positions are computed with a simple iterative layout; the result is
 * deterministic (same data, same picture).
 */
class ilUdfBubbleChart
{
    private const WIDTH = 960;
    private const HEIGHT = 620;
    private const MARGIN = 20;
    private const MIN_RADIUS = 44;
    /** the picture may be enlarged up to this factor to use the space */
    private const MAX_SCALE = 1.5;
    /** from this similarity on, two topics count as related and overlap */
    private const RELATED_FROM = 0.15;

    /**
     * @param array<string, array{label: string, tooltip: string, color: string, posts: int, replies: int, users: array<int, true>}> $bubbles
     */
    public function __construct(private array $bubbles)
    {
        $this->bubbles = array_filter($bubbles, static fn(array $b): bool => $b['posts'] > 0);
    }

    /**
     * Share of people active in both topics among all people active in one of them.
     *
     * @param array<int, true> $a
     * @param array<int, true> $b
     */
    public static function similarity(array $a, array $b): float
    {
        $union = count($a + $b);
        return $union === 0 ? 0.0 : count(array_intersect_key($a, $b)) / $union;
    }

    /**
     * @return array<string, float> key => radius
     */
    public function radii(): array
    {
        $n = count($this->bubbles);
        if ($n === 0) {
            return [];
        }
        $max_posts = max(array_map(static fn(array $b): int => $b['posts'], $this->bubbles));
        $max_radius = $n <= 3 ? 160.0 : ($n <= 6 ? 130.0 : 105.0);

        $radii = [];
        foreach ($this->bubbles as $key => $bubble) {
            // area proportional to the number of posts
            $radii[$key] = max(self::MIN_RADIUS, $max_radius * sqrt($bubble['posts'] / $max_posts));
        }
        return $radii;
    }

    /**
     * @return array<string, array{0: float, 1: float, 2: float}> key => [x, y, radius]
     */
    public function layout(): array
    {
        $radii = $this->radii();
        $keys = array_keys($radii);
        // big topics first, then by name (deterministic)
        usort($keys, fn($a, $b): int => [$radii[$b], $this->bubbles[$a]['label']] <=> [$radii[$a], $this->bubbles[$b]['label']]);
        $n = count($keys);
        if ($n === 0) {
            return [];
        }

        $cx = self::WIDTH / 2;
        $cy = self::HEIGHT / 2;
        $pos = [];
        foreach ($keys as $i => $key) {
            $angle = 2 * M_PI * $i / max($n, 1);
            $dist = $i === 0 ? 0 : 200;
            $pos[$key] = [$cx + $dist * cos($angle), $cy + $dist * sin($angle)];
        }

        // similarity of all pairs
        $sim = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $sim[$i][$j] = self::similarity($this->bubbles[$keys[$i]]['users'], $this->bubbles[$keys[$j]]['users']);
            }
        }

        for ($it = 0; $it < 900; $it++) {
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $a = $keys[$i];
                    $b = $keys[$j];
                    $dx = $pos[$b][0] - $pos[$a][0];
                    $dy = $pos[$b][1] - $pos[$a][1];
                    $dist = sqrt($dx * $dx + $dy * $dy);
                    if ($dist < 0.01) {
                        $dx = 1.0;
                        $dy = 0.0;
                        $dist = 1.0;
                    }
                    $s = $sim[$i][$j];
                    $sum = $radii[$a] + $radii[$b];

                    if ($s >= self::RELATED_FROM) {
                        // related: overlap, the more people they share the more
                        // the centre of the smaller circle stays outside the larger one (labels stay readable)
                        $big = max($radii[$a], $radii[$b]);
                        $small = min($radii[$a], $radii[$b]);
                        $target = max($big + 0.35 * $small, $sum * (1 - 0.5 * min($s * 1.4, 1.0)), 120);
                        // closer than the target: push apart firmly; further away: pull together gently
                        $move = ($dist - $target) * ($dist < $target ? 0.4 : 0.05);
                    } elseif ($dist < $sum + 16) {
                        // not related: push apart until they do not touch
                        $move = ($dist - ($sum + 16)) * 0.8;
                    } else {
                        continue;
                    }

                    $mx = $dx / $dist * $move;
                    $my = $dy / $dist * $move;
                    $wa = $radii[$b] / $sum; // the smaller circle moves more
                    $wb = $radii[$a] / $sum;
                    $pos[$a][0] += $mx * $wa;
                    $pos[$a][1] += $my * $wa;
                    $pos[$b][0] -= $mx * $wb;
                    $pos[$b][1] -= $my * $wb;
                }
            }
            // a little gravity to the centre
            foreach ($keys as $key) {
                $pos[$key][0] -= ($pos[$key][0] - $cx) * 0.004;
                $pos[$key][1] -= ($pos[$key][1] - $cy) * 0.004;
            }
        }

        // centre the picture and shrink it if it is too big
        $min_x = $min_y = INF;
        $max_x = $max_y = -INF;
        foreach ($keys as $key) {
            $min_x = min($min_x, $pos[$key][0] - $radii[$key]);
            $max_x = max($max_x, $pos[$key][0] + $radii[$key]);
            $min_y = min($min_y, $pos[$key][1] - $radii[$key]);
            $max_y = max($max_y, $pos[$key][1] + $radii[$key]);
        }
        $scale = min(
            self::MAX_SCALE,
            (self::WIDTH - 2 * self::MARGIN) / max($max_x - $min_x, 1),
            (self::HEIGHT - 2 * self::MARGIN) / max($max_y - $min_y, 1)
        );
        $mid_x = ($min_x + $max_x) / 2;
        $mid_y = ($min_y + $max_y) / 2;

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = [
                $cx + ($pos[$key][0] - $mid_x) * $scale,
                $cy + ($pos[$key][1] - $mid_y) * $scale,
                $radii[$key] * $scale,
            ];
        }
        return $result;
    }

    /**
     * @param string $posts_label     e.g. "%s Beiträge"
     * @param string $post_label_one  e.g. "1 Beitrag"
     */
    public function toSvg(string $title, string $posts_label, string $post_label_one): string
    {
        $layout = $this->layout();

        $svg = '<svg class="udf-bubbles" viewBox="0 0 ' . self::WIDTH . ' ' . self::HEIGHT . '"'
            . ' xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' . self::esc($title) . '">'
            . '<title>' . self::esc($title) . '</title>';

        // circles (largest first, so that smaller ones stay visible on top)
        foreach ($layout as $key => [$x, $y, $r]) {
            $b = $this->bubbles[$key];
            $svg .= '<circle cx="' . self::num($x) . '" cy="' . self::num($y) . '" r="' . self::num($r) . '"'
                . ' fill="' . $b['color'] . '" fill-opacity="0.22" stroke="' . $b['color'] . '" stroke-width="2.5">'
                . '<title>' . self::esc($b['tooltip']) . '</title></circle>';
        }

        // pie charts and labels on top of all circles
        foreach ($layout as $key => [$x, $y, $r]) {
            $b = $this->bubbles[$key];
            $pie_r = min(26.0, $r * 0.38);
            $svg .= '<g class="udf-bubble"><title>' . self::esc($b['tooltip']) . '</title>'
                . self::pie($x, $y - 8, $pie_r, $b['posts'], $b['replies'], $b['color'])
                . '<text x="' . self::num($x) . '" y="' . self::num($y - 8 + $pie_r + 18) . '" text-anchor="middle"'
                . ' font-size="14" font-weight="700" font-family="inherit" fill="#222"'
                . ' stroke="#fff" stroke-width="4" stroke-linejoin="round" paint-order="stroke">' . self::esc($b['label']) . '</text>'
                . '<text x="' . self::num($x) . '" y="' . self::num($y - 8 + $pie_r + 34) . '" text-anchor="middle"'
                . ' font-size="12" font-family="inherit" fill="#444"'
                . ' stroke="#fff" stroke-width="4" stroke-linejoin="round" paint-order="stroke">'
                . self::esc($b['posts'] === 1 ? $post_label_one : sprintf($posts_label, $b['posts'])) . '</text>'
                . '</g>';
        }

        return $svg . '</svg>';
    }

    /**
     * Pie chart: posts (full colour) and replies (light).
     */
    private static function pie(float $x, float $y, float $r, int $posts, int $replies, string $color): string
    {
        $base = '<circle cx="' . self::num($x) . '" cy="' . self::num($y) . '" r="' . self::num($r) . '"'
            . ' fill="#fff" stroke="' . $color . '" stroke-width="1.5"/>';
        $total = $posts + $replies;
        if ($replies === 0) {
            return $base . '<circle cx="' . self::num($x) . '" cy="' . self::num($y) . '" r="' . self::num($r) . '" fill="' . $color . '"/>';
        }

        $html = $base . '<circle cx="' . self::num($x) . '" cy="' . self::num($y) . '" r="' . self::num($r) . '"'
            . ' fill="' . $color . '" fill-opacity="0.35"/>';
        $angle = 2 * M_PI * $posts / $total;
        $x1 = $x;
        $y1 = $y - $r;
        $x2 = $x + $r * sin($angle);
        $y2 = $y - $r * cos($angle);
        $large = $angle > M_PI ? 1 : 0;
        return $html . '<path d="M' . self::num($x) . ' ' . self::num($y) . ' L' . self::num($x1) . ' ' . self::num($y1)
            . ' A' . self::num($r) . ' ' . self::num($r) . ' 0 ' . $large . ' 1 ' . self::num($x2) . ' ' . self::num($y2) . ' Z"'
            . ' fill="' . $color . '"/>';
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
