export class ClickHandler {
    /**
     * @param {{ selector: string, handler: (el: Element, event: Event) => void }[]} handlers
     */
    static init(handlers) {
        document.addEventListener('click', (event) => {
            for (const { selector, handler } of handlers) {
                const target = event.target.closest(selector);
                if (target) {
                    handler(target, event);
                    return; // only first matching handler runs
                }
            }
        });
    }
}
