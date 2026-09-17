{{--
    Hop — the Designer mark as a loader. The circle crouches, jumps off the
    square and lands with a little squash, 1.6s a loop. SMIL animates the one
    evenodd path, so it needs no JS and keeps running inside x-show and
    Livewire morphs. Size it like an icon: <x-studio::mark-loader class="h-4 w-4"/>
--}}
@php
    $f = fn (float $n) => rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
    // square 50×50 r5 at 0,25; the circle sits on its top edge, raised by $h and squashed by $sq
    $frame = function (float $h, float $sq) use ($f) {
        $ry = 25 * (1 - 0.14 * $sq);
        $rx = 25 * (1 + 0.08 * $sq);
        $cy = 50 - $ry - $h;

        return 'M5 25H45A5 5 0 0 1 50 30V70A5 5 0 0 1 45 75H5A5 5 0 0 1 0 70V30A5 5 0 0 1 5 25Z'
            ."M{$f(47 - $rx)} {$f($cy)}A{$f($rx)} {$f($ry)} 0 1 1 {$f(47 + $rx)} {$f($cy)}A{$f($rx)} {$f($ry)} 0 1 1 {$f(47 - $rx)} {$f($cy)}Z";
    };
    // [time, height, squash, spline into this key]: rise, fall, squash, recover, rest, small settle
    $keys = [
        [0, 0, 0, null],
        [0.25, 13, 0, '0.33 0.67 0.67 1'],
        [0.5, 0, 0, '0.33 0 0.67 0.33'],
        [0.6, 0, 1, '0.39 0.575 0.565 1'],
        [0.7, 0, 0, '0.47 0 0.745 0.715'],
        [0.86, 0, 0, '0 0 1 1'],
        [0.93, 0, 0.55, '0.39 0.575 0.565 1'],
        [1, 0, 0, '0.47 0 0.745 0.715'],
    ];
    $values = implode(';', array_map(fn ($k) => $frame($k[1], $k[2]), $keys));
    $keyTimes = implode(';', array_column($keys, 0));
    $keySplines = implode(';', array_filter(array_column($keys, 3)));
@endphp
<svg {{ $attributes }} viewBox="-9 -14 90 90" fill="none" role="img" aria-label="Loading">
    <path fill="currentColor" fill-rule="evenodd" d="{{ $frame(0, 0) }}">
        <animate attributeName="d" dur="1.6s" repeatCount="indefinite" calcMode="spline" keyTimes="{{ $keyTimes }}" keySplines="{{ $keySplines }}" values="{{ $values }}"/>
    </path>
</svg>
