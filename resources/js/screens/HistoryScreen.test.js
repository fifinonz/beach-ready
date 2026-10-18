import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import HistoryScreen from './HistoryScreen.vue';
import { session } from '../session.js';
import { mountScreen, stubApi } from '../test/helpers.js';

let id = 1;
const fast = (startedAt, endedAt, extra = {}) => ({
    id: id++, protocol: '16:8', started_at: startedAt, ended_at: endedAt, ended_early: false, feeling: null,
    duration_seconds: (Date.parse(endedAt) - Date.parse(startedAt)) / 1000, ...extra,
});

// Saturday 24 October 2026, 12:00 in Nairobi.
beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-24T09:00:00Z'));
    session.user = { id: 1, first_name: 'Muthoni', timezone: 'Africa/Nairobi', fasting_hours: 16 };
});

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

function stubHistory({ pages = [[]], stats = { fasts_this_week: 5, average_fast_seconds: 54000, current_streak_days: 5 } } = {}) {
    return stubApi({
        'GET /stats/week': [200, { data: stats }],
        'GET /fasts': (options) => {
            // The page number comes from the query string.
            const page = Number(new URLSearchParams(globalThis.fetch.mock.calls.at(-1)[0].split('?')[1]).get('page'));
            return new Response(JSON.stringify({ data: pages[page - 1], meta: { current_page: page, last_page: pages.length } }), { status: 200 });
        },
    });
}

describe('the History screen', () => {
    it('shows this week\'s numbers', async () => {
        stubHistory();
        const { wrapper } = await mountScreen(HistoryScreen, '/history');
        await flushPromises();

        const tiles = wrapper.findAll('.tile').map((tile) => tile.text());
        expect(tiles[0]).toContain('5');
        expect(tiles[0]).toContain('fasts this week');
        expect(tiles[1]).toContain('15h');
        expect(tiles[2]).toContain('5 days');
    });

    it('confirms the fast just saved', async () => {
        stubHistory();
        const { wrapper } = await mountScreen(HistoryScreen, '/history?saved=41040');
        await flushPromises();

        expect(wrapper.get('.saved--phone').text()).toBe('Fast saved: 11h 24m. Nice work.');
    });

    it('lists fasts and rest days, newest first', async () => {
        stubHistory({ pages: [[
            fast('2026-10-23T18:00:00Z', '2026-10-24T05:24:00Z', { ended_early: true, feeling: 'okay' }),
            fast('2026-10-22T14:00:00Z', '2026-10-23T07:06:00Z', { feeling: 'great' }),
            fast('2026-10-20T15:00:00Z', '2026-10-21T07:00:00Z'),
        ]] });
        const { wrapper } = await mountScreen(HistoryScreen, '/history');
        await flushPromises();

        const compact = wrapper.findAll('.days--phone .day').map((row) => row.text());
        expect(compact).toEqual([
            'Today · ended early11h 24m',
            'Friday · 16:817h 06m',
            'ThursdayRest day · streak kept',
            'Wednesday · 16:816h 00m',
        ]);
        const detailed = wrapper.findAll('.days--desktop .day').map((row) => row.text());
        expect(detailed[0]).toContain('16:8 · ended early · felt okay');
    });

    it('loads older fasts on request', async () => {
        stubHistory({ pages: [
            [fast('2026-10-22T14:00:00Z', '2026-10-23T07:00:00Z')],
            [fast('2026-10-21T14:00:00Z', '2026-10-22T07:00:00Z')],
        ] });
        const { wrapper } = await mountScreen(HistoryScreen, '/history');
        await flushPromises();

        expect(wrapper.findAll('.days--phone .day')).toHaveLength(1);
        await wrapper.get('.more').trigger('click');
        await flushPromises();

        expect(wrapper.findAll('.days--phone .day')).toHaveLength(2);
        expect(wrapper.find('.more').exists()).toBe(false);
    });

    it('describes the chart for screen readers', async () => {
        stubHistory({ pages: [[fast('2026-10-22T14:00:00Z', '2026-10-23T07:06:00Z')]] });
        const { wrapper } = await mountScreen(HistoryScreen, '/history');
        await flushPromises();

        expect(wrapper.get('.chart--phone .visually-hidden').text()).toContain('Fri: 17h 06m');
    });

    it('explains an empty history', async () => {
        stubHistory({ stats: { fasts_this_week: 0, average_fast_seconds: null, current_streak_days: 0 } });
        const { wrapper } = await mountScreen(HistoryScreen, '/history');
        await flushPromises();

        expect(wrapper.text()).toContain('Your finished fasts will show up here.');
        expect(wrapper.findAll('.tile')[1].text()).toContain('–');
    });

    it('says so when offline', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
        const { wrapper } = await mountScreen(HistoryScreen, '/history');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toContain("You're offline");
    });
});
