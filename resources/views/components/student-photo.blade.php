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
            aspect-ratio: 1 / 1;
            overflow: hidden;
            border: 1px solid var(--line-2, #d7d4c8);
            border-radius: 9999px;
            background: var(--navy, #0f2050);
            color: #fff;
            font-weight: 900;
            line-height: 1;
            box-shadow: inset 0 0 0 3px rgba(255,255,255,.72), 0 1px 2px rgba(14,18,38,.06);
        }
        .cernix-passport-photo img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
            object-position: center;
            border-radius: inherit;
        }
        .cernix-passport-initials { position: relative; z-index: 0; }
        .student-avatar,
        .student-avatar-fallback { border-radius: 9999px; aspect-ratio: 1 / 1; overflow: hidden; object-fit: cover; object-position: center; }
        .student-avatar-sm,
        .cernix-passport-photo--compact { width: 40px; height: 40px; font-size: 13px; }
        .student-avatar-md { width: 56px; height: 56px; font-size: 16px; }
        .student-avatar-lg,
        .cernix-passport-photo--passport { width: 108px; height: 108px; font-size: 24px; }
        .student-avatar-xl,
        .cernix-passport-photo--profile,
        .cernix-passport-photo--admin-detail { width: 108px; height: 108px; font-size: 26px; }
        .cernix-passport-photo--scan-result { width: 120px; height: 120px; font-size: 30px; }
        @media (max-width: 520px) {
            .cernix-passport-photo--profile,
            .cernix-passport-photo--admin-detail,
            .cernix-passport-photo--passport { width: 96px; height: 96px; }
            .cernix-passport-photo--scan-result { width: 104px; height: 104px; }
        }
    </style>
@endonce

<span {{ $attributes->class(['cernix-passport-photo', 'cernix-passport-photo--' . $size]) }}>
    <span class="cernix-passport-initials" aria-label="{{ $displayName }}">{{ $initials }}</span>
    @if($photoUrl)
        <img src="{{ $photoUrl }}" alt="{{ $alt ?? $displayName }}" loading="lazy" onerror="this.remove();">
    @endif
</span>
