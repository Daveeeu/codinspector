<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings Form</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
    <h1 class="text-center mb-4">Settings Form</h1>
    <p class="text-muted text-center">Please configure the settings below. Use the tooltips for more information about each field.</p>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('settings.save') }}" method="POST" class="border p-4 rounded shadow-sm bg-light">
        <!-- API Domain -->
        <div class="mb-3">
            <label for="api_domain" class="form-label">API Domain 
                <span data-bs-toggle="tooltip" data-bs-placement="top" title="Select the domain for API integration.">
                    <i class="bi bi-question-circle"></i>
                </span>
            </label>
            <select class="form-select" name="api_domain" id="api_domain">
                <option value="inspectorramburs.ro" {{ $settingsData['api_domain'] == 'inspectorramburs.ro' ? 'selected': '' }}>inspectorramburs.ro</option>
            </select>
            <small class="form-text text-muted">Choose the appropriate domain from the list.</small>
        </div>

        <!-- API Key -->
        <div class="mb-3">
            <label for="api_key" class="form-label">API Key 
                <span data-bs-toggle="tooltip" data-bs-placement="top" title="Enter your public API key for authentication.">
                    <i class="bi bi-question-circle"></i>
                </span>
            </label>
            <input type="text" name="api_key" id="api_key" class="form-control" value="{{ $settingsData['api_key'] }}" required>
            <small class="form-text text-muted">This key is used to authenticate API requests.</small>
        </div>

        <!-- Secret API Key -->
        <div class="mb-3">
            <label for="secret_api_key" class="form-label">Secret API Key 
                <span data-bs-toggle="tooltip" data-bs-placement="top" title="Enter your secret API key. Keep it confidential.">
                    <i class="bi bi-question-circle"></i>
                </span>
            </label>
            <input type="text" name="secret_api_key" id="secret_api_key" class="form-control" value="{{ $settingsData['secret_api_key'] }}" required>
            <small class="form-text text-muted">Ensure this key is not shared publicly.</small>
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn btn-primary w-100">Save Settings</button>
    </form>

    <!-- Tooltip Initialization -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    </script>
</div>
</body>
</html>
