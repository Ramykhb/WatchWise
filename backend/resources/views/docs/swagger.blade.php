<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WatchWise API</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui.css">
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
    <script>
        window.onload = () => {
            window.ui = SwaggerUIBundle({
                url: '{{ route('documentation.spec', [], false) }}',
                dom_id: '#swagger-ui',
                deepLinking: true,
                persistAuthorization: true,
                tryItOutEnabled: true,
                requestInterceptor: (req) => {
                    req.credentials = 'same-origin';
                    return req;
                },
            });
        };
    </script>
</body>
</html>
