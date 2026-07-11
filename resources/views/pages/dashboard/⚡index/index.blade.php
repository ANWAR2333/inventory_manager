<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl" >

    {{-- Cartes de statistiques --}}
    <div class="grid auto-rows-min gap-4 md:grid-cols-3 text-center ">
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 p-5 space-y-1" style="padding-top: 1.5rem; padding-bottom: 1.5rem;">
            <flux:text size="sm" class="text-zinc-500" style="text-align: center">Produits en stock</flux:text>
            <flux:heading size="xl">{{ $totalProducts }}</flux:heading>
            <flux:text size="sm" class="text-zinc-500">
                Valeur totale : {{ number_format($stockValue, 2) }} €
            </flux:text>
        </div>
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 p-5 space-y-1" style="padding-top: 1.5rem; padding-bottom: 1.5rem;">
            <flux:text size="sm" class="text-zinc-500">Commandes</flux:text>
            <flux:heading size="xl">{{ $pendingOrdersCount + $confirmedOrdersCount }}</flux:heading>
            <flux:text size="sm" class="text-zinc-500">
                {{ $pendingOrdersCount }} en attente · {{ $confirmedOrdersCount }} confirmées · {{ $deliveredOrdersCount }} livrées
            </flux:text>
        </div>
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 p-5 space-y-1" style="padding-top: 1.5rem; padding-bottom: 1.5rem;">
            <flux:text size="sm" class="text-zinc-500">Achats en attente</flux:text>
            <flux:heading size="xl">{{ $pendingPurchasesCount }}</flux:heading>
            <flux:text size="sm" class="text-zinc-500">
                {{ $totalCategories }} catégories · {{ $totalSuppliers }} fournisseurs · {{ $totalCustomers }} clients
            </flux:text>
        </div>
    </div>
    {{-- Alertes stock bas + derniers mouvements --}}
    <div class="grid gap-6 lg:grid-cols-2 flex-1">
        {{-- Stock bas --}}
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 p-5 space-y-4" style="padding: 1.5rem">
            <flux:heading size="lg">Alertes stock bas</flux:heading>
            @if ($lowStockProducts->isEmpty())
                <flux:callout variant="success" icon="check-circle" heading="Aucun produit en dessous du seuil d'alerte." />
            @else
                <flux:table> 
                    <flux:table.columns>
                        <flux:table.column>Produit</flux:table.column>
                        <flux:table.column>Stock</flux:table.column>
                        <flux:table.column>Seuil</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($lowStockProducts as $product)
                            <flux:table.row wire:key="low-stock-{{ $product->id }}">
                                <flux:table.cell class="font-medium">{{ $product->name }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" color="red">{{ $product->quantity }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="text-zinc-500">{{ $product->minimum_stock }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
                <flux:button href="{{ route('products.index') }}" variant="ghost" size="sm">
                    Voir tous les produits →
                </flux:button>
            @endif
        </div>
        {{-- Derniers mouvements --}}
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 p-5 space-y-4" style="padding: 1.5rem">
            <flux:heading size="lg">Derniers mouvements de stock</flux:heading>
            @if ($recentMovements->isEmpty())
                <flux:text class="text-zinc-500">Aucun mouvement enregistré pour le moment.</flux:text>
            @else
                <flux:table style="padding: 1rem">
                    <flux:table.columns>
                        <flux:table.column>Produit</flux:table.column>
                        <flux:table.column>Type</flux:table.column>
                        <flux:table.column>Qté</flux:table.column>
                        <flux:table.column>Date</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($recentMovements as $movement)
                            <flux:table.row wire:key="movement-{{ $movement->id }}">
                                <flux:table.cell class="font-medium">{{ $movement->products?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($movement->type === 'IN')
                                        <flux:badge size="sm" color="green">Entrée</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="red">Sortie</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ $movement->quantity }}</flux:table.cell>
                                <flux:table.cell class="text-zinc-500">{{ $movement->created_at->format('d/m/Y H:i') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
                <flux:button href="{{ route('stock-movements.index') }}" variant="ghost" size="sm">
                    Voir tous les mouvements →
                </flux:button>
            @endif
        </div>
    </div>
</div>