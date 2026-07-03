<?php

namespace App\Livewire\Base;

use Livewire\WithPagination;

abstract class BaseTable extends BaseComponent
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }
}
