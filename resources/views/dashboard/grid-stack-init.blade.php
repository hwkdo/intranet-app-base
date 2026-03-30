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

    rebuildDashboardGridstack();

    const syncEventName = @js($syncEventName);
    $wire.$on(syncEventName, () => {
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                rebuildDashboardGridstack();
            });
        });
    });
</script>
