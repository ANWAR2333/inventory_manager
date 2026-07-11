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
            placeholder="Rechercher une catégorie..."
            class="max-w-sm"
        />

        <flux:button wire:click="create" variant="primary" icon="plus">
            Nouvelle catégorie
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
            <flux:table.column>Slug</flux:table.column>
            <flux:table.column>Description</flux:table.column>
            <flux:table.column
                sortable
                :sorted="$sortField === 'created_at'"
                :direction="$sortDirection"
                wire:click="sortBy('created_at')"
            >
                Créée le
            </flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($categories as $category)
                <flux:table.row wire:key="category-{{ $category->id }}">
                    <flux:table.cell class="font-medium">{{ $category->name }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $category->slug }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm">{{ $category->products_count }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="text-zinc-500">
                        {{ $category->created_at->format('d/m/Y') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="pencil"
                                wire:click="edit({{ $category->id }})"
                            />
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="trash"
                                wire:click="delete({{ $category->id }})"
                                wire:confirm="Supprimer la catégorie « {{ $category->name }} » ? Cette action est irréversible."
                            />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-500 py-6">
                        Aucune catégorie trouvée.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div>
        {{ $categories->links() }}
    </div>

    {{-- Modal création / édition --}}
    <flux:modal wire:model="showModal" name="category-form" class="max-w-md">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingId ? 'Modifier la catégorie' : 'Nouvelle catégorie' }}
                </flux:heading>
            </div>

            <flux:input
                wire:model="name"
                label="Nom"
                placeholder="Ex. : Electronics"
                required
            />

            <flux:input
                wire:model="slug"
                label="Slug"
                placeholder="Ex. : electronics"
                required
            />

            <flux:textarea
                wire:model="description"
                label="Description"
                placeholder="Description facultative"
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