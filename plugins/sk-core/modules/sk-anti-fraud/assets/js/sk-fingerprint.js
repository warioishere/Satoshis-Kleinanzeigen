/**
 * SK Fingerprint — Collects browser fingerprint signals and sends to server.
 * Runs once per session after login.
 */
(function () {
    'use strict';

    if (typeof skFP === 'undefined') return;
    if (sessionStorage.getItem('sk_fp_sent')) return;

    // ── SHA-256 hash helper ──
    async function sha256(str) {
        var buf = new TextEncoder().encode(str);
        var hash = await crypto.subtle.digest('SHA-256', buf);
        return Array.from(new Uint8Array(hash)).map(function (b) { return b.toString(16).padStart(2, '0'); }).join('');
    }

    // ── Canvas fingerprint ──
    function getCanvasHash() {
        try {
            var canvas = document.createElement('canvas');
            canvas.width = 200;
            canvas.height = 50;
            var ctx = canvas.getContext('2d');
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillStyle = '#f60';
            ctx.fillRect(10, 1, 62, 20);
            ctx.fillStyle = '#069';
            ctx.fillText('SK-FP-2026', 2, 15);
            ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
            ctx.fillText('SK-FP-2026', 4, 17);
            ctx.beginPath();
            ctx.arc(50, 30, 10, 0, Math.PI * 2);
            ctx.fill();
            return canvas.toDataURL();
        } catch (e) {
            return '';
        }
    }

    // ── WebGL fingerprint ──
    function getWebGLHash() {
        try {
            var canvas = document.createElement('canvas');
            var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (!gl) return '';
            var debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
            var renderer = debugInfo ? gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) : '';
            var vendor = debugInfo ? gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL) : '';
            var maxTexture = gl.getParameter(gl.MAX_TEXTURE_SIZE);
            var extensions = gl.getSupportedExtensions() || [];
            return vendor + '|' + renderer + '|' + maxTexture + '|' + extensions.length;
        } catch (e) {
            return '';
        }
    }

    // ── Audio fingerprint ──
    function getAudioHash() {
        return new Promise(function (resolve) {
            try {
                var AudioContext = window.OfflineAudioContext || window.webkitOfflineAudioContext;
                if (!AudioContext) { resolve(''); return; }
                var ctx = new AudioContext(1, 44100, 44100);
                var osc = ctx.createOscillator();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(10000, ctx.currentTime);
                var comp = ctx.createDynamicsCompressor();
                comp.threshold.setValueAtTime(-50, ctx.currentTime);
                comp.knee.setValueAtTime(40, ctx.currentTime);
                comp.ratio.setValueAtTime(12, ctx.currentTime);
                comp.attack.setValueAtTime(0, ctx.currentTime);
                comp.release.setValueAtTime(0.25, ctx.currentTime);
                osc.connect(comp);
                comp.connect(ctx.destination);
                osc.start(0);
                ctx.startRendering();
                ctx.oncomplete = function (event) {
                    try {
                        var data = event.renderedBuffer.getChannelData(0);
                        var sum = 0;
                        for (var i = 4500; i < 5000; i++) { sum += Math.abs(data[i]); }
                        resolve(sum.toString());
                    } catch (e) {
                        resolve('');
                    }
                };
            } catch (e) {
                resolve('');
            }
        });
    }

    // ── Font detection ──
    function getFontsHash() {
        var testFonts = [
            'Arial', 'Verdana', 'Times New Roman', 'Courier New', 'Georgia',
            'Comic Sans MS', 'Impact', 'Trebuchet MS', 'Lucida Console',
            'Palatino Linotype', 'Tahoma', 'Segoe UI', 'Roboto', 'Helvetica Neue',
            'Fira Sans', 'Ubuntu', 'Droid Sans', 'Calibri', 'Cambria',
            'Consolas', 'Monaco', 'Liberation Mono', 'Noto Sans', 'Source Code Pro',
            'Menlo', 'Cantarell', 'DejaVu Sans', 'Bitstream Vera Sans',
            'Futura', 'Garamond'
        ];
        var baseFonts = ['monospace', 'sans-serif', 'serif'];
        var testString = 'mmmmmmmmmmlli';
        var testSize = '72px';
        var detected = '';

        var body = document.body;
        var span = document.createElement('span');
        span.style.fontSize = testSize;
        span.style.position = 'absolute';
        span.style.left = '-9999px';
        span.style.top = '-9999px';
        span.textContent = testString;
        body.appendChild(span);

        var baseWidths = {};
        baseFonts.forEach(function (f) {
            span.style.fontFamily = f;
            baseWidths[f] = span.offsetWidth;
        });

        testFonts.forEach(function (font) {
            var found = false;
            baseFonts.forEach(function (base) {
                span.style.fontFamily = '"' + font + '",' + base;
                if (span.offsetWidth !== baseWidths[base]) found = true;
            });
            detected += found ? '1' : '0';
        });

        body.removeChild(span);
        return detected;
    }

    // ── Collect and send ──
    async function collect() {
        var canvasRaw = getCanvasHash();
        var webglRaw = getWebGLHash();
        var audioRaw = await getAudioHash();
        var fontsRaw = getFontsHash();

        var canvasHash = await sha256(canvasRaw);
        var webglHash = await sha256(webglRaw);
        var audioHash = await sha256(audioRaw);
        var fontsHash = await sha256(fontsRaw);

        var components = [
            canvasHash,
            webglHash,
            audioHash,
            fontsHash,
            Intl.DateTimeFormat().resolvedOptions().timeZone || '',
            screen.width + 'x' + screen.height + 'x' + screen.colorDepth,
            navigator.language || '',
            navigator.platform || '',
            (navigator.hardwareConcurrency || 0).toString(),
            (navigator.deviceMemory || 0).toString(),
            (navigator.maxTouchPoints || 0).toString()
        ];

        var fingerprintHash = await sha256(components.join('|'));

        var data = new FormData();
        data.append('action', 'sk_collect_fingerprint');
        data.append('nonce', skFP.nonce);
        data.append('fingerprint_hash', fingerprintHash);
        data.append('canvas_hash', canvasHash);
        data.append('webgl_hash', webglHash);
        data.append('audio_hash', audioHash);
        data.append('fonts_hash', fontsHash);
        data.append('timezone', Intl.DateTimeFormat().resolvedOptions().timeZone || '');
        data.append('screen', screen.width + 'x' + screen.height + 'x' + screen.colorDepth);
        data.append('platform', navigator.platform || '');

        fetch(skFP.ajaxurl, { method: 'POST', credentials: 'same-origin', body: data })
            .then(function () { sessionStorage.setItem('sk_fp_sent', '1'); })
            .catch(function () {});
    }

    // Run after page is idle.
    if (typeof requestIdleCallback === 'function') {
        requestIdleCallback(collect);
    } else {
        setTimeout(collect, 2000);
    }
})();
