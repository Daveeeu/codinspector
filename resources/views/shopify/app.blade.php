<!DOCTYPE html>
<html>
<head>
    <title>Shopify App</title>
    <script src="https://unpkg.com/@shopify/app-bridge"></script>
    <script src="https://unpkg.com/@shopify/app-bridge-utils"></script>
</head>
<body>
    <h1>Üdvözlünk a Shopify Appban!</h1>
    <p>Ez a bolt: {{ $shop }}</p>

    <script>
        // Shopify App Bridge inicializálása
        const AppBridge = window['app-bridge'];
        const createApp = AppBridge.default;
        const actions = AppBridge.actions;

        const app = createApp({
            apiKey: '{{ config('shopify.api_key') }}',
            shopOrigin: '{{ $shop }}',
            forceRedirect: true,
        });

        console.log("Shopify Embedded App működik!");
    </script>
</body>
</html>
