export class ReadyHandler {
    static #tasks = [];
    static #fired = false;

    // Run the queued tasks when the DOM is ready.
    static {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () =>
                ReadyHandler.#runAll()
            );
        } else {
            ReadyHandler.#runAll();
        }
    }

    /**
     * Register a function to be called when the DOM is ready.
     * If the DOM is already ready, the function is invoked immediately.
     *
     * @param {() => void} fn
     */
    static add(fn) {
        if (ReadyHandler.#fired) {
            fn();
        } else {
            ReadyHandler.#tasks.push(fn);
        }
    }

    static #runAll() {
        ReadyHandler.#fired = true;
        ReadyHandler.#tasks.forEach((fn) => fn());
        ReadyHandler.#tasks = []; // release references
    }
}
