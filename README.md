# Big Games Shop Product Redirect

A WooCommerce extension that allows you to assign redirect URLs to individual products, pulled directly from an external API such as `https://big-games.shop`.  

Includes a smart dropdown UI in the product edit screen **and** inline in the admin product list (with AJAX saving).

---

## 🧩 Features

- ✅ Redirect dropdown in each WooCommerce product edit screen (from external API)
- ✅ AJAX-powered dropdown in the product list table (for quick inline updates)
- ✅ Automatic frontend redirection when a URL is set
- ✅ WooCommerce submenu with:
  - Editable API URL field
  - Cache duration setting (in hours)
  - Button to flush the cached API data
- ✅ GitHub-based plugin updates using [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)

---

## ⚙️ Admin Settings Page

Navigate to:  
`WooCommerce → Redirect Settings`

There you can:

- Set the remote API endpoint
- Define how long data should be cached (in hours)
- Clear the transient cache immediately

---

## 🔄 Git-Based Auto Updates

This plugin uses [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) for automatic updates.

Once your plugin is installed from a public GitHub repository:

- Push a new **tagged** release (e.g. `v1.1.6`)
- WordPress will detect and prompt for an update automatically 🎉

---

## 📦 Installation

### 🔌 Standard WordPress Installation

1. Download the plugin ZIP (with `vendor/` folder included)
2. In your WordPress dashboard, go to:
   `Plugins → Add New → Upload Plugin`
3. Upload the `.zip` file and activate the plugin

### ⚙️ Composer Installation (for developers)

```bash
composer require dompl/petshop-product-redirect
