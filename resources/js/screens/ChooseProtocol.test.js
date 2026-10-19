import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import ChooseProtocol from './ChooseProtocol.vue';
import { session } from '../session.js';
import { mountScreen, sentTo, stubApi } from '../test/helpers.js';

const user = { id: 1, first_name: 'Muthoni', fasting_hours: null, timezone: 'Africa/Nairobi' };

beforeEach(() => {
    document.cookie = 'XSRF-TOKEN=token';
    session.user = { ...user };
});

afterEach(() => {
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

describe('choosing a protocol', () => {
    it('offers 14:10, 16:8, 18:6 and custom as one radio group, with 16:8 chosen to start', async () => {
        const { wrapper } = await mountScreen(ChooseProtocol, '/protocol');

        const radios = wrapper.findAll('input[type="radio"]');
        expect(radios.map((radio) => radio.attributes('value'))).toEqual(['14', '16', '18', 'custom']);
        expect(wrapper.get('input[value="16"]').element.checked).toBe(true);
        expect(wrapper.text()).toContain('Step 2 of 2');
    });

    it('keeps custom hours between 12 and 20', async () => {
        const { wrapper } = await mountScreen(ChooseProtocol, '/protocol');
        await wrapper.get('input[value="custom"]').setValue(true);

        for (let i = 0; i < 10; i++) await wrapper.get('[aria-label="More hours"]').trigger('click');
        expect(wrapper.get('output').text()).toBe('20:4');
        expect(wrapper.get('[aria-label="More hours"]').attributes('disabled')).toBeDefined();

        for (let i = 0; i < 10; i++) await wrapper.get('[aria-label="Fewer hours"]').trigger('click');
        expect(wrapper.get('output').text()).toBe('12:12');
    });

    it('saves the choice when signing up and goes to Today, without starting a fast', async () => {
        const fetch = stubApi({ 'PATCH /user': [200, { data: { ...user, fasting_hours: 18 } }] });
        const { wrapper, router } = await mountScreen(ChooseProtocol, '/protocol');

        expect(wrapper.get('button[type="submit"]').text()).toBe('Continue');
        await wrapper.get('input[value="18"]').setValue(true);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(sentTo(fetch, 'PATCH', '/user')).toEqual([{ fasting_hours: 18 }]);
        expect(sentTo(fetch, 'POST', '/fasts')).toEqual([]);
        expect(session.user.fasting_hours).toBe(18);
        expect(router.currentRoute.value.path).toBe('/');
    });

    it('can be skipped, keeping 16:8, without saving or starting anything', async () => {
        const fetch = stubApi({});
        const { wrapper, router } = await mountScreen(ChooseProtocol, '/protocol');

        await wrapper.get('a.skip').trigger('click');
        await flushPromises();

        expect(fetch).not.toHaveBeenCalled();
        expect(router.currentRoute.value.path).toBe('/');
    });

    it('saves the protocol for the next fast and goes back to Profile', async () => {
        const fetch = stubApi({ 'PATCH /user': [200, { data: { ...user, fasting_hours: 14 } }] });
        session.user.fasting_hours = 16;
        const { wrapper, router } = await mountScreen(ChooseProtocol, '/protocol?change=next&from=/profile');

        expect(wrapper.text()).not.toContain('Step 2 of 2');
        expect(wrapper.find('a.skip').exists()).toBe(false);
        expect(wrapper.get('button[type="submit"]').text()).toBe('Save');
        await wrapper.get('input[value="14"]').setValue(true);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(sentTo(fetch, 'PATCH', '/user')).toEqual([{ fasting_hours: 14 }]);
        expect(sentTo(fetch, 'PATCH', '/fasts/active')).toEqual([]);
        expect(router.currentRoute.value.path).toBe('/profile');
    });

    it('starts from a custom preference when there is one', async () => {
        session.user.fasting_hours = 13;
        const { wrapper } = await mountScreen(ChooseProtocol, '/protocol?change=next');

        expect(wrapper.get('input[value="custom"]').element.checked).toBe(true);
        expect(wrapper.get('output').text()).toBe('13:11');
    });

    it('changes the fast in progress, starting from that fast\'s protocol', async () => {
        const fetch = stubApi({
            'GET /fasts/active': [200, { data: { id: 7, fasting_hours: 18 } }],
            'PATCH /user': [200, { data: { ...user, fasting_hours: 14 } }],
            'PATCH /fasts/active': [200, { data: {} }],
        });
        session.user.fasting_hours = 16;
        const { wrapper, router } = await mountScreen(ChooseProtocol, '/protocol?change=active');
        await flushPromises();

        expect(wrapper.get('input[value="18"]').element.checked).toBe(true);
        expect(wrapper.get('button[type="submit"]').text()).toBe('Save and update this fast');
        await wrapper.get('input[value="14"]').setValue(true);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(sentTo(fetch, 'PATCH', '/fasts/active')).toEqual([{ fasting_hours: 14 }]);
        expect(sentTo(fetch, 'PATCH', '/user')).toEqual([{ fasting_hours: 14 }]);
        expect(router.currentRoute.value.path).toBe('/');
    });

    it('never goes back to another site', async () => {
        stubApi({ 'PATCH /user': [200, { data: user }] });
        const { wrapper, router } = await mountScreen(ChooseProtocol, '/protocol?change=next&from=//evil.example');

        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(router.currentRoute.value.path).toBe('/');
    });

    it('says so when offline', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
        const { wrapper } = await mountScreen(ChooseProtocol, '/protocol');

        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain("You're offline");
    });
});
