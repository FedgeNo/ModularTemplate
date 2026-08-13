// ThemeSelect.js
import { sync_theme_color } from '/scripts/utils.js';
import { Api } from '/scripts/Api.js';
import { ReadyHandler } from '/scripts/ReadyHandler.js';

export class ThemeSelect {
    static init() {
        document.addEventListener('change', async (event) => {
            const select = event.target.closest('.ThemeSelect');
            if (!select) return;
            const theme = select.value;
            const previous_theme = document.documentElement.dataset.theme || 'system';
            const apply = (value) => {
                if (value === 'system') delete document.documentElement.dataset.theme;
                else document.documentElement.dataset.theme = value;
                sync_theme_color();
            };
            apply(theme);
            if (await Api.post('/api/update-theme', { theme }) === null) {
                apply(previous_theme);
                select.value = previous_theme;
            }
        });
    }
}

ReadyHandler.add(ThemeSelect.init);
