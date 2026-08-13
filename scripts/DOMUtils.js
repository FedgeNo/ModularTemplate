export class DOMUtils {
    /**
     * Slide out an element (remove with animation).
     * @param {HTMLElement} element – any node inside the item to remove
     */
    static slideOut(element) {
        if (!element) return;
        const item = element.closest('li') || element;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            item.remove();
            return;
        }
        item.style.height = item.getBoundingClientRect().height + 'px';
        item.classList.add('SlidingOut');
        void item.offsetHeight; // force reflow
        item.style.height = '0';
        setTimeout(() => item.remove(), 250); // original SLIDE_OUT_MS (200) + 50
    }
}
