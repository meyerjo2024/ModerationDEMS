@props(['title' => 'Moderation DEMS'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#006699">
  <link rel="manifest" href="/manifest.json">
  <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="Moderation">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <title>{{ $title }} · Moderation DEMS</title><link rel="icon" href="/favicon.svg" type="image/svg+xml">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">{{ $slot }}</body>
</html>
