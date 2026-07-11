<?php

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads;
    use WithPagination;

    // --- Filtres / tri de la liste ---
    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';

    // --- État du formulaire (création / édition) ---
    public bool $showModal = false;
    public ?int $editingId = null;

    #[Validate('required|exists:categories,id')]
    public ?int $category_id = null;

    #[Validate('required|exists:suppliers,id')]
    public ?int $supplier_id = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    public string $sku = '';

    #[Validate('required|string|max:255')]
    public string $barcode = '';

    #[Validate('required|numeric|min:0')]
    public string $purchase_price = '';

    #[Validate('required|numeric|min:0')]
    public string $selling_price = '';

    #[Validate('required|integer|min:0')]
    public string $quantity = '0';

    #[Validate('required|integer|min:0')]
    public string $minimum_stock = '5';

    #[Validate('nullable|string|max:1000')]
    public string $description = '';

    // Nouveau fichier en cours d'upload (temporaire, pas encore enregistré)
    #[Validate('nullable|image|mimes:jpg,jpeg,png,webp|max:2048')]
    public $image = null;

    // Chemin de l'image déjà enregistrée en base (mode édition)
    public ?string $existingImage = null;

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

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $product = Product::findOrFail($id);

        $this->editingId = $product->id;
        $this->category_id = $product->category_id;
        $this->supplier_id = $product->supplier_id;
        $this->name = $product->name;
        $this->sku = $product->sku;
        $this->barcode = $product->barcode;
        $this->purchase_price = (string) $product->purchase_price;
        $this->selling_price = (string) $product->selling_price;
        $this->quantity = (string) $product->quantity;
        $this->minimum_stock = (string) $product->minimum_stock;
        $this->description = (string) $product->description;
        $this->existingImage = $product->image;
        $this->image = null;

        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'sku')
                    ->where('user_id', auth()->id())
                    ->ignore($this->editingId),
            ],
            'barcode' => ['required', 'string', 'max:255'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0', 'gte:purchase_price'],
            'quantity' => ['required', 'integer', 'min:0'],
            'minimum_stock' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'selling_price.gte' => 'Le prix de vente doit être supérieur ou égal au prix d\'achat.',
        ]);

        $imagePath = $this->existingImage;

        if ($this->image) {
            // Supprime l'ancienne image si on en upload une nouvelle
            if ($this->existingImage) {
                Storage::disk('public')->delete($this->existingImage);
            }

            $imagePath = $this->image->store('products', 'public');
        }

        $isNew = ! $this->editingId;

        $product = Product::updateOrCreate(
            ['id' => $this->editingId],
            [
                'category_id' => $validated['category_id'],
                'supplier_id' => $validated['supplier_id'],
                'name' => $validated['name'],
                'sku' => $validated['sku'],
                'barcode' => $validated['barcode'],
                'purchase_price' => $validated['purchase_price'],
                'selling_price' => $validated['selling_price'],
                'quantity' => $validated['quantity'],
                'minimum_stock' => $validated['minimum_stock'],
                'description' => $validated['description'],
                'image' => $imagePath,
            ]
        );

        ActivityLog::record(
            $isNew ? 'created' : 'updated',
            $product,
            $isNew ? "Produit « {$product->name} » créé" : "Produit « {$product->name} » modifié"
        );

        session()->flash('success', $this->editingId
            ? 'Produit mis à jour avec succès.'
            : 'Produit créé avec succès.');

        $this->showModal = false;
        $this->resetForm();
    }

    public function removeImage(): void
    {
        $this->image = null;
        $this->existingImage = null;
    }

    /**
     * Supprime un produit.
     * Bloqué si des mouvements de stock, commandes ou achats y sont encore rattachés.
     */
    public function delete(int $id): void
    {
        $product = Product::findOrFail($id);

        if ($product->order_items()->exists() || $product->stockMovements()->exists() || $product->purchase_items()->exists()) {
            session()->flash('error', 'Impossible de supprimer : ce produit a un historique de mouvements, commandes ou achats.');
            return;
        }

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        ActivityLog::record('deleted', $product, "Produit « {$product->name} » supprimé");

        $product->delete();

        session()->flash('success', 'Produit supprimé avec succès.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId', 'category_id', 'supplier_id', 'name', 'sku', 'barcode',
            'purchase_price', 'selling_price', 'quantity', 'minimum_stock',
            'description', 'image', 'existingImage',
        ]);
        $this->quantity = '0';
        $this->minimum_stock = '5';
        $this->resetErrorBag();
    }

    public function render()
    {
        return $this->view([
            'products' => Product::query()
                ->with(['category', 'supplier'])
                ->when($this->search, function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('sku', 'like', "%{$this->search}%")
                        ->orWhere('barcode', 'like', "%{$this->search}%");
                })
                ->orderBy($this->sortField, $this->sortDirection)
                ->paginate(10),
            'categories' => Category::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }
};