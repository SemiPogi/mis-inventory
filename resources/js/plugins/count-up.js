export default function (Alpine) {
    Alpine.directive('count-up', (el, { expression }, { evaluate }) => {
        const target = parseFloat(el.textContent.replace(/,/g, '')) || 0;
        const duration = expression ? evaluate(expression) : 800;

        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            el.textContent = target.toLocaleString();
            return;
        }

        const start = performance.now();
        const ease = (t) => 1 - Math.pow(1 - t, 3);

        const tick = (now) => {
            const progress = Math.min(1, (now - start) / duration);
            const value = Math.round(target * ease(progress));
            el.textContent = value.toLocaleString();
            if (progress < 1) requestAnimationFrame(tick);
        };

        el.textContent = '0';
        requestAnimationFrame(tick);
    });
}
