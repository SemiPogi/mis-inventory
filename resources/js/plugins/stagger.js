export default function (Alpine) {
    Alpine.magic('stagger', () => (el) => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const children = Array.from(el.children);
        children.forEach((child, i) => {
            child.style.animationDelay = `${i * 100}ms`;
            child.classList.add('animate-slide-up');
        });
    });
}
