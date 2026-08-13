export class Toast {
    static container = null;

    static getContainer() {
        if (!Toast.container) {
            Toast.container = document.createElement('div');
            Toast.container.className = 'ToastContainer';
            document.body.appendWithSpace(Toast.container);
        }
        return Toast.container;
    }

    static show(message) {
        const container = Toast.getContainer();

        const toast = document.createElement('div');
        toast.className = 'Toast';
        toast.setAttribute('role', 'alert');

        const text = document.createElement('div');
        text.className = 'ToastMessage';

        if (message instanceof Node) {
            text.appendWithSpace(message);
        } else {
            text.textContent = message;
        }

        toast.appendWithSpace(text);

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'ToastCloseButton';
        closeButton.setAttribute('aria-label', 'Dismiss');
        closeButton.textContent = '×';
        // Bind directly – no delegation needed
        closeButton.addEventListener('click', () => Toast.dismiss(toast));
        toast.appendWithSpace(closeButton);

        container.appendWithSpace(toast);

        requestAnimationFrame(() => {
            toast.classList.add('Active');
        });

        setTimeout(() => Toast.dismiss(toast), 6000);

        return toast;
    }

    static dismiss(element) {
        const toast = element.closest?.('.Toast') || element;
        if (!toast?.classList.contains('Active')) return;

        toast.classList.remove('Active');
        toast.addEventListener('transitionend', () => toast.remove(), { once: true });
        setTimeout(() => toast.remove(), 300);
    }
}
