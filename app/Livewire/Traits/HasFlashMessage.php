<?php

namespace App\Livewire\Traits;

trait HasFlashMessage
{
    protected function success(string $message): void
    {
        session()->flash('success', $message);
    }

    protected function error(string $message): void
    {
        session()->flash('error', $message);
    }

    protected function warning(string $message): void
    {
        session()->flash('warning', $message);
    }

    protected function info(string $message): void
    {
        session()->flash('info', $message);
    }
}
