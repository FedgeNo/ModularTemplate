import { ReadyHandler } from '/scripts/ReadyHandler.js';

/**
 * Tells assistive tech whether the mobile menu is open.
 *
 * The menu is a checkbox and a label: the CSS reveals the stacked links while
 * the box is checked, so navigation works with no JavaScript at all. What it
 * cannot do is say so - CSS has no way to set aria-expanded, and a control
 * that opens something is supposed to announce whether it is currently open.
 *
 * So this adds only that. It reads the checkbox and mirrors it; it never
 * intercepts the click, never moves the state anywhere else, and never touches
 * the class the CSS keys on. With this file missing, or broken, or blocked,
 * the menu behaves exactly as it did before - which is the whole point of
 * doing it this way rather than rebuilding the menu in JavaScript.
 */
export class NavMenu {
    static init() {
        const toggle = document.getElementById('NavToggle');

        if (!toggle) return;

        const reflect = () => toggle.setAttribute('aria-expanded', toggle.checked ? 'true' : 'false');

        reflect();

        // change rather than click: the label, the keyboard and anything else
        // that flips a checkbox all raise it, and none of them are this
        // module's business to know about.
        toggle.addEventListener('change', reflect);
    }
}

ReadyHandler.add(NavMenu.init);
