@include('errors.minimal', [
    'title' => 'Page expired',
    'message' => 'The form expired. Go back and try again.',
    'action' => ['href' => url('/login'), 'label' => 'Return to sign in'],
])
