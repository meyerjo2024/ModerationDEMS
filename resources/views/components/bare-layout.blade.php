@props(['title' => 'Moderation DEMS'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $title }} · Moderation DEMS</title><link rel="icon" href="/favicon.svg" type="image/svg+xml">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">{{ $slot }}</body>
</html>
