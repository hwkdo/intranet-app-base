@props([
    'gridElementId' => 'dashboard-grid',
    'syncEventName' => 'dashboard-sync',
    'saveMethod' => 'saveLayout',
    'cellHeight' => 80,
])

<script>
    let dashboardGridstackInstance = null;

    const rebuildDashboardGridstack = () => {
        const gridElementId = @js($gridElementId);
        const element = $wire.$el.querySelector('#' + gridElementId);
        if (!element || typeof GridStack === 'undefined') {
            return;
        }

        if (dashboardGridstackInstance !== null) {
            dashboardGridstackInstance.off('change');
            dashboardGridstackInstance.destroy(false);
            dashboardGridstackInstance = null;
        }

        const cellHeight = @js((int) $cellHeight);

        dashboardGridstackInstance = GridStack.init({
            column: 12,
            margin: 8,
            float: true,
            cellHeight: cellHeight,
        }, element);

        dashboardGridstackInstance.compact();
        dashboardGridstackInstance.cellHeight(cellHeight);

        let saveTimeout = null;
        dashboardGridstackInstance.on('change', () => {
            if (saveTimeout !== null) {
                window.clearTimeout(saveTimeout);
            }

            saveTimeout = window.setTimeout(() => {
                if (dashboardGridstackInstance === null) {
                    return;
                }

                const layout = dashboardGridstackInstance.engine.nodes.map((node) => ({
                    widgetKey: String(node.id ?? node.el?.getAttribute('gs-id') ?? ''),
                    x: Number(node.x ?? 0),
                    y: Number(node.y ?? 0),
                    w: Number(node.w ?? 1),
                    h: Number(node.h ?? 1),
                })).filter((item) => item.widgetKey !== '');

                const saveMethod = @js($saveMethod);
                $wire[saveMethod](layout);
            }, 250);
        });
    };

    const playWidgetArrival = (item) => {
        document.querySelector('.dashboard-widget-fx')?.remove();

        const rect = item.getBoundingClientRect();
        const dark = document.documentElement.classList.contains('dark');
        const border = dark ? '#d0e3f9' : '#073070';
        const fill = dark ? 'rgba(160, 199, 242, 0.42)' : 'rgba(160, 199, 242, 0.72)';
        const glow = dark ? 'rgba(208, 227, 249, 0.55)' : 'rgba(208, 227, 249, 0.95)';

        const overlay = document.createElement('div');
        overlay.className = 'dashboard-widget-fx';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.style.position = 'absolute';
        overlay.style.zIndex = '1000';
        overlay.style.pointerEvents = 'none';
        overlay.style.top = `${rect.top + window.scrollY}px`;
        overlay.style.left = `${rect.left + window.scrollX}px`;
        overlay.style.width = `${rect.width}px`;
        overlay.style.height = `${rect.height}px`;

        const pop = document.createElement('div');
        pop.style.position = 'absolute';
        pop.style.inset = '0';
        pop.style.overflow = 'hidden';
        pop.style.borderRadius = '18px';
        pop.style.border = `4px solid ${border}`;
        pop.style.background = fill;
        pop.style.boxShadow = `0 0 0 10px ${glow}, 0 24px 60px rgba(7, 48, 112, 0.45)`;

        const sheen = document.createElement('div');
        sheen.style.position = 'absolute';
        sheen.style.top = '-30%';
        sheen.style.bottom = '-30%';
        sheen.style.width = '46%';
        sheen.style.background = 'linear-gradient(90deg, transparent, rgba(255,255,255,0.15) 20%, #fff 50%, rgba(208,227,249,0.55) 75%, transparent)';
        pop.appendChild(sheen);

        const ring = document.createElement('div');
        ring.style.position = 'absolute';
        ring.style.inset = '0';
        ring.style.borderRadius = '18px';
        ring.style.border = `4px solid ${border}`;

        overlay.append(pop, ring);
        document.body.appendChild(overlay);

        pop.animate([
            { transform: 'scale(0.15)', opacity: 0 },
            { transform: 'scale(1.08)', opacity: 1, offset: 0.2 },
            { transform: 'scale(0.96)', opacity: 1, offset: 0.38 },
            { transform: 'scale(1.04)', opacity: 1, offset: 0.58 },
            { transform: 'scale(1)', opacity: 0 },
        ], { duration: 1800, easing: 'cubic-bezier(0.16, 0.84, 0.24, 1)', fill: 'both' });

        ring.animate([
            { transform: 'scale(0.35)', opacity: 0.95 },
            { transform: 'scale(1.4)', opacity: 0 },
        ], { duration: 850, easing: 'ease-out', iterations: 2, fill: 'both' });

        sheen.animate([
            { transform: 'translateX(-180%) skewX(-18deg)', opacity: 0 },
            { transform: 'translateX(30%) skewX(-18deg)', opacity: 1, offset: 0.3 },
            { transform: 'translateX(280%) skewX(-18deg)', opacity: 0 },
        ], { duration: 700, delay: 280, easing: 'ease-in-out', iterations: 2, fill: 'both' });

        window.setTimeout(() => overlay.remove(), 2000);
    };

    const focusAddedWidget = (widgetKey) => {
        if (typeof widgetKey !== 'string' || widgetKey === '') {
            return;
        }

        const gridElementId = @js($gridElementId);
        const element = $wire.$el.querySelector('#' + gridElementId);
        if (! element) {
            return;
        }

        const item = element.querySelector('[gs-id="' + CSS.escape(widgetKey) + '"]');
        if (! item) {
            return;
        }

        item.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
        window.setTimeout(() => playWidgetArrival(item), 280);
    };

    rebuildDashboardGridstack();

    const syncEventName = @js($syncEventName);
    $wire.$on(syncEventName, (payload) => {
        const data = Array.isArray(payload) ? payload[0] : payload;
        const focusWidget = typeof data?.focusWidget === 'string' ? data.focusWidget : '';

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                rebuildDashboardGridstack();
                focusAddedWidget(focusWidget);
            });
        });
    });
</script>
