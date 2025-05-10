# 🚫 throwaway.cloud E-Mail Check

Detect and block disposable or temporary email addresses in comments, registrations, and contact forms — with full GDPR/CCPA-compliant logging and developer hooks.

---

## ✨ Features

* 🔍 Validates email addresses against the [throwaway.cloud](https://throwaway.cloud/) API
* 🛡️ Blocks temporary/disposable emails in:

  * Comment forms
  * User registrations
  * Popular contact form plugins
* 🔧 Developer-friendly filter to run checks from other plugins
* 📜 GDPR/CCPA-compliant logging with:

  * Subject access export (CSV)
  * Right to be forgotten deletion
* 🧾 Configurable logging levels: None, Domain only, or Full email
* ✅ Allowlist support for trusted emails/domains
* 🔎 Log viewer with filter/search and export options
* 🧪 PHPUnit test suite + Codecov coverage support

---

## 🛠 Installation

1. Upload the ZIP via **Plugins → Add New → Upload Plugin**
2. Activate the plugin
3. Go to **Settings → throwaway.cloud E-Mail Check Settings** to configure

---

## ⚙️ Configuration Options

### Logging Level

Choose what to store in the log:

* **None** – No logging
* **Domain Only** – `example.com` from `user@example.com`
* **Full Email** – Full address stored (be cautious under GDPR)

### Allow List

Bypass throwaway checks for specific addresses/domains:

```
admin@example.com
example.org
```

---

## 🔍 Admin Tools

Accessible under **Settings → throwaway.cloud E-Mail Check Settings**:

* 📊 Log Viewer with filters
* 📤 Export CSV by filter or by subject
* 🗑️ Delete logs by email/domain (Right to be Forgotten)

---

## 🧩 Developer Integration

### `throwaway_lookup_check` (filter)

Run a throwaway check manually from your plugin:

```php
$is_disposable = apply_filters('throwaway_lookup_check', false, 'user@example.com');
```

> 🔍 The source plugin name is automatically inferred from the call stack and logged.

---

### `throwaway_lookup_result` (filter)

Override or modify the result of a throwaway check:

```php
add_filter('throwaway_lookup_result', function($is_disposable, $email, $context) {
    if ($email === 'bypass@example.com') return false;
    return $is_disposable;
}, 10, 3);
```

---

### `throwaway_lookup_export_subject` (filter)

Retrieve logs for a subject programmatically:

```php
$logs = apply_filters('throwaway_lookup_export_subject', 'user@example.com');
```

---

### `throwaway_lookup_delete_subject` (action)

Delete logs for a subject:

```php
do_action('throwaway_lookup_delete_subject', 'user@example.com');
```

---

## 🧪 Testing

Set up a WordPress test environment:

```bash
bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest
```

Run tests with coverage:

```bash
phpunit --coverage-clover=coverage.xml --coverage-html=coverage-report
```

---

## 🪪 License

MIT License — see [LICENSE](LICENSE) for full terms.

---

Made with 💙 by [Iocium](https://github.com/iocium)