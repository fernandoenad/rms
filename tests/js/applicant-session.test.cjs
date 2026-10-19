const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const script = fs.readFileSync('public/js/applicant-session.js', 'utf8');

function createHarness(url = '/applicant/login') {
    const elements = new Map();
    const body = {
        children: [],
        appendChild(element) {
            this.children.push(element);
            elements.set(element.id, element);
        }
    };
    const document = {
        currentScript: { getAttribute(name) { return name === 'data-sign-in-url' ? url : null; } },
        body,
        getElementById(id) { return elements.get(id) || null; },
        createElement(tagName) {
            return {
                tagName, children: [], style: {}, attributes: {},
                appendChild(node) { this.children.push(node); },
                setAttribute(name, value) { this.attributes[name] = value; }
            };
        }
    };
    const window = {
        location: { href: '/assessments/attempts/100' },
        fetch() { throw Error('Unexpected automatic request'); }
    };
    vm.runInNewContext(script, { window, document });
    return { window, document, body };
}

test('401 and 419 display the safe sign-in action only once', () => {
    const { window, body } = createHarness();
    const helper = window.RMSApplicantSession;
    assert.equal(helper.expired, false);
    assert.equal(helper.check({ status: 401 }), true);
    assert.equal(helper.expired, true);
    assert.equal(body.children.length, 1);
    const notice = body.children[0];
    assert.equal(notice.id, 'rms-applicant-session-expired');
    assert.equal(notice.attributes.role, 'alert');
    assert.equal(notice.children[2].tagName, 'a');
    assert.equal(notice.children[2].href, '/applicant/login');
    assert.equal(notice.children[2].textContent, 'Sign in again');
    assert.equal(helper.check({ status: 419 }), true);
    assert.equal(body.children.length, 1);
    assert.equal(window.location.href, '/assessments/attempts/100');
});

test('other errors do not treat active sessions as expired', () => {
    const { window, body } = createHarness();
    const helper = window.RMSApplicantSession;
    for (const status of [200, 403, 409, 422, 429, 500]) {
        assert.equal(helper.check({ status }), false);
    }
    assert.equal(helper.expired, false);
    assert.equal(body.children.length, 0);
});

test('manual show reuses the same banner and never submits', () => {
    const { window, body } = createHarness('/applicant/login');
    window.RMSApplicantSession.show();
    window.RMSApplicantSession.show();
    assert.equal(body.children.length, 1);
    assert.equal(window.location.href, '/assessments/attempts/100');
});


function functionSource(relativePath, start, end) {
    const source = fs.readFileSync(relativePath, 'utf8');
    const first = source.indexOf(start);
    assert.notEqual(first, -1, 'Missing tested assessment function: ' + start);
    const last = source.indexOf(end, first + start.length);
    assert.notEqual(last, -1, 'Missing assessment function boundary: ' + end);
    return source.slice(first, last);
}

test('written exam never schedules pending-answer retries after authentication expiry', () => {
    const source = functionSource(
        'resources/views/guest/assessments/take.blade.php',
        '    function retryPendingAnswers() {',
        '    function showItem(index) {'
    );
    let requested = false;
    vm.runInNewContext(source + '\nretryPendingAnswers();', {
        window: { RMSApplicantSession: { expired: true } },
        finishing: false,
        loadPendingAnswers() { requested = true; throw Error('Must not load or resend answers'); },
        setTimeout() { throw Error('Must not schedule a retry'); }
    });
    assert.equal(requested, false);
});

test('written exam keeps an attempted answer locally but never sends it after expiry', () => {
    const source = functionSource(
        'resources/views/guest/assessments/take.blade.php',
        '    function queueAnswerSave(radio) {',
        '    async function processAnswerQueue(itemId) {'
    );
    const pending = [];
    const statuses = [];
    vm.runInNewContext(source + "\nqueueAnswerSave({dataset:{item:'11'},value:'22'});", {
        window: { RMSApplicantSession: { expired: true } },
        savePendingAnswer(item, value) { pending.push([item, value]); },
        setSaveState(message) { statuses.push(message); },
        saveQueues: new Map(),
        items: [],
        navButtons: [],
        processAnswerQueue() { throw Error('Must not resubmit answer'); }
    });
    assert.deepEqual(pending, [['11', '22']]);
    assert.match(statuses[0], /Session expired/);
});

test('skills exam keeps unsaved text locally but never submits it after expiry', async () => {
    const source = functionSource(
        'resources/views/guest/skills/take.blade.php',
        '    async function persistInline(value){',
        '    async function logEvent(type){'
    );
    const storage = [];
    const messages = [];
    const result = vm.runInNewContext(source + "\npersistInline('unsaved answer');", {
        window: { RMSApplicantSession: { expired: true } },
        pendingValue: null,
        pendingKey: 'skills-pending',
        saving: false,
        storageSet(key, value) { storage.push([key, value]); },
        state: { set textContent(message) { messages.push(message); }, className: '' },
        fetch() { throw Error('Must not resend skills response'); }
    });
    await result;
    assert.deepEqual(storage, [['skills-pending', 'unsaved answer']]);
    assert.match(messages[0], /Session expired/);
});
