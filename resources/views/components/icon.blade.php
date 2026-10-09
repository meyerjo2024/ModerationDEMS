@props(['name'])
@php
$paths = [
  'check' => 'M20 6 9 17l-5-5', 'x' => 'M18 6 6 18M6 6l12 12', 'plus' => 'M12 5v14M5 12h14',
  'trash' => 'M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6',
  'upload' => 'M12 16V4M7 9l5-5 5 5M4 20h16', 'download' => 'M12 4v12M7 11l5 5 5-5M4 20h16',
  'send' => 'M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z', 'save' => 'M5 3h11l3 3v15H5zM8 3v6h8V3M8 21v-7h8v7',
  'bell' => 'M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0',
  'logout' => 'M9 21H5V3h4M16 17l5-5-5-5M21 12H9',
  'shield' => 'M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6zM9 12l2 2 4-4',
  'file' => 'M14 3H6v18h12V7zM14 3v4h4', 'arrow-left' => 'M19 12H5M12 19l-7-7 7-7', 'arrow-right' => 'M5 12h14M12 5l7 7-7 7',
  'arrow-up-right' => 'M7 17 17 7M7 7h10v10', 'search' => 'm21 21-4.3-4.3M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14z',
  'hourglass' => 'M6 2h12M6 22h12M7 2v4l5 6-5 6v4M17 2v4l-5 6 5 6v4',
  'check-circle' => 'M22 11.1V12a10 10 0 1 1-5.9-9.1M22 4 12 14l-3-3',
  'reply' => 'M9 17 4 12l5-5M20 18v-2a4 4 0 0 0-4-4H4', 'clipboard' => 'M9 4h6v3H9zM8 5H6v16h12V5h-2M9 14l2 2 4-4',
  'pen' => 'M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z',
  'history' => 'M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 7v5l4 2',
  'sparkles' => 'M12 3l2 5 5 2-5 2-2 5-2-5-5-2 5-2zM19 14l.7 1.8 1.8.7-1.8.7L19 19l-.7-1.8-1.8-.7 1.8-.7z',
  'user-plus' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM19 8v6M22 11h-6',
  'book-plus' => 'M4 19.5V4a2 2 0 0 1 2-2h13v17H6.5a2.5 2.5 0 0 0 0 5H19M12 6v6M9 9h6',
  'lock' => 'M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4', 'table' => 'M3 5h18v14H3zM3 10h18M9 5v14',
  'file-plus' => 'M14 3H6v18h12V7zM14 3v4h4M12 11v6M9 14h6', 'alert' => 'M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z',
  'info' => 'M12 16v-4M12 8h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0z',
  'eraser' => 'M20 20H9L3.5 14.5a2 2 0 0 1 0-2.8L12 3.2a2 2 0 0 1 2.8 0l6 6a2 2 0 0 1 0 2.8L12 20',
  'spinner' => 'M21 12a9 9 0 1 1-6.2-8.6', 'party' => 'M5.8 11.3 2 22l10.7-3.8M4 3h.01M22 8h.01M15 2h.01M22 20h.01M22 2l-2.2.7a2.9 2.9 0 0 0-2 2.4 2.9 2.9 0 0 1-2 2.4L14 8M9.5 13.5l-.5-.5a2 2 0 0 1 0-2.8l.7-.7a2 2 0 0 1 2.8 0l.5.5',
];
@endphp
<svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? '' }}"/></svg>
