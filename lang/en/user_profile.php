<?php

return [
    'navigation_label' => 'My Profile',
    'navigation_group' => 'Account',
    'title' => 'My Profile',

    'fields' => [
        'preferred_locale' => [
            'label' => 'Language Preference',
            'helper' => 'Select your preferred language for the admin panel',
        ],
    ],

    'actions' => [
        'save' => 'Save Preferences',
    ],

    'notifications' => [
        'saved' => [
            'title' => 'Language preference updated',
            'body' => 'Your language preference has been saved successfully.',
        ],
    ],

    'info' => [
        'note' => 'Note: The page will refresh automatically after saving to apply the new language.',
    ],
];
