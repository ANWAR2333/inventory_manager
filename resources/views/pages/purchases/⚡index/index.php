<?php

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Purchase_Item;
use App\Models\Stock_mvt;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    // --- Filtres / tri de la liste ---
    public string $search = '';
    public string $sortField = 'purchase_date';
    public string $sortDirection = 'desc';

    // --- État du formulaire (création / édition / consultation) ---
    public bool $showModal = false;
    public bool $readOnly = false;
    public ?int $editingId = null;

    public ?int $supplier_id = null;
    public string $purchase_date = '';
    public string $notes = '';

    /** @var array<int, array{product_id: ?int, quantity: int, unit_price: float}> */
    public array $items = [];

    public function mount(): void
    {
        $this->purchase_date = now()->format('Y-m-d');
    }

    public function updatingSearch(): void
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

    /**
     * Quand un produit est choisi sur une ligne, préremplit le prix d'achat
     * si le champ prix n'a pas encore été modifié manuellement.
     */
    public function updated(string $name, mixed $value): void
    {
        if (preg_match('/^items\.(\d+)\.product_id$/', $name, $matches)) {
            $index = (int) $matches[1];
            $product = Product::find($value);

            if ($product && (float) $this->items[$index]['unit_price'] === 0.0) {
                $this->items[$index]['unit_price'] = (float) $product->purchase_price;
            }
        }
    }

    public function addItem(): void
    {
        $this->items[] = ['product_id' => null, 'quantity' => 1, 'unit_price' => 0];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function getLineSubtotal(int $index): float
    {
        $item = $this->items[$index] ?? null;

        if (! $item) {
            return 0.0;
        }

        return round((float) $item['quantity'] * (float) $item['unit_price'], 2);
    }

    #[Computed]
    public function total(): float
    {
        $total = 0.0;

        foreach (array_keys($this->items) as $index) {
            $total += $this->getLineSubtotal($index);
        }

        return round($total, 2);
    }

    public function create(): void
    {
        $this->resetForm();
        $this->readOnly = false;
        $this->showModal = true;
    }

    /**
     * Ouvre le modal. $readOnly = true pour un achat déjà reçu (consultation seule).
     */
    public function edit(int $id, bool $readOnly = false): void
    {
        $purchase = Purchase::with('purchase_items')->findOrFail($id);

        $this->editingId = $purchase->id;
        $this->supplier_id = $purchase->supplier_id;
        $this->purchase_date = $purchase->purchase_date->format('Y-m-d');
        $this->notes = (string) $purchase->notes;
        $this->readOnly = $readOnly || $purchase->is_completed;

        $this->items = $purchase->purchase_items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
            'unit_price' => (float) $item->unit_price,
        ])->all();

        if (empty($this->items)) {
            $this->items = [['product_id' => null, 'quantity' => 1, 'unit_price' => 0]];
        }

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $isNew = ! $this->editingId;
        $purchase = null;

        DB::transaction(function () use (&$purchase) {
            $totalAmount = $this->total;

            if ($this->editingId) {
                $purchase = Purchase::findOrFail($this->editingId);

                if ($purchase->is_completed) {
                    session()->flash('error', 'Impossible de modifier un achat déjà reçu.');
                    return;
                }

                $purchase->update([
                    'supplier_id' => $this->supplier_id,
                    'purchase_date' => $this->purchase_date,
                    'notes' => $this->notes,
                    'total_amount' => $totalAmount,
                ]);

                // On repart d'une liste de lignes propre (l'achat n'est pas encore reçu, aucun impact stock).
                $purchase->purchase_items()->delete();
            } else {
                $purchase = Purchase::create([
                    'supplier_id' => $this->supplier_id,
                    'purchase_number' => $this->generatePurchaseNumber(),
                    'purchase_date' => $this->purchase_date,
                    'is_completed' => false,
                    'total_amount' => $totalAmount,
                    'notes' => $this->notes,
                ]);
            }

            foreach ($this->items as $item) {
                $purchase->purchase_items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => round($item['quantity'] * $item['unit_price'], 2),
                ]);
            }
        });

        ActivityLog::record(
            $isNew ? 'created' : 'updated',
            $purchase,
            $isNew ? "Achat « {$purchase->purchase_number} » créé" : "Achat « {$purchase->purchase_number} » modifié"
        );

        session()->flash('success', $this->editingId
            ? 'Achat mis à jour avec succès.'
            : 'Achat créé avec succès.');

        $this->showModal = false;
        $this->resetForm();
    }

    /**
     * Marque l'achat comme reçu : incrémente le stock des produits
     * et crée un mouvement de stock (IN) pour chaque ligne.
     */
    public function markAsReceived(int $id): void
    {
        $purchase = Purchase::with('purchase_items')->findOrFail($id);

        if ($purchase->is_completed) {
            session()->flash('error', 'Cet achat a déjà été marqué comme reçu.');
            return;
        }

        DB::transaction(function () use ($purchase) {
            foreach ($purchase->purchase_items as $item) {
                $item->products()->increment('quantity', $item->quantity);

                Stock_mvt::create([
                    'product_id' => $item->product_id,
                    'type' => 'IN',
                    'quantity' => $item->quantity,
                    'reference' => $purchase->purchase_number,
                    'notes' => "Réception achat #{$purchase->purchase_number}",
                ]);
            }

            $purchase->update(['is_completed' => true]);
        });

        ActivityLog::record('received', $purchase, "Achat « {$purchase->purchase_number} » marqué comme reçu (stock mis à jour)");

        session()->flash('success', 'Achat marqué comme reçu, stock mis à jour.');
    }

    /**
     * Supprime un achat. Bloqué si déjà reçu (le stock a déjà été impacté).
     */
    public function delete(int $id): void
    {
        $purchase = Purchase::findOrFail($id);

        if ($purchase->is_completed) {
            session()->flash('error', 'Impossible de supprimer un achat déjà reçu.');
            return;
        }

        ActivityLog::record('deleted', $purchase, "Achat « {$purchase->purchase_number} » supprimé");

        $purchase->delete();

        session()->flash('success', 'Achat supprimé avec succès.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'supplier_id', 'notes', 'items', 'readOnly']);
        $this->purchase_date = now()->format('Y-m-d');
        $this->items = [['product_id' => null, 'quantity' => 1, 'unit_price' => 0]];
        $this->resetErrorBag();
    }

    private function generatePurchaseNumber(): string
    {
        $prefix = 'PO-' . now()->format('Ymd') . '-';
        $countToday = Purchase::whereDate('created_at', now()->toDateString())->count() + 1;

        return $prefix . str_pad((string) $countToday, 4, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        return $this->view([
            'purchases' => Purchase::query()
                ->with(['suppliers', 'purchase_items'])
                ->when($this->search, function ($query) {
                    $query->where('purchase_number', 'like', "%{$this->search}%")
                        ->orWhereHas('suppliers', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
                })
                ->orderBy($this->sortField, $this->sortDirection)
                ->paginate(10),
            'suppliers' => Supplier::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }
};