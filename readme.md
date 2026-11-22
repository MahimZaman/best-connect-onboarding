# Settings API

Best Connect Onboarding stores all plugin settings inside a single option:

```php
bcob_settings
```

The option is saved as an associative array.

Example:

```php
[
    'company_name' => 'Best Connect',
    'primary_color' => '#2563eb',
    'enable_tracking' => '1',
]
```

---

# Adding a New Settings Tab

Use the `bcob_settings_tabs` filter.

Example:

```php
add_filter('bcob_settings_tabs', function ($tabs) {

    $tabs['my_plugin'] = [
        'label' => __('My Plugin', 'my-plugin'),
        'icon'  => 'ri-puzzle-line',
    ];

    return $tabs;

});
```

The array key becomes the tab ID.

---

# Rendering a Settings Tab

Every tab has its own action.

Format:

```php
bcob_settings_tab_{tab_id}
```

Example:

```php
add_action('bcob_settings_tab_my_plugin', function () {

    $settings = get_option('bcob_settings', []);

    ?>

    <div class="bcob-form-group">

        <label>API Key</label>

        <input
            type="text"
            name="settings[api_key]"
            value="<?php echo esc_attr($settings['api_key'] ?? ''); ?>">

    </div>

    <?php

});
```

Any field using the format

```html
name="settings[your_key]"
```

will automatically be saved.

---

# Saving Custom Data

After all built-in settings have been collected, the following action is fired:

```php
bcob_save_settings
```

Arguments:

```php
$settings
$posted
```

Example:

```php
add_action('bcob_save_settings', function (&$settings, $posted) {

    $settings['custom_value'] = sanitize_text_field(
        $posted['custom_value'] ?? ''
    );

}, 10, 2);
```

Because `$settings` is passed by reference, any modification will be included in the final saved option.

---

# Reading Settings

```php
$settings = get_option('bcob_settings', []);

$value = $settings['api_key'] ?? '';
```

or

```php
$value = get_option('bcob_settings', [])['api_key'] ?? '';
```

---

# Best Practices

- Always sanitize custom values before saving.
- Use unique setting keys to avoid conflicts.
- Store only serializable data.
- Do not call `update_option()` manually for settings handled by this page.
- Use the provided hooks instead of modifying the core settings page.

---

# Available Hooks

## Filter

```php
bcob_settings_tabs
```

Register new settings tabs.

---

## Actions

```php
bcob_settings_tab_general
```

```php
bcob_settings_tab_styles
```

```php
bcob_settings_tab_integrations
```

```php
bcob_settings_tab_advanced
```

```php
bcob_settings_tab_tools
```

Render content for a specific tab.

---

```php
bcob_settings_tab_{your_tab}
```

Render content for your own custom tab.

---

```php
bcob_save_settings
```

Modify or save additional values before the option is written to the database.

# 4/8/2026

- Changed the UploadManager (JS Class) system limit to 1 call only by initializing a method "UploadManager.initialized" into condition of true / false.


