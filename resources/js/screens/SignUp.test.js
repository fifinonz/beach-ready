import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import SignUp from './SignUp.vue';
import { session } from '../session.js';

function jsonResponse(status, body) {
    return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

async function mountSignUp() {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/signup', component: SignUp },
            { path: '/login', component: { template: '<p>log in</p>' } },
            { path: '/protocol', component: { template: '<p>protocol</p>' } },
        ],
    });
    await router.push('/signup');

    const wrapper = mount(SignUp, { global: { plugins: [router] }, attachTo: document.body });

    return { wrapper, router };
}

async function fillIn(wrapper, { firstName = 'Muthoni', email = 'muthoni@example.com', password = 'a-long-password' } = {}) {
    await wrapper.get('#first-name').setValue(firstName);
    await wrapper.get('#email').setValue(email);
    await wrapper.get('#password').setValue(password);
}

beforeEach(() => {
    document.cookie = 'XSRF-TOKEN=token';
    session.user = null;
});

afterEach(() => {
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

describe('the sign-up screen', () => {
    it('labels every field', async () => {
        const { wrapper } = await mountSignUp();

        for (const [id, label] of [['first-name', 'First name'], ['email', 'Email'], ['password', 'Password']]) {
            expect(wrapper.get(`label[for="${id}"]`).text()).toBe(label);
            expect(wrapper.find(`#${id}`).exists()).toBe(true);
        }
        expect(wrapper.get('.reminders-opt-in').text()).toContain('Email me fasting reminders');
    });

    it('hides the honeypot from sight, screen readers and the tab order', async () => {
        const { wrapper } = await mountSignUp();
        const honeypot = wrapper.get('input[name="favourite_colour"]');

        expect(honeypot.element.closest('[aria-hidden="true"]')).not.toBeNull();
        expect(honeypot.attributes('tabindex')).toBe('-1');
        expect(honeypot.attributes('autocomplete')).toBe('off');
    });

    it('creates the account with the browser time zone and the reminders choice, then asks for a protocol', async () => {
        const fetch = vi.fn().mockResolvedValue(jsonResponse(201, { data: { id: 1, first_name: 'Muthoni' } }));
        vi.stubGlobal('fetch', fetch);
        const { wrapper, router } = await mountSignUp();

        await fillIn(wrapper);
        await wrapper.get('.reminders-opt-in input').setValue(true);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        const body = JSON.parse(fetch.mock.calls.at(-1)[1].body);
        expect(body).toEqual({
            first_name: 'Muthoni',
            email: 'muthoni@example.com',
            password: 'a-long-password',
            email_reminders: true,
            favourite_colour: '',
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        });
        expect(session.user).toEqual({ id: 1, first_name: 'Muthoni' });
        expect(router.currentRoute.value.path).toBe('/protocol');
    });

    it('shows the server\'s message next to the field it is about', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(422, {
            message: 'Invalid',
            errors: { password: ['The password field must be at least 10 characters.'] },
        })));
        const { wrapper } = await mountSignUp();

        await fillIn(wrapper, { password: 'short' });
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        const password = wrapper.get('#password');
        expect(password.attributes('aria-invalid')).toBe('true');
        expect(password.attributes('aria-describedby')).toBe('password-error');
        expect(wrapper.get('#password-error').text()).toBe('The password field must be at least 10 characters.');
        expect(wrapper.find('#email').attributes('aria-invalid')).toBeUndefined();
    });

    it('says clearly when the device is offline', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
        const { wrapper } = await mountSignUp();

        await fillIn(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toBe("You're offline. Connect to the internet to create your account.");
    });

    it('explains a rate limit without blaming the person', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(429, { message: 'Too Many Attempts.' })));
        const { wrapper } = await mountSignUp();

        await fillIn(wrapper);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain('Please try again in a few minutes.');
    });

    it('links to log in', async () => {
        const { wrapper } = await mountSignUp();

        expect(wrapper.get('a[href="/login"]').text()).toBe('Log in');
    });
});
