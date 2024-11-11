<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}" />

        <title>Spectrum Admin Panel</title>

        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&family=Nabla:EDPT,EHLT@30..200,24&display=swap" rel="stylesheet">
        <!-- Bootstrap -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
        <!-- Styles -->
        <style>
            html,body {
                font-family: Nunito,  sans-serif;
                line-height: 1.5;
                font-weight: 400;

                height: 100%;
                width: 100%;
                padding: 0;
                margin: 0;
                min-width: 1200px;
                min-height: 768px;

                font-synthesis: none;
                text-rendering: optimizeLegibility;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }
        </style>
        @vite('resources/js/app.js')
    </head>
    <body>
    <noscript>
        <strong>Please enable javascript otherwise this website will not work</strong>
    </noscript>
    <div id="app"></div>
    </body>
</html>
