/**
 * A button whose words change, that does not change size when they do.
 *
 * A row of buttons where one relabels itself shoves every neighbour along -
 * "Post" becoming "Schedule Post" moves the whole row, and the eye loses where
 * it was. So every label a button can show is built into it at once, stacked
 * in one grid cell with the inactive ones hidden but still measured. The
 * button is then as wide as its longest wording from the start and stays
 * there, whatever it currently says.
 *
 * Reserving each button's own longest label rather than giving them all one
 * width: a common width has to fit the longest label anywhere in the row, and
 * turns "Post" into a slab. The widths are never written down, so relabelling
 * or translating a button needs nothing here.
 */
import { ButtonButton, Span } from '/scripts/HTMLObjects.js';

class ToggleButtonLabel extends Span {
    static className = 'ToggleButtonLabel';

    constructor(text, inactive) {
        super();
        this.class = inactive ? 'Inactive' : null;
        this.addContent(text);
    }
}

export class ToggleButton extends ButtonButton {
    static className = 'ToggleButton';
    static properties = { labels: [] };

    constructor(labels = [], className = null) {
        super({ labels });
        this.class = className;
    }

    toDOM() {
        this.labels.forEach((label, index) => this.addContent(new ToggleButtonLabel(label, index !== 0)));
        return super.toDOM();
    }

    /** The stand-in a count-carrying label reserves room for. Mirrors ToggleButton.php. */
    /**
     * @param {string[]} labels every wording it can show, the first to start with
     * @param {string} className its own identity, beside Button and ToggleButton
     */
    static build(labels, className) {
        return new ToggleButton(labels, className).toDOM();
    }

    /** Shows this wording. The rest stay where they are, holding the width. */
    static select(button, text) {
        for (const label of button.querySelectorAll('.ToggleButtonLabel')) {
            label.classList.toggle('Inactive', label.textContent !== text);
        }
    }

    /** What it currently says - its textContent is every label at once. */
    static selected(button) {
        const showing = button.querySelector('.ToggleButtonLabel:not(.Inactive)');

        return showing === null ? '' : showing.textContent;
    }
}
