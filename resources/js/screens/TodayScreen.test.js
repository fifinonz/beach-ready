import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import TodayScreen from './TodayScreen.vue';
import { session } from '../session.js';
import { mountScreen, sentTo, stubApi } from '../test/helpers.js';

const NAIROBI = 'Africa/Nairobi';
const user = { id: 1, first_name: 'Muthoni', timezone: NAIROBI, fasting_hours: 16 };
// Started 20:30 in Nairobi, goal 12:30 the next day.
const fast = {
    id: 7, protocol: '16:8', fasting_hours: 16,
    started_at: '2026-10-18T17:30:00Z', target_end_at: '2026-10-19T09:30:00Z',
    ended_at: null, ended_early: false, feeling: null, duration_seconds: null,
};

function api({ active = fast, email = { fasting_reminders: true, email_confirmed: true }, reminders = { before_goal: true, goal_reached: true, eating_window_closing: false } } = {}) {
    return stubApi({
        'GET /fasts/active': [200, { data: active }],
        'GET /stats/week': [200, { data: { fasts_this_week: 4, average_fast_seconds: 57600, current_streak_days: 4 } }],
        'GET /fasts': [200, { data: [], meta: { total: 0 } }],
        'GET /settings/email': [200, { data: email }],
        'GET /settings/reminders': [200, { data: reminders }],
    });
}

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date', 'setInterval', 'clearInterval'] });
    vi.setSystemTime(new Date('2026-10-19T04:54:00Z')); // 07:54 in Nairobi
    document.cookie = 'XSRF-TOKEN=token';
    session.user = { ...user };
    localStorage.clear();
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

describe('the Today screen', () => {
    it('shows the time elapsed and the time to go, from the start time', async () => {
        api();
        const { wrapper } = await mountScreen(TodayScreen, '/');
        await flushPromises();

        expect(wrapper.text()).toContain("You're fasting");
        expect(wrapper.get('.ring__clock').text()).toBe('11:24:00');
        expect(wrapper.get('.ring__to-go').text()).toBe('4h 36m to go');
        expect(wrapper.text()).toContain('Yesterday');
        expect(wrapper.text()).toContain('Today, 12:30 pm');
    });

    it('catches up straight away after the phone sleeps, instead of counting ticks', async () => {
        api();
        const { wrapper } = await mountScreen(TodayScreen, '/');
        await flushPromises();

        // Two hours pass with no timer ticks at all, as when a phone sleeps.
        vi.setSystemTime(new Date('2026-10-19T06:54:00Z'));
        document.dispatchEvent(new Event('visibilitychange'));
        await flushPromises();

        expect(wrapper.get('.ring__clock').text()).toBe('13:24:00');
    });

    it('ticks every second', async () => {
        api();
        const { wrapper } = await mountScreen(TodayScreen, '/');
        await flushPromises();

        await vi.advanceTimersByTimeAsync(3000);

        expect(wrapper.get('.ring__clock').text()).toBe('11:24:03');
    });

    it('shows the next reminder, in local time', async () => {
        api();
        const { wrapper } = await mountScreen(TodayScreen, '/');
        await flushPromises();

        expect(wrapper.get('.reminder--phone').text()).toContain('Reminder at 12:00 pm');
        expect(wrapper.get('.reminder--phone').text()).toContain('30 minutes before your goal, by email');
    });

    it('says the reminder waits for the email to be confirmed', async () => {
        api({ email: { fasting_reminders: true, email_confirmed: false } });
        const { wrapper } = await mountScreen(TodayScreen, '/');
        await flushPromises();

        expect(wrapper.get('.reminder--phone').text()).toContain('once you confirm your email');
    });

    it('shows no reminder when reminder emails are off', async () => {
        api({ email: { fasting_reminders: false, email_confirmed: true } });
        const { wrapper } = await mountScreen(TodayScreen, '/');
        await flushPromises();

        expect(wrapper.find('.reminder--phone').exists()).toBe(false);
    });

    it('edits the start time as local time, without an offset', async () => {
        stubApi({
            'GET /fasts/active': [200, { data: fast }],
            'PATCH /fasts/active': [200, { data: { ...fast, started_at: '2026-10-18T16:00:00Z', target_end_at: '2026-10-19T08:00:00Z' } }],
        });
        const { wrapper } = await mountScreen(TodayScreen, '/');
        await flushPromises();

        await wrapper.findAll('button').find((b) => b.text() === 'Edit start time').trigger('click');
        const input = wrapper.get('#started-at');
        expect(input.element.value).toBe('2026-10-18T20:30');
        await input.setValue('2026-10-18T19:00');
        await wrapper.get('form.edit').trigger('submit');
        await flushPromises();

        expect(sentTo(globalThis.fetch, 'PATCH', '/fasts/active')).toEqual([{ started_at: '2026-10-18T19:00' }]);
        expect(wrapper.text()).toContain('Yesterday, 7:00 pm');
    });

    it('shows the server\'s reason when a start time is refused', async () => {
        stubApi({
            'GET /fasts/active': [200, { data: fast }],
            'PATCH /fasts/active': [422, { message: 'x', errors: { started_at: ["The start time can't be before your last fast ended."] } }],
        });
        const { wrapper } = await mountScreen(TodayScreen, '/');
        await flushPromises();

        await wrapper.findAll('button').find((b) => b.text() === 'Edit start time').trigger('click');
        await wrapper.get('form.edit').trigger('submit');
        await flushPromises();

        expect(wrapper.get('#started-at-error').text()).toBe("The start time can't be before your last fast ended.");
        expect(wrapper.get('#started-at').attributes('aria-invalid')).toBe('true');
    });

    it('links to change the protocol of the fast in progress', async () => {
        api();
        const { wrapper } = await mountScreen(TodayScreen, '/');
        await flushPromises();

        expect(wrapper.get('a[href="/protocol?change=active"]').text()).toBe('16:8 · change');
    });

    it('links to change the protocol for the next fast when none is running', async () => {
        stubApi({ 'GET /fasts/active': [200, { data: null }] });
        const { wrapper } = await mountScreen(TodayScreen, '/');
        await flushPromises();

        expect(wrapper.get('a[href="/protocol?change=next"]').text()).toBe('Change protocol');
        expect(wrapper.find('a[href="/protocol?change=active"]').exists()).toBe(false);
    });

    it('offers to start a fast with the chosen protocol when none is running', async () => {
        stubApi({
            'GET /fasts/active': [200, { data: null }],
            'POST /fasts': [201, { data: fast }],
        });
        session.user.fasting_hours = 18;
        const { wrapper } = await mountScreen(TodayScreen, '/');
        await flushPromises();

        expect(wrapper.text()).toContain('Ready when you are');
        await wrapper.findAll('button').find((b) => b.text() === 'Start fast').trigger('click');
        await flushPromises();

        expect(sentTo(globalThis.fetch, 'POST', '/fasts')).toEqual([{ fasting_hours: 18 }]);
        expect(wrapper.text()).toContain("You're fasting");
    });

    it('keeps the timer running offline, from the fast this device last saw', async () => {
        localStorage.setItem('beach-ready:active-fast', JSON.stringify(fast));
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
        const { wrapper } = await mountScreen(TodayScreen, '/');
        await flushPromises();

        expect(wrapper.get('.ring__clock').text()).toBe('11:24:00');
    });

    it('goes to History with the fast just saved once it ends', async () => {
        stubApi({
            'GET /fasts/active': [200, { data: fast }],
            'POST /fasts/active/end': [200, { data: { ...fast, ended_at: '2026-10-19T04:54:00Z', duration_seconds: 41040 } }],
        });
        const { wrapper, router } = await mountScreen(TodayScreen, '/');
        await flushPromises();

        await wrapper.findAll('button').find((b) => b.text() === 'End fast').trigger('click');
        await wrapper.findAll('button').find((b) => b.text() === 'Yes, end my fast').trigger('click');
        await flushPromises();

        expect(router.currentRoute.value.fullPath).toBe('/history?saved=41040');
        expect(localStorage.getItem('beach-ready:active-fast')).toBeNull();
    });

    it('stays on Today with a note when a fast was too short to save', async () => {
        vi.setSystemTime(new Date('2026-10-18T17:42:00Z')); // 12 minutes in
        stubApi({
            'GET /fasts/active': [200, { data: fast }],
            'POST /fasts/active/end': [200, { data: null, counted: false }],
        });
        localStorage.setItem('beach-ready:active-fast', JSON.stringify(fast));
        const { wrapper, router } = await mountScreen(TodayScreen, '/');
        await flushPromises();

        await wrapper.findAll('button').find((b) => b.text() === 'End fast').trigger('click');
        await wrapper.findAll('button').find((b) => b.text() === 'End without saving').trigger('click');
        await flushPromises();

        expect(router.currentRoute.value.path).toBe('/');
        expect(wrapper.text()).toContain('Ready when you are');
        expect(wrapper.get('.notice').text()).toContain("It was under an hour, so it isn't in your history.");
        expect(localStorage.getItem('beach-ready:active-fast')).toBeNull();
    });
});
