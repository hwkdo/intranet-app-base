<div>
    <flux:card class="glass-card space-y-4">
        <flux:heading size="lg">Document-Parsing</flux:heading>
        <flux:callout icon="information-circle">
            <flux:callout.heading>Standard und wirksame Einstellung</flux:callout.heading>
            <flux:callout.text>
                Base: <strong>{{ $this->baseSummary }}</strong><br>
                Aktuell wirksam: <strong>{{ $this->effectiveSummary }}</strong>
            </flux:callout.text>
        </flux:callout>

        <form wire:submit="save" class="space-y-4">
            <flux:select wire:model="engine" label="Motor (Override)">
                <flux:select.option value="">— Base-Default —</flux:select.option>
                @foreach (\Hwkdo\IntranetAppBase\Enums\DocumentParseEngine::cases() as $engine)
                    <flux:select.option value="{{ $engine->value }}">{{ $engine->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="tier" label="LlamaParse-Tier (Override)">
                <flux:select.option value="">— Base-Default —</flux:select.option>
                <flux:select.option value="fast">Fast</flux:select.option>
                <flux:select.option value="cost_effective">Cost-effective</flux:select.option>
                <flux:select.option value="agentic">Agentic</flux:select.option>
                <flux:select.option value="agentic_plus">Agentic Plus</flux:select.option>
            </flux:select>

            <flux:text class="text-sm text-zinc-500">
                Der Tier gilt nur für LlamaParse. Vision nutzt den Text-Provider dieser App bzw. den Base-Standard.
            </flux:text>

            <flux:button type="submit" variant="primary">Speichern</flux:button>
        </form>
    </flux:card>
</div>
