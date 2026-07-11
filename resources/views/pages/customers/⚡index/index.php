<?php

use App\Models\ActivityLog;
use App\Models\Customer;
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

    public function create(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $customer = Customer::findOrFail($id);

        $this->editingId = $customer->id;
        $this->name = $customer->name;
        $this->phone = $customer->phone;
        $this->email = (string) $customer->email;
        $this->address = (string) $customer->address;

        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $isNew = ! $this->editingId;

        $customer = Customer::updateOrCreate(
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
            $customer,
            $isNew ? "Client « {$customer->name} » créé" : "Client « {$customer->name} » modifié"
        );

        session()->flash('success', $this->editingId
            ? 'Client mis à jour avec succès.'
            : 'Client créé avec succès.');

        $this->showModal = false;
        $this->resetForm();
    }

    /**
     * Supprime un client. Bloqué si des commandes y sont encore rattachées.
     */
    public function delete(int $id): void
    {
        $customer = Customer::findOrFail($id);

        if ($customer->orders()->exists()) {
            session()->flash('error', 'Impossible de supprimer : des commandes sont encore rattachées à ce client.');
            return;
        }

        ActivityLog::record('deleted', $customer, "Client « {$customer->name} » supprimé");

        $customer->delete();

        session()->flash('success', 'Client supprimé avec succès.');
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
            'customers' => Customer::query()
                ->withCount('orders')
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