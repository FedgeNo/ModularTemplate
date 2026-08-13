/**
 * A control that is waiting on the server, saying so.
 *
 * Disabling a button stops a second press but tells the reader nothing: the
 * button greys slightly and then sits there, and on anything slow - a
 * translation is a round trip to a model, and can take many seconds - it reads
 * as a press that did not land. So a control that is working throbs while it
 * does, and says the same thing to assistive tech as aria-busy.
 *
 * Paired calls rather than a wrapper, because the callers already have the
 * try/finally that guarantees the second one: whatever goes wrong in between,
 * a button must never be left disabled and pulsing forever.
 */
export class Working {
    /** Stops a second press, and shows that the first one landed. */
    static start(control) {
        if (!control) return;

        control.disabled = true;
        control.classList.add('Working');
        control.setAttribute('aria-busy', 'true');
    }

    /** Gives the control back. */
    static stop(control) {
        if (!control) return;

        control.disabled = false;
        control.classList.remove('Working');
        control.removeAttribute('aria-busy');
    }
}
