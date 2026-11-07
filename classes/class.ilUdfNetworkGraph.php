<?php

declare(strict_types=1);

/**
 * "Spinnennetz" of an Udon Forum as SVG.
 *
 * - Every active person is a black dot with the name. With every post the
 *   person opened, the circle around the dot grows.
 * - If a person replied to the post of another person (or the other way
 *   round), a line connects the two; the more replies, the thicker the line.
 *
 * The positions are calculated with a force-directed layout
 * (Fruchterman-Reingold): connected people attract each other, all people
 * repel each other. The result is deterministic (same data, same picture).
 */
class ilUdfNetworkGraph
{
    private const WIDTH = 960;
    private const HEIGHT = 640;
    private const PADDING = 80;

    private const DOT_RADIUS = 4;
    private const COLOR_RING = '#c95100';
    private const COLOR_RING_FILL = '#f7a64a';
    private const COLOR_LINE = '#a94208';

    /**
     * @param array<int, array{label: string, tooltip: string, posts: int}> $nodes user id => node
     * @param array<int, array{0: int, 1: int, 2: int, 3: string}>          $edges [user a, user b, weight, tooltip]
     */
    public function __construct(private array $nodes, private array $edges)
    {
    }

    /**
     * Merges directed reply counts (a replied to b) into undirected weights.
     *
     * @param array<int, array{0: int, 1: int, 2: int}> $interactions [replier, author, number]
     * @return array<string, array{0: int, 1: int, 2: int}> "a-b" => [a, b, weight] with a < b
     */
    public static function mergeInteractions(array $interactions): array
    {
        $edges = [];
        foreach ($interactions as [$from, $to, $count]) {
            if ($from === $to) {
                continue;
            }
            $a = min($from, $to);
            $b = max($from, $to);
            $key = $a . '-' . $b;
            $edges[$key] = [$a, $b, ($edges[$key][2] ?? 0) + $count];
        }
        return $edges;
    }

    public static function ringRadius(int $posts): float
    {
        // grows visibly with every post: 1 post = 10, 5 posts = 26, 10 posts = 46 (max. 56)
        return $posts > 0 ? (float) min(6 + 4 * $posts, 56) : 0.0;
    }

    public static function lineWidth(int $weight): float
    {
        return min(1.5 + ($weight - 1) * 1.5, 16);
    }

    /**
     * @return array<int, array{0: float, 1: float}> user id => [x, y]
     */
    public function layout(): array
    {
        $ids = array_keys($this->nodes);
        sort($ids);
        $n = count($ids);
        if ($n === 0) {
            return [];
        }

        $cx = self::WIDTH / 2;
        $cy = self::HEIGHT / 2;
        if ($n === 1) {
            return [$ids[0] => [$cx, $cy]];
        }

        // start: on a circle (deterministic)
        $pos = [];
        $radius = min(self::WIDTH, self::HEIGHT) / 2 - self::PADDING;
        foreach ($ids as $i => $id) {
            $angle = 2 * M_PI * $i / $n;
            $pos[$id] = [$cx + $radius * cos($angle), $cy + $radius * sin($angle)];
        }

        $width = self::WIDTH - 2 * self::PADDING;
        $height = self::HEIGHT - 2 * self::PADDING;
        $k = 1.05 * sqrt($width * $height / $n);
        $iterations = $n > 150 ? 120 : 300;
        $temperature = $width / 8;

        for ($it = 0; $it < $iterations; $it++) {
            $disp = array_fill_keys($ids, [0.0, 0.0]);

            // repulsion between all people
            for ($i = 0; $i < $n; $i++) {
                $a = $ids[$i];
                for ($j = $i + 1; $j < $n; $j++) {
                    $b = $ids[$j];
                    $dx = $pos[$a][0] - $pos[$b][0];
                    $dy = $pos[$a][1] - $pos[$b][1];
                    $dist = max(sqrt($dx * $dx + $dy * $dy), 0.01);
                    $force = $k * $k / $dist;
                    $fx = $dx / $dist * $force;
                    $fy = $dy / $dist * $force;
                    $disp[$a][0] += $fx;
                    $disp[$a][1] += $fy;
                    $disp[$b][0] -= $fx;
                    $disp[$b][1] -= $fy;
                }
            }

            // attraction along the lines (stronger with more replies)
            foreach ($this->edges as [$a, $b, $weight]) {
                if (!isset($pos[$a], $pos[$b])) {
                    continue;
                }
                $dx = $pos[$a][0] - $pos[$b][0];
                $dy = $pos[$a][1] - $pos[$b][1];
                $dist = max(sqrt($dx * $dx + $dy * $dy), 0.01);
                $force = $dist * $dist / $k * min(1 + 0.25 * ($weight - 1), 3);
                $fx = $dx / $dist * $force;
                $fy = $dy / $dist * $force;
                $disp[$a][0] -= $fx;
                $disp[$a][1] -= $fy;
                $disp[$b][0] += $fx;
                $disp[$b][1] += $fy;
            }

            // a little gravity to the centre (keeps people without lines in the picture)
            foreach ($ids as $id) {
                $disp[$id][0] -= ($pos[$id][0] - $cx) * 0.03 * $k / 50;
                $disp[$id][1] -= ($pos[$id][1] - $cy) * 0.03 * $k / 50;
            }

            // move, limited by the temperature, and keep inside the picture
            foreach ($ids as $id) {
                $len = max(sqrt($disp[$id][0] ** 2 + $disp[$id][1] ** 2), 0.01);
                $step = min($len, $temperature);
                $pos[$id][0] = min(max($pos[$id][0] + $disp[$id][0] / $len * $step, self::PADDING), self::WIDTH - self::PADDING);
                $pos[$id][1] = min(max($pos[$id][1] + $disp[$id][1] / $len * $step, self::PADDING), self::HEIGHT - self::PADDING);
            }

            $temperature = max($temperature * 0.97, 1.0);
        }

        return $pos;
    }

    public function toSvg(string $title): string
    {
        $pos = $this->layout();

        $svg = '<svg class="udf-network" viewBox="0 0 ' . self::WIDTH . ' ' . self::HEIGHT . '"'
            . ' xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' . self::esc($title) . '">'
            . '<title>' . self::esc($title) . '</title>';

        // lines first, thicker lines on top of thinner ones
        $edges = $this->edges;
        usort($edges, static fn(array $x, array $y): int => $x[2] <=> $y[2]);
        foreach ($edges as [$a, $b, $weight, $tooltip]) {
            if (!isset($pos[$a], $pos[$b])) {
                continue;
            }
            $svg .= '<line x1="' . self::num($pos[$a][0]) . '" y1="' . self::num($pos[$a][1]) . '"'
                . ' x2="' . self::num($pos[$b][0]) . '" y2="' . self::num($pos[$b][1]) . '"'
                . ' stroke="' . self::COLOR_LINE . '" stroke-opacity="0.45" stroke-linecap="round"'
                . ' stroke-width="' . self::num(self::lineWidth($weight)) . '">'
                . '<title>' . self::esc($tooltip) . '</title></line>';
        }

        // people: circle (grows with the posts), black dot, name
        foreach ($pos as $id => [$x, $y]) {
            $node = $this->nodes[$id];
            $ring = self::ringRadius($node['posts']);
            $svg .= '<g class="udf-network-node"><title>' . self::esc($node['tooltip']) . '</title>';
            if ($ring > 0) {
                $svg .= '<circle cx="' . self::num($x) . '" cy="' . self::num($y) . '" r="' . self::num($ring) . '"'
                    . ' fill="' . self::COLOR_RING_FILL . '" fill-opacity="0.35" stroke="' . self::COLOR_RING . '" stroke-width="2"/>';
            }
            $svg .= '<circle cx="' . self::num($x) . '" cy="' . self::num($y) . '" r="' . self::DOT_RADIUS . '" fill="#000"/>'
                . '<text x="' . self::num($x) . '" y="' . self::num($y + max($ring, self::DOT_RADIUS) + 15) . '"'
                . ' text-anchor="middle" font-size="13" font-family="inherit" fill="#222"'
                . ' stroke="#fff" stroke-width="4" stroke-linejoin="round" paint-order="stroke">'
                . self::esc($node['label']) . '</text></g>';
        }

        return $svg . '</svg>';
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
