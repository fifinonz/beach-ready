import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import LogIn from './LogIn.vue';
import ForgotPassword from './ForgotPassword.vue';
import CheckInbox from './CheckInbox.vue';
import ResetPassword from './ResetPassword.vue';
import { session } from '../session.js';
import { mountScreen, sentTo, stubApi } from '../test/helpers.js';
import { createMemoryHistory, createRouter } from 'vue-router';
import { mount } from '@vue/test-utils';

beforeEach(() => {
    document.cookie = 'XSRF-TOKEN=token';
    session.user = null;
});

afterEach(() => {
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

/** Mounts a screen at a path that has route params, like /reset-password/:token. */
async function mountAt(component, pattern, path) {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: pattern, component }, { path: '/:rest(.*)*', component: { template: '<p />' } }] });
    await router.push(path);
    const wrapper = mount(component, { global: { plugins: [router] }, attachTo: document.body });

    return { wrapper, router };
}

describe('log in', () => {
    it('links to "Forgot password?"', async () => {
        const { wrapper } = await mountScreen(LogIn, '/login');

        expect(wrapper.get('a[href="/forgot-password"]').text()).toBe('Forgot password?');
    });
});

describe('forgot password', () => {
    it('asks for a reset link and shows the inbox screen', async () => {
        const fetch = stubApi({ 'POST /forgot-password': [202, { message: 'sent' }] });
        const { wrapper, router } = await mountScreen(ForgotPassword, '/forgot-password');

        await wrapper.get('#email').setValue('muthoni@example.com');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(sentTo(fetch, 'POST', '/forgot-password')).toEqual([{ email: 'muthoni@example.com' }]);
        expect(router.currentRoute.value.path).toBe('/forgot-password/sent');
        expect(router.currentRoute.value.query).toEqual({ email: 'muthoni@example.com' });
    });

    it('explains the hourly limit', async () => {
        stubApi({ 'POST /forgot-password': [429, { message: 'Too Many Attempts.' }] });
        const { wrapper } = await mountScreen(ForgotPassword, '/forgot-password');

        await wrapper.get('#email').setValue('muthoni@example.com');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toBe('Too many reset requests. Please try again in an hour.');
    });

    it('shows an invalid email next to the field', async () => {
        stubApi({ 'POST /forgot-password': [422, { message: 'x', errors: { email: ['The email field must be a valid email address.'] } }] });
        const { wrapper } = await mountScreen(ForgotPassword, '/forgot-password');

        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('#email').attributes('aria-invalid')).toBe('true');
        expect(wrapper.get('#email-error').text()).toBe('The email field must be a valid email address.');
    });
});

describe('check your inbox', () => {
    it('says the same thing whether or not the account exists', async () => {
        const { wrapper } = await mountScreen(CheckInbox, '/forgot-password/sent?email=muthoni%40example.com');

        expect(wrapper.text()).toContain("If there's an account for muthoni@example.com, we've sent a link to reset its password.");
        expect(wrapper.text()).toContain('It works once, for 60 minutes.');
        expect(wrapper.get('a[href="/login"]').text()).toBe('Back to log in');
    });
});

describe('choosing a new password', () => {
    const path = '/reset-password/the-token?email=muthoni%40example.com';

    it('sends the token, email and new password, then signs in', async () => {
        const fetch = stubApi({ 'POST /reset-password': [200, { data: { id: 1, first_name: 'Muthoni' } }] });
        const { wrapper, router } = await mountAt(ResetPassword, '/reset-password/:token', path);

        expect(wrapper.text()).toContain('For muthoni@example.com. Saving it signs you out on every other device.');
        await wrapper.get('#new-password').setValue('a-brand-new-password');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(sentTo(fetch, 'POST', '/reset-password')).toEqual([{ token: 'the-token', email: 'muthoni@example.com', password: 'a-brand-new-password' }]);
        expect(session.user).toEqual({ id: 1, first_name: 'Muthoni' });
        expect(router.currentRoute.value.path).toBe('/');
    });

    it('explains an expired or used link and offers a new one', async () => {
        stubApi({ 'POST /reset-password': [422, { message: 'x', errors: { token: ['This reset link has expired or has already been used. Ask for a new one.'] } }] });
        const { wrapper } = await mountAt(ResetPassword, '/reset-password/:token', path);

        await wrapper.get('#new-password').setValue('a-brand-new-password');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain('This reset link has expired or has already been used.');
        expect(wrapper.get('[role="alert"] a').attributes('href')).toBe('/forgot-password');
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined();
    });

    it('shows a too-short password next to the field', async () => {
        stubApi({ 'POST /reset-password': [422, { message: 'x', errors: { password: ['The password field must be at least 10 characters.'] } }] });
        const { wrapper } = await mountAt(ResetPassword, '/reset-password/:token', path);

        await wrapper.get('#new-password').setValue('short');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('#new-password-error').text()).toBe('The password field must be at least 10 characters.');
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });
});
