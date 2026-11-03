@include('errors.minimal', [
    'title' => 'Page not found',
    'message' => 'That page is not available.',
    'action' => ['href' => url('/'), 'label' => 'Return to the dashboard'],
])
