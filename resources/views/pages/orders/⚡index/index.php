<?php

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Stock_mvt;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    // --- Filtres / tri de la liste ---
    public string $search = '';
    public string $statusFilter = '';
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    // --- État du formulaire (création / édition / consultation) ---
    public bool $showModal = false;
    public bool $readOnly = false;
    public ?int $editingId = null;

    public ?int $customer_id = null;
    public string $notes = '';

    /** @var array<int, array{product_id: ?int, quantity: int, unit_price: float}> */
    public array $items = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
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
     * Quand un produit est choisi sur une ligne, préremplit le prix de vente
     * si le champ prix n'a pas encore été modifié manuellement.
     */
    public function updated(string $name, mixed $value): void
    {
        if (preg_match('/^items\.(\d+)\.product_id$/', $name, $matches)) {
            $index = (int) $matches[1];
            $product = Product::find($value);

            if ($product && (float) $this->items[$index]['unit_price'] === 0.0) {
                $this->items[$index]['unit_price'] = (float) $product->selling_price;
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
     * Ouvre le modal. Lecture seule si demandé explicitement, ou si la commande
     * est dans un état final (delivered / cancelled).
     */
    public function edit(int $id, bool $readOnly = false): void
    {
        $order = Order::with('order_items')->findOrFail($id);

        $this->editingId = $order->id;
        $this->customer_id = $order->customer_id;
        $this->notes = (string) $order->notes;
        $this->readOnly = $readOnly || in_array($order->status, ['delivered', 'cancelled'], true);

        $this->items = $order->order_items->map(fn ($item) => [
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
            'customer_id' => ['required', 'exists:customers,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $isNew = ! $this->editingId;
        $order = null;

        DB::transaction(function () use (&$order) {
            $totalAmount = $this->total;

            if ($this->editingId) {
                $order = Order::findOrFail($this->editingId);

                if (in_array($order->status, ['delivered', 'cancelled'], true)) {
                    session()->flash('error', 'Impossible de modifier une commande livrée ou annulée.');
                    return;
                }

                $order->update([
                    'customer_id' => $this->customer_id,
                    'notes' => $this->notes,
                    'total_amount' => $totalAmount,
                ]);

                // Commande encore pending/confirmed : aucun impact stock, on peut reconstruire les lignes.
                $order->order_items()->delete();
            } else {
                $order = Order::create([
                    'customer_id' => $this->customer_id,
                    'reference' => $this->generateOrderReference(),
                    'status' => 'pending',
                    'total_amount' => $totalAmount,
                    'notes' => $this->notes,
                ]);
            }

            foreach ($this->items as $item) {
                $order->order_items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => round($item['quantity'] * $item['unit_price'], 2),
                ]);
            }
        });

        ActivityLog::record(
            $isNew ? 'created' : 'updated',
            $order,
            $isNew ? "Commande « {$order->reference} » créée" : "Commande « {$order->reference} » modifiée"
        );

        session()->flash('success', $this->editingId
            ? 'Commande mise à jour avec succès.'
            : 'Commande créée avec succès.');

        $this->showModal = false;
        $this->resetForm();
    }

    /**
     * pending -> confirmed. Aucun impact sur le stock.
     */
    public function confirmOrder(int $id): void
    {
        $order = Order::findOrFail($id);

        if ($order->status !== 'pending') {
            session()->flash('error', "Seule une commande « en attente » peut être confirmée.");
            return;
        }

        $order->update(['status' => 'confirmed']);

        ActivityLog::record('confirmed', $order, "Commande « {$order->reference} » confirmée");

        session()->flash('success', 'Commande confirmée.');
    }

    /**
     * confirmed -> delivered. Décrémente le stock et crée les mouvements OUT.
     * Toute la transaction est annulée si le stock est insuffisant pour un produit.
     */
    public function markDelivered(int $id): void
    {
        $order = Order::with('order_items.products')->findOrFail($id);

        if ($order->status !== 'confirmed') {
            session()->flash('error', "Seule une commande « confirmée » peut être marquée comme livrée.");
            return;
        }

        foreach ($order->order_items as $item) {
            if (! $item->products || $item->products->quantity < $item->quantity) {
                session()->flash('error', "Stock insuffisant pour le produit « {$item->products?->name} ».");
                return;
            }
        }

        DB::transaction(function () use ($order) {
            foreach ($order->order_items as $item) {
                $item->products()->decrement('quantity', $item->quantity);

                Stock_mvt::create([
                    'product_id' => $item->product_id,
                    'type' => 'OUT',
                    'quantity' => $item->quantity,
                    'reference' => $order->reference,
                    'notes' => "Livraison commande #{$order->reference}",
                ]);
            }

            $order->update(['status' => 'delivered']);
        });

        ActivityLog::record('delivered', $order, "Commande « {$order->reference} » marquée comme livrée (stock mis à jour)");

        session()->flash('success', 'Commande marquée comme livrée, stock mis à jour.');
    }

    /**
     * Annule une commande. Si elle était "delivered", restaure le stock
     * précédemment décrémenté et crée les mouvements IN correspondants.
     */
    public function cancelOrder(int $id): void
    {
        $order = Order::with('order_items')->findOrFail($id);

        if ($order->status === 'cancelled') {
            session()->flash('error', 'Cette commande est déjà annulée.');
            return;
        }

        DB::transaction(function () use ($order) {
            if ($order->status === 'delivered') {
                foreach ($order->order_items as $item) {
                    $item->products()->increment('quantity', $item->quantity);

                    Stock_mvt::create([
                        'product_id' => $item->product_id,
                        'type' => 'IN',
                        'quantity' => $item->quantity,
                        'reference' => $order->reference,
                        'notes' => "Annulation commande #{$order->reference}",
                    ]);
                }
            }

            $order->update(['status' => 'cancelled']);
        });

        ActivityLog::record('cancelled', $order, "Commande « {$order->reference} » annulée");

        session()->flash('success', 'Commande annulée.');
    }

    /**
     * Supprime une commande. Bloqué si delivered (impact stock) — cancel avant de supprimer si besoin.
     */
    public function delete(int $id): void
    {
        $order = Order::findOrFail($id);

        if ($order->status === 'delivered') {
            session()->flash('error', 'Impossible de supprimer une commande livrée. Annulez-la d\'abord.');
            return;
        }

        ActivityLog::record('deleted', $order, "Commande « {$order->reference} » supprimée");

        $order->delete();

        session()->flash('success', 'Commande supprimée avec succès.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'customer_id', 'notes', 'items', 'readOnly']);
        $this->items = [['product_id' => null, 'quantity' => 1, 'unit_price' => 0]];
        $this->resetErrorBag();
    }

    private function generateOrderReference(): string
    {
        $prefix = 'CMD-' . now()->format('Ymd') . '-';
        $countToday = Order::whereDate('created_at', now()->toDateString())->count() + 1;

        return $prefix . str_pad((string) $countToday, 4, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        return $this->view([
            'orders' => Order::query()
                ->with(['customers', 'order_items'])
                ->when($this->search, function ($query) {
                    $query->where('reference', 'like', "%{$this->search}%")
                        ->orWhereHas('customers', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
                })
                ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
                ->orderBy($this->sortField, $this->sortDirection)
                ->paginate(10),
            'customers' => Customer::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }
};