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
