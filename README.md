# Appsmith Secure Embed

Securely embed **Appsmith applications** inside WordPress with **strict access control**, **per‑user app assignment**, and **server‑side token verification**.

This plugin is designed for **B2B / customer portal use cases**, where WordPress acts as the identity provider and Appsmith powers internal dashboards or reporting tools.

***

## Table of Contents

1.  Overview
2.  Key Features
3.  Architecture Overview
4.  Requirements
5.  Installation
6.  Required `wp-config.php` Configuration
7.  User Roles & Permissions
8.  Assigning Appsmith Apps to Users
9.  Shortcode Usage
10. Authentication & Login Flow
11. Appsmith Configuration
12. Security Model
13. Common Pitfalls
14. FAQ

***

## 1. Overview

**Appsmith Secure Embed** allows you to:

*   Embed **private Appsmith apps** inside WordPress pages
*   Restrict access to **logged‑in B2B users only**
*   Assign a **unique Appsmith embed URL per customer**
*   Prevent access to `wp-admin` for customer users
*   Use **short‑lived, signed tokens** verified server‑side
*   Ensure Appsmith apps **cannot be accessed directly**

This plugin intentionally **does not** rely on frontend cryptography or public Appsmith access.

***

## 2. Key Features

✅ Per‑user Appsmith application assignment  
✅ Custom `B2B Customer` role with **zero WordPress permissions**  
✅ Secure, signed, short‑lived access tokens  
✅ Server‑side token verification via WordPress REST API  
✅ Automatic redirect to `/customer-reporting` after login  
✅ Blocks direct Appsmith access and external embedding  
✅ Hides WordPress admin bar for B2B users  
✅ Redirects B2B users away from `/wp-admin`

***

## 3. Architecture Overview

    Browser
      |
      ├─ WordPress (authentication + authorization)
      |     ├─ Issues short-lived signed token
      |     └─ Verifies token via REST API
      |
      └─ Appsmith (embedded)
            └─ Calls WordPress to verify token

**Important principle**:

> Appsmith never validates cryptographic tokens itself —  
> **WordPress is the sole source of truth.**

***

## 4. Requirements

*   WordPress 6.x+
*   PHP 8.0+
*   Appsmith (Cloud or Self‑Hosted)
*   HTTPS (strongly recommended)
*   Ability to edit `wp-config.php`

***

## 5. Installation

1.  Copy the plugin folder to:

        wp-content/plugins/appsmith-secure-embed/

2.  Activate **Appsmith Secure Embed** in:
        WordPress Admin → Plugins

3.  On activation, the plugin automatically creates:
    *   A new role: **B2B Customer**

***

## 6. Required `wp-config.php` Configuration

This plugin **requires a shared secret** for signing tokens.

### ✅ Add this to `wp-config.php`

Place it **above**:

```php
/* That's all, stop editing! */
```

```php
define(
    'APPSMITH_SHARED_SECRET',
    'REPLACE_WITH_A_LONG_RANDOM_STRING'
);
```

### ✅ Secret requirements

*   Minimum 32–64 characters
*   High entropy (random)
*   Keep it **out of version control**
*   Must match **only WordPress** (Appsmith never stores it)

❌ Do NOT:

*   Put this inside the plugin
*   Put this in the database
*   Share it with frontend code

***

## 7. User Roles & Permissions

### B2B Customer Role

The plugin creates a role called:

    B2B Customer

This role:

*   Has **zero WordPress capabilities**
*   Cannot access `/wp-admin`
*   Cannot edit a profile
*   Cannot perform REST requests (except the plugin endpoint)
*   Has no admin bar
*   Exists **only for authentication**

This role is intentionally minimal and safe.

***

## 8. Assigning Appsmith Apps to Users

Each B2B Customer has a **custom user field**:

    Appsmith URL

### ✅ Where to set it

1.  Go to **Users → Edit User**
2.  Ensure the role is **B2B Customer**
3.  Paste the **Appsmith embed URL** into:
        Appsmith URL

### ✅ Important

You must use an **Appsmith embed URL**, for example:

    https://app.example.com/embed/reporting/home-abc123

❌ This will NOT work:

    /app/reporting/...

***

## 9. Shortcode Usage

Create a WordPress page (e.g. `/customer-reporting`) and add:

```text
[appsmith_embed height="100vh"]
```

### Shortcode parameters

| Parameter | Description                                                   |
| --------- | ------------------------------------------------------------- |
| `height`  | CSS height value (e.g. `900`, `100vh`, `calc(100vh - 120px)`) |

✅ The Appsmith URL is resolved **automatically** from the logged‑in user.

***

## 10. Authentication & Login Flow

### User Journey

1.  User visits `/customer-reporting`
2.  If not logged in:
    *   Redirected to `/login`
3.  After login:
    *   Automatically redirected back to `/customer-reporting`
4.  Appsmith loads using the user‑specific URL
5.  Token is generated and verified server‑side

### Automatic Login Redirect

B2B Customers are always redirected to:

    /customer-reporting

after successful login.

***

## 11. Appsmith Configuration

### App Settings

*   Set the Appsmith app to **Private**
*   Disable public access
*   Enable embedding

### Allowed Embed Origins

In Appsmith Admin / Security settings, allow:

    https://your-wordpress-domain.com

### Token Verification API

Appsmith must call:

    POST https://your-site.com/wp-json/appsmith/v1/verify

with body:

```json
{
  "token": "<token-from-querystring>"
}
```

The Appsmith page should block rendering unless:

```json
{
  "valid": true
}
```

is returned.

***

## 12. Security Model

This plugin enforces security at **four layers**:

1.  **WordPress authentication**
2.  **Per-user App assignment**
3.  **Signed, short-lived tokens**
4.  **Server-side token validation**

### What is prevented?

✅ Direct access to Appsmith URLs  
✅ App embedding on other sites  
✅ Token tampering  
✅ Replay attacks (token expiry)  
✅ WordPress admin access  
✅ Privilege escalation

***

## 13. Common Pitfalls

### “Authentication error” in shortcode

Causes:

*   `APPSMITH_SHARED_SECRET` not defined
*   User not logged in
*   No Appsmith URL assigned to user

### Appsmith API error: `Host not allowed`

Fix:

*   Add your WordPress domain to **Appsmith Allowed Hosts**
*   Restart Appsmith if self‑hosted

### Full screen height not working

Ensure your plugin supports CSS units and use:

```text
height="100vh"
```

***

## 14. FAQ

### Can one page support multiple customers?

✅ Yes. The Appsmith URL is resolved per user.

### Can Appsmith apps be shared between users?

✅ Yes, if they point to the same Appsmith embed URL.

### Can B2B users reset passwords?

✅ Yes (standard WordPress password reset).

### Is Appsmith Cloud supported?

✅ Yes (you must use a real domain, not `localhost`).

***

## Final Notes

This plugin intentionally treats WordPress as:

> **An identity & access gateway — not a CMS for customers**

This results in:

*   Fewer attack surfaces
*   Cleaner UX
*   Safer Appsmith deployments


