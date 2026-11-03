@include('errors.minimal', [
    'title' => 'Access denied',
    'message' => 'You do not have permission to view this page.',
    'action' => ['href' => url('/'), 'label' => 'Return to the dashboard'],
])
