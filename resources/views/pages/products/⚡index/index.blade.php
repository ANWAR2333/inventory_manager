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
            placeholder="Rechercher par nom, SKU ou code-barres..."
            class="max-w-sm"
        />

        <flux:button wire:click="create" variant="primary" icon="plus">
            Nouveau produit
        </flux:button>
    </div>

    {{-- Tableau --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column></flux:table.column>
            <flux:table.column
                sortable
                :sorted="$sortField === 'name'"
                :direction="$sortDirection"
                wire:click="sortBy('name')"
            >
                Nom
            </flux:table.column>
            <flux:table.column>SKU</flux:table.column>
            <flux:table.column>Catégorie</flux:table.column>
            <flux:table.column>Fournisseur</flux:table.column>
            <flux:table.column>Prix vente</flux:table.column>
            <flux:table.column
                sortable
                :sorted="$sortField === 'quantity'"
                :direction="$sortDirection"
                wire:click="sortBy('quantity')"
            >
                Stock
            </flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($products as $product)
                <flux:table.row wire:key="product-{{ $product->id }}">
                    <flux:table.cell>
                        @if ($product->image)
                            <img src="{{ Storage::url($product->image) }}" alt="{{ $product->name }}" class="w-10 h-10 rounded object-cover">
                        @else
                            <div class="w-10 h-10 rounded bg-zinc-700 flex items-center justify-center">
                                <flux:icon name="photo" class="size-4 text-zinc-400" />
                            </div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="font-medium">{{ $product->name }}</flux:table.cell>
                    <flux:table.cell class="text-zinc-500">{{ $product->sku }}</flux:table.cell>
                    <flux:table.cell>{{ $product->category?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $product->supplier?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ number_format($product->selling_price, 2) }} €</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$product->quantity <= $product->minimum_stock ? 'red' : 'green'">
                            {{ $product->quantity }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-2">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="pencil"
                                wire:click="edit({{ $product->id }})"
                            />
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="trash"
                                wire:click="delete({{ $product->id }})"
                                wire:confirm="Supprimer le produit « {{ $product->name }} » ? Cette action est irréversible."
                            />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8" class="text-center text-zinc-500 py-6">
                        Aucun produit trouvé.
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div>
        {{ $products->links() }}
    </div>

    {{-- Modal création / édition --}}
    <flux:modal wire:model="showModal" name="product-form" class="max-w-xl">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingId ? 'Modifier le produit' : 'Nouveau produit' }}
                </flux:heading>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>Catégorie</flux:label>
                    <select wire:model.live="category_id" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 text-white px-3 py-2">
                        <option value="">Choisir une catégorie...</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <flux:error name="category_id" />
                </flux:field>

                <flux:field>
                    <flux:label>Fournisseur</flux:label>
                    <select wire:model.live="supplier_id" class="w-full rounded-lg border border-zinc-700 bg-zinc-800 text-white px-3 py-2">
                        <option value="">Choisir un fournisseur...</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    <flux:error name="supplier_id" />
                </flux:field>
            </div>

            <flux:input wire:model="name" label="Nom du produit" placeholder="Ex. : Casserole inox 24cm" required />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="sku" label="SKU" placeholder="Ex. : CASS-INOX-24" required />
                <flux:input wire:model="barcode" label="Code-barres" placeholder="Ex. : 3760123456789" required />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="purchase_price" type="number" step="0.01" label="Prix d'achat" required />
                <flux:input wire:model="selling_price" type="number" step="0.01" label="Prix de vente" required />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="quantity" type="number" label="Quantité en stock" required />
                <flux:input wire:model="minimum_stock" type="number" label="Seuil d'alerte stock" required />
            </div>

            <flux:textarea wire:model="description" label="Description" rows="3" placeholder="Description facultative" />

            <div>
                <flux:input type="file" wire:model="image" label="Image du produit" accept="image/*" />

                <div wire:loading wire:target="image" class="text-sm text-zinc-500 mt-1">
                    Envoi en cours...
                </div>

                @if ($image)
                    <img src="{{ $image->temporaryUrl() }}" class="mt-2 w-20 h-20 rounded object-cover">
                @elseif ($existingImage)
                    <div class="flex items-center gap-2 mt-2">
                        <img src="{{ Storage::url($existingImage) }}" class="w-20 h-20 rounded object-cover">
                        <flux:button type="button" size="sm" variant="ghost" wire:click="removeImage">
                            Retirer
                        </flux:button>
                    </div>
                @endif
            </div>

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