/**
 * StudyFlow - Core Application JavaScript
 * Student Self-Teaching Platform
 */

const StudyFlow = (function() {
    'use strict';

    /* ========================================================================
       Configuration
       ======================================================================== */
    const CONFIG = {
        appName: 'StudyFlow',
        apiBase: '',
        csrfSelector: 'meta[name="csrf-token"]',
        flashDuration: 5000,
        autosaveInterval: 30000,
        debounceDelay: 300,
        animationDuration: 300,
        toastDuration: 4000,
    };

    /* ========================================================================
       Utility Helpers
       ======================================================================== */
    function $(selector, context) {
        return (context || document).querySelector(selector);
    }

    function $$(selector, context) {
        return Array.from((context || document).querySelectorAll(selector));
    }

    function getCSRFToken() {
        const meta = $('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function debounce(fn, delay) {
        let timer;
        return function(...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay || CONFIG.debounceDelay);
        };
    }

    function throttle(fn, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                fn.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    function formatTime(seconds) {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    }

    function formatDate(dateStr) {
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function formatDuration(minutes) {
        if (minutes < 60) return `${minutes}m`;
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        return m > 0 ? `${h}h ${m}m` : `${h}h`;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function generateId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2, 9);
    }

    /* ========================================================================
       HTTP Client (Fetch Wrapper)
       ======================================================================== */
    async function request(url, options = {}) {
        const defaults = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCSRFToken(),
            },
        };

        const config = {
            ...defaults,
            ...options,
            headers: { ...defaults.headers, ...(options.headers || {}) },
        };

        if (config.body && typeof config.body === 'object' && !(config.body instanceof FormData)) {
            config.body = JSON.stringify(config.body);
        }

        if (config.body instanceof FormData) {
            delete config.headers['Content-Type'];
        }

        try {
            const response = await fetch(CONFIG.apiBase + url, config);
            const contentType = response.headers.get('content-type') || '';

            let data;
            if (contentType.includes('application/json')) {
                data = await response.json();
            } else {
                data = await response.text();
            }

            if (!response.ok) {
                throw { status: response.status, data };
            }

            return data;
        } catch (error) {
            if (error.status) throw error;
            throw { status: 0, data: { error: 'Network error. Please check your connection.' } };
        }
    }

    const api = {
        get: (url) => request(url, { method: 'GET' }),
        post: (url, body) => request(url, { method: 'POST', body }),
        put: (url, body) => request(url, { method: 'PUT', body }),
        delete: (url) => request(url, { method: 'DELETE' }),
    };

    /* ========================================================================
       Toast Notifications
       ======================================================================== */
    const Toast = {
        container: null,

        init() {
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            this.container.className = 'fixed top-4 right-4 z-[200] flex flex-col gap-2 max-w-sm w-full pointer-events-none';
            document.body.appendChild(this.container);
        },

        show(message, type = 'info', duration) {
            if (!this.container) this.init();

            const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
            const colors = {
                success: 'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/50 dark:border-green-800 dark:text-green-200',
                error: 'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/50 dark:border-red-800 dark:text-red-200',
                warning: 'bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-900/50 dark:border-amber-800 dark:text-amber-200',
                info: 'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-900/50 dark:border-blue-800 dark:text-blue-200',
            };

            const toast = document.createElement('div');
            toast.className = `pointer-events-auto flex items-center gap-3 p-4 rounded-xl border shadow-lg ${colors[type] || colors.info} animate-fade-in-down`;
            toast.innerHTML = `
                <span class="text-lg shrink-0">${icons[type] || icons.info}</span>
                <p class="text-sm font-medium flex-1">${escapeHtml(message)}</p>
                <button onclick="this.parentElement.remove()" class="shrink-0 opacity-60 hover:opacity-100 transition-opacity">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            `;

            this.container.appendChild(toast);

            const removeDelay = duration || CONFIG.toastDuration;
            setTimeout(() => {
                toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }, removeDelay);
        },

        success(msg, dur) { this.show(msg, 'success', dur); },
        error(msg, dur) { this.show(msg, 'error', dur); },
        warning(msg, dur) { this.show(msg, 'warning', dur); },
        info(msg, dur) { this.show(msg, 'info', dur); },
    };

    /* ========================================================================
       Modal Manager
       ======================================================================== */
    const Modal = {
        open(id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                modal.querySelector('.modal-content')?.focus();
            }
        },

        close(id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }
        },

        confirm(message, onConfirm, onCancel) {
            const id = 'modal-confirm-' + generateId();
            const overlay = document.createElement('div');
            overlay.id = id;
            overlay.className = 'modal-overlay';
            overlay.innerHTML = `
                <div class="modal-content max-w-md w-full mx-4" role="dialog" aria-modal="true">
                    <div class="modal-body text-center py-6">
                        <div class="text-4xl mb-3">🤔</div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Are you sure?</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">${escapeHtml(message)}</p>
                    </div>
                    <div class="modal-footer justify-center">
                        <button class="btn btn-secondary modal-cancel-btn">Cancel</button>
                        <button class="btn btn-danger modal-confirm-btn">Confirm</button>
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);
            document.body.style.overflow = 'hidden';

            overlay.querySelector('.modal-cancel-btn').addEventListener('click', () => {
                overlay.remove();
                document.body.style.overflow = '';
                if (onCancel) onCancel();
            });

            overlay.querySelector('.modal-confirm-btn').addEventListener('click', () => {
                overlay.remove();
                document.body.style.overflow = '';
                if (onConfirm) onConfirm();
            });

            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.remove();
                    document.body.style.overflow = '';
                    if (onCancel) onCancel();
                }
            });
        },
    };

    /* ========================================================================
       Tabs Component
       ======================================================================== */
    function initTabs() {
        $$('[data-tabs]').forEach(tabContainer => {
            const tabs = $$('[data-tab]', tabContainer);
            const panels = $$('[data-tab-panel]', tabContainer);

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const target = tab.dataset.tab;
                    tabs.forEach(t => t.classList.remove('active'));
                    panels.forEach(p => p.classList.remove('active'));
                    tab.classList.add('active');
                    const panel = tabContainer.querySelector(`[data-tab-panel="${target}"]`);
                    if (panel) panel.classList.add('active');
                });
            });
        });
    }

    /* ========================================================================
       Dropdown Component
       ======================================================================== */
    function initDropdowns() {
        $$('[data-dropdown-toggle]').forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                const targetId = trigger.dataset.dropdownToggle;
                const menu = document.getElementById(targetId);
                if (menu) menu.classList.toggle('hidden');
            });
        });

        document.addEventListener('click', () => {
            $$('.dropdown-menu:not(.hidden)').forEach(menu => {
                menu.classList.add('hidden');
            });
        });
    }

    /* ========================================================================
       Form Utilities
       ======================================================================== */
    function initForms() {
        // Character counters
        $$('[data-max-length]').forEach(input => {
            const max = parseInt(input.dataset.maxLength);
            const counter = document.createElement('span');
            counter.className = 'text-xs text-gray-400 mt-1 block text-right';
            counter.textContent = `0 / ${max}`;
            input.parentNode.appendChild(counter);

            input.addEventListener('input', () => {
                const len = input.value.length;
                counter.textContent = `${len} / ${max}`;
                counter.classList.toggle('text-red-500', len > max);
            });
        });

        // Password visibility toggle
        $$('[data-toggle-password]').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = document.getElementById(btn.dataset.togglePassword);
                if (input) {
                    const isPassword = input.type === 'password';
                    input.type = isPassword ? 'text' : 'password';
                    btn.textContent = isPassword ? '🙈' : '👁️';
                }
            });
        });

        // Auto-resize textareas
        $$('textarea[data-auto-resize]').forEach(textarea => {
            function resize() {
                textarea.style.height = 'auto';
                textarea.style.height = textarea.scrollHeight + 'px';
            }
            textarea.addEventListener('input', resize);
            resize();
        });
    }

    /* ========================================================================
       Search (Cmd+K)
       ======================================================================== */
    function initSearch() {
        document.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = $('input[name="q"]');
                if (searchInput) searchInput.focus();
            }
        });
    }

    /* ========================================================================
       Dark Mode
       ======================================================================== */
    function initDarkMode() {
        const saved = localStorage.getItem('sf_dark_mode');
        if (saved === '1') {
            document.documentElement.classList.add('dark');
        }

        const icon = $('#dark-mode-icon');
        if (icon) {
            icon.textContent = document.documentElement.classList.contains('dark') ? '☀️' : '🌙';
        }
    }

    /* ========================================================================
       Lazy Loading Images
       ======================================================================== */
    function initLazyLoad() {
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                        }
                        observer.unobserve(img);
                    }
                });
            });

            $$('img[data-src]').forEach(img => observer.observe(img));
        }
    }

    /* ========================================================================
       Scroll to Top
       ======================================================================== */
    function initScrollToTop() {
        const btn = document.createElement('button');
        btn.id = 'scroll-top-btn';
        btn.className = 'fixed bottom-20 right-4 lg:bottom-6 z-40 w-10 h-10 bg-primary-600 text-white rounded-full shadow-lg flex items-center justify-center hover:bg-primary-700 transition-all opacity-0 invisible';
        btn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>';
        btn.setAttribute('aria-label', 'Scroll to top');
        document.body.appendChild(btn);

        btn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        window.addEventListener('scroll', throttle(() => {
            if (window.scrollY > 300) {
                btn.style.opacity = '1';
                btn.style.visibility = 'visible';
            } else {
                btn.style.opacity = '0';
                btn.style.visibility = 'hidden';
            }
        }, 200));
    }

    /* ========================================================================
       Local Storage Helpers
       ======================================================================== */
    const Storage = {
        get(key, defaultVal) {
            try {
                const item = localStorage.getItem('sf_' + key);
                return item ? JSON.parse(item) : defaultVal;
            } catch {
                return defaultVal;
            }
        },

        set(key, value) {
            try {
                localStorage.setItem('sf_' + key, JSON.stringify(value));
            } catch {
                /* storage full */
            }
        },

        remove(key) {
            localStorage.removeItem('sf_' + key);
        },
    };

    /* ========================================================================
       Event Bus (Pub/Sub)
       ======================================================================== */
    const EventBus = {
        _listeners: {},

        on(event, callback) {
            if (!this._listeners[event]) this._listeners[event] = [];
            this._listeners[event].push(callback);
        },

        off(event, callback) {
            if (!this._listeners[event]) return;
            this._listeners[event] = this._listeners[event].filter(cb => cb !== callback);
        },

        emit(event, data) {
            if (!this._listeners[event]) return;
            this._listeners[event].forEach(cb => cb(data));
        },
    };

    /* ========================================================================
       Initialize
       ======================================================================== */
    function init() {
        initDarkMode();
        initTabs();
        initDropdowns();
        initForms();
        initSearch();
        initLazyLoad();
        initScrollToTop();

        // Close flash messages
        $$('.flash-message').forEach(el => {
            setTimeout(() => {
                el.style.transition = 'opacity 0.5s ease';
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            }, CONFIG.flashDuration);
        });

        // Close modals on overlay click
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.add('hidden');
                document.body.style.overflow = '';
            }
        });

        // Close modals on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                $$('.modal-overlay:not(.hidden)').forEach(modal => {
                    modal.classList.add('hidden');
                    document.body.style.overflow = '';
                });
            }
        });
    }

    // Auto-init on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    /* ========================================================================
       Public API
       ======================================================================== */
    return {
        $, $$, api, Toast, Modal, Storage, EventBus,
        debounce, throttle, formatTime, formatDate, formatDuration,
        escapeHtml, generateId, getCSRFToken,
        init, initTabs, initDropdowns, initForms,
    };
})();

// Make globally available
window.SF = StudyFlow;
