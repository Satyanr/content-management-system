import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm.js';
import './bootstrap.js';
import 'flowbite';
import './playlist-studio.js';
import './cms-guide.js';
import './admin-navigation.js';

// Register CMS Alpine components before Livewire initializes any layout.
Livewire.start();
