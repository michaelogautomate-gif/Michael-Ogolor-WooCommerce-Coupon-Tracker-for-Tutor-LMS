# Michael Ogolor WooCommerce Coupon Tracker for Tutor LMS

[![Plugin Version](https://img.shields.io/badge/version-4.0.0-blue.svg)](https://github.com/michaelogautomate-gif/tutor-lms-coupon-usage-tracker)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Requires PHP](https://img.shields.io/badge/PHP-%E2%89%A5%207.4-8892BF.svg)](https://php.net/)
[![Requires WordPress](https://img.shields.io/badge/WordPress-%E2%89%A5%205.8-21759B.svg)](https://wordpress.org/)
[![WooCommerce HPOS Ready](https://img.shields.io/badge/WooCommerce-HPOS%20Compatible-purple.svg)](https://woocommerce.com/)

A high-performance, secure WordPress plugin that tracks WooCommerce coupon usage for Tutor LMS course enrollments. Built for B2B partnership attribution, influencer campaign tracking, guest checkout analytics, and one-click CSV reporting.

---

## 📌 Overview

When selling courses using **Tutor LMS** *(an e-learning platform plugin)* and **WooCommerce** *(an e-commerce payment engine)*, store managers often face a reporting blind spot: **Which specific coupon code was used to buy which course?**

By default, WooCommerce logs coupons across order totals, but it does not map them cleanly against Tutor LMS course enrollment relationships in a single audit log. 

**WooCommerce Coupon Tracker for Tutor LMS** bridges this gap. It operates in real time during checkout, linking WooCommerce coupon codes to Tutor LMS course IDs, customer details, and guest checkout billing data into a dedicated, indexed database table.

---

## 🚀 Key Features

* **📊 KPI Analytics Dashboard:** Instant metrics display showing **Total Redemptions**, **Active Coupons**, and the **Top Performing Coupon Code**.
* **🤝 B2B Partnership & Affiliate Attribution:** Track individual corporate partner codes (e.g., `CORP50`) or influencer campaigns (`SARAH10`) to measure partner performance.
* **⚡ WooCommerce HPOS Compatibility:** Fully compatible with **HPOS** *(High-Performance Order Storage — WooCommerce’s modern, high-speed order database system)*.
* **👤 Guest Checkout & Registered User Support:** Automatically resolves customer billing details (Name, Username, Email) whether the customer registered prior to purchase or checked out as a guest.
* **🎟️ Multi-Coupon Processing:** Accurately processes orders where multiple discount codes are applied simultaneously, creating individual log entries for every code.
* **🔒 Enterprise Security & SQL Hardening:** Built using the **Singleton pattern**, prepared SQL queries (`$wpdb->prepare`), strict column whitelisting, and nonce-verified CSV exports.
* **📁 One-Click CSV Export:** Export audit logs to `.csv` spreadsheets compatible with Microsoft Excel and Google Sheets.

---

## 🎯 Common Use Cases

### 1. Corporate Client Discounts (B2B Partnerships)
Assign a unique coupon code (e.g., `ACME2026`) to a corporate partner buying access for their workforce. Filter by that code in the Coupon Tracker admin screen and export a clean report for the corporate client.

### 2. Influencer & Affiliate Campaign Tracking
Give different marketing influencers unique codes (e.g., `ALEX10` vs. `TAYLOR10`). View real-time redemption metrics to determine campaign return on investment (ROI) and calculate commission payouts.

### 3. Promotional Sales Auditing
Track seasonal promotions (e.g., `BLACKFRIDAY`) across multi-course bundles to see which course generated the highest volume during the sale.

---

## 🛠️ System Requirements

* **WordPress:** 5.8 or higher
* **PHP:** 7.4 or higher
* **WooCommerce:** 5.0 or higher (HPOS supported)
* **Tutor LMS:** Free or Pro version

---

## 📦 Installation & Setup

### Option 1: Manual Installation via WordPress Dashboard
1. Download the latest `.zip` release from the [Releases](https://github.com/michaelogautomate-gif/tutor-lms-coupon-usage-tracker/releases) page.
2. Log into your **WordPress Admin Dashboard**.
3. Navigate to **Plugins > Add New > Upload Plugin**.
4. Upload the zip file and click **Install Now**.
5. Click **Activate Plugin**.

### Option 2: Installation via FTP/SSH
1. Clone or extract the repository into `/wp-content/plugins/tutor-wc-coupon-tracker/`.
2. Go to **Plugins > Installed Plugins** in your WordPress dashboard.
3. Locate **Michael Ogolor WooCommerce Coupon Tracker for Tutor LMS** and click **Activate**.

---

## 📖 How to Use

### 1. Linking Tutor LMS Courses to WooCommerce Products
For coupon attribution to work, your Tutor LMS course must be linked to a WooCommerce product:
1. Go to **Tutor LMS > Courses** and edit a course.
2. In the course settings, set the price type to **Paid**.
3. Link the course to its corresponding **WooCommerce Product**.
4. Save/Update the course.

### 2. Creating Coupons
1. Go to **Marketing > Coupons > Add Coupon** in WooCommerce.
2. Define your coupon code (e.g., `SUMMER50`) and discount parameters.
3. Publish the coupon.

### 3. Viewing & Exporting Logs
1. Navigate to **Coupon Tracker** in the main WordPress admin menu.
2. Review the top **KPI Cards** for summary stats.
3. Search logs by coupon code or sort by Date, Coupon, or Order ID.
4. Click **Export to CSV** to download a report.

---

## 🔒 Security Architecture

This plugin follows strict WordPress Core Security Guidelines:
* **SQL Injection Protection:** All database operations execute through `$wpdb->prepare()`. Sorting parameters utilize explicit array whitelisting (`in_array()`).
* **CSRF Defense:** Nonce tokens (`wp_verify_nonce`) protect CSV export triggers.
* **Access Control:** All administrative views and capabilities require `manage_options` permissions.
* **Direct Access Guard:** Files terminate immediately if `ABSPATH` is undefined.

---

## 🤝 Contributing

Contributions, bug reports, and feature requests are welcome!

1. **Fork** the repository.
2. Create your feature branch (`git checkout -b feature/AmazingFeature`).
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`).
4. Follow **WordPress PHP Coding Standards (WPCS)**.
5. Push to the branch (`git push origin feature/AmazingFeature`).
6. Open a **Pull Request**.

---

## ❓ Frequently Asked Questions

#### Does this plugin work without WooCommerce?
No. Version 4.0.0 is specifically engineered to track **WooCommerce** coupon redemptions for **Tutor LMS** course purchases.

#### Can it track past orders placed before installation?
No. The plugin logs usage dynamically at the time of order processing/completion (`woocommerce_order_status_completed` / `woocommerce_order_status_processing`).

#### Where is the tracking data saved?
Data is logged into a dedicated custom database table (`wp_tutor_coupon_usage`). This prevents clogging the standard `wp_postmeta` table and ensures fast query execution.

---

## 📝 Changelog

### Version 4.0.0
* **Architecture:** Complete OOP rewrite using the Singleton Design Pattern.
* **HPOS Ready:** Added support for WooCommerce High-Performance Order Storage (`custom_order_tables`).
* **Analytics:** Introduced top summary KPI cards for Total Redemptions, Active Coupons, and Top Coupon Code.
* **Security:** Hardened SQL queries with `$wpdb->prepare()`, whitelisted column sorting, and added nonce-protected CSV streaming.
* **Compatibility:** Dual lookup support for `_tutor_course_id` and `_tutor_course_product_id` postmeta keys.

### Version 3.1.0
* Added guest checkout email and billing name resolution.
* Improved pagination handling for large order logs.

### Version 2.0.0
* Added student columns: First Name, Last Name, Username, and Email.
* Initial CSV export interface.

### Version 1.0.0
* Initial release.

---

## 📄 License

Distributed under the GPL-2.0-or-later License. See `LICENSE` for more information.

---

## 👤 Author

**Michael Ogolor**
* Website/Portfolio: [Fouchix Nexus LTD](https://shorturl.at/wyUYh)
* GitHub: [@michaelogautomate-gif](https://github.com/michaelogautomate-gif)
