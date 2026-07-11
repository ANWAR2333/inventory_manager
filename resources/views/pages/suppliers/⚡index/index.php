<?php

use App\Models\ActivityLog;
use App\Models\Supplier;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    // --- Filtres / tri de la liste ---
    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';

    // --- État du formulaire (création / édition) ---
    public bool $showModal = false;
    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|max:30')]
    public string $phone = '';

    #[Validate('nullable|email|max:255')]
    public string $email = '';

    #[Validate('nullable|string|max:500')]
    public string $address = '';

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
     * Ouvre le modal en mode création.
     */
    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    /**
     * Ouvre le modal en mode édition, préchargé avec le fournisseur.
     */
    public function edit(int $id): void
    {
        $supplier = Supplier::findOrFail($id);

        $this->editingId = $supplier->id;
        $this->name = $supplier->name;
        $this->phone = $supplier->phone;
        $this->email = (string) $supplier->email;
        $this->address = (string) $supplier->address;

        $this->showModal = true;
    }

    /**
     * Valide et enregistre (création ou mise à jour selon editingId).
     */
    public function save(): void
    {
        $this->validate();

        $isNew = ! $this->editingId;

        $supplier = Supplier::updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $this->name,
                'phone' => $this->phone,
                'email' => $this->email !== '' ? $this->email : null,
                'address' => $this->address !== '' ? $this->address : null,
            ]
        );

        ActivityLog::record(
            $isNew ? 'created' : 'updated',
            $supplier,
            $isNew ? "Fournisseur « {$supplier->name} » créé" : "Fournisseur « {$supplier->name} » modifié"
        );

        session()->flash('success', $this->editingId
            ? 'Fournisseur mis à jour avec succès.'
            : 'Fournisseur créé avec succès.');

        $this->showModal = false;
        $this->resetForm();
    }

    /**
     * Supprime un fournisseur.
     * Bloqué si des produits ou des achats y sont encore rattachés.
     */
    public function delete(int $id): void
    {
        $supplier = Supplier::findOrFail($id);

        if ($supplier->products()->exists()) {
            session()->flash('error', 'Impossible de supprimer : des produits sont encore rattachés à ce fournisseur.');
            return;
        }

        if ($supplier->purchases()->exists()) {
            session()->flash('error', 'Impossible de supprimer : des achats sont encore rattachés à ce fournisseur.');
            return;
        }

        ActivityLog::record('deleted', $supplier, "Fournisseur « {$supplier->name} » supprimé");

        $supplier->delete();

        session()->flash('success', 'Fournisseur supprimé avec succès.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'phone', 'email', 'address']);
        $this->resetErrorBag();
    }

    public function render()
    {
        return $this->view([
            'suppliers' => Supplier::query()
                ->withCount('products')
                ->when($this->search, function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                })
                ->orderBy($this->sortField, $this->sortDirection)
                ->paginate(10),
        ]);
    }
};