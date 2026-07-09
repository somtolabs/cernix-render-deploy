@props([
    'size'    => 36,
    'tone'    => 'auto',
    'radius'  => 10,
    'alt'     => null,
])

@php
    $logoUrl = $brandingLogoUrl ?? '';
    $inst    = $brandingInstitutionName ?? '';
    $sys     = $brandingSystemName ?? '';
    $label   = $alt ?? ($inst ?: $sys ?: 'Brand');

    $letters = (function ($str) {
        $str = trim((string) $str);
        if ($str === '') return '';
        $parts = preg_split('/\s+/', $str);
        $first = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1));
        $second = mb_strtoupper(mb_substr($parts[1] ?? '', 0, 1));
        return $second !== '' ? $first . $second : $first;
    })($inst ?: $sys);

    $sizePx   = is_numeric($size) ? (int) $size : 36;
    $radiusPx = is_numeric($radius) ? (int) $radius : 10;
    $isDark   = $tone === 'dark';
    $isLight  = $tone === 'light';

    $wrapBg     = $isDark ? 'rgba(255,255,255,0.06)' : 'transparent';
    $wrapBorder = $isDark ? '1px solid rgba(255,255,255,0.12)' : '1px solid var(--line, #e6e2d8)';
    $textCol    = $isDark ? 'rgba(255,255,255,0.9)' : 'var(--navy, #2d3f55)';
@endphp

<span class="brand-mark"
      style="display:inline-flex;align-items:center;justify-content:center;flex:0 0 {{ $sizePx }}px;width:{{ $sizePx }}px;height:{{ $sizePx }}px;border-radius:{{ $radiusPx }}px;background:{{ $wrapBg }};border:{{ $wrapBorder }};overflow:hidden;line-height:0;"
      aria-label="{{ $label }}"
      role="img">
    @if($logoUrl !== '')
        <img src="{{ $logoUrl }}"
             alt="{{ $label }}"
             style="max-width:{{ (int) round($sizePx * 0.82) }}px;max-height:{{ (int) round($sizePx * 0.82) }}px;width:auto;height:auto;object-fit:contain;display:block;">
    @else
        <span style="font-family:'Inter',system-ui,sans-serif;font-weight:800;font-size:{{ max(10, (int) round($sizePx * 0.36)) }}px;letter-spacing:-0.02em;color:{{ $textCol }};line-height:1;">
            {{ $letters ?: '·' }}
        </span>
    @endif
</span>
