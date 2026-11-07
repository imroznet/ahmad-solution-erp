import Alpine from 'alpinejs';
import { createPreviewController } from './mushak-preview.js';

window.Alpine = Alpine;

// The draft Mushak form's live VAT preview controller (used by the
// mushakForm() Alpine component in resources/views/tax/mushak/form.blade.php).
window.createMushakPreview = createPreviewController;

Alpine.start();
