<div class="space-y-6">

    {{-- Messages flash --}}
    @if (session('success'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('success') }}" />
    @endif

    @if (session('error'))
        <flux:callout variant="danger" icon="exclamation-triangle" heading="{{ session('error') }}" />
    @endif

    {{-- En-tête : recherche + filtre statut + bouton créer --}}
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Rechercher par référence ou client..."
                class="max-w-sm"
            />

            <select wire:model.live="statusFilter" class="rounded-lg border border-zinc-700 bg-zinc-800 text-white px-3 py-2">
                <option value="">Tous les statuts</option>
                <option value="pending">En attente</option>
                <option value="confirmed">Confirmée</option>
                <option value="delivered">Livrée</option>
                <option value="cancelled">Annulée</option>
            </select>
        </div>

        <flux:button wire:click="create" variant="primary" icon="plus">
            Nouvelle commande
        </flux:button>
    </div>

    {{-- Tableau --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Référence</flux:table.column>
            <flux:table.column>Client</flux:table.column>
            <flux:table.column
                sortable
                :sorted="$sortField === 'created_at'"
                :direction="$sortDirection"
                wire:click="sortBy('created_at')"
            >
                Date
            </flux:table.column>
            <flux:table.column>Articles</flux:table.column>
            <flux:table.column>Total</flux:table.column>
            <flux:table.column>Statut</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($orders as $order)
                <flux:table.row wire:key="order-{{ $order->id }}">
                    <flux:table.cell class="font-medium">{{ $order->reference }}</flux:table.cell>
                    <flux:table.cell>{{ $order->customers?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $order->created_at->format('d/m/Y') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm">{{ $order->order_items->count() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ number_format($order->total_amount, 2) }} €</flux:table.cell>
                    <flux:table.cell>
                        @switch($order->status)
                            @case('pending')
                                <flux:badge size="sm" color="yellow">En attente</flux:badge>
                                @break
                            @case('confirmed')
                                <flux:badge size="sm" color="blue">Confirmée</flux:badge>
                                @break
                            @case('delivered')
                                <flux:badge size="sm" color="green">Livrée</flux:badge>
                                @break
                            @case('cancelled')
                                <flux:badge size="sm" color="red">Annulée</flux:badge>
                                @break
                        @endswitch
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="eye"
                                wire:click="edit({{ $order->id }}, true)"
                            />

                            @if ($order->status === 'pending')
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil"
                                    wire:click="edit({{ $order->id }})"
                                />
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="check"
                                    wire:click="confirmOrder({{ $order->id }})"
                                    wire:confirm="Confirmer cette commande ?"
                                />
                            @endif

                            @if ($order->status === 'confirmed')
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil"
                                    wire:click="edit({{ $order->id }})"
                                />
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="truck"
                                    wire:click="markDelivered({{ $order->id }})"
                                    wire:confirm="Marquer cette commande comme livrée ? Le stock des produits sera décrémenté automatiquement."
                                />
                            @endif

                            @if (in_array($order->status, ['pending', 'confirmed', 'delivered']))
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="x-mark"
                                    wire:click="cancelOrder({{ $order->id }})"
                                    wire:confirm="Annuler cette commande ? {{ $order->status === 'delivered' ? 'Le stock sera restauré automatiquement.' : '' }}"
                                />
                            @endif

                            @if (in_array($order->status, ['pending', 'confirmed']))
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="delete({{ $order->id }})"
                                    wire:confirm="Supprimer la commande « {{ $order->reference }} » ? Cette action est irréversible."
                                />
                            @endif
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-500 py-6">
                        Aucune commande trouvée.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div>
        {{ $orders->links() }}
    </div>

    {{-- Modal création / édition / consultation --}}
    <flux:modal wire:model="showModal" name="order-form" class="max-w-3xl">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    @if ($readOnly)
                        Détail de la commande
                    @elseif ($editingId)
                        Modifier la commande
                    @else
                        Nouvelle commande
                    @endif
                </flux:heading>
            </div>

            <flux:field>
                <flux:label>Client</flux:label>
                <select wire:model="customer_id" :disabled="$readOnly" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 text-white px-3 py-2 disabled:opacity-60">
                    <option value="">Choisir un client...</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                    @endforeach
                </select>
                <flux:error name="customer_id" />
            </flux:field>

            {{-- Lignes de produits --}}
            <div class="space-y-3">
                <flux:label>Articles</flux:label>

                @foreach ($items as $index => $item)
                    <div class="grid grid-cols-12 gap-2 items-start" wire:key="order-item-{{ $index }}">
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