'use strict';
const assert = require('node:assert/strict');
const {baghdadTimestamp, create} = require('../public/assets/js/attendance-time.js');
const now = Date.parse('2026-11-07T21:00:30Z');
assert.equal(baghdadTimestamp(now), '2026-11-08T00:00:30');
let elapsed = 0;
const guard = create(now, () => elapsed);
function field(value) {
    return {value, max: '', error: '', listeners: {},
        setCustomValidity(v) { this.error = v; },
        checkValidity() { return !this.error && !!this.value; },
        reportValidity() { this.reported = true; return this.checkValidity(); },
        addEventListener(event, fn) { this.listeners[event] = fn; }
    };
}
const input = field('2026-11-08T00:01');
guard.bind(input);
assert.equal(input.max, '2026-11-08T00:00:30');
assert.equal(guard.validate(input, true), false);
assert.ok(input.reported);
assert.equal(input.value, '2026-11-08T00:01', 'Must not silently replace a selected time');
input.value = '2026-11-08T00:00';
input.listeners.input();
assert.equal(input.error, '');
assert.equal(guard.validate(input), true);
input.value = '2026-11-07T23:59';
assert.equal(guard.validate(input), true, 'Past overnight time');
input.value = '2026-11-09T00:00';
assert.equal(guard.validate(input), false, 'Future date');
input.value = '2026-11-08T00:00:31';
assert.equal(guard.validate(input), false, 'Future second');
input.value = '2026-11-08T00:00:30';
assert.equal(guard.validate(input), true, 'Exact current second');
elapsed = 60000;
input.value = '2026-11-08T00:01';
input.listeners.focus();
assert.equal(guard.validate(input), true, 'Refresh maximum after time passes');
input.value = '';
assert.equal(guard.validate(input, true), false, 'Missing required time');
const originalNow = Date.now;
Date.now = () => now + 86400000;
assert.equal(guard.maximum(), '2026-11-08T00:01:30', 'Device clock change must not move server-anchored hint');
Date.now = originalNow;
console.log('PASS: Baghdad dates, future minute/second/day, current/past, midnight, repeated edits, validation reset and server-anchored elapsed time.');
