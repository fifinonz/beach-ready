import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import RemindersScreen from './RemindersScreen.vue';
import { session } from '../session.js';
import { jsonResponse, mountScreen, sentTo, stubApi } from '../test/helpers.js';

const reminders = { before_goal: true, goal_reached: true, eating_window_closing: false };
const waiting = { fasting_reminders: true, weekly_summary: false, product_news: false, email_confirmed: false, resend_available_in_seconds: 58 };

function stubReminders(extra = {}, email = waiting) {
    return stubApi({
        'GET /settings/reminders': [200, { data: reminders }],
        'GET /settings/email': [200, { data: email }],
        ...extra,
    });
}

const switches = (wrapper) => wrapper.findAll('[role="switch"]');

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date', 'setInterval', 'clearInterval'] });
    vi.setSystemTime(new Date('2026-10-24T09:00:00Z'));
    document.cookie = 'XSRF-TOKEN=token';
    session.user = { id: 1, first_name: 'Muthoni', email: 'muthoni@example.com', timezone: 'Africa/Nairobi' };
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

describe('the Reminders screen', () => {
    it('shows each reminder type as a labelled switch', async () => {
        stubReminders();
        const { wrapper } = await mountScreen(RemindersScreen, '/reminders');
        await flushPromises();

        expect(switches(wrapper).map((s) => s.attributes('aria-checked'))).toEqual(['true', 'true', 'false']);
        const first = switches(wrapper)[0];
        expect(document.getElementById(first.attributes('aria-labelledby')).textContent).toBe('30 minutes to go');
        expect(document.getElementById(first.attributes('aria-describedby')).textContent).toBe('Before your fasting goal');
    });

    it('saves a switch as soon as it changes', async () => {
        const fetch = stubReminders({ 'PATCH /settings/reminders': [200, { data: { ...reminders, eating_window_closing: true } }] });
        const { wrapper } = await mountScreen(RemindersScreen, '/reminders');
        await flushPromises();

        await switches(wrapper)[2].trigger('click');
        await flushPromises();

        expect(sentTo(fetch, 'PATCH', '/settings/reminders')).toEqual([{ eating_window_closing: true }]);
        expect(switches(wrapper)[2].attributes('aria-checked')).toBe('true');
    });

    it('puts a switch back and says why when saving fails offline', async () => {
        stubReminders({ 'PATCH /settings/reminders': () => Promise.reject(new TypeError('Failed to fetch')) });
        const { wrapper } = await mountScreen(RemindersScreen, '/reminders');
        await flushPromises();

        await switches(wrapper)[0].trigger('click');
        await flushPromises();

        expect(switches(wrapper)[0].attributes('aria-checked')).toBe('true');
        expect(wrapper.get('[role="alert"]').text()).toContain("You're offline");
    });

    it('asks for the email to be confirmed, with a resend countdown', async () => {
        stubReminders();
        const { wrapper } = await mountScreen(RemindersScreen, '/reminders');
        await flushPromises();

        expect(wrapper.get('.confirm').text()).toContain('We sent a link to mu••••@example.com. Nothing is emailed until you click it.');
        const resend = wrapper.get('.confirm .link');
        expect(resend.text()).toBe('Resend in 0:58');
        expect(resend.attributes('disabled')).toBeDefined();

        await vi.advanceTimersByTimeAsync(58000);

        expect(resend.text()).toBe('Resend the email');
        expect(resend.attributes('disabled')).toBeUndefined();
    });

    it('resends, then waits another 60 seconds', async () => {
        const fetch = stubReminders({ 'POST /settings/email/confirmation': [202, { message: 'Sent' }] }, { ...waiting, resend_available_in_seconds: 0 });
        const { wrapper } = await mountScreen(RemindersScreen, '/reminders');
        await flushPromises();

        await wrapper.get('.confirm .link').trigger('click');
        await flushPromises();

        expect(fetch.mock.calls.some(([url, o]) => url === '/api/settings/email/confirmation' && o.method === 'POST')).toBe(true);
        expect(wrapper.get('.confirm .link').text()).toBe('Resend in 1:00');
        expect(wrapper.get('.confirm__status').text()).toBe('Sent. Check your inbox.');
    });

    it('shows no confirmation banner once confirmed', async () => {
        stubReminders({}, { ...waiting, email_confirmed: true, resend_available_in_seconds: null });
        const { wrapper } = await mountScreen(RemindersScreen, '/reminders');
        await flushPromises();

        expect(wrapper.find('.confirm').exists()).toBe(false);
        expect(wrapper.get('.channel').text()).toContain('On');
    });

    it('turns email reminders on from the Email card, which sends the confirmation', async () => {
        const fetch = stubReminders(
            { 'PATCH /settings/email': [200, { data: { ...waiting, resend_available_in_seconds: 60 } }] },
            { ...waiting, fasting_reminders: false, resend_available_in_seconds: 0 },
        );
        const { wrapper } = await mountScreen(RemindersScreen, '/reminders');
        await flushPromises();

        const card = wrapper.get('.channel');
        expect(card.attributes('aria-pressed')).toBe('false');
        await card.trigger('click');
        await flushPromises();

        expect(sentTo(fetch, 'PATCH', '/settings/email')).toEqual([{ fasting_reminders: true }]);
        expect(wrapper.get('.channel').text()).toContain('Waiting for confirmation');
        expect(wrapper.find('.confirm').exists()).toBe(true);
    });

    it('explains that reminders follow the phone\'s time zone', async () => {
        stubReminders();
        const { wrapper } = await mountScreen(RemindersScreen, '/reminders');
        await flushPromises();

        expect(wrapper.get('.zone').text()).toContain('Time zone: Africa/Nairobi');
        expect(wrapper.get('.zone').text()).toContain("If you travel, reminders follow your phone's time zone.");
    });
});
