@props([
    'modalName' => 'dashboard-widgets-flyout',
    'sections' => [],
    'enabledWidgets' => [],
    'descriptionText' => 'Bereiche aufklappen und Widgets auswählen. Die Zahl zeigt ausgewählte von verfügbaren Widgets.',
    'toggleMethod' => 'toggleWidget',
    'supportsItemCountMethod' => 'supportsWidgetItemCount',
    'itemCountValueMethod' => 'widgetItemCountValue',
    'saveItemCountMethod' => 'saveWidgetItemCount',
    'resetMethod' => 'resetToDefault',
    'showMandatory' => false,
])

@php
    $enabledLookup = array_fill_keys($enabledWidgets, true);
    $searchSections = [];
    $initialOpen = [];
    $sectionStats = [];
    $selectedTotal = 0;
    $availableTotal = 0;
    $sectionCount = count($sections);

    foreach ($sections as $section) {
        $widgets = $section['widgets'] ?? [];
        $total = count($widgets);
        $selected = 0;
        $searchWidgets = [];

        foreach ($widgets as $widget) {
            if (isset($enabledLookup[$widget['key'] ?? ''])) {
                $selected++;
            }

            $searchWidgets[] = [
                'title' => (string) ($widget['title'] ?? ''),
                'description' => (string) ($widget['description'] ?? ''),
            ];
        }

        $selectedTotal += $selected;
        $availableTotal += $total;
        $searchSections[] = ['widgets' => $searchWidgets];
        $sectionStats[] = ['selected' => $selected, 'total' => $total];
        $initialOpen[] = $sectionCount < 2;
    }
@endphp

<script wire:ignore>
    window.widgetMenu = function (sections, initialOpen) {
        return {
            query: '',
            open: initialOpen,
            sections,
            init() {
                this.$watch('query', (value) => {
                    if (String(value ?? '').trim() === '') {
                        return;
                    }

                    this.sections.forEach((_, sectionIndex) => {
                        if (this.sectionHasMatches(sectionIndex)) {
                            this.open[sectionIndex] = true;
                        }
                    });
                });
            },
            normalize(value) {
                return String(value ?? '')
                    .toLocaleLowerCase('de-DE')
                    .replaceAll('ä', 'ae')
                    .replaceAll('ö', 'oe')
                    .replaceAll('ü', 'ue')
                    .replaceAll('ß', 'ss');
            },
            escapeHtml(value) {
                return String(value).replace(/[&<>"']/g, (char) => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;',
                }[char]));
            },
            widgetMatches(sectionIndex, widgetIndex) {
                const query = this.normalize(this.query.trim());

                if (query === '') {
                    return true;
                }

                const widget = this.sections[sectionIndex]?.widgets[widgetIndex];

                if (! widget) {
                    return false;
                }

                return this.normalize(`${widget.title} ${widget.description}`).includes(query);
            },
            sectionHasMatches(sectionIndex) {
                const widgets = this.sections[sectionIndex]?.widgets ?? [];

                return widgets.some((_, widgetIndex) => this.widgetMatches(sectionIndex, widgetIndex));
            },
            hasAnyMatch() {
                return this.sections.some((_, sectionIndex) => this.sectionHasMatches(sectionIndex));
            },
            matchCount(sectionIndex) {
                const widgets = this.sections[sectionIndex]?.widgets ?? [];

                return widgets.filter((_, widgetIndex) => this.widgetMatches(sectionIndex, widgetIndex)).length;
            },
            matchLabel(sectionIndex) {
                const count = this.matchCount(sectionIndex);

                return count === 1 ? '1 Treffer' : `${count} Treffer`;
            },
            resultLabel() {
                const count = this.sections.reduce((sum, _, sectionIndex) => sum + this.matchCount(sectionIndex), 0);

                if (count === 0) {
                    return 'Keine Treffer';
                }

                return count === 1 ? '1 Treffer' : `${count} Treffer`;
            },
            isOpen(sectionIndex) {
                if (this.query.trim() !== '') {
                    return this.sectionHasMatches(sectionIndex);
                }

                return this.open[sectionIndex] === true;
            },
            toggle(sectionIndex) {
                if (this.query.trim() !== '') {
                    return;
                }

                this.open[sectionIndex] = ! this.open[sectionIndex];
            },
            setAll(isOpen) {
                this.open = this.open.map(() => isOpen);
            },
            highlight(text) {
                const value = String(text ?? '');
                const query = this.query.trim();

                if (query === '') {
                    return this.escapeHtml(value);
                }

                const source = value.toLocaleLowerCase('de-DE');
                const needle = query.toLocaleLowerCase('de-DE');

                if (source.length !== value.length || needle.length !== query.length) {
                    return this.escapeHtml(value);
                }

                const index = source.indexOf(needle);

                if (index === -1) {
                    return this.escapeHtml(value);
                }

                const end = index + needle.length;

                return this.escapeHtml(value.slice(0, index))
                    + '<mark class="rounded-sm bg-[#d0e3f9] px-0.5 text-[#04214e] dark:bg-[#456494] dark:text-white">'
                    + this.escapeHtml(value.slice(index, end))
                    + '</mark>'
                    + this.escapeHtml(value.slice(end));
            },
        };
    };
</script>

<flux:modal.trigger :name="$modalName">
    <flux:button variant="ghost" icon="squares-plus" icon-trailing="chevron-down">Widgets</flux:button>
</flux:modal.trigger>

<flux:modal :name="$modalName" variant="flyout" class="md:max-w-lg">
    <div
        class="space-y-5"
        data-tour="dashboard-widgets-panel"
        wire:ignore.self
        x-data="widgetMenu(@js($searchSections), @js($initialOpen))"
    >
        <div class="sticky top-0 z-10 space-y-4 bg-white pb-4 dark:bg-zinc-800">
            <div class="space-y-1 pe-8">
                <flux:heading size="lg">Widgets</flux:heading>
                <flux:text class="text-slate-600 dark:text-white/70">{{ $descriptionText }}</flux:text>
            </div>

            <flux:input
                x-ref="search"
                icon="magnifying-glass"
                placeholder="Widgets suchen"
                aria-label="Widgets suchen"
                autocomplete="off"
                clearable
                autofocus
                x-model="query"
            />

            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm text-slate-600 dark:text-white/70">
                    <span x-show="query.trim() === ''">{{ $selectedTotal }} von {{ $availableTotal }} ausgewählt</span>
                    <span x-show="query.trim() !== ''" x-cloak x-text="resultLabel()"></span>
                </p>
                <div class="flex gap-3" x-show="query.trim() === '' && open.length > 1" x-cloak>
                    <button type="button" class="text-xs font-semibold text-[#073070] hover:underline dark:text-[#d0e3f9]" x-on:click="setAll(true)">
                        Alle aufklappen
                    </button>
                    <button type="button" class="text-xs font-semibold text-[#073070] hover:underline dark:text-[#d0e3f9]" x-on:click="setAll(false)">
                        Alle zuklappen
                    </button>
                </div>
            </div>
        </div>

        <div class="space-y-2" data-tour="dashboard-widgets-list">
            @foreach($sections as $sectionIndex => $section)
                @php
                    $selected = $sectionStats[$sectionIndex]['selected'];
                    $total = $sectionStats[$sectionIndex]['total'];
                    $initiallyOpen = $initialOpen[$sectionIndex];
                    $sectionDomId = 'widget-section-'.$modalName.'-'.$sectionIndex;
                @endphp
                <section
                    x-show="sectionHasMatches({{ $sectionIndex }})"
                    data-widget-menu-section="{{ $section['label'] }}"
                    data-widget-section-count="{{ $selected }}/{{ $total }}"
                    data-widget-section-open="{{ $initiallyOpen ? '1' : '0' }}"
                    class="overflow-hidden rounded-xl border border-[#d0e3f9] bg-white dark:border-white/10 dark:bg-zinc-900"
                >
                    <h3>
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-[#d0e3f9]/40 dark:hover:bg-white/5"
                            x-on:click="toggle({{ $sectionIndex }})"
                            x-bind:aria-expanded="isOpen({{ $sectionIndex }}) ? 'true' : 'false'"
                            aria-controls="{{ $sectionDomId }}"
                        >
                            <flux:icon
                                name="chevron-right"
                                class="size-4 shrink-0 text-[#073070] transition-transform dark:text-white"
                                x-bind:class="isOpen({{ $sectionIndex }}) ? 'rotate-90' : ''"
                            />
                            <span class="min-w-0 flex-1 text-sm font-semibold text-[#073070] dark:text-white">{{ $section['label'] }}</span>
                            <span
                                x-show="query.trim() !== ''"
                                x-cloak
                                class="shrink-0 text-xs font-medium text-slate-600 dark:text-white/70"
                                x-text="matchLabel({{ $sectionIndex }})"
                            ></span>
                            <span
                                class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums {{ $selected > 0 ? 'bg-[#073070] text-white' : 'bg-white text-[#073070] ring-1 ring-[#d0e3f9] dark:bg-white/10 dark:text-white dark:ring-white/15' }}"
                                aria-label="{{ $selected }} von {{ $total }} ausgewählt"
                            >{{ $selected }}/{{ $total }}</span>
                        </button>
                    </h3>

                    <div
                        id="{{ $sectionDomId }}"
                        class="space-y-1 border-t border-[#d0e3f9] p-2 dark:border-white/10"
                        x-show="isOpen({{ $sectionIndex }})"
                        @unless($initiallyOpen) x-cloak @endunless
                    >
                        @foreach($section['widgets'] as $widgetIndex => $widget)
                            <div
                                x-show="widgetMatches({{ $sectionIndex }}, {{ $widgetIndex }})"
                                x-bind:class="query.trim() !== '' ? 'bg-[#d0e3f9]/60 ring-1 ring-[#a0c7f2] dark:bg-[#456494]/40 dark:ring-white/20' : ''"
                                class="flex w-full items-center justify-between gap-3 rounded-lg border border-[#d0e3f9] bg-white px-3 py-2 dark:border-white/10 dark:bg-zinc-900"
                            >
                                <button
                                    type="button"
                                    class="min-w-0 flex-1 text-left hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-70"
                                    wire:click="{{ $toggleMethod }}('{{ $widget['key'] }}')"
                                    @disabled(($showMandatory === true) && ($widget['mandatory'] ?? false))
                                >
                                    <span class="block text-sm font-medium text-[#073070] dark:text-white">
                                        <span x-show="query.trim() === ''">{{ $widget['title'] }}</span>
                                        <span x-show="query.trim() !== ''" x-cloak x-html="highlight(sections[{{ $sectionIndex }}].widgets[{{ $widgetIndex }}].title)"></span>
                                    </span>
                                    @if(! empty($widget['description']))
                                        <span class="mt-0.5 block text-xs text-slate-600 dark:text-white/70">
                                            <span x-show="query.trim() === ''">{{ $widget['description'] }}</span>
                                            <span x-show="query.trim() !== ''" x-cloak x-html="highlight(sections[{{ $sectionIndex }}].widgets[{{ $widgetIndex }}].description)"></span>
                                        </span>
                                    @endif
                                </button>

                                <span class="flex shrink-0 items-center gap-2">
                                    @if($this->{$supportsItemCountMethod}($widget['key']))
                                        <span class="w-24" data-tour="dashboard-widget-item-count">
                                            <flux:input
                                                type="number"
                                                min="1"
                                                max="30"
                                                size="sm"
                                                :value="$this->{$itemCountValueMethod}($widget['key'])"
                                                wire:change="{{ $saveItemCountMethod }}('{{ $widget['key'] }}', $event.target.value)"
                                            />
                                        </span>
                                    @endif

                                    @if(($showMandatory === true) && ($widget['mandatory'] ?? false))
                                        <flux:badge size="sm" color="zinc">Pflicht</flux:badge>
                                    @elseif(in_array($widget['key'], $enabledWidgets, true))
                                        <flux:icon name="check-circle" class="size-5 text-[#073070] dark:text-[#d0e3f9]" />
                                    @else
                                        <flux:icon name="minus-circle" class="size-5 text-[#456494] dark:text-white/50" />
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <div
                x-show="query.trim() !== '' && !hasAnyMatch()"
                x-cloak
                class="rounded-xl border border-[#d0e3f9] px-4 py-8 text-center dark:border-white/10"
            >
                <p class="text-sm font-semibold text-[#073070] dark:text-white">Keine Widgets gefunden</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-white/70">Der Begriff kommt in keinem Titel und keiner Beschreibung vor.</p>
            </div>
        </div>

        <div class="flex flex-wrap justify-between gap-2 border-t border-[#d0e3f9] pt-4 dark:border-white/10">
            <flux:button
                variant="danger"
                icon="arrow-path"
                wire:click="{{ $resetMethod }}"
                wire:confirm="Dashboard wirklich auf Standard zurücksetzen?"
            >
                Zurücksetzen auf Standard
            </flux:button>
            <flux:modal.close>
                <flux:button variant="ghost">Schließen</flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>
