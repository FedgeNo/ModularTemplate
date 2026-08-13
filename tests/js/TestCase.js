export class TestCase {
    static assertTrue(value, msg = 'expected true') { if (!value) throw new Error(msg); }
    static assertFalse(value, msg = 'expected false') { if (value) throw new Error(msg); }
    static assertEquals(expected, actual, msg = '') { if (expected !== actual) throw new Error(msg || `expected ${expected}, got ${actual}`); }
    static assertNull(value, msg = 'expected null') { if (value !== null && value !== undefined) throw new Error(msg); }
    static assertNotNull(value, msg = 'expected non-null') { if (value === null || value === undefined) throw new Error(msg); }
    static assertThrows(fn, msg = 'expected an error') { try { fn(); } catch (e) { return; } throw new Error(msg); }
}

/**
 * Sets the config cookie the way ClientConfig::send() does, for a test
 * exercising code that reads ClientConfig.
 */
export function write_client_config(config) {
    document.cookie = 'APP-CONFIG=' + encodeURIComponent(JSON.stringify(config));
}
