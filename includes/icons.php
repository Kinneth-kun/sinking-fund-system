<?php

/** Render a small, theme-aware outline icon without external dependencies. */
function uiIcon(string $name, string $class = '', string $label = ''): string {
    $icons = [
        'wallet' => '<path d="M20 7V6a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h15v8a2 2 0 0 1-2 2H5a3 3 0 0 1-3-3V7"/><path d="M16 14h.01"/>',
        'dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
        'calendar' => '<path d="M8 2v4M16 2v4M3 10h18"/><rect width="18" height="18" x="3" y="4" rx="2"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'banknote' => '<rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>',
        'chart' => '<path d="M3 3v18h18"/><path d="m7 16 4-5 4 3 5-7"/>',
        'logout' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>',
        'mail' => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'lock' => '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'send' => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
        'unlock' => '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>',
        'refresh' => '<path d="M21 12a9 9 0 0 1-15.5 6.2L3 16"/><path d="M3 21v-5h5M3 12A9 9 0 0 1 18.5 5.8L21 8"/><path d="M21 3v5h-5"/>',
        'alert' => '<path d="M10.3 2.9 1.8 17a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 2.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
        'check' => '<path d="m20 6-11 11-5-5"/>',
        'check-circle' => '<path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><path d="m22 4-10 10.01-3-3"/>',
        'x-circle' => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'trophy' => '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0Z"/><path d="M7 6H4v2a3 3 0 0 0 3 3M17 6h3v2a3 3 0 0 1-3 3"/>',
        'clipboard' => '<rect width="16" height="18" x="4" y="4" rx="2"/><path d="M9 4V2h6v2M8 10h8M8 14h8M8 18h5"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'trash' => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 15H6L5 6M10 11v5M14 11v5"/>',
        'pause' => '<rect width="4" height="14" x="6" y="5" rx="1"/><rect width="4" height="14" x="14" y="5" rx="1"/>',
        'play' => '<path d="m6 4 14 8-14 8Z"/>',
        'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'download' => '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
    ];
    $content = $icons[$name] ?? $icons['alert'];
    $className = trim('ui-icon ' . $class);
    $aria = $label !== '' ? ' role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES) . '"' : ' aria-hidden="true"';
    return '<svg class="' . htmlspecialchars($className, ENT_QUOTES) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"' . $aria . '>' . $content . '</svg>';
}
