<div class="space-y-6">

    {{-- Messages flash --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('success') }}" />
    @endif

    @if (session('error'))
        <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ session('error') }}" />
    @endif

    {{-- En-tête : recherche + filtre + bouton créer --}}
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Rechercher par produit ou référence..."
                class="max-w-sm"
            />

            <select wire:model.live="typeFilter" class="rounded-lg border border-zinc-700 bg-zinc-800 text-white px-3 py-2">
                <option value="">Tous les types</option>
                <option value="IN">Entrées (IN)</option>
                <option value="OUT">Sorties (OUT)</option>
            </select>
        </div>

        <flux:button wire:click="create" variant="primary" icon="plus">
            Nouveau mouvement
        </flux:button>
    </div>

    {{-- Tableau --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column
                sortable
                :sorted="$sortField === 'created_at'"
                :direction="$sortDirection"
                wire:click="sortBy('created_at')"
            >
                Date
            </flux:table.column>
            <flux:table.column>Produit</flux:table.column>
            <flux:table.column>Type</flux:table.column>
            <flux:table.column>Quantité</flux:table.column>
            <flux:table.column>Référence</flux:table.column>
            <flux:table.column>Notes</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($movements as $movement)
                <flux:table.row wire:key="movement-{{ $movement->id }}">
                    <flux:table.cell class="text-zinc-500">{{ $movement->created_at->format('d/m/Y H:i') }}</flux:table.cell>
                    <flux:table.cell class="font-medium">{{ $movement->products?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($movement->type === 'IN')
                            <flux:badge size="sm" color="green">Entrée</flux:badge>
                        @else
                            <flux:badge size="sm" color="red">Sortie</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $movement->quantity }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $movement->reference ?: '—' }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $movement->notes ?: '—' }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($movement->reference === 'Ajustement manuel')
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="trash"
                                wire:click="delete({{ $movement->id }})"
                                wire:confirm="Supprimer ce mouvement ? Le stock du produit sera ajusté en conséquence."
                            />
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-500 py-6">
                        Aucun mouvement trouvé.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div>
        {{ $movements->links() }}
    </div>

    {{-- Modal création d'un mouvement manuel --}}
    <flux:modal wire:model="showModal" name="stock-movement-form" class="max-w-md">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">Nouveau mouvement de stock</flux:heading>
            </div>

            <flux:field>
                <flux:label>Produit</flux:label>
                <select wire:model="product_id" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 text-white px-3 py-2">
                    <option value="">Choisir un produit...</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }} (stock actuel : {{ $product->quantity }})</option>
                    @endforeach
                </select>
                <flux:error name="product_id" />
            </flux:field>

            <flux:field>
                <flux:label>Type de mouvement</flux:label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2">
                        <input type="radio" wire:model="type" value="IN">
                        Entrée (IN)
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" wire:model="type" value="OUT">
                        Sortie (OUT)
                    </label>
                </div>
            </flux:field>

            <flux:input
                type="number"
                wire:model="quantity"
                label="Quantité"
                min="1"
                required
            />

            <flux:textarea
                wire:model="notes"
                label="Notes"
                rows="2"
                placeholder="Ex. : Produit endommagé, inventaire annuel, correction d'erreur..."
            />

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="closeModal">
                    Annuler
                </flux:button>
                <flux:button type="submit" variant="primary">
                    Enregistrer
                </flux:button>
            </div>
        </form>
    </flux:modal>

</div>