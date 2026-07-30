<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'RGMO-IRMS') }}</title>
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="apple-touch-icon" href="{{ asset('images/logo.png') }}">

        <script>
            (() => {
                const storageKey = 'rgmoColorTheme';
                const storedTheme = localStorage.getItem(storageKey);
                const preference = ['light', 'dark', 'system'].includes(storedTheme) ? storedTheme : 'system';
                const resolvedTheme = preference === 'system'
                    ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                    : preference;

                document.documentElement.dataset.themePreference = preference;
                document.documentElement.dataset.bsTheme = resolvedTheme;
                document.documentElement.style.colorScheme = resolvedTheme;
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <script src="https://unpkg.com/lucide@latest"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

    </head>
    <body class="font-sans antialiased">
        <div class="rgmo-loader" data-rgmo-loader role="status" aria-live="polite" aria-busy="true">
            <div class="rgmo-loader__mark" aria-hidden="true">
                <img src="{{ asset('images/logo.png') }}" alt="" class="rgmo-loader__logo">
                <span class="rgmo-loader__track"></span>
            </div>
            <span class="visually-hidden">Loading</span>
        </div>

        <div class="layout-wrapper d-flex flex-column flex-lg-row overflow-hidden min-vh-100">
            @include('layouts.navigation')
            <div class="mobile-sidebar-backdrop" id="mobileSidebarBackdrop" aria-hidden="true"></div>

            <main class="d-flex flex-column flex-grow-1" style="min-width: 0;">
                <header class="top-nav d-flex justify-content-between align-items-center gap-3">
                    <button
                        type="button"
                        class="mobile-sidebar-toggle d-lg-none"
                        id="mobileSidebarToggle"
                        aria-label="Open navigation"
                        aria-controls="sidebar"
                        aria-expanded="false"
                    >
                        <i data-lucide="menu" aria-hidden="true"></i>
                    </button>
                    <div class="page-header-content flex-grow-1" style="min-width: 0;">
                        @if(isset($header))
                            {{ $header }}
                        @else
                            <h2 class="h5 fw-bold mb-0 text-dark">Dashboard</h2>
                        @endif
                    </div>
                </header>

                <div class="main-content">
                    @if (session('success'))
                        <div class="container-fluid mt-4">
                            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                                <div class="d-flex align-items-center">
                                    <i data-lucide="check-circle" class="me-2" style="width: 18px"></i>
                                    <span>{{ session('success') }}</span>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        </div>
                    @endif

                    {{ $slot }}
                </div>
            </main>
        </div>

        <script>
            lucide.createIcons();

            const sidebarToggle = document.getElementById('sidebarToggle');
            const topNav = document.querySelector('.top-nav');
            const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
            const mobileSidebarClose = document.getElementById('mobileSidebarClose');
            const mobileSidebarBackdrop = document.getElementById('mobileSidebarBackdrop');
            const sidebar = document.getElementById('sidebar');
            const sidebarStorageKey = 'rgmoSidebarCollapsed';
            const mobileSidebarQuery = window.matchMedia('(max-width: 992px)');
            const syncTopNavHeight = () => {
                if (topNav) {
                    document.documentElement.style.setProperty('--top-nav-height', `${Math.ceil(topNav.getBoundingClientRect().height)}px`);
                }
            };

            syncTopNavHeight();

            if ('ResizeObserver' in window && topNav) {
                new ResizeObserver(syncTopNavHeight).observe(topNav);
            } else {
                window.addEventListener('resize', syncTopNavHeight);
            }
            const setMobileSidebar = (open) => {
                const shouldOpen = open && mobileSidebarQuery.matches;

                document.body.classList.toggle('mobile-sidebar-open', shouldOpen);
                mobileSidebarToggle?.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
                mobileSidebarBackdrop?.setAttribute('aria-hidden', shouldOpen ? 'false' : 'true');
                sidebar?.toggleAttribute('inert', mobileSidebarQuery.matches && !shouldOpen);

                if (shouldOpen) {
                    mobileSidebarClose?.focus();
                }
            };
            const applySidebarState = (collapsed) => {
                document.body.classList.toggle('sidebar-collapsed', collapsed);

                if (sidebarToggle) {
                    sidebarToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    sidebarToggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Minimize sidebar');
                    sidebarToggle.setAttribute('title', collapsed ? 'Expand sidebar' : 'Minimize sidebar');
                }
            };

            applySidebarState(localStorage.getItem(sidebarStorageKey) === 'true');
            setMobileSidebar(false);

            sidebarToggle?.addEventListener('click', () => {
                const collapsed = !document.body.classList.contains('sidebar-collapsed');
                localStorage.setItem(sidebarStorageKey, String(collapsed));
                applySidebarState(collapsed);
            });

            const sidebarMenu = sidebar?.querySelector('.sidebar-menu');
            const sidebarOrderStatus = document.getElementById('sidebar-order-status');
            const savedSidebarOrder = @json(auth()->user()->sidebar_order ?? []);
            const sidebarOrderUrl = @json(route('profile.sidebar-order.update'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const sidebarItems = () => Array.from(sidebarMenu?.querySelectorAll(':scope > [data-sidebar-item]') ?? []);
            const currentSidebarOrder = () => sidebarItems().map((item) => item.dataset.sidebarItem);
            const applySidebarOrder = (order) => {
                if (!sidebarMenu || !Array.isArray(order)) return;

                const itemsById = new Map(sidebarItems().map((item) => [item.dataset.sidebarItem, item]));
                order.forEach((id) => {
                    const item = itemsById.get(id);
                    if (item) {
                        sidebarMenu.appendChild(item);
                        itemsById.delete(id);
                    }
                });
                itemsById.forEach((item) => sidebarMenu.appendChild(item));
            };

            applySidebarOrder(savedSidebarOrder);
            let persistedSidebarOrder = currentSidebarOrder();
            let draggedSidebarItem = null;
            let dragStartOrder = '';

            const animateSidebarMove = (moveItem) => {
                if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    moveItem();
                    return;
                }

                const previousPositions = new Map(
                    sidebarItems().map((item) => [item, item.getBoundingClientRect()]),
                );

                moveItem();

                sidebarItems().forEach((item) => {
                    if (item === draggedSidebarItem) return;

                    const previousPosition = previousPositions.get(item);
                    const currentPosition = item.getBoundingClientRect();
                    const distance = previousPosition?.top - currentPosition.top;

                    if (!distance) return;

                    item.getAnimations().forEach((animation) => animation.cancel());
                    item.animate(
                        [
                            { transform: `translateY(${distance}px)` },
                            { transform: 'translateY(0)' },
                        ],
                        {
                            duration: 190,
                            easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                        },
                    );
                });
            };

            const announceSidebarOrder = (message) => {
                if (sidebarOrderStatus) sidebarOrderStatus.textContent = message;
            };
            const saveSidebarOrder = async () => {
                const order = currentSidebarOrder();

                try {
                    const response = await fetch(sidebarOrderUrl, {
                        method: 'PUT',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ order }),
                    });

                    if (!response.ok) throw new Error('Unable to save sidebar order.');

                    persistedSidebarOrder = order;
                    announceSidebarOrder('Sidebar order saved.');
                } catch (error) {
                    applySidebarOrder(persistedSidebarOrder);
                    announceSidebarOrder('Sidebar order could not be saved. The previous order was restored.');
                }
            };
            const sidebarItemAfterPointer = (pointerY) => sidebarItems()
                .filter((item) => item !== draggedSidebarItem)
                .reduce((closest, item) => {
                    const box = item.getBoundingClientRect();
                    const offset = pointerY - box.top - (box.height / 2);

                    return offset < 0 && offset > closest.offset
                        ? { offset, item }
                        : closest;
                }, { offset: Number.NEGATIVE_INFINITY, item: null }).item;

            sidebarMenu?.addEventListener('dragstart', (event) => {
                const item = event.target.closest('[data-sidebar-item]');

                if (!item || item.parentElement !== sidebarMenu || event.target.closest('.nav-submenu')) {
                    event.preventDefault();
                    return;
                }

                draggedSidebarItem = item;
                dragStartOrder = currentSidebarOrder().join(',');
                item.classList.add('sidebar-dragging');
                sidebarMenu.classList.add('sidebar-reordering');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', item.dataset.sidebarItem);
            });
            sidebarMenu?.addEventListener('dragover', (event) => {
                if (!draggedSidebarItem) return;

                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                const afterItem = sidebarItemAfterPointer(event.clientY);

                if (afterItem) {
                    if (draggedSidebarItem.nextElementSibling !== afterItem) {
                        animateSidebarMove(() => sidebarMenu.insertBefore(draggedSidebarItem, afterItem));
                    }
                } else if (draggedSidebarItem !== sidebarItems().at(-1)) {
                    animateSidebarMove(() => sidebarMenu.appendChild(draggedSidebarItem));
                }
            });
            sidebarMenu?.addEventListener('drop', (event) => event.preventDefault());
            sidebarMenu?.addEventListener('dragend', () => {
                const droppedItem = draggedSidebarItem;
                droppedItem?.classList.remove('sidebar-dragging');
                droppedItem?.classList.add('sidebar-dropped');
                sidebarMenu.classList.remove('sidebar-reordering');
                draggedSidebarItem = null;

                window.setTimeout(() => droppedItem?.classList.remove('sidebar-dropped'), 280);

                if (dragStartOrder !== currentSidebarOrder().join(',')) saveSidebarOrder();
            });
            sidebarMenu?.addEventListener('keydown', (event) => {
                if (!event.altKey || !['ArrowUp', 'ArrowDown'].includes(event.key)) return;

                const item = event.target.closest('[data-sidebar-item]');
                if (!item || item.parentElement !== sidebarMenu) return;

                const items = sidebarItems();
                const currentIndex = items.indexOf(item);
                const targetIndex = event.key === 'ArrowUp' ? currentIndex - 1 : currentIndex + 1;
                if (targetIndex < 0 || targetIndex >= items.length) return;

                event.preventDefault();
                if (event.key === 'ArrowUp') {
                    sidebarMenu.insertBefore(item, items[targetIndex]);
                } else {
                    sidebarMenu.insertBefore(items[targetIndex], item);
                }

                saveSidebarOrder();
                event.target.focus();
            });

            mobileSidebarToggle?.addEventListener('click', () => setMobileSidebar(true));
            mobileSidebarClose?.addEventListener('click', () => setMobileSidebar(false));
            mobileSidebarBackdrop?.addEventListener('click', () => setMobileSidebar(false));

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && document.body.classList.contains('mobile-sidebar-open')) {
                    setMobileSidebar(false);
                    mobileSidebarToggle?.focus();
                }
            });

            document.querySelectorAll('#sidebar a[href]').forEach((link) => {
                link.addEventListener('click', () => setMobileSidebar(false));
            });

            mobileSidebarQuery.addEventListener('change', () => setMobileSidebar(false));

            const themeStorageKey = 'rgmoColorTheme';
            const themeSelect = document.getElementById('colorTheme');
            const colorSchemePreference = window.matchMedia('(prefers-color-scheme: dark)');
            const validThemes = ['light', 'dark', 'system'];
            const getThemePreference = () => {
                const savedTheme = localStorage.getItem(themeStorageKey);
                return validThemes.includes(savedTheme) ? savedTheme : 'system';
            };
            const applyTheme = (preference) => {
                const resolvedTheme = preference === 'system'
                    ? (colorSchemePreference.matches ? 'dark' : 'light')
                    : preference;

                document.documentElement.dataset.themePreference = preference;
                document.documentElement.dataset.bsTheme = resolvedTheme;
                document.documentElement.style.colorScheme = resolvedTheme;

                if (themeSelect) {
                    themeSelect.value = preference;
                }
            };

            applyTheme(getThemePreference());

            themeSelect?.addEventListener('change', (event) => {
                const preference = validThemes.includes(event.target.value) ? event.target.value : 'system';
                localStorage.setItem(themeStorageKey, preference);
                applyTheme(preference);
            });

            colorSchemePreference.addEventListener('change', () => {
                if (getThemePreference() === 'system') {
                    applyTheme('system');
                }
            });

            // Interactivity for Sidebar Dropdowns
            document.querySelectorAll('#sidebar details').forEach((details) => {
                details.addEventListener('click', (e) => {
                    if (document.body.classList.contains('sidebar-collapsed')) {
                        const target = e.target.closest('summary');

                        if (target) {
                            e.preventDefault();
                            const firstLink = details.querySelector('.nav-submenu a[href]');

                            if (firstLink) {
                                window.location.href = firstLink.href;
                            }

                            return;
                        }
                    }

                    // Close other open details if we're opening a new one
                    if (!details.open) {
                        document.querySelectorAll('#sidebar details[open]').forEach((other) => {
                            if (other !== details) {
                                other.removeAttribute('open');
                            }
                        });
                    }
                });
            });

            // Handle hover state for parent items when summary is hovered
            document.querySelectorAll('#sidebar summary').forEach(summary => {
                summary.addEventListener('mouseenter', () => {
                    summary.parentElement.classList.add('hover-active');
                });
                summary.addEventListener('mouseleave', () => {
                    summary.parentElement.classList.remove('hover-active');
                });
            });

            const notificationBadge = document.getElementById('notification-unread-badge');
            if (notificationBadge) {
                fetch('{{ route('notifications.unread-count') }}', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then((response) => response.ok ? response.json() : null)
                    .then((payload) => {
                        if (!payload) return;

                        const count = Number(payload.count ?? 0);
                        notificationBadge.textContent = count;
                        notificationBadge.classList.toggle('d-none', count <= 0);
                    })
                    .catch(() => {});
            }
        </script>

        @stack('scripts')
    </body>
</html>
