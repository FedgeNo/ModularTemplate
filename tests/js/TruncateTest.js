import { TestCase } from './TestCase.js';
import { truncate } from '../../scripts/utils.js';

/**
 * The browser half of truncate(), held to the same answers as PHP's in
 * src/functions.php. A quoted post's writing now travels whole and is cut
 * where it is shown, so both renderers cut it - and a disagreement is a post
 * that changes length when the page is reloaded.
 *
 * The expected values here were taken from the PHP function rather than
 * reasoned out, so this fails if either side moves.
 */
export default {
    suite: 'truncate',
    tests: {
        'text shorter than the limit is left alone'() {
            TestCase.assertEquals('hello world', truncate('hello world', 50));
        },
        'the cut backs up to the last word boundary'() {
            TestCase.assertEquals('the quick…', truncate('the quick brown fox jumps', 12));
        },
        'a single long word is cut where the limit falls'() {
            TestCase.assertEquals('supercal…', truncate('supercalifragilistic', 8));
        },
        'accented text counts characters, not bytes'() {
            TestCase.assertEquals('café café…', truncate('café café café café', 12));
        },
        'trailing space before the cut is dropped'() {
            TestCase.assertEquals('word…', truncate('word     padding here', 10));
        },
        'text exactly at the limit is left alone'() {
            TestCase.assertEquals('exactly ten', truncate('exactly ten', 11));
        },

        // The cases a UTF-16 count gets wrong: an emoji is one character to
        // mb_substr and two units to String.length, so counting the wrong one
        // cuts these at half the length the server does.
        'an emoji counts as one character when cutting'() {
            TestCase.assertEquals('😀😀😀😀…', truncate('😀😀😀😀😀😀', 4));
        },
        'emoji within the limit are not cut at all'() {
            TestCase.assertEquals('😀😀😀', truncate('😀😀😀', 4));
        },
        'a flag is two characters here, as it is on the server'() {
            TestCase.assertEquals('🇨🇦🇨🇦…', truncate('🇨🇦🇨🇦🇨🇦', 4));
        },
    },
};
