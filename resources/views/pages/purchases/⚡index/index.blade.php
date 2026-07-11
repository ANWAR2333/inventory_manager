<div class="space-y-6">

    {{-- Messages flash --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('success') }}" />
    @endif

    @if (session('error'))
        <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ session('error') }}" />
    @endif

    {{-- En-tête : recherche + bouton créer --}}
    <div class="flex items-center justify-between gap-4">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="Rechercher par n° d'achat ou fournisseur..."
            class="max-w-sm"
        />

        <flux:button wire:click="create" variant="primary" icon="plus">
            Nouvel achat
        </flux:button>
    </div>

    {{-- Tableau --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>N° achat</flux:table.column>
            <flux:table.column>Fournisseur</flux:table.column>
            <flux:table.column
                sortable
                :sorted="$sortField === 'purchase_date'"
                :direction="$sortDirection"
                wire:click="sortBy('purchase_date')"
            >
                Date
            </flux:table.column>
            <flux:table.column>Articles</flux:table.column>
            <flux:table.column>Total</flux:table.column>
            <flux:table.column>Statut</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($purchases as $purchase)
                <flux:table.row wire:key="purchase-{{ $purchase->id }}">
                    <flux:table.cell class="font-medium">{{ $purchase->purchase_number }}</flux:table.cell>
                    <flux:table.cell>{{ $purchase->suppliers?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $purchase->purchase_date->format('d/m/Y') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm">{{ $purchase->purchase_items->count() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ number_format($purchase->total_amount, 2) }} €</flux:table.cell>
                    <flux:table.cell>
                        @if ($purchase->is_completed)
                            <flux:badge size="sm" color="green">Reçu</flux:badge>
                        @else
                            <flux:badge size="sm" color="yellow">En attente</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="eye"
                                wire:click="edit({{ $purchase->id }}, true)"
                            />

                            @unless ($purchase->is_completed)
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil"
                                    wire:click="edit({{ $purchase->id }})"
                                />
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="check"
                                    wire:click="markAsReceived({{ $purchase->id }})"
                                    wire:confirm="Confirmer la réception de cet achat ? Le stock des produits sera mis à jour automatiquement."
                                />
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="delete({{ $purchase->id }})"
                                    wire:confirm="Supprimer l'achat « {{ $purchase->purchase_number }} » ? Cette action est irréversible."
                                />
                            @endunless
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-500 py-6">
                        Aucun achat trouvé.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div>
        {{ $purchases->links() }}
    </div>

    {{-- Modal création / édition / consultation --}}
    <flux:modal wire:model="showModal" name="purchase-form" class="max-w-3xl">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    @if ($readOnly)
                        Détail de l'achat
                    @elseif ($editingId)
                        Modifier l'achat
                    @else
                        Nouvel achat
                    @endif
                </flux:heading>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Fournisseur</flux:label>
                    <select wire:model="supplier_id" :disabled="$readOnly" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 text-white px-3 py-2 disabled:opacity-60">
                        <option value="">Choisir un fournisseur...</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    <flux:error name="supplier_id" />
                </flux:field>

                <flux:input
                    type="date"
                    wire:model="purchase_date"
                    label="Date d'achat"
                    :disabled="$readOnly"
                    required
                />
            </div>

            {{-- Lignes de produits --}}
            <div class="space-y-3">
                <flux:label>Articles</flux:label>

                @foreach ($items as $index => $item)
                    <div class="grid grid-cols-12 gap-2 items-start" wire:key="item-{{ $index }}">
                        <div class="col-span-5">
                            <select
                                wire:model="items.{{ $index }}.product_id"
                                :disabled="$readOnly"
                                class="w-full rounded-lg border border-zinc-700 bg-zinc-800 text-white px-3 py-2 disabled:opacity-60"
                            >
                                <option value="">Choisir un produit...</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                                @endforeach
                            </select>
                            <flux:error name="items.{{ $index }}.product_id" />
                        </div>

                        <div class="col-span-2">
                            <flux:input
                                type="number"
                                wire:model="items.{{ $index }}.quantity"
                                placeholder="Qté"
                                :disabled="$readOnly"
                            />
                        </div>

                        <div class="col-span-2">
                            <flux:input
                                type="number"
                                step="0.01"
                                wire:model="items.{{ $index }}.unit_price"
                                placeholder="Prix unit."
                                :disabled="$readOnly"
                            />
                        </div>

                        <div class="col-span-2 flex items-center h-10 text-sm text-zinc-400">
                            {{ number_format($this->getLineSubtotal($index), 2) }} €
                        </div>

                        @unless ($readOnly)
                            <div class="col-span-1">
                                <flux:button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="removeItem({{ $index }})"
                                />
                            </div>
                        @endunless
                    </div>
                @endforeach

                @unless ($readOnly)
                    <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addItem">
                        Ajouter une ligne
                    </flux:button>
                @endunless
            </div>

            <div class="flex justify-end text-lg font-semibold">
                Total : {{ number_format($this->total, 2) }} €
            </div>

            <flux:textarea wire:model="notes" label="Notes" rows="2" :disabled="$readOnly" />

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="closeModal">
                    {{ $readOnly ? 'Fermer' : 'Annuler' }}
                </flux:button>

                @unless ($readOnly)
                    <flux:button type="submit" variant="primary">
                        Enregistrer
                    </flux:button>
                @endunless
            </div>
        </form>
    </flux:modal>

</div>