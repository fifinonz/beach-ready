import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import SettingsScreen from './SettingsScreen.vue';
import { session } from '../session.js';
import { jsonResponse, mountScreen, sentTo, stubApi } from '../test/helpers.js';

const confirmed = { fasting_reminders: true, weekly_summary: false, product_news: false, email_confirmed: true, resend_available_in_seconds: null };

beforeEach(() => {
    document.cookie = 'XSRF-TOKEN=token';
    session.user = { id: 1, first_name: 'Muthoni', email: 'muthoni@example.com', timezone: 'Africa/Nairobi' };
});

afterEach(() => {
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

const switches = (wrapper) => wrapper.findAll('[role="switch"]');
const button = (wrapper, text) => wrapper.findAll('button').find((b) => b.text() === text);

describe('the Settings screen', () => {
    it('says reminders are on once the email is confirmed', async () => {
        stubApi({ 'GET /settings/email': [200, { data: confirmed }] });
        const { wrapper } = await mountScreen(SettingsScreen, '/settings');
        await flushPromises();

        expect(wrapper.get('.banner').text()).toBe('Email confirmed. Reminders are on.');
        expect(switches(wrapper).map((s) => s.attributes('aria-checked'))).toEqual(['true', 'false', 'false']);
    });

    it('does not claim reminders are on before confirmation', async () => {
        stubApi({ 'GET /settings/email': [200, { data: { ...confirmed, email_confirmed: false } }] });
        const { wrapper } = await mountScreen(SettingsScreen, '/settings');
        await flushPromises();

        expect(wrapper.find('.banner').exists()).toBe(false);
    });

    it('turns a topic on', async () => {
        const fetch = stubApi({
            'GET /settings/email': [200, { data: confirmed }],
            'PATCH /settings/email': [200, { data: { ...confirmed, weekly_summary: true } }],
        });
        const { wrapper } = await mountScreen(SettingsScreen, '/settings');
        await flushPromises();

        await switches(wrapper)[1].trigger('click');
        await flushPromises();

        expect(sentTo(fetch, 'PATCH', '/settings/email')).toEqual([{ weekly_summary: true }]);
        expect(switches(wrapper)[1].attributes('aria-checked')).toBe('true');
    });

    it('unsubscribes from all emails at once', async () => {
        const off = { ...confirmed, fasting_reminders: false };
        const fetch = stubApi({
            'GET /settings/email': [200, { data: confirmed }],
            'PATCH /settings/email': [200, { data: off }],
        });
        const { wrapper } = await mountScreen(SettingsScreen, '/settings');
        await flushPromises();

        await button(wrapper, 'Unsubscribe from all emails').trigger('click');
        await flushPromises();

        expect(sentTo(fetch, 'PATCH', '/settings/email')).toEqual([{ fasting_reminders: false, weekly_summary: false, product_news: false }]);
        expect(switches(wrapper).map((s) => s.attributes('aria-checked'))).toEqual(['false', 'false', 'false']);
        expect(wrapper.get('.status').text()).toContain("You're unsubscribed from all emails.");
    });

    it('downloads the data as a file', async () => {
        stubApi({ 'GET /settings/email': [200, { data: confirmed }] });
        const { wrapper } = await mountScreen(SettingsScreen, '/settings');
        await flushPromises();

        const link = wrapper.findAll('a').find((a) => a.text().includes('Download my data'));
        expect(link.attributes('href')).toBe('/api/account/export');
        expect(link.attributes('download')).toBeDefined();
    });

    it('deletes the account only with the password, then goes to sign-up', async () => {
        const fetch = stubApi({
            'GET /settings/email': [200, { data: confirmed }],
            'DELETE /account': () => jsonResponse(204, null),
        });
        localStorage.setItem('beach-ready:user', '{}');
        const { wrapper, router } = await mountScreen(SettingsScreen, '/settings');
        await flushPromises();

        await button(wrapper, 'Delete my accountRemoves everything').trigger('click');
        const confirm = button(wrapper, 'Delete my account');
        expect(confirm.attributes('disabled')).toBeDefined();
        await wrapper.get('#delete-password').setValue('a-long-password');
        await wrapper.get('form.delete').trigger('submit');
        await flushPromises();

        expect(sentTo(fetch, 'DELETE', '/account')).toEqual([{ password: 'a-long-password' }]);
        expect(session.user).toBeNull();
        expect(localStorage.getItem('beach-ready:user')).toBeNull();
        expect(router.currentRoute.value.path).toBe('/signup');
    });

    it('keeps the account when the password is wrong', async () => {
        stubApi({
            'GET /settings/email': [200, { data: confirmed }],
            'DELETE /account': [422, { message: 'x', errors: { password: ['The password is incorrect.'] } }],
        });
        const { wrapper, router } = await mountScreen(SettingsScreen, '/settings');
        await flushPromises();

        await button(wrapper, 'Delete my accountRemoves everything').trigger('click');
        await wrapper.get('#delete-password').setValue('wrong');
        await wrapper.get('form.delete').trigger('submit');
        await flushPromises();

        expect(wrapper.get('#delete-password-error').text()).toBe('That password is not right.');
        expect(session.user).not.toBeNull();
        expect(router.currentRoute.value.path).toBe('/settings');
    });
});
