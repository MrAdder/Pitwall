<?php

declare(strict_types=1);

/*
 * Generates docs/images/banner.png.
 *
 *     php docs/images/banner.php
 *
 * Drawn rather than photographed, for two reasons. The colours are
 * the product's own design tokens, the same OKLCH values that
 * resources/css/app.css defines for the interface, so the banner
 * cannot drift from what it is advertising. And a banner that can be
 * regenerated is one that can be corrected.
 *
 * The values are duplicated rather than shared, because PHP cannot
 * read a CSS custom property and neither should it try. Change one,
 * change the other, and re-run this script.
 *
 * The wordmark is built from polygons rather than set in a
 * typeface. That is not a stylistic preference: this repository
 * ships no font, and a banner whose appearance depends on which
 * fonts happen to be installed on the machine that built it is not
 * reproducible.
 *
 * Everything is drawn at SUPERSAMPLE times the final size and
 * scaled down at the end, which is where the anti-aliasing comes
 * from. GD's own line drawing is either aliased or thickness-1, so
 * strokes here are polygons and the downscale does the smoothing.
 */

ini_set('memory_limit', '512M');

const WIDTH       = 2400;
const HEIGHT      = 600;
const SUPERSAMPLE = 3;

const OUTPUT = __DIR__ . '/banner.png';

/*
 * The design tokens, matching the @theme block in
 * resources/css/app.css. Kept in OKLCH so a value can be pasted
 * between the two unaltered.
 *
 * 'danger' is defined there too but is not listed here: nothing in
 * the banner is drawn in it, and a token this file never reads would
 * be one more thing to keep in step for no benefit.
 */
const TOKENS = [
    'surface'        => [0.19, 0.012, 260],
    'surface-sunken' => [0.15, 0.012, 260],
    'surface-raised' => [0.24, 0.014, 260],
    'border-subtle'  => [0.32, 0.014, 260],
    'content'        => [0.96, 0.004, 260],
    'content-muted'  => [0.72, 0.012, 260],
    'brand'          => [0.68, 0.170, 250],
    'brand-strong'   => [0.60, 0.190, 250],
    'success'        => [0.72, 0.160, 155],
    'warning'        => [0.76, 0.150, 75],
];

/**
 * OKLCH to sRGB.
 *
 * Via OKLab and linear sRGB. The matrices are Björn Ottosson's;
 * out-of-gamut results are clipped per channel, which is crude but
 * adequate because every token above is already inside sRGB.
 *
 * @return array{int, int, int}
 */
function oklch(float $lightness, float $chroma, float $hue): array
{
    $hueRadians = deg2rad($hue);

    $a = $chroma * cos($hueRadians);
    $b = $chroma * sin($hueRadians);

    $long   = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
    $medium = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
    $short  = ($lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

    $linear = [
        4.0767416621 * $long - 3.3077115913 * $medium + 0.2309699292 * $short,
        -1.2684380046 * $long + 2.6097574011 * $medium - 0.3413193965 * $short,
        -0.0041960863 * $long - 0.7034186147 * $medium + 1.7076147010 * $short,
    ];

    $srgb = [];

    foreach ($linear as $channel) {
        $channel = max(0.0, min(1.0, $channel));

        $encoded = $channel <= 0.0031308
            ? $channel * 12.92
            : 1.055 * ($channel ** (1 / 2.4)) - 0.055;

        $srgb[] = (int) round($encoded * 255);
    }

    /** @var array{int, int, int} $srgb */
    return $srgb;
}

/**
 * @return array{int, int, int}
 */
function token(string $name): array
{
    [$lightness, $chroma, $hue] = TOKENS[$name];

    return oklch($lightness, $chroma, $hue);
}

/**
 * @param array{int, int, int} $from
 * @param array{int, int, int} $to
 *
 * @return array{int, int, int}
 */
function mix(array $from, array $to, float $amount): array
{
    $amount = max(0.0, min(1.0, $amount));

    return [
        (int) round($from[0] + ($to[0] - $from[0]) * $amount),
        (int) round($from[1] + ($to[1] - $from[1]) * $amount),
        (int) round($from[2] + ($to[2] - $from[2]) * $amount),
    ];
}

/*
 * ---------------------------------------------------------------
 * The alphabet
 * ---------------------------------------------------------------
 *
 * Drawn on a 140-unit cap height. Every glyph is a list of convex
 * polygons in that space, so no counter is a hole punched into a
 * shape — the bowl of a P is a ring of four quads with the counter
 * simply left unfilled. That matters because the background is a
 * gradient: anything painted to hide a mistake would show.
 *
 * The chamfered corners are the one deliberate flourish. They read
 * as machined rather than drawn, which is the register the rest of
 * the platform sits in.
 */

const CAP = 140.0;

/**
 * @param float $x1
 * @param float $y1
 * @param float $x2
 * @param float $y2
 *
 * @return list<float>
 */
function box(float $x1, float $y1, float $x2, float $y2): array
{
    return [$x1, $y1, $x2, $y1, $x2, $y2, $x1, $y2];
}

/**
 * The band between two open paths, as a run of quads.
 *
 * Used for the bowls, where a plain rectangle would lose the
 * chamfer and a filled outline would need a hole.
 *
 * @param list<array{float, float}> $outer
 * @param list<array{float, float}> $inner
 *
 * @return list<list<float>>
 */
function band(array $outer, array $inner): array
{
    $quads = [];

    for ($i = 0; $i < count($outer) - 1; $i++) {
        $quads[] = [
            $outer[$i][0], $outer[$i][1],
            $outer[$i + 1][0], $outer[$i + 1][1],
            $inner[$i + 1][0], $inner[$i + 1][1],
            $inner[$i][0], $inner[$i][1],
        ];
    }

    return $quads;
}

/**
 * @return array{w: float, parts: list<list<float>>}
 */
function glyph(string $character): array
{
    // The bowl shared by P and R.
    $bowlOuter = [[22, 0], [74, 0], [92, 18], [92, 60], [74, 78], [22, 78]];
    $bowlInner = [[22, 22], [63, 22], [70, 29], [70, 49], [63, 56], [22, 56]];

    switch ($character) {
        case 'A':
            return ['w' => 100, 'parts' => [
                [39, 0, 61, 0, 26, 140, 0, 140],
                [39, 0, 61, 0, 100, 140, 74, 140],
                box(34, 94, 66, 116),
            ]];

        case 'E':
            return ['w' => 88, 'parts' => [
                box(0, 0, 22, 140),
                box(22, 0, 88, 22),
                box(22, 59, 76, 81),
                box(22, 118, 88, 140),
            ]];

        case 'I':
            return ['w' => 30, 'parts' => [
                box(4, 0, 26, 140),
            ]];

        case 'L':
            return ['w' => 84, 'parts' => [
                box(0, 0, 22, 140),
                box(22, 118, 84, 140),
            ]];

        case 'N':
            return ['w' => 100, 'parts' => [
                box(0, 0, 22, 140),
                box(78, 0, 100, 140),
                [0, 0, 26, 0, 100, 140, 74, 140],
            ]];

        case 'O':
            $outer = [[26, 0], [74, 0], [100, 26], [100, 114], [74, 140], [26, 140], [0, 114], [0, 26], [26, 0]];
            $inner = [[40, 22], [60, 22], [78, 40], [78, 100], [60, 118], [40, 118], [22, 100], [22, 40], [40, 22]];

            return ['w' => 100, 'parts' => band($outer, $inner)];

        case 'P':
            return ['w' => 92, 'parts' => array_merge(
                [box(0, 0, 22, 140)],
                band($bowlOuter, $bowlInner),
            )];

        case 'R':
            return ['w' => 92, 'parts' => array_merge(
                [box(0, 0, 22, 140)],
                band($bowlOuter, $bowlInner),
                [[46, 78, 68, 78, 92, 140, 70, 140]],
            )];

        case 'S':
            // No counters, so one concave outline is enough. GD
            // fills concave polygons by scanline and copes.
            return ['w' => 92, 'parts' => [[
                0, 0, 92, 0, 92, 22, 22, 22, 22, 59, 92, 59,
                92, 140, 0, 140, 0, 118, 70, 118, 70, 81, 0, 81,
            ]]];

        case 'T':
            return ['w' => 100, 'parts' => [
                box(0, 0, 100, 22),
                box(39, 22, 61, 140),
            ]];

        case 'W':
            return ['w' => 118, 'parts' => [
                [0, 0, 22, 0, 46, 140, 24, 140],
                [24, 140, 46, 140, 70, 0, 48, 0],
                [48, 0, 70, 0, 94, 140, 72, 140],
                [72, 140, 94, 140, 118, 0, 96, 0],
            ]];

        case ' ':
            return ['w' => 60, 'parts' => []];

        default:
            throw new RuntimeException("No glyph for '{$character}'.");
    }
}

/**
 * Total advance of a string, in glyph units.
 */
function measure(string $text, float $tracking): float
{
    $width  = 0.0;
    $length = strlen($text);

    for ($i = 0; $i < $length; $i++) {
        $width += glyph($text[$i])['w'];

        if ($i < $length - 1) {
            $width += $tracking;
        }
    }

    return $width;
}

/*
 * ---------------------------------------------------------------
 * Canvas
 * ---------------------------------------------------------------
 */

$canvas = imagecreatetruecolor(WIDTH * SUPERSAMPLE, HEIGHT * SUPERSAMPLE);

if ($canvas === false) {
    fwrite(STDERR, "Could not allocate the canvas.\n");
    exit(1);
}

imagealphablending($canvas, true);

/**
 * @param array{int, int, int} $rgb
 */
function colour(GdImage $image, array $rgb, float $opacity = 1.0): int
{
    $alpha = (int) round((1 - max(0.0, min(1.0, $opacity))) * 127);

    $allocated = imagecolorallocatealpha($image, $rgb[0], $rgb[1], $rgb[2], $alpha);

    if ($allocated === false) {
        throw new RuntimeException('Ran out of colours.');
    }

    return $allocated;
}

/*
 * The background, painted at final resolution and then scaled up.
 *
 * A gradient has no edges to alias, so computing it once per final
 * pixel rather than once per supersampled pixel costs nothing and
 * saves nine tenths of the work.
 */
$background = imagecreatetruecolor(WIDTH, HEIGHT);

if ($background === false) {
    fwrite(STDERR, "Could not allocate the background.\n");
    exit(1);
}

$surface = token('surface');
$sunken  = token('surface-sunken');
$brand   = token('brand');

// Where the glow sits, in final pixels. Behind the wordmark, so the
// lettering has something to lift away from.
$glowX      = WIDTH * 0.22;
$glowY      = HEIGHT * 0.42;
$glowRadius = WIDTH * 0.42;

for ($y = 0; $y < HEIGHT; $y++) {
    for ($x = 0; $x < WIDTH; $x++) {
        // Corner to corner, darkest at the bottom right, so the
        // eye starts at the wordmark.
        $diagonal = ($x / WIDTH) * 0.55 + ($y / HEIGHT) * 0.45;

        $rgb = mix($surface, $sunken, $diagonal);

        $distance = sqrt(($x - $glowX) ** 2 + ($y - $glowY) ** 2) / $glowRadius;

        if ($distance < 1.0) {
            // Squared falloff. Linear leaves a visible edge to the
            // glow, which looks like a mistake rather than light.
            $rgb = mix($rgb, $brand, (1 - $distance) ** 2 * 0.16);
        }

        imagesetpixel($background, $x, $y, imagecolorallocate($background, ...$rgb));
    }
}

imagecopyresampled(
    $canvas,
    $background,
    0, 0, 0, 0,
    WIDTH * SUPERSAMPLE, HEIGHT * SUPERSAMPLE,
    WIDTH, HEIGHT,
);

imagedestroy($background);

/*
 * From here on the coordinate system is 1200 x 300 design units,
 * scaled up on the way to the canvas. Keeping the layout in round
 * numbers makes it possible to reason about.
 */
const DESIGN_WIDTH = 1200.0;

$scale = (WIDTH * SUPERSAMPLE) / DESIGN_WIDTH;

/**
 * @param list<float> $points
 */
function polygon(GdImage $image, array $points, int $colour, float $scale): void
{
    $scaled = [];

    foreach ($points as $value) {
        $scaled[] = (int) round($value * $scale);
    }

    imagefilledpolygon($image, $scaled, $colour);
}

/**
 * A polyline, as one closed polygon.
 *
 * Drawn in a single fill on purpose. The obvious implementation —
 * a quad per segment with a disc at each joint — beads visibly
 * wherever the colour is not fully opaque, because every overlap
 * composites twice and reads as a string of dots along the line.
 * Offsetting the path by half the stroke on each side and filling
 * the result once cannot overlap itself, so it cannot bead.
 *
 * The normal at each point is the average of its neighbouring
 * segment normals, which is a mitre good enough for traces this
 * smooth; it would pinch on a hairpin, and there are none here.
 *
 * @param list<array{float, float}> $points
 */
function polyline(GdImage $image, array $points, float $thickness, int $colour, float $scale): void
{
    $count = count($points);

    if ($count < 2) {
        return;
    }

    $normals = [];

    for ($i = 0; $i < $count; $i++) {
        $previous = $points[max(0, $i - 1)];
        $next     = $points[min($count - 1, $i + 1)];

        $dx     = $next[0] - $previous[0];
        $dy     = $next[1] - $previous[1];
        $length = sqrt($dx * $dx + $dy * $dy);

        $normals[] = $length < 0.0001
            ? [0.0, 0.0]
            : [-$dy / $length * $thickness / 2, $dx / $length * $thickness / 2];
    }

    $upper = [];
    $lower = [];

    for ($i = 0; $i < $count; $i++) {
        $upper[] = $points[$i][0] + $normals[$i][0];
        $upper[] = $points[$i][1] + $normals[$i][1];

        // Built back to front, so appending it to the upper edge
        // closes the outline rather than crossing it.
        array_unshift($lower, $points[$i][1] - $normals[$i][1]);
        array_unshift($lower, $points[$i][0] - $normals[$i][0]);
    }

    polygon($image, array_merge($upper, $lower), $colour, $scale);

    // Round the two ends. Interior joins need nothing: the fill is
    // continuous across them.
    $diameter = (int) round($thickness * $scale);

    foreach ([$points[0], $points[$count - 1]] as $end) {
        imagefilledellipse(
            $image,
            (int) round($end[0] * $scale),
            (int) round($end[1] * $scale),
            $diameter,
            $diameter,
            $colour,
        );
    }
}

/**
 * @param array{int, int, int} $rgb
 */
function text(
    GdImage $image,
    string $string,
    float $x,
    float $y,
    float $capHeight,
    float $tracking,
    array $rgb,
    float $scale,
    float $opacity = 1.0,
): void {
    $unit   = $capHeight / CAP;
    $colour = colour($image, $rgb, $opacity);
    $pen    = $x;
    $length = strlen($string);

    for ($i = 0; $i < $length; $i++) {
        $glyph = glyph($string[$i]);

        foreach ($glyph['parts'] as $part) {
            $points = [];

            foreach ($part as $index => $value) {
                $points[] = ($index % 2 === 0)
                    ? $pen + $value * $unit
                    : $y + $value * $unit;
            }

            polygon($image, $points, $colour, $scale);
        }

        $pen += ($glyph['w'] + $tracking) * $unit;
    }
}

/*
 * ---------------------------------------------------------------
 * Composition
 * ---------------------------------------------------------------
 */

$content = token('content');
$muted   = token('content-muted');
$border  = token('border-subtle');
$strong  = token('brand-strong');
$success = token('success');
$warning = token('warning');

// The edge marker. A console has one; it is the cheapest way to say
// this is an instrument rather than a document.
for ($i = 0; $i < 300; $i++) {
    $shade = mix($brand, $strong, $i / 300);

    polygon($canvas, box(0, (float) $i, 9, $i + 1.0), colour($canvas, $shade), $scale);
}

$left = 66.0;

text($canvas, 'PITWALL', $left, 78.0, 86.0, 14.0, $content, $scale);

// The rule, in the brand hue, sized to the descriptor beneath it
// rather than to the wordmark above. Matching the wordmark would
// make it a second underline; matching the descriptor makes it a
// join between the two.
$descriptorTracking = 40.0;
$descriptorUnit     = 18.0 / CAP;
$descriptorWidth    = measure('IT OPERATIONS', $descriptorTracking) * $descriptorUnit;

polygon($canvas, box($left, 188.0, $left + $descriptorWidth, 190.5), colour($canvas, $brand, 0.9), $scale);

text($canvas, 'IT OPERATIONS', $left, 208.0, 18.0, $descriptorTracking, $muted, $scale);

/*
 * The telemetry panel.
 *
 * Three traces over a dot grid, which is what half the screens in
 * this platform look like. The series are deterministic sums of
 * sines rather than random walks: a walk drawn once looks like
 * noise, and this needs to look like something being measured.
 *
 * The colours are the platform's own brand, success and warning
 * tokens — the same three used by the dashboard sparklines, in the
 * same order, so nothing here introduces a fourth meaning.
 */
$plotLeft   = 545.0;
$plotRight  = 1140.0;
$plotTop    = 74.0;
$plotBottom = 232.0;

$dot = colour($canvas, $border, 0.5);

for ($gx = $plotLeft; $gx <= $plotRight; $gx += 24.5) {
    for ($gy = $plotTop; $gy <= $plotBottom; $gy += 26.3) {
        imagefilledellipse(
            $canvas,
            (int) round($gx * $scale),
            (int) round($gy * $scale),
            (int) round(2.0 * $scale),
            (int) round(2.0 * $scale),
            $dot,
        );
    }
}

// Sector boundaries. Three sectors, as on a circuit.
foreach ([1 / 3, 2 / 3] as $fraction) {
    $sx = $plotLeft + ($plotRight - $plotLeft) * $fraction;

    polygon($canvas, box($sx, $plotTop, $sx + 1.0, $plotBottom), colour($canvas, $border, 0.55), $scale);
}

$series = [
    ['colour' => $brand, 'centre' => 0.34, 'amplitude' => 0.19, 'phase' => 0.0],
    ['colour' => $success, 'centre' => 0.56, 'amplitude' => 0.15, 'phase' => 2.1],
    ['colour' => $warning, 'centre' => 0.76, 'amplitude' => 0.12, 'phase' => 4.3],
];

$samples = 62;

foreach ($series as $line) {
    $points = [];

    for ($i = 0; $i <= $samples; $i++) {
        $t = $i / $samples;

        // Three frequencies. One is a wave, two is a beat, three
        // stops looking periodic within the width available.
        $wave = sin($t * 7.1 + $line['phase']) * 0.55
            + sin($t * 13.7 + $line['phase'] * 1.7) * 0.30
            + sin($t * 23.3 + $line['phase'] * 2.3) * 0.15;

        $points[] = [
            $plotLeft + ($plotRight - $plotLeft) * $t,
            $plotTop + ($plotBottom - $plotTop) * ($line['centre'] + $wave * $line['amplitude']),
        ];
    }

    // Fully opaque, and that is what makes one fill possible. A
    // faded trace would have to be drawn in pieces, and the seams
    // between the pieces are exactly the artefact this avoids.
    polyline($canvas, $points, 2.6, colour($canvas, $line['colour']), $scale);

    // The latest reading, as a marker with a surface ring so it
    // stays legible where the traces cross.
    $last = $points[count($points) - 1];

    imagefilledellipse(
        $canvas,
        (int) round($last[0] * $scale),
        (int) round($last[1] * $scale),
        (int) round(11.0 * $scale),
        (int) round(11.0 * $scale),
        colour($canvas, $surface),
    );

    imagefilledellipse(
        $canvas,
        (int) round($last[0] * $scale),
        (int) round($last[1] * $scale),
        (int) round(7.5 * $scale),
        (int) round(7.5 * $scale),
        colour($canvas, $line['colour']),
    );
}

/*
 * ---------------------------------------------------------------
 * Output
 * ---------------------------------------------------------------
 */

$final = imagecreatetruecolor(WIDTH, HEIGHT);

if ($final === false) {
    fwrite(STDERR, "Could not allocate the output image.\n");
    exit(1);
}

imagealphablending($final, false);
imagesavealpha($final, true);

imagecopyresampled(
    $final,
    $canvas,
    0, 0, 0, 0,
    WIDTH, HEIGHT,
    WIDTH * SUPERSAMPLE, HEIGHT * SUPERSAMPLE,
);

imagedestroy($canvas);

if (! imagepng($final, OUTPUT, 9)) {
    fwrite(STDERR, "Could not write " . OUTPUT . "\n");
    exit(1);
}

imagedestroy($final);

printf("Wrote %s (%d x %d, %s)\n", OUTPUT, WIDTH, HEIGHT, number_format(filesize(OUTPUT) / 1024, 1) . ' KB');
