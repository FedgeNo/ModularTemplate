import { TestCase } from './TestCase.js';
import { ToggleButton } from '../../scripts/ToggleButton.js';

/**
 * The point of the thing is a width that does not move. That rests on every
 * wording staying in the button - present, measured, merely not shown - so
 * these check that the unselected ones are still there rather than replaced,
 * which is what a plain textContent swap would do.
 */
export default {
    suite: 'ToggleButton',
    tests: {
        'it starts on the first wording'() {
            const button = ToggleButton.build(['Add Poll', 'Remove Poll'], 'ComposerPollButton');

            TestCase.assertEquals('Add Poll', ToggleButton.selected(button));
        },
        'the wordings it is not showing stay in it, holding the width'() {
            const button = ToggleButton.build(['Post', 'Schedule Post'], 'ComposerSubmitButton');
            const labels = [...button.querySelectorAll('.ToggleButtonLabel')].map((label) => label.textContent);

            TestCase.assertEquals('Post,Schedule Post', labels.join(','));
        },
        'selecting one hides the others and shows only it'() {
            const button = ToggleButton.build(['Add Schedule', 'Remove Schedule'], 'ComposerScheduleButton');

            ToggleButton.select(button, 'Remove Schedule');

            TestCase.assertEquals('Remove Schedule', ToggleButton.selected(button));
            TestCase.assertEquals(1, button.querySelectorAll('.ToggleButtonLabel:not(.Inactive)').length);
            TestCase.assertEquals(2, button.querySelectorAll('.ToggleButtonLabel').length);
        },
        'it can hold more than two, as the location button does'() {
            const button = ToggleButton.build(['Add Location', 'Remove Location', 'Locating…'], 'LocationButton');

            ToggleButton.select(button, 'Locating…');

            TestCase.assertEquals('Locating…', ToggleButton.selected(button));
            TestCase.assertEquals(3, button.querySelectorAll('.ToggleButtonLabel').length);
        },
        'it carries its own identity beside the shared ones'() {
            const button = ToggleButton.build(['Use Markdown', 'Use Rich Text'], 'MarkdownModeButton');

            TestCase.assertTrue(button.classList.contains('Button'));
            TestCase.assertTrue(button.classList.contains('ToggleButton'));
            TestCase.assertTrue(button.classList.contains('MarkdownModeButton'));
        },
    }
};
