<?php

declare(strict_types=1);

/**
 * Colour of an Udon Forum: traditional Japanese colours (和色, wairo).
 *
 * The chosen colour is the accent of the forum (buttons, filters, links,
 * labels, charts). All colours are dark enough for white text on buttons
 * (contrast at least 4.5:1). Lighter and darker shades are derived.
 * Names come from the language file (color_<key>).
 */
class ilUdfColors
{
    public const DEFAULT = 'kaki';

    /** key => [colour, kanji] */
    public const THEMES = [
        'kaki' => ['#c95100', '柿色'],
        'ai' => ['#165e83', '藍色'],
        'hanada' => ['#1e6f8c', '縹色'],
        'tokiwa' => ['#007b43', '常盤色'],
        'kuchiba' => ['#8a6420', '朽葉'],
        'enji' => ['#b94047', '臙脂'],
        'botan' => ['#b0306a', '牡丹色'],
        'edomurasaki' => ['#745399', '江戸紫'],
        'sumi' => ['#3f3f3f', '墨'],
    ];

    public static function isValid(string $key): bool
    {
        return isset(self::THEMES[$key]);
    }

    public static function accent(string $key): string
    {
        return (self::THEMES[$key] ?? self::THEMES[self::DEFAULT])[0];
    }

    public static function kanji(string $key): string
    {
        return (self::THEMES[$key] ?? self::THEMES[self::DEFAULT])[1];
    }

    /**
     * The shades of a colour, as CSS custom properties for the forum pages.
     */
    public static function cssVariables(string $key): string
    {
        $a = self::accent($key);
        $dark = self::mix($a, '#000000', 0.18);
        $vars = [
            '--udf-a' => $a,
            '--udf-a-dark' => $dark,
            '--udf-a-darker' => self::mix($a, '#000000', 0.38),
            '--udf-a-bright' => self::mix($a, '#ffffff', 0.2),
            '--udf-a-light' => self::mix($a, '#ffffff', 0.55),
            '--udf-a-soft' => self::mix($a, '#ffffff', 0.88),
            '--udf-a-tint' => self::mix($a, '#ffffff', 0.92),
            '--udf-a-pale' => self::mix($a, '#ffffff', 0.96),
            '--udf-a-rgb' => implode(',', self::rgb($a)),
            '--udf-a-dark-rgb' => implode(',', self::rgb($dark)),
        ];
        $css = '';
        foreach ($vars as $name => $value) {
            $css .= $name . ':' . $value . ';';
        }
        return ':root{' . $css . '}';
    }

    /**
     * Mixes a colour with another one ($share = share of the other colour).
     */
    public static function mix(string $color, string $with, float $share): string
    {
        $a = self::rgb($color);
        $b = self::rgb($with);
        $mixed = [];
        for ($i = 0; $i < 3; $i++) {
            $mixed[] = (int) round($a[$i] * (1 - $share) + $b[$i] * $share);
        }
        return sprintf('#%02x%02x%02x', ...$mixed);
    }

    /**
     * Text colour that is readable on the given background: white if the
     * contrast is at least 4.5:1, otherwise a dark shade of the background.
     */
    public static function textOn(string $background): string
    {
        $luminance = 0.0;
        foreach (self::rgb($background) as $i => $channel) {
            $c = $channel / 255;
            $c = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            $luminance += [0.2126, 0.7152, 0.0722][$i] * $c;
        }
        return 1.05 / ($luminance + 0.05) >= 4.5 ? '#ffffff' : self::mix($background, '#000000', 0.72);
    }

    /**
     * @return int[] [r, g, b]
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /**
     * Small round colour sample for the settings.
     */
    public static function swatch(string $key): string
    {
        return '<span style="display:inline-block;width:1.1em;height:1.1em;border-radius:50%;vertical-align:-0.15em;'
            . 'margin-right:0.4em;background:' . self::accent($key) . '"></span>';
    }
}
