@props([
    'student' => null,
    'name' => null,
    'photoPath' => null,
    'size' => 'profile',
    'alt' => null,
])

@php
    $displayName = $name
        ?? data_get($student, 'full_name')
        ?? data_get($student, 'student_name')
        ?? 'Student';
    $path = $photoPath ?? data_get($student, 'photo_path');
    $photoUrl = null;

    if ($path) {
        $normalized = ltrim(str_replace('\\', '/', trim((string) $path)), '/');
        if ($normalized !== '' && ! str_contains($normalized, '..') && ! preg_match('/^https?:/i', $normalized)) {
            $encoded = collect(explode('/', $normalized))
                ->filter()
                ->map(fn ($segment) => rawurlencode($segment))
                ->implode('/');
            $photoUrl = url('/photo-thumb/' . $encoded);
        }
    }

    $initials = collect(explode(' ', $displayName))
        ->filter()
        ->take(2)
        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
        ->implode('') ?: 'ST';
@endphp

@once
    <style>
        .cernix-passport-photo {
            position: relative;
            display: inline-grid;
            place-items: center;
            flex: 0 0 auto;
            aspect-ratio: 3 / 4;
            overflow: hidden;
            border: 1px solid var(--line-2, #d7d4c8);
            border-radius: 14px;
            background: linear-gradient(180deg, #f7f7f3, #ecebe3);
            color: var(--ink-3, #6b7085);
            font-weight: 900;
            line-height: 1;
            box-shadow: inset 0 0 0 4px rgba(255,255,255,.64), 0 1px 2px rgba(14,18,38,.06);
        }
        .cernix-passport-photo img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
            object-position: center;
        }
        .cernix-passport-initials { position: relative; z-index: 0; }
        .cernix-passport-photo--compact { width: 48px; height: 64px; border-radius: 10px; font-size: 14px; }
        .cernix-passport-photo--passport { width: 72px; height: 96px; border-radius: 12px; font-size: 18px; }
        .cernix-passport-photo--profile { width: 92px; height: 122px; border-radius: 16px; font-size: 24px; }
        .cernix-passport-photo--scan-result { width: 106px; height: 140px; border-radius: 18px; font-size: 28px; }
        .cernix-passport-photo--admin-detail { width: 104px; height: 138px; border-radius: 18px; font-size: 28px; }
        @media (max-width: 520px) {
            .cernix-passport-photo--profile,
            .cernix-passport-photo--admin-detail { width: 82px; height: 110px; }
            .cernix-passport-photo--scan-result { width: 82px; height: 108px; }
        }
    </style>
@endonce

<span {{ $attributes->class(['cernix-passport-photo', 'cernix-passport-photo--' . $size]) }}>
    <span class="cernix-passport-initials" aria-label="{{ $displayName }}">{{ $initials }}</span>
    @if($photoUrl)
        <img src="{{ $photoUrl }}" alt="{{ $alt ?? $displayName }}" loading="lazy" onerror="this.remove();">
    @endif
</span>
