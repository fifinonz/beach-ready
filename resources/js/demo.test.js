import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import SignUp from './screens/SignUp.vue';
import LogIn from './screens/LogIn.vue';
import AppLayout from './components/AppLayout.vue';
import RemindersScreen from './screens/RemindersScreen.vue';
import SettingsScreen from './screens/SettingsScreen.vue';
import { session } from './session.js';
import { mountScreen, sentTo, stubApi } from './test/helpers.js';

const demoUser = { id: 9, first_name: 'Alex', email: 'demo-abc@demo.invalid', timezone: 'Africa/Nairobi', is_demo: true };
const button = (wrapper, text) => wrapper.findAll('button').find((b) => b.text() === text);

beforeEach(() => {
    document.cookie = 'XSRF-TOKEN=token';
    session.user = null;
});

afterEach(() => {
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

describe('"Try the demo"', () => {
    it('is on the sign-up and log-in screens', async () => {
        for (const [screen, path] of [[SignUp, '/signup'], [LogIn, '/login']]) {
            const { wrapper } = await mountScreen(screen, path);
            expect(button(wrapper, 'Try the demo')).toBeDefined();
        }
    });

    it('makes a demo account in this browser\'s time zone and opens Today', async () => {
        const fetch = stubApi({ 'POST /demo': [201, { data: demoUser }] });
        const { wrapper, router } = await mountScreen(SignUp, '/signup');

        await button(wrapper, 'Try the demo').trigger('click');
        await flushPromises();

        expect(sentTo(fetch, 'POST', '/demo')).toEqual([{ timezone: Intl.DateTimeFormat().resolvedOptions().timeZone }]);
        expect(session.user).toEqual(demoUser);
        expect(router.currentRoute.value.path).toBe('/');
    });

    it('explains the hourly limit', async () => {
        stubApi({ 'POST /demo': [429, { message: 'Too Many Attempts.' }] });
        const { wrapper } = await mountScreen(SignUp, '/signup');

        await button(wrapper, 'Try the demo').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Too many demos from here just now.');
    });
});

describe('inside the demo', () => {
    beforeEach(() => {
        session.user = { ...demoUser };
    });

    async function mountLayout() {
        const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:p(.*)*', component: { template: '<p />' } }] });
        await router.push('/');

        return { wrapper: mount(AppLayout, { global: { plugins: [router] }, attachTo: document.body }), router };
    }

    it('says it\'s a demo on every screen, with a way out to a real account', async () => {
        const fetch = stubApi({ 'POST /logout': () => new Response(null, { status: 204 }) });
        const { wrapper, router } = await mountLayout();

        expect(wrapper.get('.demo-banner').text()).toContain('this account is deleted after 24 hours and never gets email');
        await button(wrapper, 'Create your own account').trigger('click');
        await flushPromises();

        expect(fetch.mock.calls.some(([url]) => url === '/api/logout')).toBe(true);
        expect(session.user).toBeNull();
        expect(router.currentRoute.value.path).toBe('/signup');
    });

    it('shows no banner for a real account', async () => {
        session.user = { ...demoUser, is_demo: false };
        const { wrapper } = await mountLayout();

        expect(wrapper.find('.demo-banner').exists()).toBe(false);
    });

    it('says on Reminders that no email will arrive, and never claims one was sent', async () => {
        stubApi({
            'GET /settings/reminders': [200, { data: { before_goal: true, goal_reached: true, eating_window_closing: false } }],
            'GET /settings/email': [200, { data: { fasting_reminders: true, email_confirmed: false, resend_available_in_seconds: 0 } }],
        });
        const { wrapper } = await mountScreen(RemindersScreen, '/reminders');
        await flushPromises();

        expect(wrapper.get('.demo-note').text()).toContain('Demo accounts never get email');
        expect(wrapper.find('.confirm').exists()).toBe(false);
        expect(wrapper.get('.channel').text()).toContain('Not in the demo');
    });

    it('says on Settings that the demo deletes itself, instead of asking for a password', async () => {
        stubApi({ 'GET /settings/email': [200, { data: { fasting_reminders: false, weekly_summary: false, product_news: false, email_confirmed: false } }] });
        const { wrapper } = await mountScreen(SettingsScreen, '/settings');
        await flushPromises();

        expect(wrapper.text()).toContain('Demo accounts delete themselves after 24 hours');
        expect(button(wrapper, 'Delete my accountRemoves everything')).toBeUndefined();
    });
});
