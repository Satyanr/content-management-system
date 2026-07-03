<?php

namespace App\Livewire\Traits;

trait HasModal
{
    public bool $showModal = false;

    public bool $isEdit = false;

    public function openModal(): void
    {
        $this->resetForm();

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    abstract protected function resetForm(): void;
}
