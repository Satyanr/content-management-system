<?php

namespace App\Livewire\Base;

use Livewire\Component;

abstract class BaseComponent extends Component
{
    protected function success(string $message): void
    {
        session()->flash('success', $message);
    }

    protected function error(string $message): void
    {
        session()->flash('error', $message);
    }
}
