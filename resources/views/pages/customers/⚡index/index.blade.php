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
            placeholder="Rechercher un client..."
            class="max-w-sm"
        />

        <flux:button wire:click="create" variant="primary" icon="plus">
            Nouveau client
        </flux:button>
    </div>

    {{-- Tableau --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column
                sortable
                :sorted="$sortField === 'name'"
                :direction="$sortDirection"
                wire:click="sortBy('name')"
            >
                Nom
            </flux:table.column>
            <flux:table.column>Téléphone</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column>Commandes</flux:table.column>
            <flux:table.column
                sortable
                :sorted="$sortField === 'created_at'"
                :direction="$sortDirection"
                wire:click="sortBy('created_at')"
            >
                Créé le
            </flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($customers as $customer)
                <flux:table.row wire:key="customer-{{ $customer->id }}">
                    <flux:table.cell class="font-medium">{{ $customer->name }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $customer->phone }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $customer->email ?: '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm">{{ $customer->orders_count }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500">
                        {{ $customer->created_at->format('d/m/Y') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="pencil"
                                wire:click="edit({{ $customer->id }})"
                            />
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="trash"
                                wire:click="delete({{ $customer->id }})"
                                wire:confirm="Supprimer le client « {{ $customer->name }} » ? Cette action est irréversible."
                            />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500 py-6">
                        Aucun client trouvé.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div>
        {{ $customers->links() }}
    </div>

    {{-- Modal création / édition --}}
    <flux:modal wire:model="showModal" name="customer-form" class="max-w-md">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingId ? 'Modifier le client' : 'Nouveau client' }}
                </flux:heading>
            </div>

            <flux:input
                wire:model="name"
                label="Nom"
                placeholder="Ex. : Karim Benali"
                required
            />

            <flux:input
                wire:model="phone"
                label="Téléphone"
                placeholder="Ex. : 0600000000"
                required
            />

            <flux:input
                wire:model="email"
                type="email"
                label="Email"
                placeholder="client@email.com"
            />

            <flux:textarea
                wire:model="address"
                label="Adresse"
                placeholder="Adresse facultative"
                rows="3"
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