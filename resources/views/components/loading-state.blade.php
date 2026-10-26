@props([
    'preset'    => 'general',
    'messages'  => null,
    'delay'     => 800,
    'target'    => null,
    'inline'    => false,
])

@php
    $presets = [
        'general' => [
            'Working on it…',
            'Waking the service',
            'Almost ready',
            'A few more seconds',
        ],
        'import' => [
            'Reading your file',
            'Validating rows',
            'Matching student records',
            'Saving to the registry',
            'Finalising the import',
        ],
        'qr' => [
            'Verifying your eligibility',
            'Signing your exam pass',
            'Generating the QR code',
            'Preparing your pass',
        ],
        'upload' => [
            'Uploading your photo',
            'Checking the image',
            'Saving to your record',
            'One moment',
        ],
        'export' => [
            'Gathering records',
            'Formatting the report',
            'Compiling the export',
            'Almost done',
        ],
    ];

    $resolved = is_array($messages) && count($messages) > 0
        ? array_values($messages)
        : ($presets[$preset] ?? $presets['general']);

    $delayMs = max(0, (int) $delay);
    $id      = 'cx-loading-' . substr(md5(uniqid('', true)), 0, 8);
    $encoded = json_encode(array_values($resolved), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp

<div id="{{ $id }}"
     class="cx-loading-state"
     data-cx-loading
     data-delay="{{ $delayMs }}"
     data-target="{{ $target }}"
     data-messages='{{ $encoded }}'
     role="status"
     aria-live="polite"
     hidden
     style="display:none;align-items:center;gap:10px;padding:{{ $inline ? '2px 0' : '10px 14px' }};font-size:12.5px;color:var(--ink-3);">
    <span class="dots" aria-hidden="true"><span></span><span></span><span></span></span>
    <span data-cx-loading-text>{{ $resolved[0] }}</span>
</div>

@once
    <script>
    (function () {
        if (window.__cxLoadingInit) return;
        window.__cxLoadingInit = true;

        function activate(node) {
            if (!node || node.__cxActive) return;
            node.__cxActive = true;
            var messages;
            try { messages = JSON.parse(node.getAttribute('data-messages') || '[]'); }
            catch (e) { messages = []; }
            if (!messages.length) messages = ['Working on it…'];
            var textEl = node.querySelector('[data-cx-loading-text]');
            var delay  = parseInt(node.getAttribute('data-delay') || '800', 10);
            var idx    = 0;
            node.__cxTimer = setTimeout(function () {
                node.hidden = false;
                node.style.display = 'inline-flex';
                if (textEl) textEl.textContent = messages[0];
                node.__cxInterval = setInterval(function () {
                    idx = (idx + 1) % messages.length;
                    if (textEl) textEl.textContent = messages[idx];
                }, 1000);
            }, delay);
        }

        function reset(node) {
            if (!node) return;
            if (node.__cxTimer)    { clearTimeout(node.__cxTimer); node.__cxTimer = null; }
            if (node.__cxInterval) { clearInterval(node.__cxInterval); node.__cxInterval = null; }
            node.__cxActive = false;
            node.hidden = true;
            node.style.display = 'none';
        }

        function findNodeFor(el) {
            if (!el) return null;
            var target = el.getAttribute('data-cx-loading-for');
            if (target) return document.getElementById(target);
            var form = el.tagName === 'FORM' ? el : el.closest('form');
            if (form) {
                var inside = form.querySelector('[data-cx-loading]');
                if (inside) return inside;
            }
            return null;
        }

        document.addEventListener('submit', function (ev) {
            var form = ev.target;
            if (form && !ev.defaultPrevented) {
                var btns = form.querySelectorAll('button[type="submit"], input[type="submit"], .admin-action:not([type="button"]):not([href])');
                setTimeout(function () {
                    btns.forEach(function (btn) {
                        btn.disabled = true;
                        btn.style.opacity = '0.65';
                        btn.style.cursor = 'not-allowed';
                        btn.setAttribute('aria-busy', 'true');
                    });
                }, 10);
            }
            var node = findNodeFor(ev.target);
            if (node) activate(node);
        }, true);

        document.addEventListener('click', function (ev) {
            var trigger = ev.target.closest('[data-cx-loading-trigger]');
            if (!trigger) return;
            var node = findNodeFor(trigger);
            if (node) activate(node);
        }, true);

        window.cxLoading = { activate: activate, reset: reset };
    })();
    </script>
@endonce
