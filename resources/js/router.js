import { createRouter, createWebHistory } from 'vue-router';
import { loadSession, session } from './session.js';
import NotBuiltYet from './screens/NotBuiltYet.vue';

/*
 * Screens for visitors (`guest`) and for signed-in people (`auth`). Screens
 * still to come in Stage 5 use NotBuiltYet until they're built.
 */
const routes = [
    { path: '/signup', component: NotBuiltYet, meta: { guest: true, title: 'Sign up' } },
    { path: '/login', component: NotBuiltYet, meta: { guest: true, title: 'Log in' } },
    { path: '/protocol', component: NotBuiltYet, meta: { auth: true, title: 'Choose a protocol' } },
    { path: '/', component: NotBuiltYet, meta: { auth: true, title: 'Today' } },
    { path: '/:path(.*)*', redirect: '/' },
];

export const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    if (!session.loaded) {
        await loadSession();
    }

    if (to.meta.auth && !session.user) {
        return '/signup';
    }

    if (to.meta.guest && session.user) {
        return '/';
    }
});

router.afterEach((to) => {
    document.title = to.meta.title ? `${to.meta.title} · Beach Ready` : 'Beach Ready';
});
