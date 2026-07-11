<?php

use App\Models\ActivityLog;
use App\Models\Category;
use Illuminate\Support\Str;
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

    #[Validate('nullable|string')]
    public string $description = '';

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
     * Ouvre le modal en mode édition, préchargé avec la catégorie.
     */
    public function edit(int $id): void
    {
        $category = Category::findOrFail($id);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->description = (string) $category->description;

        $this->showModal = true;
    }

    /**
     * Valide et enregistre (création ou mise à jour selon editingId).
     */
    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $slug = Str::slug($this->name);

        // Garantit l'unicité du slug (hors catégorie en cours d'édition)
        $baseSlug = $slug;
        $counter = 1;
        while (
            Category::where('slug', $slug)
                ->when($this->editingId, fn ($query) => $query->where('id', '!=', $this->editingId))
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $isNew = ! $this->editingId;

        $category = Category::updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $this->name,
                'slug' => $slug,
                'description' => $this->description,
            ]
        );

        ActivityLog::record(
            $isNew ? 'created' : 'updated',
            $category,
            $isNew ? "Catégorie « {$category->name} » créée" : "Catégorie « {$category->name} » modifiée"
        );

        session()->flash('success', $this->editingId
            ? 'Catégorie mise à jour avec succès.'
            : 'Catégorie créée avec succès.');

        $this->showModal = false;
        $this->resetForm();
    }

    /**
     * Supprime une catégorie.
     * La confirmation est gérée côté vue via wire:confirm.
     */
    public function delete(int $id): void
    {
        $category = Category::findOrFail($id);

        if ($category->products()->exists()) {
            session()->flash('error', 'Impossible de supprimer : des produits sont encore rattachés à cette catégorie.');
            return;
        }

        ActivityLog::record('deleted', $category, "Catégorie « {$category->name} » supprimée");

        $category->delete();

        session()->flash('success', 'Catégorie supprimée avec succès.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description']);
        $this->resetErrorBag();
    }

    public function render()
    {
        return $this->view([
            'categories' => Category::query()
                ->withCount('products')
                ->when($this->search, function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('description', 'like', "%{$this->search}%");
                })
                ->orderBy($this->sortField, $this->sortDirection)
                ->paginate(10),
        ]);
    }
};