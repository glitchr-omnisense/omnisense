// First paint: the Stimulus app and the type. jQuery comes from omnibase's
// own "base" entry, loaded before this one.
Object.assign(window, { jQuery: jQuery, $: jQuery });

import './bootstrap.js';
import './styles/app-async.scss';
