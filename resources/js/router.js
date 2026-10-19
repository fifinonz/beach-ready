import { createRouter, createWebHistory } from 'vue-router';
import { loadSession, session } from './session.js';
import SignUp from './screens/SignUp.vue';
import LogIn from './screens/LogIn.vue';
import ForgotPassword from './screens/ForgotPassword.vue';
import CheckInbox from './screens/CheckInbox.vue';
import ResetPassword from './screens/ResetPassword.vue';
import ChooseProtocol from './screens/ChooseProtocol.vue';
import TodayScreen from './screens/TodayScreen.vue';
import HistoryScreen from './screens/HistoryScreen.vue';
import ProfileScreen from './screens/ProfileScreen.vue';
import RemindersScreen from './screens/RemindersScreen.vue';
import SettingsScreen from './screens/SettingsScreen.vue';

/*
 * Screens for visitors (`guest`) and for signed-in people (`auth`).
 */
const routes = [
    { path: '/signup', component: SignUp, meta: { guest: true, title: 'Sign up' } },
    { path: '/login', component: LogIn, meta: { guest: true, title: 'Log in' } },
    { path: '/forgot-password', component: ForgotPassword, meta: { guest: true, title: 'Forgot password' } },
    { path: '/forgot-password/sent', component: CheckInbox, meta: { guest: true, title: 'Check your inbox' } },
    // Open whether or not this device is signed in: the link comes from an email.
    { path: '/reset-password/:token', component: ResetPassword, meta: { title: 'Choose a new password' } },
    { path: '/protocol', component: ChooseProtocol, meta: { auth: true, title: 'Choose a protocol' } },
    { path: '/', component: TodayScreen, meta: { auth: true, title: 'Today' } },
    { path: '/history', component: HistoryScreen, meta: { auth: true, title: 'History' } },
    { path: '/reminders', component: RemindersScreen, meta: { auth: true, title: 'Reminders' } },
    { path: '/profile', component: ProfileScreen, meta: { auth: true, title: 'Profile' } },
    { path: '/settings', component: SettingsScreen, meta: { auth: true, title: 'Settings' } },
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
