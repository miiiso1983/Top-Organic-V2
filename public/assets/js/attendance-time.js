/* Attendance form hints. The PHP service always rechecks against the server clock. */
(function (root) {
    'use strict';
    const message = 'لا يمكن طلب بصمة لوقت لم يحن بعد؛ اختر وقتاً حدث بالفعل بتوقيت بغداد';
    function baghdadTimestamp(milliseconds) {
        const parts = new Intl.DateTimeFormat('en-GB', {
            timeZone: 'Asia/Baghdad', year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23'
        }).formatToParts(new Date(milliseconds));
        const value = Object.fromEntries(parts.map(part => [part.type, part.value]));
        return `${value.year}-${value.month}-${value.day}T${value.hour}:${value.minute}:${value.second}`;
    }
    function create(serverMilliseconds, elapsed = () => performance.now()) {
        const started = elapsed();
        const maximum = () => baghdadTimestamp(serverMilliseconds + Math.max(0, elapsed() - started));
        function validate(input, report = false) {
            input.max = maximum();
            const value = input.value.length === 16 ? input.value + ':00' : input.value;
            input.setCustomValidity(value && value > input.max ? message : '');
            return report ? input.reportValidity() : input.checkValidity();
        }
        function bind(input) {
            validate(input);
            ['focus', 'input', 'change'].forEach(event => input.addEventListener(event, () => validate(input)));
        }
        return {maximum, validate, bind};
    }
    const api = {baghdadTimestamp, create};
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    else root.AttendanceTime = api;
})(typeof window === 'undefined' ? globalThis : window);
