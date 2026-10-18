import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import ProfileScreen from './ProfileScreen.vue';
import { session } from '../session.js';
import { mountScreen, sentTo, stubApi } from '../test/helpers.js';

const user = {
    id: 1, first_name: 'Muthoni', email: 'muthoni@example.com', timezone: 'Africa/Nairobi',
    fasting_hours: 16, why: 'More energy for long beach days.', member_since: '2026-10-01T09:00:00Z',
};

function stubProfile(extra = {}) {
    return stubApi({
        'GET /stats/all-time': [200, { data: { fasts_logged: 23, longest_fast_seconds: 17 * 3600 + 360 } }],
        'GET /settings/reminders': [200, { data: { before_goal: true, goal_reached: true, eating_window_closing: false } }],
        'GET /settings/email': [200, { data: { fasting_reminders: true } }],
        ...extra,
    });
}

beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-24T09:00:00Z'));
    document.cookie = 'XSRF-TOKEN=token';
    session.user = { ...user };
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

describe('the Profile screen', () => {
    it('shows who you are, with the email address partly hidden', async () => {
        stubProfile();
        const { wrapper } = await mountScreen(ProfileScreen, '/profile');
        await flushPromises();

        expect(wrapper.get('.identity__name').text()).toBe('Muthoni');
        expect(wrapper.get('.identity__email').text()).toBe('mu••••@example.com');
        expect(wrapper.text()).not.toContain('muthoni@example.com');
    });

    it('shows all-time numbers and when you joined', async () => {
        stubProfile();
        const { wrapper } = await mountScreen(ProfileScreen, '/profile');
        await flushPromises();

        expect(wrapper.findAll('.tile dd').map((dd) => dd.text())).toEqual(['23', '17h', 'Oct']);
    });

    it('summarises the protocol, time zone and reminders', async () => {
        stubProfile();
        const { wrapper } = await mountScreen(ProfileScreen, '/profile');
        await flushPromises();

        expect(wrapper.findAll('.row').map((row) => row.text())).toEqual([
            'Fasting protocol16:8',
            'Time zoneAfrica/Nairobi',
            'Reminders2 on',
        ]);
    });

    it('says reminders are off when reminder emails are switched off', async () => {
        stubProfile({ 'GET /settings/email': [200, { data: { fasting_reminders: false } }] });
        const { wrapper } = await mountScreen(ProfileScreen, '/profile');
        await flushPromises();

        expect(wrapper.findAll('.row')[2].text()).toBe('RemindersOff');
    });

    it('saves "my why" when you leave the field, and can clear it', async () => {
        const fetch = stubProfile({ 'PATCH /user': (options) => new Response(JSON.stringify({ data: { ...user, why: JSON.parse(options.body).why } }), { status: 200 }) });
        const { wrapper } = await mountScreen(ProfileScreen, '/profile');
        await flushPromises();

        const why = wrapper.get('#why');
        await why.setValue('Feel lighter on the beach.');
        await why.trigger('blur');
        await flushPromises();
        expect(wrapper.get('#why-status').text()).toBe('Saved');

        await why.setValue('');
        await why.trigger('blur');
        await flushPromises();

        expect(sentTo(fetch, 'PATCH', '/user')).toEqual([{ why: 'Feel lighter on the beach.' }, { why: null }]);
    });

    it('changes the first name', async () => {
        stubProfile({ 'PATCH /user': [200, { data: { ...user, first_name: 'Muthoni N.' } }] });
        const { wrapper } = await mountScreen(ProfileScreen, '/profile');
        await flushPromises();

        await wrapper.findAll('button').find((b) => b.text() === 'Edit profile').trigger('click');
        await wrapper.get('#edit-first-name').setValue('Muthoni N.');
        await wrapper.get('form.edit').trigger('submit');
        await flushPromises();

        expect(wrapper.get('.identity__name').text()).toBe('Muthoni N.');
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('logs out and forgets the cached account', async () => {
        stubProfile({ 'POST /logout': (options) => new Response(null, { status: 204 }) });
        localStorage.setItem('beach-ready:user', JSON.stringify(user));
        localStorage.setItem('beach-ready:active-fast', '{}');
        const { wrapper, router } = await mountScreen(ProfileScreen, '/profile');
        await flushPromises();

        await wrapper.findAll('button').find((b) => b.text() === 'Log out').trigger('click');
        await flushPromises();

        expect(session.user).toBeNull();
        expect(localStorage.getItem('beach-ready:user')).toBeNull();
        expect(localStorage.getItem('beach-ready:active-fast')).toBeNull();
        expect(router.currentRoute.value.path).toBe('/login');
    });
});
