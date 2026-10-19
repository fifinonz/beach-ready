import { afterEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import App from './App.vue';
import { pwa } from './pwa.js';

afterEach(() => {
    pwa.updated = false;
    document.body.innerHTML = '';
});

async function mountApp() {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/', component: { template: '<p />' } }] });
    await router.push('/');

    return mount(App, { global: { plugins: [router] }, attachTo: document.body });
}

describe('the update notice', () => {
    it('appears after a new version takes over, with a Reload button', async () => {
        const wrapper = await mountApp();
        expect(wrapper.find('.update').exists()).toBe(false);

        pwa.updated = true;
        await wrapper.vm.$nextTick();

        expect(wrapper.get('.update').attributes('role')).toBe('status');
        expect(wrapper.get('.update__reload').text()).toBe('Reload');
    });
});
