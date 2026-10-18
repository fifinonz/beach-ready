<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · Beach Ready</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    {{-- The few pages reached from email links, before the Vue app exists.
         Brand colours are from CLAUDE.md. --}}
    <style>
        :root { --foam: #F7FBFA; --deep: #0C3B4A; --lagoon: #12808A; --shallows: #DDF1EE; --line: #CFE3E0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--foam); color: var(--deep); font: 18px/1.5 'Fira Sans', system-ui, sans-serif; }
        main { max-width: 34rem; margin: 0 auto; padding: 3rem 1.25rem; }
        .brand { font-weight: 700; font-size: 1.25rem; margin-bottom: 2rem; }
        h1 { font-size: 2rem; line-height: 1.2; margin: 0 0 1rem; }
        p { margin: 0 0 1rem; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 1rem; padding: 0.5rem 1.25rem; margin: 1.5rem 0; }
        label.topic { display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-height: 44px; padding: 0.75rem 0; border-bottom: 1px solid var(--line); }
        label.topic:last-child { border-bottom: 0; }
        label.topic small { display: block; opacity: 0.8; }
        input[type=checkbox] { width: 28px; height: 28px; accent-color: var(--lagoon); }
        .button { display: inline-block; min-height: 44px; padding: 0.6rem 1.5rem; border: 2px solid var(--lagoon); border-radius: 999px; background: var(--lagoon); color: #fff; font: 600 1rem 'Fira Sans', system-ui, sans-serif; text-decoration: none; cursor: pointer; }
        .button.secondary { background: transparent; color: var(--deep); border-color: var(--deep); }
        .notice { background: var(--shallows); border-radius: 1rem; padding: 1rem 1.25rem; }
        a { color: var(--lagoon); }
        :focus-visible { outline: 3px solid var(--deep); outline-offset: 2px; }
    </style>
</head>
<body>
    <main>
        <div class="brand">Beach Ready</div>
        @yield('content')
    </main>
</body>
</html>
