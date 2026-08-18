<?php

namespace App\Support;

/**
 * A company's chosen colour, and the one the app is actually painted in.
 *
 * The two are not always the same. Every dark surface in the app carries white
 * text — table headers, buttons, the top bar — so a colour chosen for a logo
 * can be perfectly good and still leave a heading unreadable. Rather than
 * refuse a pale yellow, this darkens it until white text on it clears WCAG's
 * 4.5:1 and paints with that, keeping the hue the company picked. What they
 * chose is what is stored; what is legible is what is served.
 *
 * The correction happens **here and only here**, server-side, so the browser is
 * handed a single base colour and derives the ten steps from it in CSS. A
 * second copy of this arithmetic in TypeScript would be a second answer.
 *
 * Everything else — the ten `--color-coffee-*` steps — is a `color-mix()` off
 * that base, which is why a rebrand is still one value and nothing else.
 */
final class BrandColor
{
    /**
     * The house colour: `coffee-500`, the middle of the default scale.
     *
     * Offered as the starting point on the settings form, so somebody nudging
     * the sliders begins from what they can already see rather than from black.
     */
    public const DEFAULT = '#8b6244';

    /**
     * The lightest a colour may be and still carry white text at 4.5:1.
     *
     * Contrast against white is `1.05 / (L + 0.05)`, so 4.5:1 is reached at
     * `L = 1.05/4.5 - 0.05`. Kept as the derived figure rather than a rounded
     * one, because a hair over is still a fail.
     */
    private const MAX_LUMINANCE = 1.05 / 4.5 - 0.05;

    /**
     * Encode a red/green/blue triple as `#rrggbb`.
     *
     * Each channel is clamped rather than rejected: the form has already
     * validated 0–255, and a colour is not worth a 500 if something slipped
     * past.
     */
    public static function fromRgb(int $red, int $green, int $blue): string
    {
        return sprintf(
            '#%02x%02x%02x',
            max(0, min(255, $red)),
            max(0, min(255, $green)),
            max(0, min(255, $blue)),
        );
    }

    /**
     * Decode `#rrggbb` back into the three numbers the settings form edits.
     *
     * @return array{red: int, green: int, blue: int}
     */
    public static function toRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            'red' => (int) hexdec(substr($hex, 0, 2)),
            'green' => (int) hexdec(substr($hex, 2, 2)),
            'blue' => (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * The colour the app is painted in: the chosen one, darkened only as far as
     * white text on it demands.
     *
     * Luminance is linear in the linear-light channels, so the factor that
     * brings it to the ceiling is a single division — no searching, and the
     * ratio between the channels (and so the hue) is left alone.
     */
    public static function applied(string $hex): string
    {
        ['red' => $red, 'green' => $green, 'blue' => $blue] = self::toRgb($hex);

        $linear = [
            self::toLinear($red),
            self::toLinear($green),
            self::toLinear($blue),
        ];

        $luminance = 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];

        if ($luminance <= self::MAX_LUMINANCE || $luminance <= 0.0) {
            return self::normalize($hex);
        }

        $factor = self::MAX_LUMINANCE / $luminance;

        return self::fromRgb(
            self::toSrgb($linear[0] * $factor),
            self::toSrgb($linear[1] * $factor),
            self::toSrgb($linear[2] * $factor),
        );
    }

    /**
     * `#8B6244` and `8b6244` are the same colour; store one of them.
     */
    public static function normalize(string $hex): string
    {
        return '#'.strtolower(ltrim($hex, '#'));
    }

    /**
     * sRGB channel (0–255) to linear light (0–1).
     */
    private static function toLinear(int $channel): float
    {
        $value = $channel / 255;

        return $value <= 0.04045
            ? $value / 12.92
            : (($value + 0.055) / 1.055) ** 2.4;
    }

    /**
     * Linear light (0–1) back to an sRGB channel (0–255).
     */
    private static function toSrgb(float $value): int
    {
        $value = max(0.0, min(1.0, $value));

        $channel = $value <= 0.0031308
            ? $value * 12.92
            : 1.055 * $value ** (1 / 2.4) - 0.055;

        return (int) round($channel * 255);
    }
}
