<?php

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Stock_mvt;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    // --- Filtres / tri de la liste ---
    public string $search = '';
    public string $typeFilter = '';
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    // --- État du formulaire (création manuelle uniquement) ---
    public bool $showModal = false;

    public ?int $product_id = null;
    public string $type = 'IN';
    public string $quantity = '1';
    public string $notes = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    /**
     * Enregistre un mouvement de stock manuel et met à jour la quantité du produit.
     * Les mouvements manuels ont une référence "Ajustement manuel" (pas de lien avec un achat/commande).
     */
    public function save(): void
    {
        $validated = $this->validate([
            'product_id' => ['required', 'exists:products,id'],
            'type' => ['required', 'in:IN,OUT'],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $product = Product::findOrFail($validated['product_id']);

        if ($validated['type'] === 'OUT' && $product->quantity < (int) $validated['quantity']) {
            $this->addError('quantity', "Stock insuffisant : seulement {$product->quantity} unité(s) disponible(s).");
            return;
        }

        DB::transaction(function () use ($product, $validated) {
            if ($validated['type'] === 'IN') {
                $product->increment('quantity', (int) $validated['quantity']);
            } else {
                $product->decrement('quantity', (int) $validated['quantity']);
            }

            Stock_mvt::create([
                'product_id' => $product->id,
                'type' => $validated['type'],
                'quantity' => (int) $validated['quantity'],
                'reference' => 'Ajustement manuel',
                'notes' => $validated['notes'],
            ]);
        });

        ActivityLog::record(
            $validated['type'] === 'IN' ? 'stock_in' : 'stock_out',
            $product,
            "Ajustement manuel : {$validated['type']} de {$validated['quantity']} unité(s) sur « {$product->name} »"
        );

        session()->flash('success', 'Mouvement de stock enregistré avec succès.');

        $this->showModal = false;
        $this->resetForm();
    }

    /**
     * Supprime un mouvement et annule son effet sur le stock.
     * Uniquement autorisé pour les ajustements manuels (pas ceux issus d'un achat/commande),
     * afin de ne jamais désynchroniser l'historique lié à un document (achat, commande...).
     */
    public function delete(int $id): void
    {
        $movement = Stock_mvt::findOrFail($id);

        if ($movement->reference !== 'Ajustement manuel') {
            session()->flash('error', 'Impossible de supprimer un mouvement lié à un achat ou une commande.');
            return;
        }

        $productName = $movement->products?->name ?? 'produit supprimé';

        DB::transaction(function () use ($movement) {
            $product = $movement->products;

            if ($product) {
                if ($movement->type === 'IN') {
                    $product->decrement('quantity', $movement->quantity);
                } else {
                    $product->increment('quantity', $movement->quantity);
                }
            }

            $movement->delete();
        });

        ActivityLog::record('deleted', null, "Mouvement de stock manuel supprimé sur « {$productName} » (#{$movement->id})");

        session()->flash('success', 'Mouvement supprimé, stock ajusté en conséquence.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['product_id', 'notes']);
        $this->type = 'IN';
        $this->quantity = '1';
        $this->resetErrorBag();
    }

    public function render()
    {
        return $this->view([
            'movements' => Stock_mvt::query()
                ->with('products')
                ->when($this->search, function ($query) {
                    $query->where('reference', 'like', "%{$this->search}%")
                        ->orWhereHas('products', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
                })
                ->when($this->typeFilter, fn ($query) => $query->where('type', $this->typeFilter))
                ->orderBy($this->sortField, $this->sortDirection)
                ->paginate(15),
            'products' => Product::orderBy('name')->get(),
        ]);
    }
};