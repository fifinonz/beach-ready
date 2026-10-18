import { describe, expect, it } from 'vitest';
import { shouldShowInstallHint } from './pwa.js';

const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
const IPAD = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15';
const ANDROID = 'Mozilla/5.0 (Linux; Android 15) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Mobile Safari/537.36';

describe('the "Add to Home Screen" hint', () => {
    it('shows once on an iPhone in the browser', () => {
        expect(shouldShowInstallHint({ userAgent: IPHONE, maxTouchPoints: 5, standalone: false, seen: false })).toBe(true);
    });

    it('shows on an iPad, which says it is a Mac', () => {
        expect(shouldShowInstallHint({ userAgent: IPAD, maxTouchPoints: 5, standalone: false, seen: false })).toBe(true);
    });

    it('does not show on a real Mac', () => {
        expect(shouldShowInstallHint({ userAgent: IPAD, maxTouchPoints: 0, standalone: false, seen: false })).toBe(false);
    });

    it('does not show once installed', () => {
        expect(shouldShowInstallHint({ userAgent: IPHONE, maxTouchPoints: 5, standalone: true, seen: false })).toBe(false);
    });

    it('does not show again once dismissed', () => {
        expect(shouldShowInstallHint({ userAgent: IPHONE, maxTouchPoints: 5, standalone: false, seen: true })).toBe(false);
    });

    it('does not show on Android, which offers its own install prompt', () => {
        expect(shouldShowInstallHint({ userAgent: ANDROID, maxTouchPoints: 5, standalone: false, seen: false })).toBe(false);
    });
});
