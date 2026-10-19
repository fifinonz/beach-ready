import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import EndFastSheet from './EndFastSheet.vue';
import { sentTo, stubApi } from '../test/helpers.js';

const fast = { id: 7, started_at: '2026-10-18T17:30:00Z', target_end_at: '2026-10-19T09:30:00Z' };
const elevenHoursIn = Date.parse('2026-10-19T04:54:07Z');

function mountSheet(now = elevenHoursIn) {
    return mount(EndFastSheet, { props: { fast, now }, attachTo: document.body });
}

beforeEach(() => {
    document.cookie = 'XSRF-TOKEN=token';
});

afterEach(() => {
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

describe('ending a fast', () => {
    it('says an early end still counts, with no language of failure', () => {
        const wrapper = mountSheet();

        expect(wrapper.get('[role="dialog"]').text()).toContain('End your fast now?');
        expect(wrapper.text()).toContain("You've fasted 11h 24m. That still counts, and it's saved to your history either way.");
        expect(wrapper.text()).toContain('11:24:07');
        expect(wrapper.text().toLowerCase()).not.toMatch(/fail|give up|quit|broke/);
    });

    it('celebrates a fast that reached its goal', () => {
        const wrapper = mountSheet(Date.parse('2026-10-19T10:36:00Z'));

        expect(wrapper.text()).toContain('End your fast?');
        expect(wrapper.text()).toContain("You've fasted 17h 06m and reached your goal.");
    });

    it('is a labelled dialog', () => {
        const wrapper = mountSheet();
        const dialog = wrapper.get('[role="dialog"]');

        expect(dialog.attributes('aria-modal')).toBe('true');
        expect(document.getElementById(dialog.attributes('aria-labelledby')).textContent).toBe('End your fast now?');
    });

    it('ends the fast with the feeling chosen, and the feeling can be cleared again', async () => {
        const fetch = stubApi({ 'POST /fasts/active/end': [200, { data: { id: 7, duration_seconds: 41047 } }] });
        const wrapper = mountSheet();
        const okay = wrapper.findAll('.chip').find((chip) => chip.text() === 'Okay');

        await okay.trigger('click');
        expect(okay.attributes('aria-pressed')).toBe('true');
        await okay.trigger('click');
        expect(okay.attributes('aria-pressed')).toBe('false');
        await wrapper.findAll('.chip').find((chip) => chip.text() === 'Hungry').trigger('click');

        await wrapper.findAll('button').find((button) => button.text() === 'Yes, end my fast').trigger('click');
        await flushPromises();

        expect(sentTo(fetch, 'POST', '/fasts/active/end')).toEqual([{ feeling: 'hungry' }]);
        expect(wrapper.emitted('ended')[0][0]).toEqual({ id: 7, duration_seconds: 41047 });
    });

    it('sends no feeling when none is chosen', async () => {
        const fetch = stubApi({ 'POST /fasts/active/end': [200, { data: {} }] });
        const wrapper = mountSheet();

        await wrapper.findAll('button').find((button) => button.text() === 'Yes, end my fast').trigger('click');
        await flushPromises();

        expect(sentTo(fetch, 'POST', '/fasts/active/end')).toEqual([{ feeling: null }]);
    });

    it('keeps going without ending anything', async () => {
        const fetch = stubApi({});
        const wrapper = mountSheet();

        await wrapper.findAll('button').find((button) => button.text() === 'Keep going').trigger('click');

        expect(wrapper.emitted('close')).toHaveLength(1);
        expect(fetch).not.toHaveBeenCalled();
    });

    it('closes with Escape', async () => {
        const wrapper = mountSheet();

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('explains that the fast is still running when offline', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
        const wrapper = mountSheet();

        await wrapper.findAll('button').find((button) => button.text() === 'Yes, end my fast').trigger('click');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain('Your fast is still running');
        expect(wrapper.emitted('ended')).toBeUndefined();
    });
});

describe('ending a fast in its first hour', () => {
    const twelveMinutesIn = Date.parse('2026-10-18T17:42:00Z');

    it('says kindly that it won\'t be saved yet, and encourages keeping going', () => {
        const wrapper = mountSheet(twelveMinutesIn);

        expect(wrapper.text()).toContain("You've fasted 12m. Fasts shorter than an hour aren't saved, so this one won't count yet. Keep going and it will.");
        expect(wrapper.text().toLowerCase()).not.toMatch(/fail|give up|quit/);
        expect(wrapper.find('.chip').exists()).toBe(false);

        const buttons = wrapper.findAll('button.button').map((b) => b.text());
        expect(buttons).toEqual(['Keep going', 'End without saving']);
    });

    it('keeps going without ending anything', async () => {
        const fetch = stubApi({});
        const wrapper = mountSheet(twelveMinutesIn);

        await wrapper.findAll('button').find((b) => b.text() === 'Keep going').trigger('click');

        expect(wrapper.emitted('close')).toHaveLength(1);
        expect(fetch).not.toHaveBeenCalled();
    });

    it('can still end it, which reports that nothing was saved', async () => {
        stubApi({ 'POST /fasts/active/end': [200, { data: null, counted: false }] });
        const wrapper = mountSheet(twelveMinutesIn);

        await wrapper.findAll('button').find((b) => b.text() === 'End without saving').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('ended')[0][0]).toBeNull();
    });

    it('counts from exactly an hour', () => {
        const wrapper = mountSheet(Date.parse('2026-10-18T18:30:00Z'));

        expect(wrapper.text()).toContain('That still counts');
        expect(wrapper.find('.chip').exists()).toBe(true);
    });
});

