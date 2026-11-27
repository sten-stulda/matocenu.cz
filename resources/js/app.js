/*
 | MáToCenu.cz – small Alpine helpers. Alpine itself ships with Livewire.
 */

// Theme: "light" | "dark" | "system" in localStorage (per-device convenience only).
const applyTheme = () => {
    let pref = 'system';
    try { pref = localStorage.getItem('mtc-theme') || 'system'; } catch (e) {}
    const dark = pref === 'dark' || (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
};
window.mtcSetTheme = (pref) => {
    try { localStorage.setItem('mtc-theme', pref); } catch (e) {}
    applyTheme();
};
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', applyTheme);
document.addEventListener('livewire:navigated', applyTheme);

// Unicode-aware length (grapheme clusters ≈ what people see; server validates code points).
const graphemeLength = (s) => {
    if (typeof Intl !== 'undefined' && Intl.Segmenter) {
        return [...new Intl.Segmenter('cs', { granularity: 'grapheme' }).segment(s)].length;
    }
    return [...s].length;
};
window.mtcLength = graphemeLength;

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    Alpine.data('theme', () => ({
        pref: (() => { try { return localStorage.getItem('mtc-theme') || 'system'; } catch (e) { return 'system'; } })(),
        set(p) { this.pref = p; window.mtcSetTheme(p); },
        toggle() { this.set(document.documentElement.classList.contains('dark') ? 'light' : 'dark'); },
    }));

    // Countdown to an ISO timestamp, e.g. next question of the day.
    Alpine.data('countdown', (target) => ({
        left: '',
        timer: null,
        init() { this.tick(); this.timer = setInterval(() => this.tick(), 1000); },
        destroy() { clearInterval(this.timer); },
        tick() {
            let s = Math.max(0, Math.floor((new Date(target).getTime() - Date.now()) / 1000));
            const h = String(Math.floor(s / 3600)).padStart(2, '0');
            const m = String(Math.floor((s % 3600) / 60)).padStart(2, '0');
            const sec = String(s % 60).padStart(2, '0');
            this.left = `${h}:${m}:${sec}`;
        },
    }));

    // Live character counter bound to a textarea via x-model.
    Alpine.data('charCounter', (max) => ({
        max,
        text: '',
        get count() { return graphemeLength(this.text); },
        get over() { return this.count > this.max; },
    }));

    // Copy-to-clipboard / native share.
    Alpine.data('share', (url, title) => ({
        copied: false,
        async go() {
            if (navigator.share) {
                try { await navigator.share({ url, title }); return; } catch (e) { /* cancelled */ }
            }
            try { await navigator.clipboard.writeText(url); this.copied = true; setTimeout(() => (this.copied = false), 2000); } catch (e) {}
        },
    }));
});
