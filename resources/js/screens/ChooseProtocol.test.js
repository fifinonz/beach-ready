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

    it('saves the choice and starts the first fast when signing up', async () => {
        const fetch = stubApi({
            'PATCH /user': [200, { data: { ...user, fasting_hours: 18 } }],
            'POST /fasts': [201, { data: {} }],
        });
        const { wrapper, router } = await mountScreen(ChooseProtocol, '/protocol');

        await wrapper.get('input[value="18"]').setValue(true);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(sentTo(fetch, 'PATCH', '/user')).toEqual([{ fasting_hours: 18 }]);
        expect(sentTo(fetch, 'POST', '/fasts')).toEqual([{ fasting_hours: 18 }]);
        expect(session.user.fasting_hours).toBe(18);
        expect(router.currentRoute.value.path).toBe('/');
    });

    it('changes the fast in progress when opened from its "change" link', async () => {
        const fetch = stubApi({
            'PATCH /user': [200, { data: { ...user, fasting_hours: 14 } }],
            'PATCH /fasts/active': [200, { data: {} }],
        });
        session.user.fasting_hours = 16;
        const { wrapper } = await mountScreen(ChooseProtocol, '/protocol?change=active');

        expect(wrapper.text()).not.toContain('Step 2 of 2');
        await wrapper.get('input[value="14"]').setValue(true);
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(sentTo(fetch, 'PATCH', '/fasts/active')).toEqual([{ fasting_hours: 14 }]);
        expect(sentTo(fetch, 'POST', '/fasts')).toEqual([]);
    });

    it('says so when offline', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
        const { wrapper } = await mountScreen(ChooseProtocol, '/protocol');

        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain("You're offline");
    });
});
